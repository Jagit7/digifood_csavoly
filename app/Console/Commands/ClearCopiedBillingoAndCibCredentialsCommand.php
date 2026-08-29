<?php

namespace App\Console\Commands;

use App\Models\InstitutionSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Egyszeri takarító parancs: a "digifood_testverkek" mappa egy működő,
 * éles fizetős-számlázós rendszerből lett átmásolva, ezért az
 * institution_settings tábla intézményi soraiban valódi, éles Billingo
 * API kulcsok és CIB terminál/secret kulcsok maradhattak - itt, ebben a
 * teszt/dev környezetben ezekre nincs szükség, és nem is szabad éles
 * kulccsal véletlenül élesben számlázni/fizettetni.
 *
 * A parancs KIZÁRÓLAG a másolt, intézményi Billingo és CIB titkokat
 * törli (billingo_api_key, billingo_document_block_id és a Billingo
 * szinkron-állapot mezői, valamint cib_terminal_id / cib_secret_key) -
 * mindent mást (a számlázási/fizetési szolgáltató KIVÁLASZTÁSÁT, a
 * fizetési határidőt, a számla nyelvét, e-számla beállítást, stb.)
 * változatlanul hagy, hogy egy esetleges jövőbeli éles bekapcsoláshoz
 * csak az új kulcsokat kelljen visszatölteni.
 *
 * Amelyik intézménynél a törölt Billingo kulcs miatt a "Billingo"
 * lenne az aktív számlázási szolgáltató (invoicing_provider), ott az
 * invoicing_enabled kapcsolót is kikapcsolja, hogy a rendszer ne
 * próbáljon meg kulcs nélkül szinkronizálni. Ugyanígy CIB esetén a
 * card_payment_enabled kapcsolót kapcsolja ki, ha a card_payment_provider
 * 'cib' volt. Ezek a kapcsolók bármikor egyszerűen visszakapcsolhatók,
 * ha egyszer újra éles Billingo/CIB integrációra váltunk.
 *
 * Számlázz.hu kulcsokhoz (szamlazz_hu_agent_key) ez a parancs NEM nyúl -
 * azt külön kell kezelni, ha az is másolt/éles adatot tartalmaz.
 */
class ClearCopiedBillingoAndCibCredentialsCommand extends Command
{
    protected $signature = 'digifood:institution-settings:clear-copied-billing-credentials
        {--dry-run : Csak jelentést készít, nem ír adatbázist}';

    protected $description = 'A másolt (éles) Billingo API kulcsok és CIB terminál/secret kulcsok törlése az institution_settings táblából, a szolgáltató-beállítások (provider, határidő, stb.) érintetlenül hagyása mellett.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $affected = InstitutionSetting::query()
            ->with('institution:id,name')
            ->where(function ($query) {
                $query
                    ->whereNotNull('billingo_api_key')
                    ->orWhereNotNull('billingo_document_block_id')
                    ->orWhereNotNull('cib_terminal_id')
                    ->orWhereNotNull('cib_secret_key');
            })
            ->get();

        if ($affected->isEmpty()) {
            $this->info('Nincs törlendő Billingo/CIB kulcs - egyik intézménynél sincs beállítva egyik sem.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d db intézmény érintett.', $affected->count()));

        foreach ($affected as $setting) {
            $fields = array_filter([
                $setting->billingo_api_key ? 'billingo_api_key' : null,
                $setting->billingo_document_block_id ? 'billingo_document_block_id' : null,
                $setting->cib_terminal_id ? 'cib_terminal_id' : null,
                $setting->cib_secret_key ? 'cib_secret_key' : null,
            ]);

            $this->line(sprintf(
                '  intézmény #%d (%s): %s%s%s',
                $setting->institution_id,
                $setting->institution?->name ?? 'ismeretlen',
                implode(', ', $fields),
                $setting->invoicing_provider === 'billingo' && $setting->invoicing_enabled ? ' + invoicing_enabled kikapcsolása' : '',
                $setting->card_payment_provider === 'cib' && $setting->card_payment_enabled ? ' + card_payment_enabled kikapcsolása' : ''
            ));
        }

        if ($dryRun) {
            $this->warn('Dry-run mód: nem történt adatbázis-írás.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Biztosan törlöd a fenti Billingo/CIB kulcsokat (és szükség esetén kikapcsolod az érintett szolgáltatót)?', false)) {
            $this->warn('Megszakítva, nem történt módosítás.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($affected) {
            foreach ($affected as $setting) {
                $updates = [
                    'billingo_api_key' => null,
                    'billingo_document_block_id' => null,
                    'billingo_last_successful_sync_at' => null,
                    'billingo_sync_last_modified_at' => null,
                    'billingo_last_sync_error' => null,
                    'cib_terminal_id' => null,
                    'cib_secret_key' => null,
                ];

                if ($setting->invoicing_provider === 'billingo' && $setting->invoicing_enabled) {
                    $updates['invoicing_enabled'] = false;
                }

                if ($setting->card_payment_provider === 'cib' && $setting->card_payment_enabled) {
                    $updates['card_payment_enabled'] = false;
                }

                InstitutionSetting::query()->whereKey($setting->id)->update($updates);
            }
        });

        $this->info('Kész: a fenti intézmények Billingo/CIB kulcsai törölve, az érintett szolgáltatók (ha kellett) kikapcsolva.');

        return self::SUCCESS;
    }
}
