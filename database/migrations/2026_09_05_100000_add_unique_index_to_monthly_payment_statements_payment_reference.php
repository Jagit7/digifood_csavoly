<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2. FÁZIS - szülő által indított számlázás: EGYEDI, adatbázisban tárolt,
 * kereshető fizetési referencia (banki közlemény).
 *
 * FONTOS: a `monthly_payment_statements.payment_reference` oszlop MÁR
 * LÉTEZIK (ld. 2026_07_15_131000_add_invoice_and_payment_fields_to_monthly_payment_statements_table),
 * de eddig sehol nem volt ténylegesen feltöltve/felhasznált - egy korábbi,
 * azóta az App\Services\Finance\InstitutionInvoiceService + InstitutionInvoice
 * tábla köré átépített számlázási architektúra előtti maradvány mező volt.
 *
 * A meglévő üzleti szabály (régi migrációt nem módosítunk) miatt ITT, egy ÚJ
 * migrációban csak egyedi (unique) indexet adunk hozzá a már létező
 * oszlophoz - ez teszi lehetővé, hogy a szülő által indított számlázási
 * folyamathoz generált referencia garantáltan egyedi legyen adatbázis
 * szinten is (ld. App\Services\Finance\InstitutionInvoiceService::createForParent()),
 * ne csak alkalmazás szinten. Az oszlop NULL marad minden olyan
 * elszámolásnál, ahol a szülő (vagy az admin) még nem indított el számlázási
 * folyamatot - ez MySQL/MariaDB alatt nem ütközik a unique kényszerrel
 * (több NULL érték is megengedett unique oszlopon).
 */
return new class extends Migration
{
    private const TABLE = 'monthly_payment_statements';

    private const INDEX = 'monthly_payment_statements_payment_reference_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'payment_reference')) {
            return;
        }

        if ($this->hasIndex(self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unique('payment_reference', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! $this->hasIndex(self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }

    private function hasIndex(string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', self::TABLE)
                ->where('index_name', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            foreach (DB::select(sprintf('PRAGMA index_list("%s")', self::TABLE)) as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};
