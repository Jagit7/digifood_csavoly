<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Controller;
use App\Services\EmployeePortal\EmployeePaymentPageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeePaymentController extends Controller
{
    public function __construct(
        private readonly EmployeePaymentPageService $service
    ) {}

    public function index(Request $request): View
    {
        return view('employee.payments.index', $this->service->buildPageData($request->user()));
    }
}
