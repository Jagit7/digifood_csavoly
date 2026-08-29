<?php

namespace App\Console\Commands;

use App\Services\Billing\SaasBillingSummaryService;
use App\Services\InstitutionCalendarService;
use Illuminate\Console\Command;

class DispatchSaasBillingSummaryCommand extends Command
{
    protected $signature = 'digifood:saas-billing-summary:dispatch';

    protected $description = 'Elküldi a havi Digifood számlázási összesítőt, ha ma a hónap konfigurált napja van, és ebben a hónapban még nem ment ki.';

    public function handle(
        SaasBillingSummaryService $service,
        InstitutionCalendarService $calendar
    ): int {
        $now = $calendar->now();
        $configuredDay = (int) config('digifood.saas_billing_summary_day_of_month', 5);

        // Ha a beállított nap (pl. 29/30/31) nagyobb, mint az aktuális
        // hónap napjainak száma (pl. februárban nincs 30. nap), a hónap
        // UTOLSÓ napján futtatjuk - enélkül egy 29/30/31-re beállított nap
        // esetén a parancs egyes hónapokban (pl. február, vagy 30 napos
        // hónapokban a 31-es beállítás mellett) sosem találná el a napot,
        // és az adott havi összesítő csendben kimaradna.
        $effectiveDay = min($configuredDay, $now->daysInMonth);

        if ($now->day !== $effectiveDay) {
            return self::SUCCESS;
        }

        $month = $now->startOfMonth();

        if ($service->alreadySentThisMonth($month)) {
            return self::SUCCESS;
        }

        $service->send($month, 'schedule');

        return self::SUCCESS;
    }
}
