<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->boolean('invoicing_enabled')->default(false)->after('payment_due_day');
            $table->string('invoicing_provider', 30)->nullable()->after('invoicing_enabled');
            $table->boolean('card_payment_enabled')->default(false)->after('invoicing_provider');
            $table->string('card_payment_provider', 30)->nullable()->after('card_payment_enabled');
            $table->boolean('card_payment_test_mode')->default(true)->after('card_payment_provider');
            $table->text('billingo_api_key')->nullable()->after('card_payment_test_mode');
            $table->string('billingo_document_block_id', 100)->nullable()->after('billingo_api_key');
            $table->string('billingo_default_payment_method', 100)->nullable()->after('billingo_document_block_id');
            $table->unsignedSmallInteger('billingo_due_days')->nullable()->after('billingo_default_payment_method');
            $table->string('billingo_invoice_language', 10)->nullable()->after('billingo_due_days');
            $table->boolean('billingo_e_invoice_enabled')->default(false)->after('billingo_invoice_language');
            $table->boolean('billingo_test_mode')->default(true)->after('billingo_e_invoice_enabled');
            $table->text('szamlazz_hu_agent_key')->nullable()->after('billingo_test_mode');
            $table->string('szamlazz_hu_invoice_prefix', 100)->nullable()->after('szamlazz_hu_agent_key');
            $table->string('szamlazz_hu_default_payment_method', 100)->nullable()->after('szamlazz_hu_invoice_prefix');
            $table->unsignedSmallInteger('szamlazz_hu_due_days')->nullable()->after('szamlazz_hu_default_payment_method');
            $table->string('szamlazz_hu_invoice_language', 10)->nullable()->after('szamlazz_hu_due_days');
            $table->boolean('szamlazz_hu_e_invoice_enabled')->default(false)->after('szamlazz_hu_invoice_language');
            $table->boolean('szamlazz_hu_test_mode')->default(true)->after('szamlazz_hu_e_invoice_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'invoicing_enabled',
                'invoicing_provider',
                'card_payment_enabled',
                'card_payment_provider',
                'card_payment_test_mode',
                'billingo_api_key',
                'billingo_document_block_id',
                'billingo_default_payment_method',
                'billingo_due_days',
                'billingo_invoice_language',
                'billingo_e_invoice_enabled',
                'billingo_test_mode',
                'szamlazz_hu_agent_key',
                'szamlazz_hu_invoice_prefix',
                'szamlazz_hu_default_payment_method',
                'szamlazz_hu_due_days',
                'szamlazz_hu_invoice_language',
                'szamlazz_hu_e_invoice_enabled',
                'szamlazz_hu_test_mode',
            ]);
        });
    }
};