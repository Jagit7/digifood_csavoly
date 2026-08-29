<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('institution_code', 16)->unique();
            $table->string('type', 20)->nullable()->index();

            $table->string('address_zip', 10)->nullable()->index();
            $table->string('address_city', 100)->nullable()->index();
            $table->string('address_line')->nullable(); // utca, házszám
            $table->string('om_identifier', 6)->nullable()->unique();

            $table->string('contact_name')->nullable();
            $table->string('email', 191)->nullable()->index();
            $table->string('phone', 50)->nullable();

            $table->string('billing_name')->nullable();
            $table->string('billing_tax_number', 50)->nullable()->index();

            $table->string('billing_zip', 10)->nullable()->index();
            $table->string('billing_city', 100)->nullable()->index();
            $table->string('billing_address')->nullable(); // utca, házszám

            $table->unsignedSmallInteger('billing_payment_due_days')->nullable();

            $table->string('kreta_code', 100)->nullable()->index();
            $table->text('szamlazz_api_key')->nullable();
            $table->string('szamlazz_partner_id', 100)->nullable()->index();
            $table->string('invoice_prefix', 32)->nullable();

            $table->boolean('active')->default(true)->index();
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};