<?php

namespace App\Services\Kiosk;

use App\Models\InstitutionSetting;
use App\Services\Barcodes\EaterBarcodeService;
use Illuminate\Support\Str;

/**
 * Az intézményi kioszk "vezérlő kártyája" - egy, kifejezetten erre a célra
 * generált vonalkód, ami nem tartozik diákhoz/dolgozóhoz. Ezzel a kártyával
 * lehet a kioszk gépén, a vonalkód-olvasóval aktiválni/lezárni a kioszkot,
 * billentyűzet és egér nélkül (ld. MealKioskController::scan() - a
 * matches() metódussal dönti el, hogy egy beolvasott kód a vezérlő kártya
 * kódja-e, és ha igen, a normál étkezés-ellenőrzés helyett a kioszk
 * zárolt/aktív állapotát váltja).
 *
 * A token generálása és titkosított tárolása ugyanazt a mintát követi,
 * mint a gyermek/dolgozó vonalkódoknál (ld. EaterBarcodeService): a nyers
 * token az institution_settings.barcode_kiosk_control_token oszlopban
 * titkosítva (encrypted cast) tárolódik, a beolvasásos kereséshez pedig a
 * determinisztikus (SHA-256) barcode_kiosk_control_token_hash oszlop
 * szolgál majd.
 */
class KioskControlCardService
{
    public function __construct(
        private readonly EaterBarcodeService $barcodeService
    ) {}

    public function generate(InstitutionSetting $setting): bool
    {
        if ($setting->hasKioskControlCard()) {
            return false;
        }

        $this->persist($setting, $this->generateUniqueToken());

        return true;
    }

    public function regenerate(InstitutionSetting $setting): void
    {
        $this->persist($setting, $this->generateUniqueToken());
    }

    public function renderSvg(string $token, float $width = 260, float $height = 46): string
    {
        return $this->barcodeService->renderSvg($token, $width, $height);
    }

    /**
     * Igaz, ha a beolvasott (nyers) kód megegyezik az intézmény vezérlő
     * kártyájának kódjával. hash_equals() -t használunk az összehasonlításra
     * (időzítéses támadás elleni védelem), ugyanúgy, ahogy a gyermek/dolgozó
     * vonalkódok ellenőrzésénél is szokás.
     */
    public function matches(InstitutionSetting $setting, string $token): bool
    {
        if (! $setting->hasKioskControlCard() || $token === '') {
            return false;
        }

        return hash_equals((string) $setting->barcode_kiosk_control_token_hash, EaterBarcodeService::hashToken($token));
    }

    private function persist(InstitutionSetting $setting, string $token): void
    {
        $setting->forceFill([
            'barcode_kiosk_control_token' => $token,
            'barcode_kiosk_control_token_hash' => EaterBarcodeService::hashToken($token),
            'barcode_kiosk_control_generated_at' => now(),
        ])->save();
    }

    private function generateUniqueToken(): string
    {
        do {
            // "DFK" előtag (DigiFood Kiosk), hogy egy pillantásra
            // megkülönböztethető legyen egy gyermek/dolgozó "DF..." kódjától.
            $token = 'DFK'.Str::upper(Str::random(13));
        } while ($this->tokenExists($token));

        return $token;
    }

    private function tokenExists(string $token): bool
    {
        return InstitutionSetting::query()
            ->where('barcode_kiosk_control_token_hash', EaterBarcodeService::hashToken($token))
            ->exists();
    }
}
