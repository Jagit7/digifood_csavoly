<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['class_group_id', 'child_id']);
            $table->index(['child_id', 'status']);
        });

        $memberships = DB::table('children')
            ->join('school_years', function ($join) {
                $join->on('school_years.institution_id', '=', 'children.institution_id')
                    ->on('school_years.name', '=', 'children.school_year');
            })
            ->join('class_groups', function ($join) {
                $join->on('class_groups.institution_id', '=', 'children.institution_id')
                    ->on('class_groups.school_year_id', '=', 'school_years.id')
                    ->on('class_groups.name', '=', 'children.group_name');
            })
            ->select(
                'class_groups.id as class_group_id',
                'children.id as child_id',
                'children.active',
                'school_years.starts_on'
            )
            ->get();

        foreach ($memberships as $membership) {
            DB::table('class_group_memberships')->insert([
                'class_group_id' => $membership->class_group_id,
                'child_id' => $membership->child_id,
                'status' => $membership->active ? 'active' : 'inactive',
                'joined_on' => $membership->starts_on,
                'left_on' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_group_memberships');
    }
};
