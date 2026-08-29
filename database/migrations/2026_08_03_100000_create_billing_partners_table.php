<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('billing_name', 191)->nullable();
            $table->string('tax_number', 50)->nullable()->index();
            $table->string('billing_zip', 10)->nullable()->index();
            $table->string('billing_city', 100)->nullable()->index();
            $table->string('billing_address', 191)->nullable();
            $table->string('billing_email', 191)->nullable()->index();
            $table->unsignedSmallInteger('payment_due_days')->default(8);
            $table->decimal('vat_rate', 5, 2)->default(27);
            $table->text('invoice_note')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_partners');
    }
};
