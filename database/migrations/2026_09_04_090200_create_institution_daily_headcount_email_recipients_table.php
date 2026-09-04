<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_daily_headcount_email_recipients', function (Blueprint $table) {
            $table->id();

            // Az institutions tábla ezen a rendszeren MyISAM,
            // ezért foreign key constraint nem használható.
            $table->unsignedBigInteger('institution_id');

            $table->string('group_name', 80);
            $table->string('email', 150);

            $table->timestamps();

            $table->unique(
                ['institution_id', 'group_name', 'email'],
                'daily_headcount_email_recipients_unique'
            );

            $table->index(
                ['institution_id', 'group_name'],
                'daily_headcount_email_recipients_group_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_daily_headcount_email_recipients');
    }
};