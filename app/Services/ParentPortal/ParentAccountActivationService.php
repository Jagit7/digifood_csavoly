<?php

namespace App\Services\ParentPortal;

use App\Mail\ParentAccountActivationMail;
use App\Models\Guardian;
use App\Models\ParentAccountActivationToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ParentAccountActivationService
{
    private const EXPIRES_IN_MINUTES = 60;

    /**
     * Az intézményi admin által, "másolható linkként" (saját e-mailben
     * kiküldve) generált aktiváló linkek jóval tovább élnek, mint a szülő
     * saját kérésére, azonnali beváltásra szánt linkek (ld. EXPIRES_IN_MINUTES
     * fent) - itt ugyanis eltelhet több nap is aközött, hogy az admin
     * legenerálja a linket, és a szülő ténylegesen megkapja és rákattint.
     */
    private const MANUAL_LINK_EXPIRES_IN_MINUTES = 60 * 24 * 7;

    public function requestActivation(string $email): array
    {
        $email = $this->normalizeEmail($email);

        [$guardians, $errorStatus] = $this->resolveActivatableGuardians($email);

        if ($errorStatus !== null) {
            return $errorStatus;
        }

        $this->invalidateActiveTokens($email);

        $plainToken = bin2hex(random_bytes(32));
        $token = ParentAccountActivationToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        $activationUrl = route('parent.activation.show', ['token' => $plainToken]);
        $mailPayload = $this->buildMailPayload($guardians, $activationUrl);
        $mailable = new ParentAccountActivationMail($mailPayload);

        // Korábban ez a feltétel ("delivery_enabled" alapból false, ÉS ne
        // legyen 'local' env) azt eredményezte, hogy a valódi aktiváló
        // e-mail SOHA nem ment ki - sem helyi teszteléskor (ahol ez direkt
        // szándékos volt), sem éles/staging környezetben (ahol viszont ez
        // hiba volt: a szülő az admin által küldött meghívóban lévő
        // linkre kattintva idejutott, de a "valódi", jelszó-beállításhoz
        // szükséges linket soha nem kapta volna meg e-mailben - csak akkor
        // látta, ha valaki PHPUnit teszt módban futtatta a debug-linkes
        // kapcsolóval). Mostantól a debug-link mód KIZÁRÓLAG automatizált
        // PHPUnit tesztfutás alatt aktiválódik (ld. isDebugLinkMode()) -
        // normál böngészős használatnál (helyi fejlesztés, staging, éles)
        // mindig a valódi e-mail megy ki, a ténylegesen beállított
        // MAIL_MAILER driveren keresztül (local fejlesztésnél ez tipikusan
        // "log", így a storage/logs/laravel.log-ban ellenőrizhető a levél
        // kinézete).
        if ($this->shouldSendRealActivationEmail()) {
            Mail::to($email)->send($mailable);
        }

        return [
            'status' => 'activation_created',
            'token_id' => $token->id,
            'activation_url' => $activationUrl,
            'email' => $email,
            'debug_link_available' => $this->isDebugLinkMode(),
        ];
    }

    /**
     * Intézményi admin által kezdeményezett link-generálás: ugyanaz a
     * jogosultsági ellenőrzés és token-mechanizmus, mint a szülő saját
     * aktiválás-kérésénél (requestActivation()), de itt nem megy ki e-mail
     * a rendszerből - az admin maga küldi ki a linket egy saját levélben,
     * ezért az élettartam is jóval hosszabb (ld. MANUAL_LINK_EXPIRES_IN_MINUTES).
     */
    public function createManualActivationLink(string $email): array
    {
        $email = $this->normalizeEmail($email);

        [, $errorStatus] = $this->resolveActivatableGuardians($email);

        if ($errorStatus !== null) {
            return $errorStatus;
        }

        $this->invalidateActiveTokens($email);

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(self::MANUAL_LINK_EXPIRES_IN_MINUTES);

        ParentAccountActivationToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'status' => 'activation_created',
            'activation_url' => route('parent.activation.show', ['token' => $plainToken]),
            'expires_at' => $expiresAt,
            'email' => $email,
        ];
    }

    /**
     * Közös jogosultsági ellenőrzés a requestActivation() és a
     * createManualActivationLink() számára - korábban ez a logika csak a
     * requestActivation()-ben létezett; a szétválasztás nélkül a link-
     * generáló funkció vagy megkerülte volna ezeket az ellenőrzéseket, vagy
     * másolni kellett volna a teljes kódot.
     *
     * @return array{0: ?Collection, 1: ?array} [$guardians, $errorStatus] -
     *         sikeres esetben $errorStatus null és $guardians a talált,
     *         aktiválható gondviselő-rekordok; hiba esetén $guardians null,
     *         és $errorStatus a requestActivation()-nel megegyező alakú
     *         hibaválasz (status + esetleges kiegészítő adatok).
     */
    private function resolveActivatableGuardians(string $email): array
    {
        $guardians = $this->activationGuardians($email);

        if ($guardians->isEmpty()) {
            return [null, ['status' => 'guardian_not_found']];
        }

        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($users->contains(fn (User $user) => $user->role !== User::ROLE_PARENT)) {
            return [null, ['status' => 'non_parent_user_exists']];
        }

        $activeParentUser = $users->first(function (User $user) use ($guardians) {
            if ($user->role !== User::ROLE_PARENT || ! $user->is_active) {
                return false;
            }

            return $guardians->contains(fn (Guardian $guardian) => (int) $guardian->user_id === (int) $user->id)
                || $user->guardians()->where('active', true)->exists();
        });

        if ($activeParentUser !== null) {
            // Önjavítás: ha időközben (ebben vagy egy másik intézményben)
            // keletkezett olyan guardian-rekord ugyanezzel az e-mail
            // címmel, amit még senki nem kapcsolt a fiókjához, kössük
            // most hozzá - így egy ismételt aktiválás-kérés is "meggyógyítja"
            // a korábban árván maradt kapcsolatokat, anélkül hogy új
            // tokent kellene kiadni vagy a szülőnek bármit tennie kellene.
            $this->linkOrphanedGuardiansForEmail($email);

            return [null, [
                'status' => 'already_active_parent',
                'login_url' => route('parent.login', [], false),
            ]];
        }

        return [$guardians, null];
    }

    /**
     * Önjavító összekapcsolás: ha az adott e-mail címhez már tartozik AKTÍV,
     * ROLE_PARENT `users` sor, akkor az ehhez az e-mailhez tartozó, de még
     * SENKIHEZ nem kapcsolt (user_id === null), aktív guardian-rekordokat
     * hozzákapcsolja ehhez a felhasználóhoz.
     *
     * Ez teszi lehetővé, hogy egy szülő - akinek a fiókja már aktív egyik
     * intézményben - automatikusan hozzáférjen azokhoz a gyerekekhez is,
     * akikhez egy MÁSIK intézmény (a szülő aktiválása előtt vagy után)
     * ÚJABB, önálló guardian-rekordot hozott létre ugyanazzal az e-mail
     * címmel - anélkül, hogy külön meghívót kellene elfogadnia.
     *
     * Biztonsági szabályok (szándékosan szigorúak):
     *  - csak ROLE_PARENT és is_active=true felhasználóhoz kapcsolunk;
     *  - kizárólag azokat a guardian-rekordokat kapcsoljuk hozzá, amelyeknek
     *    JELENLEG NINCS user_id-ja - tehát még senkihez sincsenek rendelve;
     *  - egy már MÁS user_id-hoz kötött guardian-rekordot SOHA nem írunk
     *    felül automatikusan (ez konfliktusnak számít, ugyanúgy, mint az
     *    activate()-ben lévő conflictingGuardians eset - kézi kezelést
     *    igényelne, nem hallgatólagos összevonást).
     *
     * Nem "minden azonos e-mailű guardian gyerekeit" mutatjuk meg
     * automatikusan - csak azokat kapcsoljuk hozzá, amelyek jelenleg
     * ténylegesen gazdátlanok.
     */
    public function linkOrphanedGuardiansForEmail(string $email): ?User
    {
        $email = $this->normalizeEmail($email);

        if ($email === '') {
            return null;
        }

        $activeParent = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', User::ROLE_PARENT)
            ->where('is_active', true)
            ->first();

        if ($activeParent === null) {
            return null;
        }

        Guardian::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('user_id')
            ->update(['user_id' => $activeParent->id]);

        return $activeParent;
    }

    private function shouldSendRealActivationEmail(): bool
    {
        return ! $this->isDebugLinkMode() && (bool) config('mail.parent_activation_delivery_enabled', true);
    }

    /**
     * Egyetlen, központi forrás arra, hogy mikor mutatjuk a linket
     * közvetlenül a felületen (e-mail küldés helyett) - korábban ez a
     * logika két helyen (itt és a controllerben) külön-külön, egymástól
     * függetlenül, eltérő alapértékkel volt megírva, ami pontosan ehhez a
     * hibához (production-ben se e-mail, se debug link) vezetett volna.
     */
    private function isDebugLinkMode(): bool
    {
        return app()->runningUnitTests() && (bool) config('mail.parent_activation_debug_link_for_tests', false);
    }

    public function activationViewData(string $plainToken): ?array
    {
        $token = $this->findValidToken($plainToken);

        if ($token === null) {
            return null;
        }

        $guardians = $this->activationGuardians($token->email);

        if ($guardians->isEmpty()) {
            return null;
        }

        return [
            'email' => $token->email,
            'expires_at' => $token->expires_at,
            'guardian_names' => $guardians->pluck('full_name')->filter()->unique()->values(),
            'institution_names' => $guardians->pluck('institution.name')->filter()->unique()->values(),
            'display_name' => $this->resolveDisplayName($guardians),
        ];
    }

    public function activate(string $plainToken, string $password): User
    {
        return DB::transaction(function () use ($plainToken, $password): User {
            $token = ParentAccountActivationToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if ($token === null || $token->used_at !== null || $token->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => 'Az aktiváló link érvénytelen vagy lejárt.',
                ]);
            }

            $email = $token->email;
            $guardians = Guardian::query()
                ->with('institution')
                ->where('active', true)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($guardians->isEmpty()) {
                throw ValidationException::withMessages([
                    'email' => 'A megadott e-mail címhez nem található aktiválható szülői hozzáférés.',
                ]);
            }

            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($users->contains(fn (User $user) => $user->role !== User::ROLE_PARENT)) {
                throw ValidationException::withMessages([
                    'email' => 'Ehhez az e-mail címhez már más típusú felhasználói fiók tartozik. Kérjük, vegye fel a kapcsolatot az intézménnyel.',
                ]);
            }

            $user = $users->firstWhere('role', User::ROLE_PARENT);
            $displayName = $this->resolveDisplayName($guardians);
            $primaryGuardian = $guardians->sortBy('id')->first();

            if ($user === null) {
                // forceCreate(): a "role"/"institution_id"/"is_active"
                // mezők tudatosan nincsenek a User modell fillable
                // listájában (jogosultság-eszkalációs védelem).
                $user = User::forceCreate([
                    'name' => $displayName,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_PARENT,
                    'institution_id' => $primaryGuardian?->institution_id,
                    'is_active' => true,
                    'invited_at' => now(),
                    'accepted_invitation_at' => now(),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'name' => $displayName,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'accepted_invitation_at' => $user->accepted_invitation_at ?? now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'invited_at' => $user->invited_at ?? now(),
                ])->save();
            }

            $assignableGuardians = $guardians->filter(fn (Guardian $guardian) => $guardian->user_id === null || (int) $guardian->user_id === (int) $user->id);
            $conflictingGuardians = $guardians->reject(fn (Guardian $guardian) => $guardian->user_id === null || (int) $guardian->user_id === (int) $user->id);

            if ($assignableGuardians->isEmpty()) {
                Log::warning('Parent activation blocked because every matching guardian belongs to another user.', [
                    'email' => $email,
                    'guardian_ids' => $guardians->pluck('id')->all(),
                    'conflicting_user_ids' => $conflictingGuardians->pluck('user_id')->filter()->unique()->values()->all(),
                ]);

                throw ValidationException::withMessages([
                    'email' => 'A megadott e-mail címhez tartozó gondviselői rekordok jelenleg nem aktiválhatók. Kérjük, vegye fel a kapcsolatot az intézménnyel.',
                ]);
            }

            Guardian::query()
                ->whereIn('id', $assignableGuardians->pluck('id'))
                ->update(['user_id' => $user->id]);

            if ($conflictingGuardians->isNotEmpty()) {
                Log::warning('Parent activation skipped conflicting guardians already linked to another user.', [
                    'email' => $email,
                    'activated_user_id' => $user->id,
                    'guardian_ids' => $conflictingGuardians->pluck('id')->all(),
                    'conflicting_user_ids' => $conflictingGuardians->pluck('user_id')->filter()->unique()->values()->all(),
                ]);
            }

            $token->forceFill([
                'used_at' => now(),
            ])->save();

            ParentAccountActivationToken::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->where('id', '!=', $token->id)
                ->update(['used_at' => now()]);

            return $user->fresh();
        });
    }

    public function loginActivatedParent(User $user): void
    {
        Auth::login($user);
    }

    private function findValidToken(string $plainToken): ?ParentAccountActivationToken
    {
        $token = ParentAccountActivationToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || $token->used_at !== null || $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    private function activationGuardians(string $email): Collection
    {
        return Guardian::query()
            ->with('institution')
            ->where('active', true)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->get();
    }

    private function buildMailPayload(Collection $guardians, string $activationUrl): array
    {
        $institutionNames = $guardians->pluck('institution.name')->filter()->unique()->values();

        return [
            'recipientName' => $this->resolveDisplayName($guardians),
            'institutionName' => $institutionNames->count() === 1
                ? $institutionNames->first()
                : 'Digifood partnerintézmények',
            'activationUrl' => $activationUrl,
            'expiresInMinutes' => self::EXPIRES_IN_MINUTES,
            'institutionNames' => $institutionNames,
        ];
    }

    private function invalidateActiveTokens(string $email): void
    {
        ParentAccountActivationToken::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function resolveDisplayName(Collection $guardians): string
    {
        return (string) ($guardians->pluck('full_name')->filter()->first() ?: 'Szülő / Gondviselő');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
