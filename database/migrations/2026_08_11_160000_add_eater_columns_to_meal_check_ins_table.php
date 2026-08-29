<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_check_ins', function (Blueprint $table) {
            $table->string('eater_type', 50)->nullable()->after('child_id');
            $table->unsignedBigInteger('eater_id')->nullable()->after('eater_type');
            $table->index(['eater_type', 'eater_id', 'service_date'], 'meal_ci_eater_date_idx');
        });

        DB::table('meal_check_ins')
            ->whereNull('eater_type')
            ->whereNotNull('child_id')
            ->update([
                'eater_type' => 'child',
                'eater_id' => DB::raw('child_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('meal_check_ins', function (Blueprint $table) {
            $table->dropIndex('meal_ci_eater_date_idx');
            $table->dropColumn(['eater_type', 'eater_id']);
        });
    }
};
