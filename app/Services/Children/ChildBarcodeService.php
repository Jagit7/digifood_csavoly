<?php

namespace App\Services\Children;

use App\Models\Child;
use App\Services\Barcodes\EaterBarcodeService;
use Illuminate\Support\Collection;

class ChildBarcodeService
{
    public function __construct(
        private readonly EaterBarcodeService $barcodeService
    ) {}

    public function generateForChild(Child $child): bool
    {
        return $this->barcodeService->generate($child);
    }

    public function regenerateForChild(Child $child): void
    {
        $this->barcodeService->regenerate($child);
    }

    public function disableForChild(Child $child): bool
    {
        return $this->barcodeService->disable($child);
    }

    public function generateMissingForChildren(Collection $children): int
    {
        $generatedCount = 0;

        foreach ($children as $child) {
            if ($this->generateForChild($child)) {
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    public function renderSvg(string $token, float $width = 250, float $height = 44): string
    {
        return $this->barcodeService->renderSvg($token, $width, $height);
    }
}
