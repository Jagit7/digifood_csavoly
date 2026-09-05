<?php

namespace App\Services\ParentPortal;

use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\InstitutionInvoiceService;

/**
 * 2. FÁZIS: a szülő saját maga indítja el a tárgyhavi számlázási folyamatot
 * a havi elszámolás oldalán. Ez a service KIZÁRÓLAG a szülői felület
 * jogosultság-ellenőrzését és a banki tájékoztató blokk összeállítását végzi
 * - a tényleges számlakiállítási logikát (guard-ok, idempotencia, provider
 * hívás) teljes egészében az already meglévő InstitutionInvoiceService::
 * createForParent()-nek adja át, hogy ne jöjjön létre egy második,
 * párhuzamos számlázási útvonal.
 */
class ParentInvoiceInitiationService
{
    public function __construct(
        private readonly InstitutionInvoiceService $invoiceService
    ) {}

    /**
     * A statementhez tartozó gyermek a bejelentkezett szülő saját (aktív
     * gondviselői kapcsolattal rendelkező) gyermeke-e. Ez a "Másik szülő
     * gyermekének elszámolására NEM indíthat számlázást" (5. feladat)
     * ellenőrzés forrása - ugyanazt a mintát követi, mint
     * ParentInvoicePageService::resolveScope().
     */
    public function guardianOwnsStatement(User $user, MonthlyPaymentStatement $statement): bool
    {
        $guardianIds = $user->guardians()
            ->where('active', true)
            ->pluck('guardians.id');

        if ($guardianIds->isEmpty()) {
            return false;
        }

        return \App\Models\Child::query()
            ->whereKey($statement->child_id)
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds->all()))
            ->exists();
    }

    /**
     * Igaz, ha a szülői felületen egyáltalán megjeleníthető a "Számla
     * elkészítése" művelet erre a statementre (még nincs kiállított számla,
     * pozitív az aktuális havi összeg, az elszámolás lezárt/véglegesített,
     * és az intézmény valódi (nem "manual") automatikus szolgáltatóval
     * számláz). Ugyanazokat a feltételeket nézi, mint amiket
     * InstitutionInvoiceService::createForParent() ténylegesen kikényszerít
     * - itt csak MEGJELENÍTÉSI döntéshez használjuk, a tényleges védelem a
     * service oldalán, szerveroldalon történik.
     */
    public function canOfferInvoicing(MonthlyPaymentStatement $statement, InstitutionSetting $setting): bool
    {
        if ($statement->invoiceable_amount <= 0) {
            return false;
        }

        if (! $statement->isClosed() || ! empty($statement->issues ?? [])) {
            return false;
        }

        if (! $setting->invoicing_enabled) {
            return false;
        }

        return in_array($setting->invoicing_provider, [
            InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
            InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU,
        ], true);
    }

    /**
     * A számlázási folyamat elindítása utáni banki tájékoztató blokk egy
     * konkrét (már kiállított) számlához - ld. "4. BANKI ÁTUTALÁSI ADATOK".
     * Szándékosan NEM azonos a ParentMonthlySettlementService::
     * bankTransferInfo()-val: az az ÖSSZES gyermek ÖSSZEVONT, korábbi
     * egyenleggel növelt total_payable-jét mutatja (kártyás fizetés
     * hiányában), ez viszont EGYETLEN, már kiállított számla pontos,
     * korábbi egyenleget NEM tartalmazó összegét és a hozzá tartozó egyedi
     * payment_reference-t.
     *
     * A split_manual_transfer_enabled beállítású intézményeknél (ahol a
     * Zsárica/Óvoda befizetés két KÜLÖN bankszámlára, két külön, fix
     * (nem egyedi) közleménnyel történik) ez a blokk szándékosan nem
     * jelenik meg - ott a kiállított számla EGY összevont összeg, ami nem
     * felel meg egyetlen banki átutalásnak sem, ez üzleti döntést igényel
     * (ld. a végjelentés H) pontját).
     */
    public function bankPaymentInfo(Institution $institution, InstitutionSetting $setting, InstitutionInvoice $invoice): ?array
    {
        if ($setting->usesSplitManualTransfer()) {
            return null;
        }

        if (! $setting->hasBankTransferAccount()) {
            return null;
        }

        $accountHolder = trim((string) (
            $setting->bank_transfer_account_holder
            ?: $institution->billing_name
            ?: $institution->name
            ?: ''
        ));

        return [
            'account_holder' => $accountHolder !== '' ? $accountHolder : null,
            'account_number' => $setting->bank_transfer_account_number,
            'amount' => (int) $invoice->gross_amount,
            'amount_label' => number_format((int) $invoice->gross_amount, 0, ',', ' ').' Ft',
            'reference' => $invoice->monthlyPaymentStatement?->payment_reference,
        ];
    }
}
