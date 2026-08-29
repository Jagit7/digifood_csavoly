<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "szamlazz_api_key" mező az Institution modellen egy régi, párhuzamos
 * mező volt, amit a tényleges számlázási folyamat sosem olvasott - a valódi
 * Számlázz.hu kulcsot az InstitutionSetting.szamlazz_hu_agent_key tárolja
 * (titkosítva). A kettősség csak megtévesztő volt, ezért törlésre kerül.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            if (Schema::hasColumn('institutions', 'szamlazz_api_key')) {
                $table->dropColumn('szamlazz_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('szamlazz_api_key')->nullable();
        });
    }
};
