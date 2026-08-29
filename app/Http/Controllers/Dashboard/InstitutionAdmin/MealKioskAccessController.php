<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\User;
use App\Services\Kiosk\KioskControlCardService;
use App\Services\Kiosk\KioskDeviceBindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MealKioskAccessController extends Controller
{
    public function __construct(
        private readonly KioskControlCardService $controlCardService,
        private readonly KioskDeviceBindingService $deviceBindingService
    ) {}

    public function edit(): View
    {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $kioskUser = $this->kioskUser($institution);

        return view('dashboard.institution_admin.children.barcodes.kiosk', [
            'institution' => $institution,
            'setting' => $setting,
            'kioskUser' => $kioskUser,
            'generatedPassword' => session('kiosk_generated_password'),
            'controlCardSvg' => $setting->hasKioskControlCard()
                ? $this->controlCardService->renderSvg($setting->barcode_kiosk_control_token)
                : null,
        ]);
    }

    public function generateControlCard(): RedirectResponse
    {
        $setting = $this->settingForCurrentInstitution();

        if (! $this->controlCardService->generate($setting)) {
            return redirect()
                ->route('dashboard.institution.children.barcodes.kiosk.edit')
                ->with('error', 'Vezérlő kártya már létezik ehhez az intézményhez - ha lecserélnéd, használd az újragenerálást.');
        }

        return redirect()
            ->route('dashboard.institution.children.barcodes.kiosk.edit')
            ->with('success', 'A vezérlő kártya elkészült. Nyomtasd ki, és tartsd biztonságos helyen.');
    }

    public function regenerateControlCard(): RedirectResponse
    {
        $setting = $this->settingForCurrentInstitution();
        $this->controlCardService->regenerate($setting);

        return redirect()
            ->route('dashboard.institution.children.barcodes.kiosk.edit')
            ->with('success', 'A vezérlő kártya újragenerálva. A korábbi, kinyomtatott kártya mostantól nem fog működni - nyomtasd ki az újat.');
    }

    public function printControlCard(): View|RedirectResponse
    {
        $institution = $this->institution();
        $setting = $this->settingForCurrentInstitution();

        if (! $setting->hasKioskControlCard()) {
            return redirect()
                ->route('dashboard.institution.children.barcodes.kiosk.edit')
                ->with('error', 'Előbb generálj vezérlő kártyát.');
        }

        return view('dashboard.institution_admin.children.barcodes.kiosk-control-card-print', [
            'institution' => $institution,
            'barcodeSvg' => $this->controlCardService->renderSvg($setting->barcode_kiosk_control_token),
            'token' => $setting->barcode_kiosk_control_token,
        ]);
    }

    public function unbindDevice(): RedirectResponse
    {
        $setting = $this->settingForCurrentInstitution();
        $this->deviceBindingService->unbind($setting);

        return redirect()
            ->route('dashboard.institution.children.barcodes.kiosk.edit')
            ->with('success', 'A böngésző-kötés törölve. A kioszk gépén a következő bejelentkezéskor (e-mail + jelszó) a böngésző automatikusan újra hozzá lesz kötve.');
    }

    private function settingForCurrentInstitution(): InstitutionSetting
    {
        $institution = $this->institution();

        return InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        if (! $setting->barcodeEntryEnabled()) {
            return redirect()
                ->route('dashboard.institution.children.barcodes.kiosk.edit')
                ->with('error', 'A beléptető modul nem része az intézmény előfizetésének.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:191', 'confirmed'],
            'admin_pin' => ['required', 'digits_between:4,8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kioskUser = DB::transaction(function () use ($institution, $validated, $request, $setting) {
            $user = $this->kioskUser($institution) ?? new User;

            $user->forceFill([
                'name' => 'Kioszk - '.$institution->name,
                'email' => $this->kioskEmail($institution),
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_MEAL_KIOSK,
                'institution_id' => $institution->id,
                'is_active' => $request->boolean('is_active', true),
            ])->save();

            DB::table('institution_user')->updateOrInsert(
                [
                    'institution_id' => $institution->id,
                    'user_id' => $user->id,
                ],
                [
                    'scope_role' => User::ROLE_MEAL_KIOSK,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $setting->forceFill([
                'barcode_kiosk_pin_hash' => Hash::make($validated['admin_pin']),
            ])->save();

            return $user;
        });

        return redirect()
            ->route('dashboard.institution.children.barcodes.kiosk.edit')
            ->with('success', 'A kioszk hozzáférés frissítve lett.')
            ->with('kiosk_generated_password', $validated['password'])
            ->with('kiosk_email', $kioskUser->email);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function kioskUser(Institution $institution): ?User
    {
        return User::query()
            ->where('institution_id', $institution->id)
            ->where('role', User::ROLE_MEAL_KIOSK)
            ->first();
    }

    private function kioskEmail(Institution $institution): string
    {
        return 'kiosk+'.strtolower($institution->institution_code).'@digifood.local';
    }
}
