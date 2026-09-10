<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_billing_summary_runs', function (Blueprint $table) {
            // Immutable mail data, including fixed/minimum pricing explanations.
            // Existing runs deliberately remain null; no historical recalculation.
            $table->json('snapshot_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('saas_billing_summary_runs', function (Blueprint $table) {
            $table->dropColumn('snapshot_payload');
        });
    }
};
