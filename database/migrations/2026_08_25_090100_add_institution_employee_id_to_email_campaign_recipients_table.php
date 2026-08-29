<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->foreignId('institution_employee_id')
                ->nullable()
                ->after('guardian_id')
                ->constrained('institution_employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_employee_id');
        });
    }
};
