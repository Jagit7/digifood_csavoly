<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Services\Barcodes\EaterBarcodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InstitutionEmployeeBarcodeController extends Controller
{
    public function __construct(
        private readonly EaterBarcodeService $barcodeService
    ) {}

    public function store(InstitutionEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);

        if (! $this->barcodeService->generate($employee)) {
            return back()->with('error', 'Ehhez a dolgozóhoz már tartozik vonalkód.');
        }

        return back()->with('success', 'A vonalkód sikeresen létrehozva.');
    }

    public function destroy(InstitutionEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);

        if (! $this->barcodeService->disable($employee)) {
            return back()->with('error', 'Csak aktív vonalkód tiltható le.');
        }

        return back()->with('success', 'A vonalkód letiltva.');
    }

    public function activate(InstitutionEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);

        if (! $this->barcodeService->activate($employee)) {
            return back()->with('error', 'Csak letiltott vonalkód aktiválható.');
        }

        return back()->with('success', 'A vonalkód aktiválva.');
    }

    public function regenerate(InstitutionEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($employee);
        $this->barcodeService->regenerate($employee);

        return back()->with('success', 'A vonalkód sikeresen újragenerálva.');
    }

    public function print(InstitutionEmployee $employee): View|RedirectResponse
    {
        $this->authorizeEmployee($employee);

        if (! $employee->hasActiveBarcode()) {
            return back()->with('error', 'Csak aktív vonalkóddal rendelkező dolgozó nyomtatható.');
        }

        return view('dashboard.institution_admin.employees.barcodes.print', [
            'institution' => $this->institution(),
            'employee' => $employee,
            'barcodeSvg' => $this->barcodeService->renderSvg($employee->barcode_token),
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeEmployee(InstitutionEmployee $employee): void
    {
        abort_if($employee->institution_id !== $this->institution()->id, 403);
    }
}
