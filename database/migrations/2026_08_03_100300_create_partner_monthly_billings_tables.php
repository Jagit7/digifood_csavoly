<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_monthly_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_partner_id')->constrained('billing_partners')->cascadeOnDelete();
            $table->date('billing_month');
            $table->unsignedInteger('total_children')->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->string('invoice_number', 100)->nullable();
            $table->dateTime('invoiced_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['billing_partner_id', 'billing_month']);
            $table->index(['status', 'billing_month']);
            $table->index('invoice_number');
        });

        Schema::create('partner_monthly_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_monthly_billing_id')
                ->constrained('partner_monthly_billings')
                ->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institution_name_snapshot', 191);
            $table->unsignedInteger('child_count')->default(0);
            $table->decimal('price_per_child', 12, 2)->nullable();
            $table->decimal('fixed_monthly_fee', 12, 2)->nullable();
            $table->decimal('minimum_monthly_fee', 12, 2)->nullable();
            $table->decimal('net_amount', 12, 2);
            $table->text('calculation_description')->nullable();
            $table->timestamps();

            $table->index(['partner_monthly_billing_id', 'institution_id'], 'partner_monthly_billing_items_billing_institution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_monthly_billing_items');
        Schema::dropIfExists('partner_monthly_billings');
    }
};
