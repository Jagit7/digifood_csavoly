<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Controller;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Services\EmployeePortal\EmployeeInvoicePageService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EmployeeInvoiceController extends Controller
{
    public function __construct(
        private readonly EmployeeInvoicePageService $service
    ) {}

    public function index(Request $request): View
    {
        return view('employee.invoices.index', $this->service->buildPageData(
            $request->user(),
            $request->query('year')
        ));
    }

    public function download(Request $request, EmployeeMonthlyPaymentStatement $statement): Response
    {
        return $this->service->downloadInvoiceDocument($request->user(), $statement);
    }
}
