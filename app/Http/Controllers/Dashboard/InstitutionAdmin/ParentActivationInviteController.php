<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignEmailJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Guardian;
use App\Models\Institution;
use App\Services\ParentPortal\ParentAccountActivationService;
use App\Support\Html\CampaignHtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParentActivationInviteController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();

        $eligibleGuardians = $this->eligibleGuardiansQuery($institution)->get();
        $recipientPayloads = $this->buildRecipientPayloads($eligibleGuardians);

        $alreadyInvitedCount = Guardian::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('activation_email_sent_at')
            ->count();

        $alreadyActivatedCount = Guardian::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('user_id')
            ->count();

        return view('dashboard.institution_admin.communication.parent-activation-invite.index', [
            'institution' => $institution,
            'eligibleCount' => $recipientPayloads->count(),
            'eligibleRecipients' => $recipientPayloads,
            'alreadyInvitedCount' => $alreadyInvitedCount,
            'alreadyActivatedCount' => $alreadyActivatedCount,
            'defaultSubject' => $this->defaultSubject(),
            'defaultBody' => $this->defaultBody(),
            'individualInviteGuardians' => $this->buildIndividualInviteOptions($institution),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();

        $validated = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
        ], [
            'subject.max' => 'Az e-mail tárgya legfeljebb 191 karakter lehet.',
        ])->validate();
        // ld. EmailCampaignController::store() - ugyanaz a tárolt XSS
        // elleni védelem, mivel ez a controller is szabadon szerkeszthető
        // "body" mezőt ment el egy EmailCampaign rekordba.
        $validated['body'] = CampaignHtmlSanitizer::clean($validated['body']);

        $eligibleGuardians = $this->eligibleGuardiansQuery($institution)->get();
        $recipientPayloads = $this->buildRecipientPayloads($eligibleGuardians);

        if ($recipientPayloads->isEmpty()) {
            throw ValidationException::withMessages([
                'recipients' => 'Jelenleg nincs olyan gondviselő, aki e-mail címmel rendelkezik, még nem hozott létre szülői fiókot, és még nem kapott aktivációs meghívót.',
            ]);
        }

        $campaign = $this->queueCampaign($institution, $validated, $recipientPayloads);

        return redirect()
            ->route('dashboard.institution.communication.emails.show', $campaign)
            ->with('success', 'A fiókaktiválási meghívók sorba állítva.');
    }

    /**
     * Egyetlen, konkrét gondviselőnek szóló aktivációs meghívó. Ugyanazt a
     * kampány-infrastruktúrát használja, mint a tömeges küldés (store()),
     * csak a címzettek körét szűkíti egyetlen gondviselőre (és az ugyanazt
     * az e-mail címet használó testvér-gondviselőire, ld.
     * buildRecipientPayloads()) - így ugyanúgy megjelenik a küldési
     * előzményekben, és ugyanúgy jelöli az activation_email_sent_at mezőt,
     * mint a tömeges kiküldés.
     */
    public function sendIndividual(Request $request, Guardian $guardian): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($guardian->institution_id !== $institution->id, 404);

        $validated = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
        ], [
            'subject.max' => 'Az e-mail tárgya legfeljebb 191 karakter lehet.',
        ])->validate();
        $validated['body'] = CampaignHtmlSanitizer::clean($validated['body']);

        $normalizedEmail = $this->normalizeEmail($guardian->email);

        if ($normalizedEmail === null || $guardian->user_id !== null || ! $guardian->active) {
            throw ValidationException::withMessages([
                'guardian' => 'Ehhez a gondviselőhöz jelenleg nem küldhető aktiválási meghívó (nincs e-mail címe, vagy már aktiválta a fiókját).',
            ]);
        }

        $relatedGuardians = $this->activatableGuardiansQuery($institution)
            ->get()
            ->filter(fn (Guardian $candidate) => $this->normalizeEmail($candidate->email) === $normalizedEmail)
            ->values();

        $recipientPayloads = $this->buildRecipientPayloads($relatedGuardians);

        if ($recipientPayloads->isEmpty()) {
            throw ValidationException::withMessages([
                'guardian' => 'Ehhez a gondviselőhöz jelenleg nem küldhető aktiválási meghívó.',
            ]);
        }

        $this->queueCampaign($institution, $validated, $recipientPayloads);

        return redirect()
            ->route('dashboard.institution.communication.parent-activation-invite.index')
            ->with('success', "Aktivációs meghívó elküldve ide: {$normalizedEmail}.");
    }

    /**
     * Egyszeri, másolható aktiváló link legenerálása egy adott
     * gondviselőhöz - a rendszer NEM küld e-mailt, az admin maga küldi ki a
     * linket egy saját levélben. A link élettartama emiatt jóval hosszabb,
     * mint a szülő saját, azonnali kérésre generált linkjéé (ld.
     * ParentAccountActivationService::createManualActivationLink()).
     */
    public function generateLink(Guardian $guardian, ParentAccountActivationService $activationService): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($guardian->institution_id !== $institution->id, 404);

        $normalizedEmail = $this->normalizeEmail($guardian->email);

        if ($normalizedEmail === null || $guardian->user_id !== null || ! $guardian->active) {
            return redirect()
                ->route('dashboard.institution.communication.parent-activation-invite.index')
                ->with('error', 'Ehhez a gondviselőhöz jelenleg nem generálható aktiváló link (nincs e-mail címe, vagy már aktiválta a fiókját).');
        }

        $result = $activationService->createManualActivationLink($normalizedEmail);

        if ($result['status'] !== 'activation_created') {
            return redirect()
                ->route('dashboard.institution.communication.parent-activation-invite.index')
                ->with('error', match ($result['status']) {
                    'non_parent_user_exists' => 'Ehhez az e-mail címhez már más típusú felhasználói fiók tartozik, link nem generálható.',
                    'already_active_parent' => 'Ez a gondviselő már aktiválta a fiókját, link nem generálható.',
                    default => 'Ehhez a gondviselőhöz jelenleg nem generálható aktiváló link.',
                });
        }

        // A tömeges küldéssel megegyezően jelöljük, hogy ez az e-mail cím
        // (és az azt használó összes gondviselő rekord) kapott már valamilyen
        // aktivációs lehetőséget - így nem jelenik meg még egyszer a
        // tömeges "kiküldhető" listán, és nem kap véletlenül két, egymástól
        // független aktiváló linket.
        Guardian::query()
            ->where('institution_id', $institution->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNull('activation_email_sent_at')
            ->update(['activation_email_sent_at' => now()]);

        return redirect()
            ->route('dashboard.institution.communication.parent-activation-invite.index')
            ->with('manual_activation_url', $result['activation_url'])
            ->with('manual_activation_expires_at', $result['expires_at']->translatedFormat('Y. m. d. H:i'))
            ->with('manual_activation_recipient', ($guardian->full_name ?: $normalizedEmail).' ('.$normalizedEmail.')');
    }

    /**
     * Egy kampány (EmailCampaign + recipiensek) létrehozása és a küldő jobok
     * sorba állítása - a tömeges (store()) és az egyedi (sendIndividual())
     * küldés is ugyanezt a logikát használja, csak más címzett-listával.
     *
     * @param  array{subject: string, body: string}  $validated
     */
    private function queueCampaign(Institution $institution, array $validated, Collection $recipientPayloads): EmailCampaign
    {
        $campaign = null;

        DB::transaction(function () use ($institution, $validated, $recipientPayloads, &$campaign) {
            $campaign = EmailCampaign::query()->create([
                'institution_id' => $institution->id,
                'created_by' => auth()->id(),
                'type' => EmailCampaign::TYPE_PARENT_ACTIVATION_INVITE,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => EmailCampaign::STATUS_QUEUED,
                'recipient_count' => $recipientPayloads->count(),
                'sent_count' => 0,
                'failed_count' => 0,
                'queued_at' => now(),
            ]);

            $guardianIds = [];

            foreach ($recipientPayloads as $payload) {
                $campaign->recipients()->create([
                    'guardian_id' => $payload['guardian_id'],
                    'email' => $payload['email'],
                    'recipient_name' => $payload['recipient_name'],
                    'child_names' => $payload['child_names'],
                    'class_group_names' => $payload['class_group_names'],
                    'status' => EmailCampaignRecipient::STATUS_QUEUED,
                ]);

                foreach ($payload['guardian_ids'] as $guardianId) {
                    $guardianIds[] = $guardianId;
                }
            }

            Guardian::query()
                ->whereIn('id', array_values(array_unique($guardianIds)))
                ->update(['activation_email_sent_at' => now()]);

            DB::afterCommit(function () use ($campaign) {
                $campaign->recipients()
                    ->pluck('id')
                    ->each(fn (int $recipientId) => SendCampaignEmailJob::dispatch($recipientId));
            });
        });

        return $campaign;
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    /**
     * Alap-lekérdezés minden még nem aktivált, e-mail címmel rendelkező
     * gondviselőre - függetlenül attól, hogy kapott-e már korábban
     * aktivációs meghívót. Ezt használja mind a tömeges küldés eligibility-
     * szűrése (ld. eligibleGuardiansQuery() lentebb), mind az egyedi
     * meghívó / link-generálás gondviselő-választója.
     */
    private function activatableGuardiansQuery(Institution $institution): Builder
    {
        return Guardian::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('children', fn ($query) => $query->where('children.active', true))
            ->with([
                'children' => fn ($query) => $query->where('children.active', true)->orderBy('name'),
                'children.classGroups' => fn ($query) => $query->where('class_groups.active', true)->orderBy('name'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function eligibleGuardiansQuery(Institution $institution): Builder
    {
        return $this->activatableGuardiansQuery($institution)
            ->whereNull('activation_email_sent_at');
    }

    /**
     * Az egyedi meghívó / link-generáló szekció gondviselő-választójához:
     * minden még nem aktivált gondviselő, jelezve azt is, ha korábban már
     * kapott meghívót (ez esetben "újraküldésről" van szó).
     */
    private function buildIndividualInviteOptions(Institution $institution): Collection
    {
        return $this->activatableGuardiansQuery($institution)
            ->get()
            ->map(fn (Guardian $guardian) => [
                'id' => $guardian->id,
                'name' => $guardian->full_name ?: $guardian->email,
                'email' => $guardian->email,
                'child_names' => $guardian->children->pluck('name')->filter()->unique()->values()->all(),
                'already_invited' => $guardian->activation_email_sent_at !== null,
            ])
            ->values();
    }

    /**
     * Egy címzett (payload) egyedi e-mail cím szerint jön létre, de mivel
     * elméletileg több gondviselő is megoszthat egy e-mail címet, minden
     * érintett gondviselő azonosítóját megőrizzük a "guardian_ids" alatt,
     * hogy küldés után mindegyiküknél jelöljük az activation_email_sent_at
     * mezőt - így senki nem kaphatja meg kétszer ugyanazt a meghívót.
     */
    private function buildRecipientPayloads(Collection $guardians): Collection
    {
        $recipients = collect();

        foreach ($guardians as $guardian) {
            $normalizedEmail = $this->normalizeEmail($guardian->email);

            if ($normalizedEmail === null) {
                continue;
            }

            $existing = $recipients->get($normalizedEmail, [
                'guardian_id' => $guardian->id,
                'guardian_ids' => [],
                'email' => $normalizedEmail,
                'recipient_name' => $guardian->full_name ?: null,
                'child_names' => [],
                'class_group_names' => [],
            ]);

            $existing['guardian_ids'][] = $guardian->id;

            $existing['child_names'] = collect($existing['child_names'])
                ->merge($guardian->children->pluck('name'))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            $existing['class_group_names'] = collect($existing['class_group_names'])
                ->merge($guardian->children->flatMap(fn ($child) => $child->classGroups->pluck('name')))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            $recipients->put($normalizedEmail, $existing);
        }

        return $recipients->values();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalizedEmail = mb_strtolower(trim((string) $email));

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $normalizedEmail;
    }

    private function defaultSubject(): string
    {
        return 'Digifood szülői fiók létrehozása – első lépések';
    }

    private function defaultBody(): string
    {
        $activationUrl = '#SZULOI_FIOK_AKTIVALASA_URL#';

        return <<<HTML
        <p>Kedves #SZULO_NEVE#!</p>

        <p>
            Örömmel értesítjük, hogy a(z) <strong>#INTEZMENY_NEVE#</strong> intézmény bevezette a Digifood
            online étkezés-nyilvántartó rendszerét. Ezen keresztül egyszerűen nyomon követheti
            gyermeke (#GYERMEK_NEVE#) étkezéseit, kényelmesen intézheti a lemondásokat, és elérheti a
            befizetésekkel kapcsolatos információkat is - mindezt egy helyen, otthonról.
        </p>

        <p><strong>Az első belépéshez mindössze néhány lépésre van szükség:</strong></p>

        <ol>
            <li>Kattintson az alábbi "Fiók aktiválása" gombra.</li>
            <li>Adja meg azt az e-mail címet, amelyen ezt az üzenetet kapta.</li>
            <li>Ellenőrizze postafiókját - hamarosan kap egy jelszó-létrehozó linket tartalmazó levelet.</li>
            <li>A linkre kattintva állítsa be jelszavát - ezzel a fiókja aktiválódik, és azonnal használhatja is.</li>
            <li>
                Belépés után a <strong>Saját fiók &rarr; Számlázási adatok</strong> menüpont alatt kérjük,
                ellenőrizze és szükség esetén pontosítsa a számlázási nevét, címét (irányítószám, település,
                utca, házszám) és adószámát - ez segít elkerülni a hibás vagy hiányos adatú számlákat.
            </li>
        </ol>

        <p style="text-align:center; margin: 24px 0;">
            <a href="{$activationUrl}"
               style="background-color:#d94a16; color:#ffffff; padding:12px 28px; border-radius:30px; text-decoration:none; font-weight:bold; display:inline-block;">
                Fiók aktiválása
            </a>
        </p>

        <p>
            Amennyiben a gomb nem működne, másolja be az alábbi linket böngészője címsorába:<br>
            <a href="{$activationUrl}">{$activationUrl}</a>
        </p>

        <p>
            Kérdés esetén forduljon bizalommal gyermeke osztályához (#OSZTALY#) tartozó intézményi
            adminisztrációhoz.
        </p>

        <p>Üdvözlettel:<br>#INTEZMENY_NEVE#</p>
        HTML;
    }
}
