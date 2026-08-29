<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_discount_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('discount_type_id')->constrained('discount_types')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->string('note', 191)->nullable();
            $table->timestamps();

            $table->index(['child_id', 'valid_from']);
        });

        DB::table('children')
            ->select(['id', 'discount_type_id', 'source_type', 'created_at', 'updated_at'])
            ->whereNotNull('discount_type_id')
            ->orderBy('id')
            ->chunkById(200, function ($children): void {
                $now = now();
                $rows = [];

                foreach ($children as $child) {
                    $createdAt = $child->created_at
                        ? Carbon::parse($child->created_at)
                        : $now;

                    $rows[] = [
                        'child_id' => $child->id,
                        'discount_type_id' => $child->discount_type_id,
                        'valid_from' => $createdAt->toDateString(),
                        'valid_to' => null,
                        'source_type' => $child->source_type,
                        'note' => 'Migrated from children.discount_type_id',
                        'created_at' => $child->created_at ?? $now,
                        'updated_at' => $child->updated_at ?? $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('child_discount_periods')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_discount_periods');
    }
};
