<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Billing\RevenueOverviewService;
use Illuminate\View\View;

class RevenueOverviewController extends Controller
{
    public function __construct(
        private readonly RevenueOverviewService $service,
    ) {
    }

    public function index(): View
    {
        $summary = $this->service->summary(12);

        return view('dashboard.superadmin.revenue_overview.index', [
            'summary' => $summary,
        ]);
    }
}
