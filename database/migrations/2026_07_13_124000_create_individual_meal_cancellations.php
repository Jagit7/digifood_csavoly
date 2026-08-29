<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy reference tables currently use MyISAM, therefore these high-volume
        // InnoDB tables use indexed numeric references without cross-engine foreign keys.
        Schema::create('meal_cancellations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('child_id');
            $table->date('service_date');
            $table->enum('source', ['admin', 'parent'])->default('admin');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->string('reason', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'service_date'], 'meal_cancel_child_date_unique');
            $table->index(['institution_id', 'status', 'service_date'], 'meal_cancel_institution_status_date');
            $table->index(['institution_id', 'created_at'], 'meal_cancel_institution_created');
            $table->index(['child_id', 'status', 'service_date'], 'meal_cancel_child_status_date');
        });

        Schema::create('recurring_cancellation_rules', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedTinyInteger('weekday');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('source', ['admin', 'parent'])->default('admin');
            $table->enum('status', ['active', 'ended', 'revoked'])->default('active');
            $table->string('reason', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['institution_id', 'status', 'weekday', 'starts_on', 'ends_on'],
                'recurring_cancel_effective_lookup'
            );
            $table->index(
                ['child_id', 'status', 'weekday', 'starts_on', 'ends_on'],
                'recurring_cancel_child_lookup'
            );
            $table->index(
                ['institution_id', 'status', 'starts_on'],
                'recurring_cancel_list_lookup'
            );
            $table->index(['institution_id', 'created_at'], 'recurring_cancel_institution_created');
        });

        Schema::table('class_cancellations', function (Blueprint $table) {
            $table->index(
                ['class_group_id', 'date_from', 'date_to'],
                'class_cancel_group_date_lookup'
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE children ADD INDEX children_institution_name_lookup (institution_id, name(100))'
            );
        } else {
            Schema::table('children', function (Blueprint $table) {
                $table->index(['institution_id', 'name'], 'children_institution_name_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropIndex('children_institution_name_lookup');
        });

        Schema::table('class_cancellations', function (Blueprint $table) {
            $table->dropIndex('class_cancel_group_date_lookup');
        });

        Schema::dropIfExists('recurring_cancellation_rules');
        Schema::dropIfExists('meal_cancellations');
    }
};
