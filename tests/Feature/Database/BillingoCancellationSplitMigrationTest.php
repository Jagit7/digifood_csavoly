<?php

namespace Tests\Feature\Database;

use App\Models\InstitutionInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingoCancellationSplitMigrationTest extends TestCase
{
    private const MIGRATION_PATH = 'database/migrations/2026_08_24_090000_split_cancellation_invoices_and_add_billingo_sync_fields.php';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropAllTables();
        Schema::enableForeignKeyConstraints();

        $this->createLegacySchema();
    }

    public function test_migration_moves_legacy_cancellation_data_into_separate_row(): void
    {
        $originalId = $this->insertLegacyOriginalInvoice();

        $this->runTargetMigration();

        $original = DB::table('institution_invoices')->where('id', $originalId)->first();
        $cancellation = DB::table('institution_invoices')
            ->where('original_invoice_id', $originalId)
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION)
            ->first();

        $this->assertNotNull($cancellation);
        $this->assertSame(InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL, $original->document_type);
        $this->assertSame('12345', $original->provider_invoice_id);
        $this->assertSame('67890', $original->cancellation_provider_document_id);
        $this->assertSame(1, $original->institution_payment_id);
        $this->assertSame($original->monthly_payment_statement_id, $cancellation->monthly_payment_statement_id);
        $this->assertNull($cancellation->institution_payment_id);
        $this->assertSame('67890', $cancellation->provider_invoice_id);
        $this->assertSame('12345', $cancellation->provider_original_invoice_id);
        $this->assertSame('STORNO-2026-0001', $cancellation->invoice_number);
    }

    public function test_migration_is_safe_to_rerun_after_partial_state(): void
    {
        $originalId = $this->insertLegacyOriginalInvoice();

        Schema::table('institution_invoices', function (Blueprint $table) {
            $table->string('document_type', 30)->nullable()->after('provider');
            $table->string('provider_original_invoice_id', 191)->nullable()->after('provider_invoice_id');
            $table->string('pdf_disk', 50)->nullable()->after('invoice_pdf_path');
            $table->timestamp('pdf_downloaded_at')->nullable()->after('pdf_disk');
        });

        DB::statement('DROP INDEX institution_invoices_monthly_payment_statement_id_unique');

        $this->runTargetMigration();
        $this->runTargetMigration();

        $this->assertSame(2, DB::table('institution_invoices')->count());

        $original = DB::table('institution_invoices')->where('id', $originalId)->first();
        $cancellationRows = DB::table('institution_invoices')
            ->where('original_invoice_id', $originalId)
            ->where('provider_invoice_id', '67890')
            ->get();

        $this->assertCount(1, $cancellationRows);
        $this->assertSame('12345', $original->provider_invoice_id);
        $this->assertSame(1, $original->institution_payment_id);
        $this->assertNull($cancellationRows->first()->institution_payment_id);
        $this->assertTrue($this->hasIndex('institution_invoices', 'institution_invoices_provider_doc_unique'));
        $this->assertTrue($this->hasIndex('institution_invoices', 'institution_invoices_institution_payment_id_unique'));
    }

    public function test_repeated_backfill_does_not_create_duplicate_cancellation_rows(): void
    {
        $originalId = $this->insertLegacyOriginalInvoice();

        $this->runTargetMigration();
        $this->runTargetMigration();

        $cancellationRows = DB::table('institution_invoices')
            ->where('original_invoice_id', $originalId)
            ->where('provider_invoice_id', '67890')
            ->get();

        $this->assertCount(1, $cancellationRows);
        $this->assertNull($cancellationRows->first()->institution_payment_id);
        $this->assertFalse($this->hasIndex('institution_invoices', 'institution_invoices_monthly_payment_statement_id_unique'));
        $this->assertTrue($this->hasIndex('institution_invoices', 'institution_invoices_statement_type_index'));
    }

    public function test_migration_replaces_legacy_unique_with_normal_statement_index_and_keeps_foreign_key(): void
    {
        $originalId = $this->insertLegacyOriginalInvoice();

        $this->assertTrue($this->hasIndex('institution_invoices', 'institution_invoices_monthly_payment_statement_id_unique'));
        $this->assertFalse($this->hasIndex('institution_invoices', 'institution_invoices_monthly_payment_statement_id_index'));
        $this->assertTrue($this->hasForeignKeyColumn('institution_invoices', 'monthly_payment_statement_id'));

        $this->runTargetMigration();
        $this->runTargetMigration();

        $this->assertFalse($this->hasIndex('institution_invoices', 'institution_invoices_monthly_payment_statement_id_unique'));
        $this->assertTrue($this->hasIndex('institution_invoices', 'institution_invoices_monthly_payment_statement_id_index'));
        $this->assertTrue($this->hasForeignKeyColumn('institution_invoices', 'monthly_payment_statement_id'));

        $original = DB::table('institution_invoices')->where('id', $originalId)->first();
        $cancellation = DB::table('institution_invoices')
            ->where('original_invoice_id', $originalId)
            ->where('provider_invoice_id', '67890')
            ->first();

        $this->assertNotNull($cancellation);
        $this->assertSame($original->monthly_payment_statement_id, $cancellation->monthly_payment_statement_id);
        $this->assertSame(1, $original->institution_payment_id);
        $this->assertNull($cancellation->institution_payment_id);
    }

    private function createLegacySchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('password', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('institution_code', 16)->unique();
            $table->string('type', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('last_name', 191)->nullable();
            $table->string('first_name', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->timestamps();
        });

        Schema::create('monthly_payment_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('total_payable')->default(0);
            $table->string('status', 30)->default('closed');
            $table->timestamps();
        });

        Schema::create('institution_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('billingo_test_mode')->default(true);
            $table->timestamps();
        });

        Schema::create('institution_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->foreignId('monthly_payment_statement_id')->constrained('monthly_payment_statements')->cascadeOnDelete();
            $table->foreignId('institution_payment_id')->nullable()->constrained('institution_payments')->nullOnDelete();
            $table->string('provider', 30);
            $table->string('provider_invoice_id', 191)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('status', 30);
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->date('fulfillment_date')->nullable();
            $table->unsignedBigInteger('net_amount');
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('gross_amount');
            $table->string('currency', 3)->default('HUF');
            $table->string('payment_method', 50);
            $table->string('customer_name', 191);
            $table->string('customer_email', 191)->nullable();
            $table->string('customer_tax_number', 50)->nullable();
            $table->string('billing_postcode', 20);
            $table->string('billing_city', 100);
            $table->string('billing_address', 191);
            $table->string('invoice_pdf_path', 191)->nullable();
            $table->text('invoice_url')->nullable();
            $table->text('error_message')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancellation_provider_document_id', 191)->nullable();
            $table->string('cancellation_invoice_number', 100)->nullable();
            $table->string('cancellation_pdf_path', 191)->nullable();
            $table->timestamps();

            $table->unique('monthly_payment_statement_id', 'institution_invoices_monthly_payment_statement_id_unique');
            $table->unique('institution_payment_id', 'institution_invoices_institution_payment_id_unique');
        });
    }

    private function insertLegacyOriginalInvoice(): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Teszt User',
            'email' => 'test@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Teszt Intezmeny',
            'institution_code' => 'MIGT01',
            'type' => 'iskola',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = DB::table('children')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Teszt Gyermek',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guardianId = DB::table('guardians')->insertGetId([
            'institution_id' => $institutionId,
            'last_name' => 'Szulo',
            'first_name' => 'Payer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statementId = DB::table('monthly_payment_statements')->insertGetId([
            'institution_id' => $institutionId,
            'child_id' => $childId,
            'year' => 2026,
            'month' => 8,
            'total_payable' => 1000,
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('institution_settings')->insert([
            'institution_id' => $institutionId,
            'billingo_test_mode' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('institution_payments')->insertGetId([
            'institution_id' => $institutionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('institution_invoices')->insertGetId([
            'institution_id' => $institutionId,
            'child_id' => $childId,
            'guardian_id' => $guardianId,
            'monthly_payment_statement_id' => $statementId,
            'institution_payment_id' => $paymentId,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'provider_invoice_id' => '12345',
            'invoice_number' => 'BILL-2026-0001',
            'status' => InstitutionInvoice::STATUS_VOIDED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => 1000,
            'vat_amount' => 0,
            'gross_amount' => 1000,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.com',
            'customer_tax_number' => '12345678-1-42',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_pdf_path' => 'invoices/billingo/1/BILL-2026-0001.pdf',
            'invoice_url' => 'https://billingo.test/invoice/12345',
            'error_message' => null,
            'note' => 'Eredeti szamla',
            'created_by' => $userId,
            'cancelled_at' => '2026-08-10 10:00:00',
            'cancelled_by' => $userId,
            'cancellation_reason' => 'Teszt sztorno',
            'cancellation_provider_document_id' => '67890',
            'cancellation_invoice_number' => 'STORNO-2026-0001',
            'cancellation_pdf_path' => 'invoices/billingo_storno/1/STORNO-2026-0001.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runTargetMigration(): void
    {
        /** @var Migration $migration */
        $migration = require base_path(self::MIGRATION_PATH);
        $migration->up();
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select(sprintf('PRAGMA index_list("%s")', $table));

        foreach ($indexes as $index) {
            if (($index->name ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKeyColumn(string $table, string $columnName): bool
    {
        $foreignKeys = DB::select(sprintf('PRAGMA foreign_key_list("%s")', $table));

        foreach ($foreignKeys as $foreignKey) {
            if (($foreignKey->from ?? null) === $columnName) {
                return true;
            }
        }

        return false;
    }
}
