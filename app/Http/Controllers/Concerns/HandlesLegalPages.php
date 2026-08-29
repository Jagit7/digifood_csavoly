<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Közös logika a szülői és a dolgozói portál jogi/tájékoztató oldalaihoz
 * (ÁSZF, impresszum, bankkártyás fizetési tájékoztató, GYFK stb.).
 *
 * A tartalmi partial-ok (resources/views/parent/legal/partials/*) a "parent"
 * mappanév ellenére mindkét portálon megjelennek - egyetlen helyen tartjuk a
 * szöveget, hogy egy tartalomfrissítés (pl. a bank által előírt szöveg
 * cseréje) ne igényeljen két helyen párhuzamos módosítást.
 */
trait HandlesLegalPages
{
    /**
     * @return array<string, string|null>
     */
    private function buildMerchantProfile(?Institution $institution): array
    {
        $name = trim((string) ($institution?->billing_name ?: $institution?->name));
        $address = trim((string) ($institution?->hasCompleteBillingAddress()
            ? $institution?->full_billing_address
            : $institution?->full_address));

        return [
            'name' => $name !== '' ? $name : 'Az intézmény',
            'institution_name' => trim((string) ($institution?->name ?: '')),
            'address' => $address !== '' ? $address : null,
            'tax_number' => trim((string) ($institution?->billing_tax_number ?: '')) ?: null,
            'company_registration_number' => trim((string) ($institution?->company_registration_number ?: '')) ?: null,
            'email' => trim((string) ($institution?->email ?: '')) ?: null,
            'phone' => trim((string) ($institution?->phone ?: '')) ?: null,
            'om_identifier' => trim((string) ($institution?->om_identifier ?: '')) ?: null,
            'country' => 'Magyarország (HU)',
        ];
    }

    /**
     * @return Collection<int, array{label: string, route: string}>
     */
    private function buildLegalLinks(string $routePrefix): Collection
    {
        return collect([
            ['label' => 'Általános Szerződési Feltételek', 'route' => route("{$routePrefix}.terms-of-service")],
            ['label' => 'Étkezési és fizetési feltételek', 'route' => route("{$routePrefix}.terms")],
            ['label' => 'Bankkártyás fizetési tájékoztató', 'route' => route("{$routePrefix}.card-payment")],
            ['label' => 'CIB GYFK', 'route' => route("{$routePrefix}.card-payment-faq")],
            ['label' => 'Adatkezelési tájékoztató (online fizetés)', 'route' => route("{$routePrefix}.data-processing")],
            ['label' => 'Fizetési folyamat', 'route' => route("{$routePrefix}.payment-flow")],
            ['label' => 'Reklamáció és visszatérítés', 'route' => route("{$routePrefix}.complaints")],
            ['label' => 'Ügyfélszolgálat', 'route' => route("{$routePrefix}.customer-service")],
            ['label' => 'Impresszum', 'route' => route("{$routePrefix}.imprint")],
        ]);
    }

    private function renderLegalPage(
        Request $request,
        string $pageView,
        string $routePrefix,
        string $fallbackRouteName,
        string $title,
        string $subtitle,
        string $contentView,
        ?Institution $institution
    ): View {
        $previousUrl = url()->previous();
        $fallbackUrl = route($fallbackRouteName);
        $backUrl = $previousUrl !== url()->current() ? $previousUrl : $fallbackUrl;

        return view($pageView, [
            'title' => $title,
            'subtitle' => $subtitle,
            'contentView' => $contentView,
            'backUrl' => $backUrl,
            'institution' => $institution,
            'routePrefix' => $routePrefix,
            'merchant' => $this->buildMerchantProfile($institution),
            'legalLinks' => $this->buildLegalLinks($routePrefix),
        ]);
    }
}
