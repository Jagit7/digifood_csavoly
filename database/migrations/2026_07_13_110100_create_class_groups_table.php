<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->string('section', 20)->nullable();
            $table->string('group_type', 30)->default('school_class');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['school_year_id', 'name']);
            $table->index(['institution_id', 'active']);
            $table->index(['school_year_id', 'grade_level']);
        });

        $groups = DB::table('children')
            ->join('school_years', function ($join) {
                $join->on('school_years.institution_id', '=', 'children.institution_id')
                    ->on('school_years.name', '=', 'children.school_year');
            })
            ->join('institutions', 'institutions.id', '=', 'children.institution_id')
            ->whereNotNull('children.group_name')
            ->where('children.group_name', '!=', '')
            ->select(
                'children.institution_id',
                'school_years.id as school_year_id',
                'children.group_name',
                'institutions.type as institution_type'
            )
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            preg_match('/^(\d{1,2})\.\s*(.+)$/u', trim($group->group_name), $parts);

            DB::table('class_groups')->insert([
                'institution_id' => $group->institution_id,
                'school_year_id' => $group->school_year_id,
                'name' => $group->group_name,
                'grade_level' => isset($parts[1]) ? (int) $parts[1] : null,
                'section' => isset($parts[2]) ? trim($parts[2]) : null,
                'group_type' => $group->institution_type === 'ovoda' ? 'kindergarten_group' : 'school_class',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_groups');
    }
};
