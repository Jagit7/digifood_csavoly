<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\InstitutionInvoice;
use App\Services\ParentPortal\ParentInvoicePageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ParentInvoiceController extends Controller
{
    public function __construct(
        private readonly ParentInvoicePageService $service
    ) {
    }

    public function index(Request $request): View
    {
        return view('parent.invoices.index', $this->service->buildPageData(
            $request->user(),
            $request->query('year')
        ));
    }

    public function download(Request $request, InstitutionInvoice $invoice): Response
    {
        return $this->service->downloadInvoiceDocument($request->user(), $invoice);
    }
}
