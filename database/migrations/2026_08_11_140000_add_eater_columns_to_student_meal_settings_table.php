<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->string('eater_type', 50)->nullable()->after('student_id');
            $table->unsignedBigInteger('eater_id')->nullable()->after('eater_type');
            $table->index(
                ['eater_type', 'eater_id', 'institution_id', 'valid_from', 'valid_to'],
                'sms_eater_validity_idx'
            );
        });

        DB::table('student_meal_settings')
            ->whereNull('eater_type')
            ->whereNotNull('student_id')
            ->update([
                'eater_type' => 'child',
                'eater_id' => DB::raw('student_id'),
            ]);

        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->foreign('student_id', 'student_meal_settings_student_id_foreign')
                ->references('id')
                ->on('children')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $employeeSettingExists = DB::table('student_meal_settings')
            ->where('eater_type', 'institution_employee')
            ->exists();

        if ($employeeSettingExists) {
            throw new RuntimeException('Cannot rollback while institution employee meal settings exist.');
        }

        DB::table('student_meal_settings')
            ->where('eater_type', 'child')
            ->whereNull('student_id')
            ->update([
                'student_id' => DB::raw('eater_id'),
            ]);

        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
            $table->foreign('student_id', 'student_meal_settings_student_id_foreign')
                ->references('id')
                ->on('children')
                ->cascadeOnDelete();
            $table->dropIndex('sms_eater_validity_idx');
            $table->dropColumn(['eater_type', 'eater_id']);
        });
    }
};
