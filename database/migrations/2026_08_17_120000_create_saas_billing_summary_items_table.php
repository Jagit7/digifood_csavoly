<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_summary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_billing_summary_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institution_name_snapshot', 191);
            $table->string('billing_tax_number_snapshot', 50)->nullable();
            $table->string('billing_address_snapshot')->nullable();
            $table->unsignedInteger('children_count')->default(0);
            $table->unsignedInteger('employees_count')->default(0);
            $table->unsignedInteger('eaters_count')->default(0);
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('invoice_number', 100)->nullable();
            $table->dateTime('invoiced_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['saas_billing_summary_run_id', 'institution_id'], 'saas_billing_summary_items_run_institution_unique');
            $table->index('status');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_summary_items');
    }
};
