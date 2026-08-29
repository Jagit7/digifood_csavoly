<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_choices', function (Blueprint $table) {
            $table->foreignId('ab_menu_item_id')
                ->nullable()
                ->after('child_id')
                ->constrained('ab_menu_items')
                ->nullOnDelete();
            $table->foreignId('selected_by')
                ->nullable()
                ->after('choice')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('menu_choices')
            ->orderBy('id')
            ->get()
            ->each(function ($choice) {
                $itemId = DB::table('ab_menu_items')
                    ->join('ab_menu_plans', 'ab_menu_plans.id', '=', 'ab_menu_items.ab_menu_plan_id')
                    ->where('ab_menu_plans.institution_id', $choice->institution_id)
                    ->whereDate('ab_menu_items.menu_date', $choice->menu_date)
                    ->orderByDesc('ab_menu_plans.published_at')
                    ->orderByDesc('ab_menu_plans.id')
                    ->value('ab_menu_items.id');

                if ($itemId !== null) {
                    DB::table('menu_choices')
                        ->where('id', $choice->id)
                        ->update(['ab_menu_item_id' => $itemId]);
                }
            });

        Schema::table('menu_choices', function (Blueprint $table) {
            $table->unique(['child_id', 'ab_menu_item_id'], 'menu_choices_child_item_unique');
            $table->index(['institution_id', 'ab_menu_item_id'], 'menu_choices_institution_item_index');
        });
    }

    public function down(): void
    {
        Schema::table('menu_choices', function (Blueprint $table) {
            $table->dropUnique('menu_choices_child_item_unique');
            $table->dropIndex('menu_choices_institution_item_index');
            $table->dropConstrainedForeignId('selected_by');
            $table->dropConstrainedForeignId('ab_menu_item_id');
        });
    }
};
