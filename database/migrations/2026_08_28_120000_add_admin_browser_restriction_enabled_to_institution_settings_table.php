<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intézményenként kapcsolható, hogy aktív-e az intézményi adminokra
     * vonatkozó "gép szerinti" böngésző-korlátozás (max.
     * InstitutionAdminDevice::MAX_DEVICES_PER_USER jóváhagyott eszköz,
     * superadmin jóváhagyással - ld. AuthController::
     * verifyInstitutionAdminDevice()). A kapcsolót kizárólag superadmin
     * állíthatja az intézmény szerkesztő felületén (ld.
     * dashboard.superadmin.institutions.edit / InstitutionController::update()).
     *
     * FONTOS - visszamenőleges kompatibilitás: jelenleg MINDEN intézménynél
     * aktív ez a korlátozás, ezért az alapértelmezés true - így a mező
     * bevezetése egyetlen meglévő intézmény viselkedését sem változtatja
     * meg (sem a már létező institution_settings sorokét: a DB-szintű
     * default(true) ezekre is automatikusan érvényesül, sem az újakét:
     * ld. InstitutionSetting::defaults()).
     *
     * A kapcsoló kikapcsolása szándékosan NEM törli a korábban
     * jóváhagyott/függő InstitutionAdminDevice sorokat - csak figyelmen
     * kívül hagyatja azokat a bejelentkezéskor (ld. AuthController::
     * adminBrowserRestrictionActive()), így egy későbbi visszakapcsolás
     * ugyanazokat az engedélyeket élesztheti újra.
     */
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->boolean('admin_browser_restriction_enabled')
                ->default(true)
                ->after('barcode_entry_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn('admin_browser_restriction_enabled');
        });
    }
};
