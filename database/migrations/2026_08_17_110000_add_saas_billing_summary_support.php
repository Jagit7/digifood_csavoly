<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->decimal('saas_fee_per_active_eater', 10, 2)->nullable()->after('billing_payment_due_days');
        });

        Schema::create('saas_billing_summary_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('institution_count')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('sent');
            $table->string('triggered_by', 20)->default('schedule');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_summary_runs');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('saas_fee_per_active_eater');
        });
    }
};
