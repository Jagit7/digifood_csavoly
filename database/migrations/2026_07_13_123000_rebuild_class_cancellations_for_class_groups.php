<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_cancellations_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_group_id')->constrained('class_groups')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('reason', 191)->nullable();
            $table->unsignedSmallInteger('affected_children_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'class_group_id']);
            $table->index(['date_from', 'date_to']);
        });

        DB::table('class_cancellations')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.school_class_id')
            ->whereColumn('class_groups.institution_id', 'class_cancellations.institution_id')
            ->orderBy('class_cancellations.id')
            ->select('class_cancellations.*')
            ->each(function ($cancellation) {
                DB::table('class_cancellations_new')->insert([
                    'id' => $cancellation->id,
                    'institution_id' => $cancellation->institution_id,
                    'class_group_id' => $cancellation->school_class_id,
                    'date_from' => $cancellation->date_from,
                    'date_to' => $cancellation->date_to,
                    'reason' => $cancellation->reason,
                    'affected_children_count' => 0,
                    'created_by' => $cancellation->created_by,
                    'created_at' => $cancellation->created_at,
                    'updated_at' => $cancellation->updated_at,
                ]);
            });

        Schema::drop('class_cancellations');
        Schema::rename('class_cancellations_new', 'class_cancellations');
    }

    public function down(): void
    {
        Schema::create('class_cancellations_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id');
            $table->foreignId('school_class_id');
            $table->date('date_from');
            $table->date('date_to');
            $table->string('reason', 191)->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        DB::table('class_cancellations')->orderBy('id')->each(function ($cancellation) {
            DB::table('class_cancellations_legacy')->insert([
                'id' => $cancellation->id,
                'institution_id' => $cancellation->institution_id,
                'school_class_id' => $cancellation->class_group_id,
                'date_from' => $cancellation->date_from,
                'date_to' => $cancellation->date_to,
                'reason' => $cancellation->reason,
                'created_by' => $cancellation->created_by,
                'created_at' => $cancellation->created_at,
                'updated_at' => $cancellation->updated_at,
            ]);
        });

        Schema::drop('class_cancellations');
        Schema::rename('class_cancellations_legacy', 'class_cancellations');
    }
};
