<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionFinanceExportRequest;
use App\Models\Institution;
use App\Services\Finance\InstitutionFinanceExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstitutionFinanceExportController extends Controller
{
    public function __construct(
        private readonly InstitutionFinanceExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $institution = $this->institution();

        return view('dashboard.institution_admin.finance.exports.index', [
            'institution' => $institution,
            'groupOptions' => $this->exportService->groupOptions($institution),
            'exportTypes' => InstitutionFinanceExportRequest::exportTypeOptions(),
            'statusOptions' => InstitutionFinanceExportRequest::statusOptions(),
            'exportCards' => $this->exportService->exportCards(),
        ]);
    }

    public function download(InstitutionFinanceExportRequest $request): BinaryFileResponse
    {
        return $this->exportService->download($this->institution(), $request->validated());
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }
}
