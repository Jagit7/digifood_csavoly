<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A gyermekek és dolgozók vonalkód-tokenje (barcode_token) eddig sima
 * szövegként volt tárolva az adatbázisban. Egy DB-dump kiszivárgása
 * esetén ez lehetővé tenné, hogy valaki más nevében "beolvasva" ingyen
 * étkezéshez jusson (fizikai vonalkód-hamisítás). A mostantól a
 * barcode_token oszlop titkosított (Laravel 'encrypted' cast az
 * App\Models\Child / App\Models\InstitutionEmployee modelleken), a
 * tényleges beolvasásos keresés pedig egy új, determinisztikus
 * (SHA-256) hash oszlopon (barcode_token_hash) keresztül történik - a
 * titkosítás ugyanis véletlen IV-t használ, ezért nem alkalmas
 * pontos egyezés szerinti WHERE lekérdezésre.
 *
 * Az oszlop típusát varchar(64)-ről text-re kell bővíteni, mert a
 * titkosított érték hosszabb, mint az eredeti nyers token - ezt NEM a
 * Schema::table(...)->change() metódussal végezzük, mert az
 * doctrine/dbal csomagot igényelne, ami nincs telepítve a projektben.
 * Ehelyett egy ideiglenes oszlopba mentjük a jelenlegi értékeket, majd
 * eldobjuk és újra létrehozzuk az oszlopot a megfelelő típussal.
 */
return new class extends Migration
{
    private array $tables = ['children', 'institution_employees'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'barcode_token_plain_tmp')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->text('barcode_token_plain_tmp')->nullable();
                });
            }

            DB::statement("UPDATE {$table} SET barcode_token_plain_tmp = barcode_token WHERE barcode_token IS NOT NULL");

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique("{$table}_barcode_token_unique");
                $blueprint->dropColumn('barcode_token');
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->text('barcode_token')->nullable();

                if (! Schema::hasColumn($table, 'barcode_token_hash')) {
                    $blueprint->string('barcode_token_hash', 64)->nullable()->unique()->after('barcode_token');
                }
            });

            DB::table($table)
                ->whereNotNull('barcode_token_plain_tmp')
                ->where('barcode_token_plain_tmp', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $plainToken = $row->barcode_token_plain_tmp;

                        DB::table($table)->where('id', $row->id)->update([
                            'barcode_token' => Crypt::encryptString($plainToken),
                            'barcode_token_hash' => hash('sha256', $plainToken),
                        ]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('barcode_token_plain_tmp');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->text('barcode_token_plain_tmp')->nullable();
            });

            DB::table($table)
                ->whereNotNull('barcode_token')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        try {
                            $plainToken = Crypt::decryptString($row->barcode_token);
                        } catch (\Throwable) {
                            $plainToken = null;
                        }

                        DB::table($table)->where('id', $row->id)->update([
                            'barcode_token_plain_tmp' => $plainToken,
                        ]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['barcode_token', 'barcode_token_hash']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->renameColumn('barcode_token_plain_tmp', 'barcode_token');
            });
        }
    }
};
