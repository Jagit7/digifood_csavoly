<?php

namespace App\Jobs\Finance;

use App\Models\Institution;
use App\Models\InstitutionInvoiceSyncRun;
use App\Models\User;
use App\Services\Finance\BillingoInvoiceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RunBillingoInvoiceSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $institutionId,
        public readonly ?int $initiatedBy = null,
        public readonly string $mode = InstitutionInvoiceSyncRun::MODE_MANUAL,
        public readonly bool $missingPdfsOnly = false,
    ) {}

    public function handle(BillingoInvoiceSyncService $syncService): void
    {
        $lock = Cache::lock("billingo-invoice-sync:institution:{$this->institutionId}", 900);

        if (! $lock->get()) {
            return;
        }

        try {
            $institution = Institution::findOrFail($this->institutionId);
            $initiator = $this->initiatedBy ? User::find($this->initiatedBy) : null;

            $syncService->syncInstitution(
                $institution,
                $initiator,
                $this->mode,
                $this->missingPdfsOnly,
                false
            );
        } finally {
            $lock->release();
        }
    }
}
