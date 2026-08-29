<?php

namespace App\Services\Barcodes;

use App\Models\Child;
use App\Models\InstitutionEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class EaterBarcodeService
{
    public function generate(Model $eater): bool
    {
        if ($this->hasBarcode($eater)) {
            return false;
        }

        $this->persistBarcode($eater, $this->generateUniqueToken());

        return true;
    }

    public function regenerate(Model $eater): void
    {
        $this->persistBarcode($eater, $this->generateUniqueToken());
    }

    public function disable(Model $eater): bool
    {
        if (! $this->hasActiveBarcode($eater)) {
            return false;
        }

        $eater->forceFill([
            'barcode_disabled_at' => now(),
        ])->save();

        return true;
    }

    public function activate(Model $eater): bool
    {
        if (! $this->hasDisabledBarcode($eater)) {
            return false;
        }

        $eater->forceFill([
            'barcode_disabled_at' => null,
        ])->save();

        return true;
    }

    public function renderSvg(string $token, float $width = 250, float $height = 44): string
    {
        $barcode = (new TypeCode128)->getBarcode($token);
        $renderer = new SvgRenderer;
        $renderer->setSvgType(SvgRenderer::TYPE_SVG_INLINE);

        return $renderer->render($barcode, $width, $height);
    }

    private function persistBarcode(Model $eater, string $token): void
    {
        $eater->forceFill([
            'barcode_token' => $token,
            // A barcode_token maga titkosítva tárolódik (nem kereshető
            // pontos egyezéssel), ezért egy determinisztikus (SHA-256)
            // hash-t is tárolunk mellette - ez teszi lehetővé az
            // egyediség-ellenőrzést és a beolvasásos keresést
            // (MealEligibilityService) anélkül, hogy a nyers tokent
            // bárhol WHERE feltételben kellene használni.
            'barcode_token_hash' => self::hashToken($token),
            'barcode_generated_at' => now(),
            'barcode_disabled_at' => null,
        ])->save();
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = 'DF'.Str::upper(Str::random(14));
        } while ($this->tokenExists($token));

        return $token;
    }

    private function tokenExists(string $token): bool
    {
        $hash = self::hashToken($token);

        return Child::query()->where('barcode_token_hash', $hash)->exists()
            || InstitutionEmployee::query()->where('barcode_token_hash', $hash)->exists();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function hasBarcode(Model $eater): bool
    {
        return method_exists($eater, 'hasBarcode') && $eater->hasBarcode();
    }

    private function hasActiveBarcode(Model $eater): bool
    {
        return method_exists($eater, 'hasActiveBarcode') && $eater->hasActiveBarcode();
    }

    private function hasDisabledBarcode(Model $eater): bool
    {
        return method_exists($eater, 'hasDisabledBarcode') && $eater->hasDisabledBarcode();
    }
}
