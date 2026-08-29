<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sztornó (storno) számla támogatás: a "voided" (sztornózva) státuszú
 * számlákhoz tartozó, a szolgáltatónál (Billingo / Számlázz.hu) létrejött
 * sztornó bizonylat adatait tároljuk el, az eredeti számla mezőinek
 * felülírása nélkül - így a számla adatlapján egyszerre látható marad az
 * eredeti bizonylat és a hozzá tartozó sztornó bizonylat is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_invoices', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('error_message');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->string('cancellation_provider_document_id', 191)->nullable()->after('cancellation_reason');
            $table->string('cancellation_invoice_number', 100)->nullable()->after('cancellation_provider_document_id');
            $table->string('cancellation_pdf_path', 191)->nullable()->after('cancellation_invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('institution_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
                'cancellation_provider_document_id',
                'cancellation_invoice_number',
                'cancellation_pdf_path',
            ]);
        });
    }
};
