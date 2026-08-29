<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SaasBillingSummaryItem;
use App\Models\SaasBillingSummaryRun;
use App\Services\Billing\SaasBillingSummaryService;
use App\Services\InstitutionCalendarService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SaasBillingSummaryController extends Controller
{
    public function __construct(
        private readonly SaasBillingSummaryService $service,
        private readonly InstitutionCalendarService $calendar,
    ) {
    }

    public function index(): View
    {
        $month = $this->calendar->now()->startOfMonth();

        $rows = $this->service->pendingBillableInstitutions($month);
        $missingRateInstitutions = $this->service->institutionsMissingRate();

        return view('dashboard.superadmin.saas_billing_summary.index', [
            'rows' => $rows,
            'missingRateInstitutions' => $missingRateInstitutions,
            'institutionsHandledByPartner' => $this->service->institutionsHandledByPartner(),
            'totalAmount' => $rows->sum('total_amount'),
            'totalEaters' => $rows->sum('eaters_count'),
            'monthLabel' => $this->service->formatMonthLabel($month),
            'alreadySent' => $this->service->alreadySentThisMonth($month),
            'dayOfMonth' => (int) config('digifood.saas_billing_summary_day_of_month', 5),
            'recipientEmail' => config('digifood.saas_billing_summary_recipient', 'info@digifood.hu'),
            'lastRuns' => $this->service->lastRuns(),
            'items' => $this->service->items(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $month = $this->calendar->now()->startOfMonth();

        $run = $this->service->send($month, 'manual', auth()->id());

        if ($run->status === SaasBillingSummaryRun::STATUS_SENT) {
            return redirect()
                ->route('dashboard.saas-billing-summary.index')
                ->with('success', 'A havi számlázási összesítő elküldve.');
        }

        return redirect()
            ->route('dashboard.saas-billing-summary.index')
            ->withErrors(['saas_billing_summary' => 'A küldés sikertelen: '.$run->error_message]);
    }

    public function markItemInvoiced(Request $request, SaasBillingSummaryItem $item): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ], [
            'invoice_number.required' => 'A számlaszám megadása kötelező.',
            'invoice_number.max' => 'A számlaszám legfeljebb 100 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('dashboard.saas-billing-summary.index')
                ->withErrors($validator);
        }

        try {
            $this->service->markItemInvoiced(
                $item,
                $validator->validated()['invoice_number'],
                $validator->validated()['note'] ?? null,
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('dashboard.saas-billing-summary.index')
                ->withErrors(['saas_billing_summary' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard.saas-billing-summary.index')
            ->with('success', 'A tétel számlázottnak jelölve.');
    }

    public function markItemPaid(SaasBillingSummaryItem $item): RedirectResponse
    {
        try {
            $this->service->markItemPaid($item);
        } catch (DomainException $exception) {
            return redirect()
                ->route('dashboard.saas-billing-summary.index')
                ->withErrors(['saas_billing_summary' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard.saas-billing-summary.index')
            ->with('success', 'A tétel fizetettnek jelölve.');
    }
}
