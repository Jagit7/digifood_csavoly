<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_daily_attendance_recipients', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->string('group_name', 64);
            $table->string('email', 128);
            $table->timestamps();

            $table->unique(
                ['institution_id', 'group_name', 'email'],
                'daily_attendance_recipients_unique'
            );

            $table->index(
                ['institution_id', 'group_name'],
                'daily_attendance_recipients_group_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_daily_attendance_recipients');
    }
};
