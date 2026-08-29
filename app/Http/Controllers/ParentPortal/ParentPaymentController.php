<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Services\ParentPortal\ParentPaymentPageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentPaymentController extends Controller
{
    public function __construct(
        private readonly ParentPaymentPageService $service
    ) {
    }

    public function index(Request $request): View
    {
        return view('parent.payments.index', $this->service->buildPageData($request->user()));
    }
}
