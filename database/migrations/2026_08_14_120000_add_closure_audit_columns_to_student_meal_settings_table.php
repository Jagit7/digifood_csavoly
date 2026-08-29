<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->foreignId('closed_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('closed_at')->nullable()->after('closed_by');
            $table->string('closure_reason', 50)->nullable()->after('closed_at');
            $table->string('closure_note', 191)->nullable()->after('closure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('student_meal_settings', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['closed_by', 'closed_at', 'closure_reason', 'closure_note']);
        });
    }
};
