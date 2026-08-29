<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\InstitutionAdminDevice;
use App\Models\InstitutionSetting;
use App\Models\User;
use App\Services\Kiosk\KioskDeviceBindingService;
use App\Support\AdminInstitutionContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly AdminInstitutionContext $institutionContext
    ) {}

    /**
     * Intézményi adminok "gép szerinti" bejelentkezés-korlátozásához
     * használt, tartós HttpOnly süti neve, ld. verifyInstitutionAdminDevice().
     */
    private const DEVICE_COOKIE = 'iad_device';

    private const DEVICE_COOKIE_MINUTES = 60 * 24 * 365 * 5; // 5 év

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors(['email' => 'Túl sok sikertelen belépési kísérlet. Kérjük, próbáld újra kb. '.ceil($seconds / 60).' perc múlva.'])
                ->withInput();
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);

            $request->session()->regenerate();

            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['email' => 'A fiókja inaktív. Kérjük, vegye fel a kapcsolatot az intézménnyel.'])
                    ->withInput();
            }

            if ($this->shouldVerifyInstitutionAdminDevice($user) && $this->adminBrowserRestrictionActive($user)) {
                $deviceCheck = $this->verifyInstitutionAdminDevice($request, $user);

                if (! $deviceCheck['allowed']) {
                    Auth::logout();

                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return back()
                        ->withErrors(['email' => $deviceCheck['message']])
                        ->withInput();
                }
            }

            switch ($user->role) {
                case User::ROLE_SUPER_ADMIN:
                    return redirect()->route('dashboard.superadmin');

                case User::ROLE_INSTITUTION_ADMIN:
                case User::ROLE_INSTITUTION_SECRETARY:
                case User::ROLE_KITCHEN:
                case User::ROLE_MUNICIPALITY:
                    return redirect()->route('dashboard.institution.home');

                case User::ROLE_MEAL_KIOSK:
                    $this->bindKioskDevice($request, $user);

                    return redirect()->route('kiosk.show');

                case User::ROLE_PARENT:
                    return redirect()->route('parent.dashboard');

                case User::ROLE_EMPLOYEE:
                    return redirect()->route('employee.dashboard');

                default:
                    Auth::logout();

                    return redirect()
                        ->route('auth.login')
                        ->withErrors(['email' => 'Nincs jogosultságod a belépéshez.']);
            }
        }

        RateLimiter::hit($throttleKey, 60);

        return back()
            ->withErrors(['email' => 'Hibás belépési adatok.'])
            ->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:6'],
            'school_code' => ['required', 'string', 'max:50', 'exists:institutions,institution_code'],
        ], [
            'school_code.exists' => 'A megadott iskolakód nem található.',
        ]);

        $institution = Institution::where('institution_code', $validated['school_code'])->firstOrFail();

        // forceCreate(): a "role"/"institution_id"/"is_active" mezők
        // tudatosan nincsenek a User modell fillable listájában
        // (jogosultság-eszkalációs védelem).
        $user = User::forceCreate([
            'name' => 'Felhasználó',
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('parent.dashboard');
    }

    public function showForgotForm()
    {
        return view('auth.forgot');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()
            ->withErrors(['email' => __($status)])
            ->withInput();
    }

    /**
     * Aktív-e az intézménynél a "gép szerinti" böngésző-korlátozás -
     * intézményenként kapcsolható (ld. InstitutionSetting::
     * adminBrowserRestrictionEnabled(), superadmin állítja az intézmény
     * szerkesztő felületén). Ez az EGYETLEN hely, ahol ezt eldöntjük -
     * a tényleges eszköz-jóváhagyási logika (verifyInstitutionAdminDevice)
     * változatlan marad, csak akkor hívódik meg, ha ez true-t ad vissza.
     *
     * Ha a felhasználóhoz valamiért nem tartozik intézmény, biztonsági
     * okból a korlátozás marad érvényben (ez volt az egyetlen lehetséges
     * viselkedés is a mező bevezetése előtt).
     */
    private function adminBrowserRestrictionActive(User $user): bool
    {
        $institutionIds = $this->resolveInstitutionIdsForAdminBrowserRestriction($user);

        if ($institutionIds === []) {
            return true;
        }

        foreach ($institutionIds as $institutionId) {
            $setting = InstitutionSetting::firstOrCreate(
                ['institution_id' => $institutionId],
                InstitutionSetting::defaults()
            );

            if ($setting->adminBrowserRestrictionEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Az intézményi adminok egy része nem a users.institution_id mezőn,
     * hanem az institution_user pivoton keresztül kap intézményi
     * hozzárendelést (pl. meghívás elfogadása után). A böngésző-
     * korlátozás kapcsolóját ezért a tényleges intézményi kapcsolatból
     * kell feloldani, különben a kikapcsolt korlátozás tévesen aktív
     * marad új eszközről belépve.
     */
    private function resolveInstitutionIdsForAdminBrowserRestriction(User $user): array
    {
        $institutionIds = [];

        if ($user->role === User::ROLE_INSTITUTION_ADMIN && $user->institution_id !== null) {
            $institutionIds[] = (int) $user->institution_id;
        }

        $adminInstitutionIds = $user->institutions()
            ->wherePivot('scope_role', User::ROLE_INSTITUTION_ADMIN)
            ->orderBy('institutions.id')
            ->pluck('institutions.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $institutionIds = array_values(array_unique([...$institutionIds, ...$adminInstitutionIds]));

        if ($institutionIds !== []) {
            return $institutionIds;
        }

        $fallbackInstitutionId = $this->institutionContext->currentInstitution($user)?->id;

        return $fallbackInstitutionId !== null ? [(int) $fallbackInstitutionId] : [];
    }

    private function shouldVerifyInstitutionAdminDevice(User $user): bool
    {
        if ($user->role === User::ROLE_INSTITUTION_ADMIN) {
            return true;
        }

        return $user->institutions()
            ->wherePivot('scope_role', User::ROLE_INSTITUTION_ADMIN)
            ->exists();
    }

    /**
     * "Gép szerinti" bejelentkezés-korlátozás intézményi adminoknak.
     *
     * Egy weboldal nem tudja lekérdezni a fizikai gép azonosítóját, ezért
     * ehelyett egy tartós, HttpOnly sütiben tárolt, véletlenszerű tokenhez
     * kötjük a bejelentkezést - ez a gyakorlatban egy adott böngésző-
     * példányt jelent ugyanazon a gépen.
     *
     * Felhasználónként legfeljebb InstitutionAdminDevice::MAX_DEVICES_PER_USER
     * (jelenleg 2) egymástól független eszköz-sor tartozhat, azaz akár két
     * különböző böngésző/gép is lehet egyszerre jóváhagyva. Ha a sütiben lévő
     * token megegyezik BÁRMELYIK jóváhagyott sor tokenjével, a bejelentkezés
     * folytatódhat. Ha a süti egy már folyamatban lévő ("pending") kérelemhez
     * tartozik, a kérelem metaadatai frissülnek, de a belépés továbbra sem
     * engedélyezett. Ha egyik sorhoz sem illik a süti (ismeretlen eszköz), és
     * van még szabad hely (kevesebb, mint MAX_DEVICES_PER_USER foglalt sor),
     * egy új, jóváhagyásra váró kérelmet hozunk létre - ezt csak a superadmin
     * hagyhatja jóvá (ld. InstitutionAdminDeviceController). Ha nincs szabad
     * hely, a próbálkozás elutasításra kerül.
     *
     * Egy már jóváhagyott, működő eszközt egy ismeretlen eszközről indított
     * próbálkozás szándékosan SOSEM ír felül/érvénytelenít automatikusan,
     * hogy ellopott jelszóval se lehessen kizárni a jogos felhasználót a
     * saját gépéről(ei)ről.
     *
     * @return array{allowed: bool, message: ?string}
     */
    private function verifyInstitutionAdminDevice(Request $request, User $user): array
    {
        $cookieToken = (string) $request->cookie(self::DEVICE_COOKIE);
        $cookieHash = $cookieToken !== '' ? hash('sha256', $cookieToken) : null;

        $devices = InstitutionAdminDevice::where('user_id', $user->id)->get();

        if ($cookieHash !== null) {
            foreach ($devices as $device) {
                if ($device->hasApprovedDevice() && hash_equals((string) $device->approved_token_hash, $cookieHash)) {
                    $device->forceFill(['last_used_at' => now()])->save();

                    return ['allowed' => true, 'message' => null];
                }
            }

            foreach ($devices as $device) {
                if ($device->hasPendingRequest() && hash_equals((string) $device->pending_token_hash, $cookieHash)) {
                    // Ugyanarról az eszközről érkező, még mindig jóvá nem hagyott
                    // ismételt próbálkozás - csak frissítjük a kérelem adatait,
                    // nem hozunk létre új sort.
                    $device->forceFill([
                        'pending_ip' => $request->ip(),
                        'pending_user_agent' => (string) $request->userAgent(),
                        'pending_requested_at' => now(),
                    ])->save();

                    return [
                        'allowed' => false,
                        'message' => 'Ez az eszköz még nincs jóváhagyva. Kérje meg a rendszer superadminját, hogy hagyja jóvá ezt a gépet, utána újra be tud jelentkezni.',
                    ];
                }
            }
        }

        // Ismeretlen eszköz - keressünk egy szabad (sem jóváhagyott, sem
        // függőben lévő) sort, amit fel lehet használni; ha nincs ilyen, és
        // a felhasználó már elérte a max. eszközszámot, elutasítjuk.
        $hadApprovedDevice = $devices->contains(fn (InstitutionAdminDevice $d) => $d->hasApprovedDevice());
        $freeSlot = $devices->first(fn (InstitutionAdminDevice $d) => $d->isFreeSlot());

        if (! $freeSlot && $devices->count() >= InstitutionAdminDevice::MAX_DEVICES_PER_USER) {
            return [
                'allowed' => false,
                'message' => 'Ehhez a fiókhoz már a megengedett legtöbb ('.InstitutionAdminDevice::MAX_DEVICES_PER_USER.') eszköz van regisztrálva vagy jóváhagyásra várva. Kérje meg a superadmint, hogy vonjon vissza vagy utasítson el egy meglévő eszközt, mielőtt egy újabbat próbálna jóváhagyatni.',
            ];
        }

        $device = $freeSlot ?? new InstitutionAdminDevice(['user_id' => $user->id]);
        $newToken = Str::random(64);

        $device->forceFill([
            'pending_token_hash' => hash('sha256', $newToken),
            'pending_ip' => $request->ip(),
            'pending_user_agent' => (string) $request->userAgent(),
            'pending_requested_at' => now(),
        ])->save();

        Cookie::queue(self::DEVICE_COOKIE, $newToken, self::DEVICE_COOKIE_MINUTES);

        return [
            'allowed' => false,
            'message' => $hadApprovedDevice
                ? 'Ez az eszköz nincs jóváhagyva. A fiók jelenleg egy másik, jóváhagyott gépről érhető el - kérje meg a superadmint, hogy hagyja jóvá ezt az új eszközt, ha erről szeretne belépni.'
                : 'Ez az eszköz még nincs jóváhagyva. Kérje meg a rendszer superadminját, hogy hagyja jóvá ezt a gépet, utána újra be tud jelentkezni.',
        ];
    }

    /**
     * A kioszk-felhasználó sikeres, billentyűzettel történő bejelentkezése
     * után az aktuális böngészőt hozzáköti az intézmény kioszkjához (ld.
     * KioskDeviceAutoLogin middleware) - ettől kezdve ezen a gépen nem kell
     * újra bejelentkezni. Nem érint semmilyen fizetési/számlázási adatot.
     */
    private function bindKioskDevice(Request $request, User $user): void
    {
        if ($user->institution_id === null) {
            return;
        }

        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $user->institution_id],
            InstitutionSetting::defaults()
        );

        app(KioskDeviceBindingService::class)->bind($setting);
    }
}
