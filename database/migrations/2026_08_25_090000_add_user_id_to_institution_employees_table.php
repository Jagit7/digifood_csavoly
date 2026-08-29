<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_employees', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('activation_email_sent_at')->nullable()->after('user_id');

            $table->index(['user_id', 'institution_id']);
        });

        // Ld. add_user_id_to_guardians_table migráció - ugyanaz a minta: ha
        // már létezik dolgozói szerepkörű user ugyanazzal az e-mail címmel,
        // automatikusan összekapcsoljuk.
        DB::table('institution_employees')
            ->select('id', 'email')
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($employees): void {
                foreach ($employees as $employee) {
                    $userId = DB::table('users')
                        ->where('email', $employee->email)
                        ->where('role', User::ROLE_EMPLOYEE)
                        ->value('id');

                    if ($userId) {
                        DB::table('institution_employees')
                            ->where('id', $employee->id)
                            ->update(['user_id' => $userId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('institution_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('activation_email_sent_at');
        });
    }
};
