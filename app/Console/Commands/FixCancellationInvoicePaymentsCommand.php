<?php

namespace App\Console\Commands;

use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Services\Finance\InstitutionInvoiceService;
use Illuminate\Console\Command;

/**
 * Egyszeri adatjavító parancs a "sztornózott számla befizetése nem lett
 * visszavonva" hibához.
 *
 * A hiba oka: App\Services\Finance\InstitutionInvoiceService::cancel()
 * eddig csak magát a számlát sztornózta, a hozzá tartozó, már teljesült
 * (completed) InstitutionPayment rekordot nem vonta vissza. Emiatt a
 * szülői havi elszámolás (ParentMonthlySettlementService) a sztornózás
 * UTÁN is "kifizetettként" mutatta a hónapot (hiszen a korábbi befizetés
 * összege továbbra is levonásra került a fennmaradó összegből), és a
 * szülő nem tudott újra fizetni - miközben az admin oldalon a hónap
 * ténylegesen újra fizetendőnek számított.
 *
 * A kódjavítás (lásd InstitutionInvoiceService::cancel() és az újonnan
 * hozzáadott reverseLinkedPaymentAfterCancellation() metódus) ezt
 * mostantól helyesen kezeli ÚJ sztornózásoknál - ez a parancs a MÁR
 * KORÁBBAN sztornózott, de emiatt még "kifizetettként" ragadt
 * kimutatásokat javítja ki egyszeri lefuttatással, UGYANAZZAL a biztonsági
 * logikával (csak egyértelműen azonosítható befizetést vonunk vissza).
 *
 * Idempotens: csak azokat az eredeti (voided) számlákat vizsgálja, amelyek
 * mellé már létrejött egy sztornó (cancellation) számla, ÉS a hozzájuk
 * tartozó kimutatáshoz még van "completed" állapotú befizetés - egy már
 * kijavított esetet (ahol a befizetés már "cancelled") változatlanul
 * hagy, így a parancs többször is biztonságosan lefuttatható.
 */
class FixCancellationInvoicePaymentsCommand extends Command
{
    protected $signature = 'digifood:institution-invoices:fix-cancellation-payments
        {--dry-run : Csak jelentést készít, nem ír adatbázist}';

    protected $description = 'A korábban sztornózott számlákhoz tartozó, tévesen "kifizetve" állapotban maradt befizetések visszavonása (cancelled).';

    public function handle(InstitutionInvoiceService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $voidedWithCancellation = InstitutionInvoice::query()
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
            ->where('status', InstitutionInvoice::STATUS_VOIDED)
            ->whereHas('cancellationInvoice')
            ->get();

        $candidates = $voidedWithCancellation->filter(function (InstitutionInvoice $invoice) {
            return $this->findReversiblePayment($invoice) !== null;
        })->values();

        if ($candidates->isEmpty()) {
            $this->info('Nincs javítandó tétel - minden sztornózott számlához tartozó befizetés már rendben van.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d db sztornózott számlához tartozik még visszavonandó befizetés.', $candidates->count()));

        foreach ($candidates as $invoice) {
            $payment = $this->findReversiblePayment($invoice);
            $this->line(sprintf(
                '  Számla #%d (%s, kimutatás #%d): befizetés #%d, %d Ft, hivatkozás: %s',
                $invoice->id,
                $invoice->invoice_number ?: '(szám nélkül)',
                $invoice->monthly_payment_statement_id,
                $payment->id,
                $payment->amount,
                $payment->reference
            ));
        }

        if ($dryRun) {
            $this->warn('Dry-run mód: nem történt adatbázis-írás.');

            return self::SUCCESS;
        }

        foreach ($candidates as $invoice) {
            $service->reverseLinkedPaymentAfterCancellation($invoice);
        }

        $this->info('Kész: a fent listázott befizetések "cancelled" állapotba állítva.');

        return self::SUCCESS;
    }

    /**
     * Ugyanaz az egyértelműség-ellenőrzés, mint
     * InstitutionInvoiceService::reverseLinkedPaymentAfterCancellation()
     * belsejében - csak jelentéskészítés (dry-run/lista) céljából, magát a
     * módosítást a szolgáltatás tényleges metódusa végzi.
     */
    private function findReversiblePayment(InstitutionInvoice $invoice): ?InstitutionPayment
    {
        $payment = $invoice->institution_payment_id
            ? InstitutionPayment::query()
                ->where('id', $invoice->institution_payment_id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->first()
            : null;

        if (! $payment && $invoice->monthly_payment_statement_id) {
            $completedPayments = InstitutionPayment::query()
                ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->get();

            if ($completedPayments->count() === 1) {
                $payment = $completedPayments->first();
            }
        }

        return $payment;
    }
}
