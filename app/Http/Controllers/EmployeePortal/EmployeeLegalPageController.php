<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Concerns\HandlesLegalPages;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A ParentPortal\ParentLegalPageController dolgozói portálra vonatkozó
 * megfelelője - ugyanazokat a (portálfüggetlen) tartalmi partial-okat
 * jeleníti meg, csak az intézmény feloldása történik a dolgozói
 * jogviszonyon (InstitutionEmployee) keresztül a szülői Guardian helyett.
 */
class EmployeeLegalPageController extends Controller
{
    use HandlesLegalPages;

    public function termsOfService(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Általános Szerződési Feltételek',
            subtitle: 'A Digifood dolgozói felület használatának általános feltételei',
            contentView: 'parent.legal.partials.terms-of-service-content'
        );
    }

    public function terms(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Étkezési és fizetési feltételek',
            subtitle: 'Általános tájékoztató az elszámolásról és az online fizetésről',
            contentView: 'parent.legal.partials.terms-content'
        );
    }

    public function cardPayment(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Bankkártyás fizetési tájékoztató',
            subtitle: 'CIB Bank online bankkártyás fizetési tájékoztató',
            contentView: 'parent.legal.partials.card-payment-content'
        );
    }

    public function cardPaymentFaq(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'CIB bankkártyás fizetés GYFK',
            subtitle: 'Gyakran ismételt kérdések az online kártyás fizetésről',
            contentView: 'parent.legal.partials.card-payment-faq-content'
        );
    }

    public function dataProcessing(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Adatkezelési tájékoztató (online fizetés)',
            subtitle: 'A CIB Bank felé történő online fizetéshez kapcsolódó adattovábbításról',
            contentView: 'parent.legal.partials.data-processing-content'
        );
    }

    public function paymentFlow(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Vásárlói fizetési folyamat',
            subtitle: 'Lépésről lépésre a Digifood és a CIB Bank online fizetésében',
            contentView: 'parent.legal.partials.payment-flow-content'
        );
    }

    public function complaints(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Reklamáció és visszatérítés',
            subtitle: 'Tájékoztató panaszkezelésről, hibajelzésről és visszatérítésről',
            contentView: 'parent.legal.partials.complaints-content'
        );
    }

    public function customerService(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Ügyfélszolgálati adatok',
            subtitle: 'Kapcsolattartási pontok az intézményi és fizetési kérdésekhez',
            contentView: 'parent.legal.partials.customer-service-content'
        );
    }

    public function imprint(Request $request): View
    {
        return $this->renderPage(
            request: $request,
            title: 'Impresszum',
            subtitle: 'Intézményi és kapcsolattartási adatok',
            contentView: 'parent.legal.partials.imprint-content'
        );
    }

    private function renderPage(Request $request, string $title, string $subtitle, string $contentView): View
    {
        return $this->renderLegalPage(
            request: $request,
            pageView: 'employee.legal.page',
            routePrefix: 'employee.legal',
            fallbackRouteName: 'employee.dashboard',
            title: $title,
            subtitle: $subtitle,
            contentView: $contentView,
            institution: $this->resolveInstitution($request)
        );
    }

    private function resolveInstitution(Request $request): ?Institution
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $employee = InstitutionEmployee::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->with('institution.setting')
            ->orderBy('id')
            ->first();

        return $employee?->institution;
    }
}
