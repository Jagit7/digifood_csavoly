<?php

namespace App\Services\Kiosk;

use App\Models\InstitutionSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * A kioszk gépének böngészőjéhez kötött, automatikus bejelentkezés.
 *
 * Ugyanazt a mintát követi, mint az intézményi adminok "gép szerinti"
 * bejelentkezés-korlátozása (ld. AuthController::verifyInstitutionAdminDevice()
 * / InstitutionAdminDevice), egyszerűsítve: itt nem korlátozásról van szó,
 * hanem arról, hogy az ELSŐ, billentyűzettel történő bejelentkezés (ld.
 * AuthController::login()) után a kioszk böngészője a jövőben magától
 * felismerje magát, és ne kelljen naponta újra e-mailt/jelszót gépelni.
 *
 * Intézményenként egyetlen böngésző lehet "kötve" (a fizikai kioszk-gép),
 * a token hash-e az institution_settings táblában tárolódik. Egy új
 * bejelentkezés (pl. egy másik gépen) egyszerűen felülírja az előzőt -
 * ez szándékos, hiszen egy intézménynek jellemzően egy kioszk-gépe van.
 */
class KioskDeviceBindingService
{
    public const COOKIE = 'kiosk_device';

    private const COOKIE_MINUTES = 60 * 24 * 365 * 5; // 5 év

    /**
     * Az aktuális böngészőt (response-hoz csatolt süti formájában) az adott
     * intézmény kioszkjához köti. A nyers tokent sosem tároljuk, csak a
     * SHA-256 hash-ét (ld. InstitutionAdminDevice minta).
     */
    public function bind(InstitutionSetting $setting): void
    {
        $token = Str::random(64);

        $setting->forceFill([
            'barcode_kiosk_device_token_hash' => hash('sha256', $token),
            'barcode_kiosk_device_bound_at' => now(),
            'barcode_kiosk_device_last_used_at' => now(),
        ])->save();

        Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);
    }

    /**
     * Leválasztja a jelenleg kötött böngészőt (pl. ha kicserélik a
     * kioszk gépét) - a következő látogatáskor újra be kell jelentkezni
     * billentyűzettel, ami a böngészőt automatikusan újra köti.
     */
    public function unbind(InstitutionSetting $setting): void
    {
        $setting->forceFill([
            'barcode_kiosk_device_token_hash' => null,
            'barcode_kiosk_device_bound_at' => null,
            'barcode_kiosk_device_last_used_at' => null,
        ])->save();
    }

    /**
     * Megkeresi azt az intézményi beállítást, amelyikhez a kérésben lévő
     * süti (böngésző) hozzá van kötve - null, ha nincs süti, vagy ismeretlen/
     * érvénytelen a benne lévő token.
     */
    public function resolveSettingForRequest(Request $request): ?InstitutionSetting
    {
        $token = (string) $request->cookie(self::COOKIE);

        if ($token === '') {
            return null;
        }

        return InstitutionSetting::query()
            ->whereNotNull('barcode_kiosk_device_token_hash')
            ->where('barcode_kiosk_device_token_hash', hash('sha256', $token))
            ->first();
    }

    public function touchLastUsed(InstitutionSetting $setting): void
    {
        $setting->forceFill(['barcode_kiosk_device_last_used_at' => now()])->save();
    }
}
