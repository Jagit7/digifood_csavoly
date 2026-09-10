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
    ) {}

    public function index(Request $request): View
    {
        $month = $this->selectedMonth($request);
        $summary = $this->service->summary($month);
        $rows = collect($summary['rows']);
        $missingRateInstitutions = $summary['missing'];

        return view('dashboard.superadmin.saas_billing_summary.index', [
            'rows' => $rows,
            'missingRateInstitutions' => $missingRateInstitutions,
            'institutionsHandledByPartner' => $this->service->institutionsHandledByPartner(),
            'totalAmount' => $summary['totalAmount'],
            'totalEaters' => $summary['totalEaters'],
            'selectedMonth' => $month->format('Y-m'),
            'maximumMonth' => $this->service->billingMonth()->format('Y-m'),
            'snapshotRun' => $summary['run'],
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
        $month = $this->selectedMonth($request);
        try {
            $run = $this->service->send($month, 'manual', auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('dashboard.saas-billing-summary.index', ['month' => $month->format('Y-m')])
                ->withErrors(['saas_billing_summary' => $exception->getMessage()]);
        }

        if ($run->status === SaasBillingSummaryRun::STATUS_SENT) {
            return redirect()
                ->route('dashboard.saas-billing-summary.index', ['month' => $month->format('Y-m')])
                ->with('success', 'A havi összesítő elküldött állapotú. Ismételt levél nem készül.');
        }

        return redirect()
            ->route('dashboard.saas-billing-summary.index', ['month' => $month->format('Y-m')])
            ->withErrors(['saas_billing_summary' => 'A küldés ellenőrzést igényel; automatikus újraküldés nincs. '.($run->error_message ?: $run->status_label)]);
    }

    private function selectedMonth(Request $request): \Carbon\CarbonImmutable
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['month'])
            ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $validated['month'], $this->calendar->timezone())
            : $this->service->billingMonth();
        abort_if($month->gt($this->service->billingMonth()), 422, 'Jövőbeli hónap nem választható.');

        return $month;
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
