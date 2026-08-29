<?php

use App\Models\InstitutionInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE_INVOICES = 'institution_invoices';
    private const TABLE_SYNC_RUNS = 'institution_invoice_sync_runs';
    private const TABLE_SETTINGS = 'institution_settings';

    private const LEGACY_STATEMENT_UNIQUE = 'institution_invoices_monthly_payment_statement_id_unique';
    private const STATEMENT_FK_INDEX = 'institution_invoices_monthly_payment_statement_id_index';
    private const PROVIDER_DOC_UNIQUE = 'institution_invoices_provider_doc_unique';
    private const STATEMENT_TYPE_INDEX = 'institution_invoices_statement_type_index';
    private const INSTITUTION_TYPE_INDEX = 'institution_invoices_institution_type_index';
    private const ORIGINAL_INVOICE_INDEX = 'institution_invoices_original_invoice_index';
    private const SYNC_RUN_MODE_STATUS_INDEX = 'invoice_sync_runs_institution_mode_status_index';
    private const SYNC_RUN_STARTED_AT_INDEX = 'invoice_sync_runs_started_at_index';

    public function up(): void
    {
        $this->addInstitutionInvoiceColumns();
        $this->backfillInvoicePdfMetadata();
        $this->ensureMonthlyPaymentStatementForeignKeyIndex();
        $this->dropLegacyStatementUniqueBeforeBackfill();
        $this->ensureOriginalDocumentTypes();
        $this->backfillCancellationInvoices();
        $this->createInstitutionInvoiceIndexes();
        $this->createSyncRunsTable();
        $this->addInstitutionSettingColumns();
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE_SETTINGS)) {
            Schema::table(self::TABLE_SETTINGS, function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn(self::TABLE_SETTINGS, 'billingo_last_successful_sync_at') ? 'billingo_last_successful_sync_at' : null,
                    Schema::hasColumn(self::TABLE_SETTINGS, 'billingo_sync_last_modified_at') ? 'billingo_sync_last_modified_at' : null,
                    Schema::hasColumn(self::TABLE_SETTINGS, 'billingo_last_sync_error') ? 'billingo_last_sync_error' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable(self::TABLE_SYNC_RUNS)) {
            Schema::drop(self::TABLE_SYNC_RUNS);
        }

        if (! Schema::hasTable(self::TABLE_INVOICES)) {
            return;
        }

        $this->dropIndexIfExists(self::TABLE_INVOICES, self::PROVIDER_DOC_UNIQUE);
        $this->dropIndexIfExists(self::TABLE_INVOICES, self::STATEMENT_TYPE_INDEX);
        $this->dropIndexIfExists(self::TABLE_INVOICES, self::INSTITUTION_TYPE_INDEX);
        $this->dropIndexIfExists(self::TABLE_INVOICES, self::ORIGINAL_INVOICE_INDEX);
        $this->dropIndexIfExists(self::TABLE_INVOICES, self::STATEMENT_FK_INDEX);

        if (! $this->hasIndex(self::TABLE_INVOICES, self::LEGACY_STATEMENT_UNIQUE)) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->unique('monthly_payment_statement_id', self::LEGACY_STATEMENT_UNIQUE);
            });
        }

        if (Schema::hasColumn(self::TABLE_INVOICES, 'original_invoice_id')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->dropConstrainedForeignId('original_invoice_id');
            });
        }

        Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn(self::TABLE_INVOICES, 'document_type') ? 'document_type' : null,
                Schema::hasColumn(self::TABLE_INVOICES, 'provider_original_invoice_id') ? 'provider_original_invoice_id' : null,
                Schema::hasColumn(self::TABLE_INVOICES, 'pdf_disk') ? 'pdf_disk' : null,
                Schema::hasColumn(self::TABLE_INVOICES, 'pdf_downloaded_at') ? 'pdf_downloaded_at' : null,
                Schema::hasColumn(self::TABLE_INVOICES, 'pdf_size') ? 'pdf_size' : null,
                Schema::hasColumn(self::TABLE_INVOICES, 'last_synced_at') ? 'last_synced_at' : null,
                Schema::hasColumn(self::TABLE_INVOICES, 'sync_error_message') ? 'sync_error_message' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function addInstitutionInvoiceColumns(): void
    {
        if (! Schema::hasTable(self::TABLE_INVOICES)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'document_type')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->string('document_type', 30)
                    ->default(InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
                    ->after('provider');
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'original_invoice_id')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->foreignId('original_invoice_id')
                    ->nullable()
                    ->after('monthly_payment_statement_id')
                    ->constrained(self::TABLE_INVOICES)
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'provider_original_invoice_id')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->string('provider_original_invoice_id', 191)
                    ->nullable()
                    ->after('provider_invoice_id');
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'pdf_disk')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->string('pdf_disk', 50)->nullable()->after('invoice_pdf_path');
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'pdf_downloaded_at')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->timestamp('pdf_downloaded_at')->nullable()->after('pdf_disk');
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'pdf_size')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->unsignedBigInteger('pdf_size')->nullable()->after('pdf_downloaded_at');
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'last_synced_at')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->timestamp('last_synced_at')->nullable()->after('pdf_size');
            });
        }

        if (! Schema::hasColumn(self::TABLE_INVOICES, 'sync_error_message')) {
            Schema::table(self::TABLE_INVOICES, function (Blueprint $table) {
                $table->text('sync_error_message')->nullable()->after('last_synced_at');
            });
        }
    }

    private function backfillInvoicePdfMetadata(): void
    {
        if (! Schema::hasTable(self::TABLE_INVOICES)
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'invoice_pdf_path')
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'pdf_disk')
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'pdf_downloaded_at')) {
            return;
        }

        DB::table(self::TABLE_INVOICES)
            ->whereNotNull('invoice_pdf_path')
            ->where(function ($query) {
                $query->whereNull('pdf_disk')
                    ->orWhereNull('pdf_downloaded_at');
            })
            ->update([
                'pdf_disk' => 'local',
                'pdf_downloaded_at' => now(),
            ]);
    }

    private function dropLegacyStatementUniqueBeforeBackfill(): void
    {
        if (Schema::hasTable(self::TABLE_INVOICES)) {
            $this->dropIndexIfExists(self::TABLE_INVOICES, self::LEGACY_STATEMENT_UNIQUE);
        }
    }

    private function ensureMonthlyPaymentStatementForeignKeyIndex(): void
    {
        if (! Schema::hasTable(self::TABLE_INVOICES)) {
            return;
        }

        if ($this->hasNonUniqueIndexStartingWithColumn(self::TABLE_INVOICES, 'monthly_payment_statement_id')) {
            return;
        }

        $this->createIndexIfMissing(
            self::TABLE_INVOICES,
            self::STATEMENT_FK_INDEX,
            fn (Blueprint $table) => $table->index(
                ['monthly_payment_statement_id'],
                self::STATEMENT_FK_INDEX
            )
        );
    }

    private function ensureOriginalDocumentTypes(): void
    {
        if (! Schema::hasTable(self::TABLE_INVOICES) || ! Schema::hasColumn(self::TABLE_INVOICES, 'document_type')) {
            return;
        }

        DB::table(self::TABLE_INVOICES)
            ->where(function ($query) {
                $query->whereNull('document_type')
                    ->orWhere('document_type', '');
            })
            ->update([
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL,
            ]);
    }

    private function backfillCancellationInvoices(): void
    {
        if (! Schema::hasTable(self::TABLE_INVOICES)
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'cancellation_provider_document_id')
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'cancellation_invoice_number')
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'original_invoice_id')
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'document_type')
            || ! Schema::hasColumn(self::TABLE_INVOICES, 'provider_original_invoice_id')) {
            return;
        }

        DB::table(self::TABLE_INVOICES)
            ->whereNotNull('cancellation_provider_document_id')
            ->whereNotNull('cancellation_invoice_number')
            ->orderBy('id')
            ->get()
            ->each(function (object $invoice): void {
                $existingMatches = DB::table(self::TABLE_INVOICES)
                    ->where('institution_id', $invoice->institution_id)
                    ->where('provider', $invoice->provider)
                    ->where('provider_invoice_id', $invoice->cancellation_provider_document_id)
                    ->orderBy('id')
                    ->get();

                if ($existingMatches->count() > 1) {
                    return;
                }

                $existing = $existingMatches->first();

                if (! $existing) {
                    DB::table(self::TABLE_INVOICES)->insert([
                        'institution_id' => $invoice->institution_id,
                        'child_id' => $invoice->child_id,
                        'guardian_id' => $invoice->guardian_id,
                        'monthly_payment_statement_id' => $invoice->monthly_payment_statement_id,
                        'original_invoice_id' => $invoice->id,
                        'institution_payment_id' => null,
                        'provider' => $invoice->provider,
                        'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                        'provider_invoice_id' => $invoice->cancellation_provider_document_id,
                        'provider_original_invoice_id' => $invoice->provider_invoice_id,
                        'invoice_number' => $invoice->cancellation_invoice_number,
                        'status' => InstitutionInvoice::STATUS_ISSUED,
                        'issue_date' => $invoice->cancelled_at ? substr((string) $invoice->cancelled_at, 0, 10) : $invoice->issue_date,
                        'due_date' => $invoice->due_date,
                        'fulfillment_date' => $invoice->fulfillment_date,
                        'net_amount' => $invoice->net_amount,
                        'vat_amount' => $invoice->vat_amount,
                        'gross_amount' => $invoice->gross_amount,
                        'currency' => $invoice->currency,
                        'payment_method' => $invoice->payment_method,
                        'customer_name' => $invoice->customer_name,
                        'customer_email' => $invoice->customer_email,
                        'customer_tax_number' => $invoice->customer_tax_number,
                        'billing_postcode' => $invoice->billing_postcode,
                        'billing_city' => $invoice->billing_city,
                        'billing_address' => $invoice->billing_address,
                        'invoice_pdf_path' => $invoice->cancellation_pdf_path,
                        'pdf_disk' => $invoice->cancellation_pdf_path ? 'local' : null,
                        'pdf_downloaded_at' => $invoice->cancellation_pdf_path ? now() : null,
                        'invoice_url' => null,
                        'error_message' => null,
                        'note' => $invoice->cancellation_reason,
                        'created_by' => $invoice->cancelled_by ?: $invoice->created_by,
                        'created_at' => $invoice->created_at,
                        'updated_at' => $invoice->updated_at,
                        'last_synced_at' => null,
                        'sync_error_message' => null,
                    ]);

                    return;
                }

                $updates = array_filter([
                    'original_invoice_id' => $existing->original_invoice_id ?: $invoice->id,
                    'document_type' => filled($existing->document_type) ? null : InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                    'provider_original_invoice_id' => $existing->provider_original_invoice_id ?: $invoice->provider_invoice_id,
                ], fn ($value) => $value !== null);

                if ($updates !== []) {
                    DB::table(self::TABLE_INVOICES)
                        ->where('id', $existing->id)
                        ->update($updates);
                }
            });
    }

    private function createInstitutionInvoiceIndexes(): void
    {
        if (! Schema::hasTable(self::TABLE_INVOICES)) {
            return;
        }

        if (! $this->hasDuplicateProviderDocumentIds()) {
            $this->createUniqueIfMissing(
                self::TABLE_INVOICES,
                self::PROVIDER_DOC_UNIQUE,
                fn (Blueprint $table) => $table->unique(
                    ['institution_id', 'provider', 'provider_invoice_id'],
                    self::PROVIDER_DOC_UNIQUE
                )
            );
        }

        $this->createIndexIfMissing(
            self::TABLE_INVOICES,
            self::STATEMENT_TYPE_INDEX,
            fn (Blueprint $table) => $table->index(
                ['monthly_payment_statement_id', 'document_type'],
                self::STATEMENT_TYPE_INDEX
            )
        );

        $this->createIndexIfMissing(
            self::TABLE_INVOICES,
            self::INSTITUTION_TYPE_INDEX,
            fn (Blueprint $table) => $table->index(
                ['institution_id', 'document_type'],
                self::INSTITUTION_TYPE_INDEX
            )
        );

        $this->createIndexIfMissing(
            self::TABLE_INVOICES,
            self::ORIGINAL_INVOICE_INDEX,
            fn (Blueprint $table) => $table->index(
                ['original_invoice_id'],
                self::ORIGINAL_INVOICE_INDEX
            )
        );
    }

    private function createSyncRunsTable(): void
    {
        if (! Schema::hasTable(self::TABLE_SYNC_RUNS)) {
            Schema::create(self::TABLE_SYNC_RUNS, function (Blueprint $table) {
                $table->id();
                $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
                $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('mode', 30);
                $table->boolean('missing_pdfs_only')->default(false);
                $table->string('status', 30);
                $table->unsignedInteger('fetched_documents')->default(0);
                $table->unsignedInteger('created_invoices')->default(0);
                $table->unsignedInteger('updated_invoices')->default(0);
                $table->unsignedInteger('downloaded_pdfs')->default(0);
                $table->unsignedInteger('existing_pdfs')->default(0);
                $table->unsignedInteger('unmatched_documents')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        $this->createIndexIfMissing(
            self::TABLE_SYNC_RUNS,
            self::SYNC_RUN_MODE_STATUS_INDEX,
            fn (Blueprint $table) => $table->index(
                ['institution_id', 'mode', 'status'],
                self::SYNC_RUN_MODE_STATUS_INDEX
            )
        );

        $this->createIndexIfMissing(
            self::TABLE_SYNC_RUNS,
            self::SYNC_RUN_STARTED_AT_INDEX,
            fn (Blueprint $table) => $table->index(['started_at'], self::SYNC_RUN_STARTED_AT_INDEX)
        );
    }

    private function addInstitutionSettingColumns(): void
    {
        if (! Schema::hasTable(self::TABLE_SETTINGS)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE_SETTINGS, 'billingo_last_successful_sync_at')) {
            Schema::table(self::TABLE_SETTINGS, function (Blueprint $table) {
                $table->timestamp('billingo_last_successful_sync_at')
                    ->nullable()
                    ->after('billingo_test_mode');
            });
        }

        if (! Schema::hasColumn(self::TABLE_SETTINGS, 'billingo_sync_last_modified_at')) {
            Schema::table(self::TABLE_SETTINGS, function (Blueprint $table) {
                $table->timestamp('billingo_sync_last_modified_at')
                    ->nullable()
                    ->after('billingo_last_successful_sync_at');
            });
        }

        if (! Schema::hasColumn(self::TABLE_SETTINGS, 'billingo_last_sync_error')) {
            Schema::table(self::TABLE_SETTINGS, function (Blueprint $table) {
                $table->text('billingo_last_sync_error')
                    ->nullable()
                    ->after('billingo_sync_last_modified_at');
            });
        }
    }

    private function hasDuplicateProviderDocumentIds(): bool
    {
        return DB::table(self::TABLE_INVOICES)
            ->select('institution_id', 'provider', 'provider_invoice_id')
            ->whereNotNull('provider_invoice_id')
            ->groupBy('institution_id', 'provider', 'provider_invoice_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function createIndexIfMissing(string $table, string $indexName, callable $callback): void
    {
        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($callback): void {
            $callback($blueprint);
        });
    }

    private function createUniqueIfMissing(string $table, string $indexName, callable $callback): void
    {
        $this->createIndexIfMissing($table, $indexName, $callback);
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->hasIndex($table, $indexName)) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(sprintf('DROP INDEX IF EXISTS "%s"', $indexName));

            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $indexName));
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select(sprintf('PRAGMA index_list("%s")', $table));

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasNonUniqueIndexStartingWithColumn(string $table, string $columnName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->select('index_name')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('seq_in_index', 1)
                ->where('column_name', $columnName)
                ->where('non_unique', 1)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select(sprintf('PRAGMA index_list("%s")', $table));

            foreach ($indexes as $index) {
                $indexName = $index->name ?? null;
                $isUnique = (int) ($index->unique ?? 0) === 1;

                if (! $indexName || $isUnique) {
                    continue;
                }

                $columns = DB::select(sprintf('PRAGMA index_info("%s")', $indexName));
                $firstColumn = $columns[0]->name ?? null;

                if ($firstColumn === $columnName) {
                    return true;
                }
            }
        }

        return false;
    }
};
