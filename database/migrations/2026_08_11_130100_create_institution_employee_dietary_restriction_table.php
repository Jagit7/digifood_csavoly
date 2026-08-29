<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_employee_dietary_restriction', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('institution_employee_id');
            $table->unsignedBigInteger('dietary_restriction_id');

            $table->timestamps();

            $table->foreign('institution_employee_id', 'iedr_employee_fk')
                ->references('id')
                ->on('institution_employees')
                ->cascadeOnDelete();

            $table->foreign('dietary_restriction_id', 'iedr_dietary_fk')
                ->references('id')
                ->on('dietary_restrictions')
                ->cascadeOnDelete();

            $table->unique(
                ['institution_employee_id', 'dietary_restriction_id'],
                'iedr_employee_dietary_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_employee_dietary_restriction');
    }
};
