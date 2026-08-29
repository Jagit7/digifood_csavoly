<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('active');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['institution_id', 'name']);
            $table->index(['institution_id', 'is_current']);
        });

        $years = DB::table('children')
            ->whereNotNull('school_year')
            ->where('school_year', '!=', '')
            ->select('institution_id', 'school_year')
            ->distinct()
            ->get();

        foreach ($years as $year) {
            if (!preg_match('/^(\d{4})\/(\d{4})$/', $year->school_year, $matches)) {
                continue;
            }

            $startsOn = $matches[1].'-09-01';
            $endsOn = $matches[2].'-08-31';

            DB::table('school_years')->insert([
                'institution_id' => $year->institution_id,
                'name' => $year->school_year,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'active',
                'is_current' => now()->toDateString() >= $startsOn && now()->toDateString() <= $endsOn,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
