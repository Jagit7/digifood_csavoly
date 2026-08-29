<?php

namespace App\Services\Finance;

use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionInvoiceSyncRun;
use App\Models\InstitutionSetting;
use App\Models\User;
use App\Services\Finance\Providers\BillingoInvoiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingoInvoiceSyncService
{
    public function __construct(
        private readonly InstitutionInvoiceService $invoiceService,
        private readonly BillingoInvoiceProvider $provider,
    ) {}

    public function syncInstitution(
        Institution $institution,
        ?User $initiator = null,
        string $mode = InstitutionInvoiceSyncRun::MODE_AUTOMATIC,
        bool $missingPdfsOnly = false,
        bool $dryRun = false
    ): array {
        $settings = $this->invoiceService->settings($institution);
        $summary = $this->emptySummary($institution, $mode, $missingPdfsOnly, $dryRun);

        if (! $this->isReady($settings)) {
            $summary['status'] = InstitutionInvoiceSyncRun::STATUS_SKIPPED;
            $summary['last_error'] = 'A Billingo szinkronhoz hiányzik az aktív integráció, az API-kulcs vagy a dokumentumtömb.';

            return $summary;
        }

        $run = $dryRun ? null : InstitutionInvoiceSyncRun::create([
            'institution_id' => $institution->id,
            'initiated_by' => $initiator?->id,
            'mode' => $mode,
            'missing_pdfs_only' => $missingPdfsOnly,
            'status' => InstitutionInvoiceSyncRun::STATUS_SUCCESS,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $lastModifiedAt = $missingPdfsOnly ? null : $settings->billingo_sync_last_modified_at;
        $maxModifiedAt = $lastModifiedAt;
        $page = 1;

        try {
            do {
                $response = $this->provider->listDocuments($settings, $page, 50, $lastModifiedAt);
                $documents = $response['data'] ?? [];

                foreach ($documents as $document) {
                    $summary['fetched_documents']++;

                    $normalized = $this->normalizeDocument($document);
                    if (! $this->belongsToInstitutionBlock($normalized, $settings)) {
                        continue;
                    }

                    if ($normalized['last_modified_at'] && ($maxModifiedAt === null || $normalized['last_modified_at']->gt($maxModifiedAt))) {
                        $maxModifiedAt = $normalized['last_modified_at'];
                    }

                    $localInvoice = InstitutionInvoice::query()
                        ->where('institution_id', $institution->id)
                        ->where('provider', InstitutionInvoice::PROVIDER_BILLINGO)
                        ->where('provider_invoice_id', $normalized['provider_invoice_id'])
                        ->first();

                    if ($localInvoice) {
                        $this->syncKnownInvoice($institution, $localInvoice, $normalized, $summary, $dryRun, $missingPdfsOnly);

                        continue;
                    }

                    if ($normalized['is_cancellation'] && $normalized['original_provider_invoice_id']) {
                        $created = $this->createCancellationInvoiceIfMatch(
                            $institution,
                            $normalized,
                            $summary,
                            $dryRun
                        );

                        if ($created) {
                            continue;
                        }
                    }

                    $summary['unmatched_documents']++;
                }

                $page++;
            } while ($page <= (($response['last_page'] ?? 1) ?: 1));

            $summary['status'] = $summary['error_count'] > 0
                ? InstitutionInvoiceSyncRun::STATUS_PARTIAL
                : InstitutionInvoiceSyncRun::STATUS_SUCCESS;
        } catch (\Throwable $exception) {
            $summary['status'] = InstitutionInvoiceSyncRun::STATUS_FAILED;
            $summary['error_count']++;
            $summary['last_error'] = $exception->getMessage();
            Log::error('Billingo számlaszinkron sikertelen', [
                'institution_id' => $institution->id,
                'mode' => $mode,
                'dry_run' => $dryRun,
                'missing_pdfs_only' => $missingPdfsOnly,
                'exception' => $exception->getMessage(),
            ]);
        }

        if (! $dryRun) {
            $this->finalizeRun($settings, $run, $summary, $maxModifiedAt);
        }

        return $summary;
    }

    public function latestSyncMeta(Institution $institution): array
    {
        $latestSuccess = InstitutionInvoiceSyncRun::query()
            ->where('institution_id', $institution->id)
            ->where('status', InstitutionInvoiceSyncRun::STATUS_SUCCESS)
            ->latest('finished_at')
            ->first();

        $latestError = InstitutionInvoiceSyncRun::query()
            ->where('institution_id', $institution->id)
            ->whereIn('status', [InstitutionInvoiceSyncRun::STATUS_FAILED, InstitutionInvoiceSyncRun::STATUS_PARTIAL])
            ->whereNotNull('last_error')
            ->latest('finished_at')
            ->first();

        return [
            'last_successful_sync_at' => $latestSuccess?->finished_at,
            'last_error' => $latestError?->last_error ?: $institution->setting?->billingo_last_sync_error,
            'last_run' => InstitutionInvoiceSyncRun::query()
                ->where('institution_id', $institution->id)
                ->latest('started_at')
                ->first(),
        ];
    }

    private function syncKnownInvoice(
        Institution $institution,
        InstitutionInvoice $invoice,
        array $document,
        array &$summary,
        bool $dryRun,
        bool $missingPdfsOnly
    ): void {
        $changes = [];

        if ($invoice->invoice_number !== $document['invoice_number'] && $document['invoice_number']) {
            $changes['invoice_number'] = $document['invoice_number'];
        }

        if ($document['issue_date'] && optional($invoice->issue_date)->toDateString() !== $document['issue_date']->toDateString()) {
            $changes['issue_date'] = $document['issue_date']->toDateString();
        }

        if ($document['fulfillment_date'] && optional($invoice->fulfillment_date)->toDateString() !== $document['fulfillment_date']->toDateString()) {
            $changes['fulfillment_date'] = $document['fulfillment_date']->toDateString();
        }

        if ($document['due_date'] && optional($invoice->due_date)->toDateString() !== $document['due_date']->toDateString()) {
            $changes['due_date'] = $document['due_date']->toDateString();
        }

        if ($document['public_url'] && $invoice->invoice_url !== $document['public_url']) {
            $changes['invoice_url'] = $document['public_url'];
        }

        if (
            $invoice->isOriginalDocument()
            && $document['is_cancellation'] === false
            && ! in_array($invoice->status, [InstitutionInvoice::STATUS_VOIDED, InstitutionInvoice::STATUS_CANCELLED], true)
            && $invoice->status !== InstitutionInvoice::STATUS_ISSUED
        ) {
            $changes['status'] = InstitutionInvoice::STATUS_ISSUED;
        }

        if ($changes !== []) {
            $summary['updated_invoices']++;

            if (! $dryRun) {
                $changes['last_synced_at'] = now();
                $changes['sync_error_message'] = null;
                $invoice->fill($changes)->save();
            }
        }

        if (! $this->invoiceService->hasUsableInvoicePdf($invoice)) {
            if ($dryRun) {
                $summary['downloaded_pdfs']++;

                return;
            }

            try {
                $reloaded = $this->invoiceService->reloadInvoicePdf($institution, $invoice);
                if ($reloaded->invoice_pdf_path) {
                    $summary['downloaded_pdfs']++;
                }
            } catch (\Throwable $exception) {
                $summary['error_count']++;
                $summary['last_error'] = $exception->getMessage();
                $invoice->forceFill([
                    'sync_error_message' => $exception->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
            }

            return;
        }

        $summary['existing_pdfs']++;
    }

    private function createCancellationInvoiceIfMatch(
        Institution $institution,
        array $document,
        array &$summary,
        bool $dryRun
    ): bool {
        $originalInvoice = InstitutionInvoice::query()
            ->where('institution_id', $institution->id)
            ->where('provider', InstitutionInvoice::PROVIDER_BILLINGO)
            ->where('provider_invoice_id', $document['original_provider_invoice_id'])
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
            ->first();

        if (! $originalInvoice) {
            return false;
        }

        $summary['created_invoices']++;

        if ($dryRun) {
            return true;
        }

        DB::transaction(function () use ($originalInvoice, $document): void {
            $originalInvoice->forceFill([
                'status' => InstitutionInvoice::STATUS_VOIDED,
                'last_synced_at' => now(),
                'sync_error_message' => null,
            ])->save();

            InstitutionInvoice::updateOrCreate(
                [
                    'institution_id' => $originalInvoice->institution_id,
                    'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
                    'provider_invoice_id' => $document['provider_invoice_id'],
                ],
                [
                    'child_id' => $originalInvoice->child_id,
                    'guardian_id' => $originalInvoice->guardian_id,
                    'monthly_payment_statement_id' => $originalInvoice->monthly_payment_statement_id,
                    'original_invoice_id' => $originalInvoice->id,
                    'institution_payment_id' => null,
                    'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                    'provider_original_invoice_id' => $originalInvoice->provider_invoice_id,
                    'invoice_number' => $document['invoice_number'],
                    'status' => InstitutionInvoice::STATUS_ISSUED,
                    'issue_date' => $document['issue_date']?->toDateString(),
                    'due_date' => optional($originalInvoice->due_date)->toDateString(),
                    'fulfillment_date' => $document['fulfillment_date']?->toDateString() ?: optional($originalInvoice->fulfillment_date)->toDateString(),
                    // Lásd InstitutionInvoiceService::cancel() megjegyzését:
                    // a sztornó (jóváíró) számla összegeit negatívan kell
                    // rögzíteni, hogy az összesítésekben nullázza az eredeti
                    // számlát, ahelyett hogy tévesen duplán, pozitívként
                    // adódna hozzá. Ez a Billingo-oldali (távolról érkező)
                    // sztornó szinkronizálási útvonal, ugyanaz a hiba, mint
                    // a helyi cancel() műveletben.
                    'net_amount' => -$originalInvoice->net_amount,
                    'vat_amount' => -$originalInvoice->vat_amount,
                    'gross_amount' => -$originalInvoice->gross_amount,
                    'currency' => $originalInvoice->currency,
                    'payment_method' => $originalInvoice->payment_method,
                    'customer_name' => $originalInvoice->customer_name,
                    'customer_email' => $originalInvoice->customer_email,
                    'customer_tax_number' => $originalInvoice->customer_tax_number,
                    'billing_postcode' => $originalInvoice->billing_postcode,
                    'billing_city' => $originalInvoice->billing_city,
                    'billing_address' => $originalInvoice->billing_address,
                    'invoice_url' => $document['public_url'],
                    'created_by' => $originalInvoice->created_by,
                    'last_synced_at' => now(),
                    'sync_error_message' => null,
                ]
            );
        });

        return true;
    }

    private function finalizeRun(
        InstitutionSetting $settings,
        ?InstitutionInvoiceSyncRun $run,
        array $summary,
        ?Carbon $maxModifiedAt
    ): void {
        if ($run) {
            $run->forceFill([
                'status' => $summary['status'],
                'fetched_documents' => $summary['fetched_documents'],
                'created_invoices' => $summary['created_invoices'],
                'updated_invoices' => $summary['updated_invoices'],
                'downloaded_pdfs' => $summary['downloaded_pdfs'],
                'existing_pdfs' => $summary['existing_pdfs'],
                'unmatched_documents' => $summary['unmatched_documents'],
                'error_count' => $summary['error_count'],
                'last_error' => $summary['last_error'],
                'finished_at' => now(),
            ])->save();
        }

        $settings->forceFill([
            'billingo_last_successful_sync_at' => $summary['status'] === InstitutionInvoiceSyncRun::STATUS_SUCCESS ? now() : $settings->billingo_last_successful_sync_at,
            'billingo_sync_last_modified_at' => $maxModifiedAt,
            'billingo_last_sync_error' => $summary['last_error'],
        ])->save();
    }

    private function normalizeDocument(array $document): array
    {
        $providerInvoiceId = $this->firstFilled($document, ['id']);
        $blockId = $this->firstFilled($document, ['block_id', 'blockId']);
        $type = mb_strtolower((string) $this->firstFilled($document, ['type', 'document_type', 'documentType']));
        $invoiceNumber = $this->firstFilled($document, ['invoice_number', 'invoiceNumber']);
        $originalProviderInvoiceId = $this->firstFilled($document, [
            'original_document_id',
            'originalDocumentId',
            'parent_document_id',
            'parentDocumentId',
            'document_ancestor_id',
            'documentAncestorId',
            'canceled_document_id',
            'cancelled_document_id',
        ]);

        $isCancellation = str_contains($type, 'cancel')
            || str_contains($type, 'storno')
            || str_contains(mb_strtolower((string) $invoiceNumber), 'storno')
            || $originalProviderInvoiceId !== null;

        return [
            'provider_invoice_id' => $providerInvoiceId,
            'original_provider_invoice_id' => $originalProviderInvoiceId,
            'block_id' => $blockId,
            'invoice_number' => $invoiceNumber,
            'public_url' => $this->firstFilled($document, ['public_url', 'publicUrl']),
            'issue_date' => $this->parseDate($this->firstFilled($document, ['issue_date', 'issueDate'])),
            'due_date' => $this->parseDate($this->firstFilled($document, ['due_date', 'dueDate'])),
            'fulfillment_date' => $this->parseDate($this->firstFilled($document, ['fulfillment_date', 'fulfillmentDate'])),
            'last_modified_at' => $this->parseDateTime($this->firstFilled($document, ['updated_at', 'updatedAt', 'last_modified_date', 'lastModifiedDate'])),
            'is_cancellation' => $isCancellation,
        ];
    }

    private function emptySummary(Institution $institution, string $mode, bool $missingPdfsOnly, bool $dryRun): array
    {
        return [
            'institution_id' => $institution->id,
            'mode' => $mode,
            'missing_pdfs_only' => $missingPdfsOnly,
            'dry_run' => $dryRun,
            'status' => InstitutionInvoiceSyncRun::STATUS_SUCCESS,
            'fetched_documents' => 0,
            'created_invoices' => 0,
            'updated_invoices' => 0,
            'downloaded_pdfs' => 0,
            'existing_pdfs' => 0,
            'unmatched_documents' => 0,
            'error_count' => 0,
            'last_error' => null,
        ];
    }

    private function belongsToInstitutionBlock(array $document, InstitutionSetting $settings): bool
    {
        return (string) ($document['block_id'] ?? '') === (string) $settings->billingo_document_block_id;
    }

    private function isReady(InstitutionSetting $settings): bool
    {
        return $settings->invoicing_enabled
            && $settings->invoicing_provider === InstitutionSetting::INVOICING_PROVIDER_BILLINGO
            && $settings->hasBillingoApiKey()
            && filled($settings->billingo_document_block_id);
    }

    private function firstFilled(array $document, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $document) && $document[$key] !== null && $document[$key] !== '') {
                return $document[$key];
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }
}
