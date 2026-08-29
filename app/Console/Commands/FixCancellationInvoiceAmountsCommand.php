<?php

namespace App\Console\Commands;

use App\Models\InstitutionInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Egyszeri adatjavító parancs a "sztornó számla nem számít bele az
 * összesítésekbe/egyenlegbe" hibához.
 *
 * A hiba oka: App\Services\Finance\InstitutionInvoiceService::cancel()
 * (és a Billingo-szinkron App\Services\Finance\BillingoInvoiceSyncService
 * megfelelő ága) a sztornó (jóváíró) számla net/vat/gross_amount mezőit
 * eddig az eredeti számla POZITÍV összegeként mentette el, ahelyett hogy
 * annak negatívját rögzítette volna. Emiatt minden olyan összesítés, ami
 * gross_amount-ot összegez (admin számla-lista összesítő, pénzügyi export,
 * szülői portál éves összesítője), tévesen duplán, pozitívként adta hozzá
 * a sztornózott számla összegét ahelyett, hogy az nullázta volna az
 * eredetit.
 *
 * A kódjavítás (lásd a fenti szolgáltatásokat) ezt mostantól helyesen,
 * negatív előjellel menti el ÚJ sztornózásoknál - ez a parancs a MÁR
 * LÉTEZŐ, hibásan (pozitív előjellel) elmentett sztornó-rekordokat
 * javítja ki egyszeri lefuttatással.
 *
 * Idempotens: csak azokat a sztornó-rekordokat módosítja, amelyeknek a
 * gross_amount (vagy net/vat_amount) mezője jelenleg POZITÍV - egy már
 * kijavított (negatív összegű) sztornó-rekordot változatlanul hagy, így a
 * parancs többször is biztonságosan lefuttatható.
 */
class FixCancellationInvoiceAmountsCommand extends Command
{
    protected $signature = 'digifood:institution-invoices:fix-cancellation-amounts
        {--dry-run : Csak jelentést készít, nem ír adatbázist}';

    protected $description = 'A hibásan pozitív előjellel mentett sztornó (jóváíró) számlák net/vat/gross_amount mezőinek egyszeri, negatív előjelűre javítása.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $affected = InstitutionInvoice::query()
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION)
            ->where(function ($query) {
                $query
                    ->where('gross_amount', '>', 0)
                    ->orWhere('net_amount', '>', 0)
                    ->orWhere('vat_amount', '>', 0);
            })
            ->get(['id', 'institution_id', 'invoice_number', 'original_invoice_id', 'net_amount', 'vat_amount', 'gross_amount']);

        if ($affected->isEmpty()) {
            $this->info('Nincs javítandó sztornó számla - minden jóváíró számla összege már helyesen negatív.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d db javítandó sztornó számla található.', $affected->count()));

        foreach ($affected as $invoice) {
            $this->line(sprintf(
                '  #%d (intézmény #%d, %s): net %d -> %d | vat %d -> %d | gross %d -> %d',
                $invoice->id,
                $invoice->institution_id,
                $invoice->invoice_number ?: '(szám nélkül)',
                $invoice->net_amount,
                $invoice->net_amount > 0 ? -$invoice->net_amount : $invoice->net_amount,
                $invoice->vat_amount,
                $invoice->vat_amount > 0 ? -$invoice->vat_amount : $invoice->vat_amount,
                $invoice->gross_amount,
                $invoice->gross_amount > 0 ? -$invoice->gross_amount : $invoice->gross_amount,
            ));
        }

        if ($dryRun) {
            $this->warn('Dry-run mód: nem történt adatbázis-írás.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($affected) {
            foreach ($affected as $invoice) {
                InstitutionInvoice::query()
                    ->whereKey($invoice->id)
                    ->update([
                        'net_amount' => DB::raw('CASE WHEN net_amount > 0 THEN net_amount * -1 ELSE net_amount END'),
                        'vat_amount' => DB::raw('CASE WHEN vat_amount > 0 THEN vat_amount * -1 ELSE vat_amount END'),
                        'gross_amount' => DB::raw('CASE WHEN gross_amount > 0 THEN gross_amount * -1 ELSE gross_amount END'),
                    ]);
            }
        });

        $this->info('Kész: a fent listázott sztornó számlák összegei negatívra javítva.');

        return self::SUCCESS;
    }
}
