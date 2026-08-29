<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A banki (CIB) ellenőrzés visszajelzése alapján az impresszumból
     * hiányzik a cégjegyzékszám - ehhez kell egy új, önmagában is
     * értelmezhető mező az om_identifier (OM azonosító) mellé, függetlenül
     * a számlázási adószámtól (billing_tax_number).
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('company_registration_number', 100)->nullable()->after('om_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('company_registration_number');
        });
    }
};
