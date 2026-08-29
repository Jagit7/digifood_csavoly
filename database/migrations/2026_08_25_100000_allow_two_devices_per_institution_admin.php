<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mostantól egy intézményi admin felhasználóhoz akár KÉT, egymástól
     * független eszköz-sor is tartozhat (két, külön jóváhagyható böngésző-
     * eszköz), ezért a korábbi "legfeljebb 1 sor / felhasználó" megkötést
     * biztosító unique indexet le kell cserélni egy sima (nem-unique)
     * indexre - a max. 2 eszköz szabályt mostantól alkalmazás-szinten
     * kényszerítjük ki (ld. App\Models\InstitutionAdminDevice::
     * MAX_DEVICES_PER_USER és AuthController::verifyInstitutionAdminDevice()).
     */
    public function up(): void
    {
        Schema::table('institution_admin_devices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('institution_admin_devices', function (Blueprint $table) {
            $table->dropUnique('institution_admin_devices_user_id_unique');
        });

        Schema::table('institution_admin_devices', function (Blueprint $table) {
            $table->index(
                'user_id',
                'institution_admin_devices_user_id_index'
            );
        });

        Schema::table('institution_admin_devices', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institution_admin_devices', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
