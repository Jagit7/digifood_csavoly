<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('billing_partner_id')
                ->nullable()
                ->after('billing_payment_due_days')
                ->constrained('billing_partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_partner_id');
        });
    }
};
