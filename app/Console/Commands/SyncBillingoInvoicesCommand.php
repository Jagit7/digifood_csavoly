<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\InstitutionInvoiceSyncRun;
use App\Models\InstitutionSetting;
use App\Services\Finance\BillingoInvoiceSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncBillingoInvoicesCommand extends Command
{
    protected $signature = 'digifood:billingo-invoices:sync
        {--institution= : Csak az adott intézmény azonosítójára fusson}
        {--dry-run : Csak jelentést készít, nem ír adatbázist és nem tölt le PDF-et}
        {--missing-pdfs-only : Csak a hiányzó PDF-eket ellenőrizze}';

    protected $description = 'Billingo számlák és PDF-ek biztonságos szinkronizálása intézményenként.';

    public function handle(BillingoInvoiceSyncService $syncService): int
    {
        $institutionId = $this->option('institution');
        $dryRun = (bool) $this->option('dry-run');
        $missingPdfsOnly = (bool) $this->option('missing-pdfs-only');

        $institutions = Institution::query()
            ->when($institutionId, fn ($query) => $query->whereKey($institutionId))
            ->whereHas('setting', function ($query) {
                $query
                    ->where('invoicing_enabled', true)
                    ->where('invoicing_provider', InstitutionSetting::INVOICING_PROVIDER_BILLINGO);
            })
            ->orderBy('id')
            ->get();

        if ($institutions->isEmpty()) {
            $this->warn('Nincs szinkronizálható Billingo intézmény.');

            return self::SUCCESS;
        }

        foreach ($institutions as $institution) {
            $lock = Cache::lock("billingo-invoice-sync:institution:{$institution->id}", 900);

            if (! $lock->get()) {
                $this->warn("Az intézmény #{$institution->id} szinkronja már fut.");

                continue;
            }

            try {
                $summary = $syncService->syncInstitution(
                    $institution,
                    null,
                    InstitutionInvoiceSyncRun::MODE_AUTOMATIC,
                    $missingPdfsOnly,
                    $dryRun
                );

                $this->line(sprintf(
                    '#%d %s | státusz: %s | lekért: %d | új: %d | frissített: %d | pdf: %d | meglévő pdf: %d | párosítatlan: %d | hibák: %d',
                    $institution->id,
                    $institution->name,
                    $summary['status'],
                    $summary['fetched_documents'],
                    $summary['created_invoices'],
                    $summary['updated_invoices'],
                    $summary['downloaded_pdfs'],
                    $summary['existing_pdfs'],
                    $summary['unmatched_documents'],
                    $summary['error_count'],
                ));

                if ($summary['last_error']) {
                    $this->line('  Utolsó hiba: '.$summary['last_error']);
                }
            } finally {
                $lock->release();
            }
        }

        return self::SUCCESS;
    }
}
