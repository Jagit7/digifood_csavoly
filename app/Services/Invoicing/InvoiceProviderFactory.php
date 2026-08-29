<?php

namespace App\Services\Invoicing;

use App\Models\InstitutionSetting;
use InvalidArgumentException;

class InvoiceProviderFactory
{
    public function make(?string $provider): InvoiceProviderInterface
    {
        return match ($provider) {
            InstitutionSetting::INVOICING_PROVIDER_MANUAL => app(ManualInvoiceProvider::class),
            InstitutionSetting::INVOICING_PROVIDER_BILLINGO => app(BillingoInvoiceProvider::class),
            InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU => app(SzamlazzHuInvoiceProvider::class),
            default => throw new InvalidArgumentException('Ismeretlen számlázási szolgáltató.'),
        };
    }
}
