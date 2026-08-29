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
        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['user_id', 'institution_id']);
        });

        DB::table('guardians')
            ->select('id', 'email')
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($guardians): void {
                foreach ($guardians as $guardian) {
                    $userId = DB::table('users')
                        ->where('email', $guardian->email)
                        ->where('role', User::ROLE_PARENT)
                        ->value('id');

                    if ($userId) {
                        DB::table('guardians')
                            ->where('id', $guardian->id)
                            ->update(['user_id' => $userId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
