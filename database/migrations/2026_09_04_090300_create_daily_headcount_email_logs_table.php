<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_headcount_email_logs', function (Blueprint $table) {
            $table->id();

            // Az institutions tábla ezen a rendszeren MyISAM,
            // ezért foreign key constraint nem használható.
            $table->unsignedBigInteger('institution_id');

            $table->string('group_name', 100);
            $table->date('headcount_date');
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // Egy osztály/csoport egy adott napra csak egyszer
            // kaphat automatikus létszám e-mailt.
            $table->unique(
                ['institution_id', 'group_name', 'headcount_date'],
                'daily_headcount_email_logs_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_headcount_email_logs');
    }
};