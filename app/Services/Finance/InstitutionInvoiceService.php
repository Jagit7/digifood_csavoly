<?php

namespace App\Services\Finance;

use App\Models\BillingProfile;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\Providers\BillingoInvoiceProvider;
use App\Services\Finance\Providers\InvoiceProviderInterface;
use App\Services\Finance\Providers\InvoiceProviderPayload;
use App\Services\Finance\Providers\ManualInvoiceProvider;
use App\Services\Finance\Providers\SzamlazzHuInvoiceProvider;
use App\Support\Finance\SettlementAmountPresenter;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstitutionInvoiceService
{
    public function __construct(
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {}

    public function query(Institution $institution, array $filters = []): Builder
    {
        $payments = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as completed_payments_total')
            ->where('institution_id', $institution->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id');

        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $effectiveStatusSql = $this->effectiveStatusSql($today);

        return InstitutionInvoice::query()
            ->with([
                'child',
                'guardian',
                'monthlyPaymentStatement',
                'creator',
                'originalInvoice',
                'cancellationInvoice',
            ])
            ->leftJoinSub($payments, 'completed_payments', function ($join) {
                $join->on('completed_payments.monthly_payment_statement_id', '=', 'institution_invoices.monthly_payment_statement_id');
            })
            ->select('institution_invoices.*')
            ->selectRaw('COALESCE(completed_payments.completed_payments_total, 0) as completed_payments_total')
            ->selectRaw("{$effectiveStatusSql} as effective_status")
            ->where('institution_invoices.institution_id', $institution->id)
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function (Builder $query) use ($search) {
                    $query->where('institution_invoices.invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('child', fn (Builder $childQuery) => $childQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('guardian', function (Builder $guardianQuery) use ($search) {
                            $guardianQuery->where('last_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%");
                        })
                        // 2. FÁZIS - "5. ADMIN FELÜLET": kereshetőség a szülő
                        // önkiszolgáló számlázásakor generált, a statementen tárolt
                        // egyedi fizetési közlemény (payment_reference) szerint is.
                        ->orWhereHas('monthlyPaymentStatement', function (Builder $statementQuery) use ($search) {
                            $statementQuery->where('payment_reference', 'like', "%{$search}%");
                        });
                });
            })
            ->when(! empty($filters['month']), function (Builder $query) use ($filters) {
                [$year, $month] = explode('-', $filters['month']);

                $query->whereHas('monthlyPaymentStatement', function (Builder $statementQuery) use ($year, $month) {
                    $statementQuery->where('year', (int) $year)
                        ->where('month', (int) $month);
                });
            })
            ->when(! empty($filters['provider']), fn (Builder $query) => $query->where('institution_invoices.provider', $filters['provider']))
            ->when(! empty($filters['document_type']), fn (Builder $query) => $query->where('institution_invoices.document_type', $filters['document_type']))
            ->when(! empty($filters['status']), function (Builder $query) use ($filters, $today) {
                $query->whereRaw($this->effectiveStatusSql($today).' = ?', [$filters['status']]);
            })
            ->when(! empty($filters['payment_method']), fn (Builder $query) => $query->where('institution_invoices.payment_method', $filters['payment_method']))
            ->when(! empty($filters['date_from']), fn (Builder $query) => $query->whereDate('institution_invoices.issue_date', '>=', Carbon::parse($filters['date_from'])->toDateString()))
            ->when(! empty($filters['date_to']), fn (Builder $query) => $query->whereDate('institution_invoices.issue_date', '<=', Carbon::parse($filters['date_to'])->toDateString()))
            ->when(($filters['pdf_state'] ?? null) === 'missing', fn (Builder $query) => $query->whereNull('institution_invoices.invoice_pdf_path'))
            ->when(($filters['pdf_state'] ?? null) === 'error', fn (Builder $query) => $query->whereNotNull('institution_invoices.sync_error_message'));
    }

    public function summary(Builder $query): array
    {
        $base = clone $query;
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $effectiveStatusSql = $this->effectiveStatusSql($today);

        return [
            'issued_count' => (clone $base)->count(),
            'gross_total' => (clone $base)->sum('institution_invoices.gross_amount'),
            // A sztornó (jóváíró) számla negatív gross_amount-ja a
            // gross_total-ban helyesen nullázza az eredeti számlát, de a
            // "kiegyenlítetlen" (unpaid) és "lejárt" (overdue) kategóriák
            // fogalmilag nem értelmezhetők egy jóváíró dokumentumra - az
            // sosem "kintlévőség", ezért itt kifejezetten kizárjuk a
            // document_type = cancellation sorokat (nem elég az
            // effective_status-ra hagyatkozni, mert annak "issued" értéke a
            // sztornó saját, változatlan állapota is lehet).
            'unpaid_total' => (clone $base)
                ->where('institution_invoices.document_type', '!=', InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION)
                ->whereRaw("{$effectiveStatusSql} in (?, ?, ?, ?)", [
                    InstitutionInvoice::STATUS_DRAFT,
                    InstitutionInvoice::STATUS_PENDING,
                    InstitutionInvoice::STATUS_ISSUED,
                    InstitutionInvoice::STATUS_OVERDUE,
                ])
                ->sum('institution_invoices.gross_amount'),
            'overdue_count' => (clone $base)
                ->where('institution_invoices.document_type', '!=', InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION)
                ->whereRaw("{$effectiveStatusSql} = ?", [InstitutionInvoice::STATUS_OVERDUE])
                ->count(),
        ];
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        $paginator = $query
            ->orderByDesc('institution_invoices.issue_date')
            ->orderByDesc('institution_invoices.id')
            ->paginate(20)
            ->withQueryString();

        $items = $paginator->getCollection()->map(function (InstitutionInvoice $invoice) {
            $invoice->setAttribute('effective_status_meta', InstitutionInvoice::statusMeta($invoice->effective_status));
            $invoice->setAttribute('provider_meta', InstitutionInvoice::providerMeta($invoice->provider));
            $invoice->setAttribute('document_type_label', InstitutionInvoice::documentTypeOptions()[$invoice->document_type] ?? $invoice->document_type);

            return $invoice;
        });

        $paginator->setCollection($items);

        return $paginator;
    }

    public function settings(Institution $institution): InstitutionSetting
    {
        return InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
    }

    public function searchableStatements(Institution $institution, string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return MonthlyPaymentStatement::query()
            ->with('child')
            ->where('institution_id', $institution->id)
            // FONTOS: kizárólag az AKTUÁLIS havi, ténylegesen számlázható
            // összeg (invoiceable_amount) alapján szűrünk, nem a korábbi
            // tartozást/túlfizetést is tartalmazó total_payable alapján -
            // lásd store()/buildPreview() ugyanezen indoklását lentebb. Egy
            // statement, aminek csak korábbi tartozása van (invoiceable_amount
            // <= 0, de total_payable > 0), NEM kereshető itt elő, mert rá
            // ebben a hónapban nem állítható ki új számla.
            ->where('invoiceable_amount', '>', 0)
            ->whereDoesntHave('invoice')
            ->where(function (Builder $query) use ($term) {
                $query->whereHas('child', function (Builder $childQuery) use ($term) {
                    $childQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('educational_identifier', 'like', "%{$term}%");
                })->orWhereRaw("CONCAT(year, '-', LPAD(month, 2, '0')) like ?", ["%{$term}%"]);
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('child_id')
            ->limit(20)
            ->get();
    }

    public function paymentInvoiceAction(Institution $institution, InstitutionPayment $payment): ?array
    {
        abort_if($payment->institution_id !== $institution->id, 403);

        $payment->loadMissing(['child', 'guardian', 'monthlyPaymentStatement.child', 'invoice']);

        if (! $this->isCashPaymentInvoiceSource($payment)) {
            return null;
        }

        $invoice = $this->existingInvoiceForStatement($institution, $payment->monthly_payment_statement_id);

        if ($invoice) {
            return [
                'label' => 'Számla megnyitása',
                'url' => route('dashboard.institution.finance.invoices.show', $invoice),
                'class' => 'btn btn-primary btn-lg',
                'icon' => 'fa-solid fa-file-invoice',
                'helper' => $invoice->status === InstitutionInvoice::STATUS_FAILED
                    ? 'Ehhez a készpénzes befizetéshez már tartozik egy sikertelen számlapróbálkozás. A meglévő számlaoldalon a jelenlegi szabályok szerint újrapróbálhatod.'
                    : 'Ehhez a készpénzes befizetéshez már tartozik számla. Új helyett a meglévő rekord nyitható meg.',
            ];
        }

        return [
            'label' => 'Számla készítése',
            'url' => route('dashboard.institution.finance.invoices.create', ['payment_id' => $payment->id]),
            'class' => 'btn btn-success btn-lg',
            'icon' => 'fa-solid fa-file-circle-plus',
            'helper' => 'A befizetés adataiból előkészített számlaűrlap nyílik meg. A számla nem készül el automatikusan.',
        ];
    }

    public function invoiceDraftFromPayment(Institution $institution, InstitutionPayment $payment, string $provider): array
    {
        abort_if($payment->institution_id !== $institution->id, 403);

        $payment->loadMissing(['child', 'guardian', 'monthlyPaymentStatement.child', 'invoice']);

        if (! $this->isCashPaymentInvoiceSource($payment)) {
            abort(422, 'Csak sikeres, havi kötelezettséghez kapcsolt készpénzes befizetésből készíthető elő számla.');
        }

        $existingInvoice = $this->existingInvoiceForStatement($institution, $payment->monthly_payment_statement_id);

        return [
            'payment' => $payment,
            'existing_invoice' => $existingInvoice,
            'preview' => $existingInvoice
                ? null
                : $this->buildPreview($institution, $payment->monthlyPaymentStatement, $provider, $payment),
        ];
    }

    public function preview(Institution $institution, MonthlyPaymentStatement $statement, string $provider): array
    {
        return $this->buildPreview($institution, $statement, $provider);
    }

    public function store(Institution $institution, User $user, array $data): InstitutionInvoice
    {
        $settings = $this->settings($institution);
        $provider = $data['provider'];

        return DB::transaction(function () use ($institution, $user, $data, $settings, $provider) {
            $sourcePayment = null;

            $statement = MonthlyPaymentStatement::query()
                ->where('institution_id', $institution->id)
                ->whereKey($data['monthly_payment_statement_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // FONTOS (2026-09-es javítás): a számlázandó összeg KIZÁRÓLAG az
            // adott havi elszámolás aktuális havi része (invoiceable_amount)
            // lehet - a total_payable ezzel szemben a korábbi
            // tartozást/túlfizetést (previous_balance) IS tartalmazza
            // (total_payable = invoiceable_amount + previous_balance, ld.
            // PaymentObligationCalculatorService::refreshStatementTotals()).
            // A korábbi egyenleget a szülőnek/intézménynek külön, személyesen
            // kell rendeznie, az az önkormányzatnál - a most kiállított
            // számlába NEM kerülhet bele. Ezért itt is, és a $grossAmount
            // számításánál lentebb is invoiceable_amount-ot kell nézni.
            if ($statement->invoiceable_amount <= 0) {
                $this->throwValidation(['monthly_payment_statement_id' => '0 Ft vagy negatív összegű aktuális havi kötelezettségre nem készülhet számla.']);
            }

            $existingInvoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->where('monthly_payment_statement_id', $statement->id)
                ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
                ->lockForUpdate()
                ->exists();

            if ($existingInvoice) {
                $this->throwValidation(['monthly_payment_statement_id' => 'Ehhez a fizetési kötelezettséghez már tartozik számla.']);
            }

            $providerState = $this->providerState($institution, $settings, $provider);
            if (! $providerState['ready']) {
                $this->throwValidation(['provider' => implode(' ', $providerState['messages'])]);
            }

            $billing = $this->billingSnapshot($institution, $statement);
            if (! empty($billing['missing_fields'])) {
                $this->throwValidation([
                    'monthly_payment_statement_id' => 'A számlázási adatok hiányosak: '.implode(', ', $billing['missing_fields']).'.',
                ]);
            }

            if (! empty($data['source_payment_id'])) {
                $sourcePayment = InstitutionPayment::query()
                    ->where('institution_id', $institution->id)
                    ->whereKey($data['source_payment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $this->isCashPaymentInvoiceSource($sourcePayment)) {
                    $this->throwValidation([
                        'source_payment_id' => 'Csak teljesült, havi kötelezettséghez kapcsolt készpénzes befizetésből készíthető elő számla.',
                    ]);
                }

                if ((int) $sourcePayment->monthly_payment_statement_id !== (int) $statement->id) {
                    $this->throwValidation([
                        'source_payment_id' => 'A kiválasztott befizetés nem ehhez a havi kötelezettséghez tartozik.',
                    ]);
                }
            }

            // Ld. a fenti invoiceable_amount <= 0 ellenőrzés kommentjét: a
            // ténylegesen kiszámlázott bruttó összeg SOSEM tartalmazhatja a
            // korábbi tartozást/túlfizetést, ezért itt is invoiceable_amount
            // a forrás, NEM total_payable.
            $grossAmount = (int) $statement->invoiceable_amount;
            $invoice = new InstitutionInvoice;
            $invoice->fill([
                'child_id' => $statement->child_id,
                'guardian_id' => $billing['guardian_id'],
                'monthly_payment_statement_id' => $statement->id,
                'institution_payment_id' => $sourcePayment?->id,
                'provider' => $provider,
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL,
                'status' => InstitutionInvoice::STATUS_PENDING,
                'issue_date' => null,
                'due_date' => Carbon::parse($data['due_date'])->toDateString(),
                'fulfillment_date' => Carbon::parse($data['fulfillment_date'])->toDateString(),
                'net_amount' => $grossAmount,
                'vat_amount' => 0,
                'gross_amount' => $grossAmount,
                'currency' => 'HUF',
                'payment_method' => $sourcePayment ? InstitutionPayment::METHOD_CASH : $data['payment_method'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?: null,
                'customer_tax_number' => $data['customer_tax_number'] ?: null,
                'billing_postcode' => $data['billing_postcode'],
                'billing_city' => $data['billing_city'],
                'billing_address' => $data['billing_address'],
                'note' => $data['note'] ?: null,
            ]);
            $invoice->institution_id = $institution->id;
            $invoice->created_by = $user->id;

            $result = $this->provider($provider)->createInvoice(
                new InvoiceProviderPayload($institution, $statement, $invoice)
            );

            $invoice->status = $result->status;
            $invoice->provider_invoice_id = $result->providerInvoiceId;
            $invoice->invoice_number = $result->invoiceNumber;
            $invoice->issue_date = $result->issueDate?->toDateString();
            $invoice->fulfillment_date = $result->fulfillmentDate?->toDateString() ?? $invoice->fulfillment_date;
            $invoice->invoice_url = $result->invoiceUrl;
            $invoice->invoice_pdf_path = $result->invoicePdfPath;
            $invoice->pdf_disk = $result->invoicePdfPath ? 'local' : null;
            $invoice->pdf_downloaded_at = $result->invoicePdfPath ? now() : null;
            $invoice->last_synced_at = now();
            $invoice->sync_error_message = null;
            $invoice->error_message = $result->errorMessage;
            $invoice->save();

            return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'institutionPayment']);
        });
    }

    public function issueAutomaticInvoiceForPayment(Institution $institution, User $user, InstitutionPayment $payment): ?InstitutionInvoice
    {
        abort_if($payment->institution_id !== $institution->id, 403);

        $settings = $this->settings($institution);

        if (! $settings->invoicing_enabled || $settings->invoicing_provider !== InstitutionSetting::INVOICING_PROVIDER_BILLINGO) {
            return null;
        }

        $payment->loadMissing(['monthlyPaymentStatement.child']);

        $preview = $this->invoiceDraftFromPayment(
            institution: $institution,
            payment: $payment,
            provider: InstitutionInvoice::PROVIDER_BILLINGO,
        );

        if (($preview['existing_invoice'] ?? null) instanceof InstitutionInvoice) {
            return $preview['existing_invoice'];
        }

        $statementPreview = $preview['preview']['statement'] ?? null;

        if (! is_array($statementPreview)) {
            return null;
        }

        return $this->store($institution, $user, [
            'monthly_payment_statement_id' => $payment->monthly_payment_statement_id,
            'source_payment_id' => $payment->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'due_date' => $statementPreview['due_date'],
            'fulfillment_date' => $statementPreview['fulfillment_date'],
            'payment_method' => $statementPreview['payment_method'],
            'customer_name' => $statementPreview['customer_name'],
            'customer_email' => $statementPreview['customer_email'],
            'customer_tax_number' => $statementPreview['customer_tax_number'],
            'billing_postcode' => $statementPreview['billing_postcode'],
            'billing_city' => $statementPreview['billing_city'],
            'billing_address' => $statementPreview['billing_address'],
            'note' => 'Automatikus számlakiállítás sikeres CIB bankkártyás fizetés után.',
        ]);
    }

    /**
     * 2. FÁZIS: a SZÜLŐ saját maga indítja el a tárgyhavi számlázási
     * folyamatot (ld. ParentInvoiceInitiationService). Szándékosan a MEGLÉVŐ
     * store() útvonalat hívja - nem épül párhuzamos, önálló
     * számlakiállítási logika -, ugyanazokkal a garanciákkal:
     *  - kizárólag az aktuális havi invoiceable_amount kerül a számlára
     *    (store() saját, változatlan guard-ja);
     *  - a statement sorát lockForUpdate()-tel zároljuk, ami MySQL/InnoDB
     *    alatt a párhuzamos (dupla kattintás / oldalfrissítés) kéréseket a
     *    tranzakció végéig sorba állítja, nem csak frontend gombtiltással;
     *  - ha időközben már létrejött a számla, nem hibázunk, hanem a MEGLÉVŐ
     *    rekordot adjuk vissza (idempotens - dupla POST nem hoz létre
     *    második számlát és nem generál új referenciát sem).
     *
     * A szolgáltatót (Billingo/Számlázz.hu) a szülő NEM választhatja meg - ez
     * mindig az intézmény InstitutionSetting::invoicing_provider beállítása
     * (ugyanaz a minta, mint az admin felület InstitutionInvoiceStoreRequest::
     * allowedProvider()-jénél). "manual" szolgáltatónál (vagy ha a számlázás
     * nincs bekapcsolva) NINCS önkiszolgáló számlakiállítás - ott a
     * ManualInvoiceProvider csak egy üres "draft" placeholder rekordot hozna
     * létre, valódi bizonylat és PDF nélkül, ami a szülőnek félrevezető
     * lenne.
     */
    public function createForParent(Institution $institution, User $parent, MonthlyPaymentStatement $statement): InstitutionInvoice
    {
        abort_if($statement->institution_id !== $institution->id, 403);

        return DB::transaction(function () use ($institution, $parent, $statement) {
            $lockedStatement = MonthlyPaymentStatement::query()
                ->where('institution_id', $institution->id)
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Ld. store() azonos indoklású ellenőrzését: a szülő is
            // kizárólag az aktuális havi, ténylegesen számlázható összegre
            // (invoiceable_amount) indíthat számlázást - a korábbi
            // tartozás/túlfizetés (previous_balance) ebbe SOSEM számít bele.
            if ($lockedStatement->invoiceable_amount <= 0) {
                $this->throwValidation(['statement' => '0 Ft vagy negatív összegű aktuális havi kötelezettségre nem indítható számlázás.']);
            }

            if (! $lockedStatement->isClosed() || ! empty($lockedStatement->issues ?? [])) {
                $this->throwValidation(['statement' => 'Ehhez a hónaphoz még nem véglegesített (lezárt) az elszámolás, ezért a szülő egyelőre nem indíthat számlázást.']);
            }

            $existingInvoice = $this->existingInvoiceForStatement($institution, $lockedStatement->id);
            if ($existingInvoice) {
                // Idempotencia: dupla kattintás / oldalfrissítés / párhuzamos
                // kérés esetén a MEGLÉVŐ számlát adjuk vissza, nem hibázunk és
                // nem hozunk létre másodikat.
                return $existingInvoice;
            }

            $settings = $this->settings($institution);
            $provider = $settings->invoicing_provider;

            if (! $settings->invoicing_enabled || ! in_array($provider, [
                InstitutionInvoice::PROVIDER_BILLINGO,
                InstitutionInvoice::PROVIDER_SZAMLAZZ_HU,
            ], true)) {
                $this->throwValidation(['statement' => 'Ennél az intézménynél jelenleg nem érhető el önálló, szülő által indított számlázás.']);
            }

            // A payment_reference-t MÁR ITT, a tényleges számla létrehozása
            // előtt generáljuk és mentjük - a "3. EGYEDI FIZETÉSI KÖZLEMÉNY"
            // feladat szerint ennek a statement teljes életciklusán át
            // (újranyitáskor is) ugyanannak kell maradnia, ezért csak akkor
            // generálunk újat, ha még nincs neki. A lockForUpdate() miatt két
            // párhuzamos kérés nem generálhat két különböző referenciát
            // ugyanarra a statementre.
            if (! filled($lockedStatement->payment_reference)) {
                $this->assignUniquePaymentReference($lockedStatement);
            }

            $preview = $this->buildPreview($institution, $lockedStatement, $provider);

            if (! $preview['available']) {
                $this->throwValidation(['statement' => implode(' ', $preview['errors'])]);
            }

            $statementPreview = $preview['statement'];

            return $this->store($institution, $parent, [
                'monthly_payment_statement_id' => $lockedStatement->id,
                'provider' => $provider,
                'due_date' => $statementPreview['due_date'],
                'fulfillment_date' => $statementPreview['fulfillment_date'],
                // A szülői önkiszolgáló folyamat célja pont az, hogy a szülő
                // banki átutalással tudjon fizetni (ld. a payment_reference-t
                // felhasználó, e módszer által visszaadott számlához tartozó
                // banki tájékoztató blokkot) - ha az intézmény nem állított be
                // eltérő alapértelmezett fizetési módot a szolgáltatóhoz,
                // "átutalás" az ésszerű alapértelmezés.
                'payment_method' => $statementPreview['payment_method'] ?? InstitutionPayment::METHOD_BANK_TRANSFER,
                'customer_name' => $statementPreview['customer_name'],
                'customer_email' => $statementPreview['customer_email'],
                'customer_tax_number' => $statementPreview['customer_tax_number'],
                'billing_postcode' => $statementPreview['billing_postcode'],
                'billing_city' => $statementPreview['billing_city'],
                'billing_address' => $statementPreview['billing_address'],
                'note' => 'Szülő által önállóan indított számlázás a szülői felületen.',
            ]);
        });
    }

    /**
     * Emberi diktálásra is alkalmas, egyedi fizetési referencia/közlemény
     * beállítása és mentése a statementre - ld. a "3. EGYEDI FIZETÉSI
     * KÖZLEMÉNY" feladatot. A karakterkészletből szándékosan hiányzik a 0/O
     * és 1/I/L (könnyen összetéveszthető telefonon/papíron bediktálva).
     *
     * A tényleges egyediséget a payment_reference oszlopon lévő DB-szintű
     * unique index garantálja (ld. migráció) - a hívó fél már zárolta
     * (lockForUpdate) az AKTUÁLIS statement sorát, ez véd a rá irányuló
     * dupla kéréstől, de nem véd egy MÁSIK statementre párhuzamosan futó
     * kérés esetleges (rendkívül valószínűtlen) véletlenszerű ütközése
     * ellen - erre szolgál az újrapróbálkozás unique constraint hiba esetén,
     * ugyanaz a minta, mint a ParentMonthlySettlementService::
     * createPaymentIntent()-ben már meglévő, bevált unique-ütközés kezelés.
     */
    private function assignUniquePaymentReference(MonthlyPaymentStatement $statement, int $attempt = 0): void
    {
        $charset = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $suffix = '';
        for ($i = 0; $i < 5; $i++) {
            $suffix .= $charset[random_int(0, strlen($charset) - 1)];
        }

        $statement->payment_reference = sprintf(
            'DF-%02d%02d-%s',
            $statement->year % 100,
            $statement->month,
            $suffix
        );

        try {
            $statement->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            abort_if($attempt >= 10, 500, 'Nem sikerült egyedi fizetési referenciát generálni.');

            $this->assignUniquePaymentReference($statement, $attempt + 1);
        }
    }

    /**
     * Kiállított számla sztornózása. Csak "issued" (a szolgáltatónál is
     * ténylegesen kiállított) bizonylat sztornózható - draft/pending/failed
     * állapotú "számlához" nincs mit sztornózni a szolgáltatónál, egy már
     * cancelled/voided számla pedig már sztornózva van. A sztornózás a
     * szolgáltatónál kiállított sztornó bizonylat adatait a
     * cancellation_* mezőkbe menti, az eredeti bizonylat adatait nem
     * írja felül (ld. InvoiceProviderInterface::cancelInvoice()).
     */
    public function cancel(Institution $institution, User $user, InstitutionInvoice $invoice, ?string $reason = null): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        return DB::transaction(function () use ($institution, $user, $invoice, $reason) {
            $invoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isCancellable()) {
                $this->throwValidation(['invoice' => 'Csak kiállított számla sztornózható.']);
            }

            $statement = $invoice->monthlyPaymentStatement()->first();
            if (! $statement) {
                $this->throwValidation(['invoice' => 'A számlához nem található fizetési kötelezettség, sztornózás nem lehetséges.']);
            }

            $result = $this->provider($invoice->provider)->cancelInvoice(
                new InvoiceProviderPayload($institution, $statement, $invoice),
                $reason
            );

            if ($result->status !== InstitutionInvoice::STATUS_VOIDED) {
                $this->throwValidation(['invoice' => $result->errorMessage ?: 'A számla sztornózása sikertelen.']);
            }

            $invoice->status = InstitutionInvoice::STATUS_VOIDED;
            $invoice->cancelled_at = now();
            $invoice->cancelled_by = $user->id;
            $invoice->cancellation_reason = $reason ?: null;
            $invoice->cancellation_provider_document_id = $result->providerInvoiceId;
            $invoice->cancellation_invoice_number = $result->invoiceNumber;
            $invoice->cancellation_pdf_path = $result->invoicePdfPath;
            $invoice->save();

            $this->reverseLinkedPaymentAfterCancellation($invoice);

            $cancellationInvoice = InstitutionInvoice::query()->firstOrNew([
                'institution_id' => $institution->id,
                'provider' => $invoice->provider,
                'provider_invoice_id' => $result->providerInvoiceId,
            ]);

            $cancellationInvoice->fill([
                'child_id' => $invoice->child_id,
                'guardian_id' => $invoice->guardian_id,
                'monthly_payment_statement_id' => $invoice->monthly_payment_statement_id,
                'original_invoice_id' => $invoice->id,
                'institution_payment_id' => null,
                'provider' => $invoice->provider,
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                'provider_invoice_id' => $result->providerInvoiceId,
                'provider_original_invoice_id' => $invoice->provider_invoice_id,
                'invoice_number' => $result->invoiceNumber,
                'status' => InstitutionInvoice::STATUS_ISSUED,
                'issue_date' => $result->issueDate?->toDateString() ?? now()->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'fulfillment_date' => $invoice->fulfillment_date?->toDateString(),
                // A sztornó (jóváíró) számla az eredeti számla összegeinek
                // NEGATÍVJÁT rögzíti, hogy az összes olyan összesítés, ami
                // gross_amount-ot (vagy net/vat_amount-ot) összegez az
                // intézmény/időszak számláin (pl. summary(), a szülői
                // portál éves összesítője, a pénzügyi export), a sztornózott
                // számlát automatikusan nullára nettósítsa az eredeti
                // számlával szemben, ahelyett hogy tévesen duplán,
                // pozitívként adná hozzá az összeghez.
                'net_amount' => -$invoice->net_amount,
                'vat_amount' => -$invoice->vat_amount,
                'gross_amount' => -$invoice->gross_amount,
                'currency' => $invoice->currency,
                'payment_method' => $invoice->payment_method,
                'customer_name' => $invoice->customer_name,
                'customer_email' => $invoice->customer_email,
                'customer_tax_number' => $invoice->customer_tax_number,
                'billing_postcode' => $invoice->billing_postcode,
                'billing_city' => $invoice->billing_city,
                'billing_address' => $invoice->billing_address,
                'invoice_url' => $result->invoiceUrl,
                'invoice_pdf_path' => $result->invoicePdfPath,
                'pdf_disk' => $result->invoicePdfPath ? 'local' : null,
                'pdf_downloaded_at' => $result->invoicePdfPath ? now() : null,
                'note' => $reason ?: null,
                'sync_error_message' => null,
                'last_synced_at' => now(),
            ]);
            $cancellationInvoice->created_by = $cancellationInvoice->created_by ?: $user->id;
            $cancellationInvoice->save();

            return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'cancelledBy']);
        });
    }

    public function reloadInvoicePdf(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        return $this->reloadProviderPdf($institution, $invoice);
    }

    public function reloadCancellationPdf(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        $cancellationInvoice = $invoice->isCancellationDocument()
            ? $invoice
            : ($invoice->cancellationInvoice()->first() ?? $this->materializeLegacyCancellationInvoice($invoice));

        if (! $cancellationInvoice) {
            $this->throwValidation(['invoice' => 'Ehhez a számlához nem tartozik külön sztornó bizonylatrekord.']);
        }

        return $this->reloadProviderPdf($institution, $cancellationInvoice);
    }

    /**
     * Egy sikertelen ("failed") számlapróbálkozás helyi törlése. Mivel a
     * szolgáltatónál ilyenkor sosem jött létre valódi bizonylat, nincs mit
     * sztornózni - a törlés csak a helyi rekordot szünteti meg, hogy a
     * monthly_payment_statement_id oszlopon lévő unique megkötés ne
     * akadályozza tovább az adott fizetési kötelezettséghez tartozó új
     * számlázási próbálkozást.
     */
    public function delete(Institution $institution, InstitutionInvoice $invoice): void
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        DB::transaction(function () use ($institution, $invoice) {
            $invoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isDeletable()) {
                $this->throwValidation(['invoice' => 'Csak sikertelen próbálkozás törölhető helyben - kiállított számlát sztornózni kell.']);
            }

            $invoice->delete();
        });
    }

    public function findForInstitution(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        $invoice->load([
            'child',
            'guardian',
            'creator',
            'cancelledBy',
            'institutionPayment.recordedBy',
            'monthlyPaymentStatement.child',
            'originalInvoice',
            'cancellationInvoice',
        ]);
        $invoice->setAttribute('provider_meta', InstitutionInvoice::providerMeta($invoice->provider));
        $invoice->setAttribute('effective_status', $this->effectiveStatusForInvoice($invoice));
        $invoice->setAttribute('effective_status_meta', InstitutionInvoice::statusMeta($invoice->effective_status));
        $invoice->setAttribute('completed_payments_total', $this->completedPaymentsTotal($institution, $invoice));

        return $invoice;
    }

    public function relatedPayments(Institution $institution, InstitutionInvoice $invoice): Collection
    {
        return InstitutionPayment::query()
            ->with(['guardian', 'recordedBy'])
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    public function hasUsableInvoicePdf(InstitutionInvoice $invoice): bool
    {
        return $this->hasUsableLocalPdfPath($invoice->invoice_pdf_path);
    }

    public function hasUsableCancellationPdf(InstitutionInvoice $invoice): bool
    {
        $cancellationInvoice = $invoice->isCancellationDocument()
            ? $invoice
            : ($invoice->cancellationInvoice()->first() ?? $this->materializeLegacyCancellationInvoice($invoice, false));

        return $cancellationInvoice
            ? $this->hasUsableLocalPdfPath($cancellationInvoice->invoice_pdf_path)
            : $this->hasUsableLocalPdfPath($invoice->cancellation_pdf_path);
    }

    private function provider(string $provider): InvoiceProviderInterface
    {
        return match ($provider) {
            InstitutionInvoice::PROVIDER_BILLINGO => app(BillingoInvoiceProvider::class),
            InstitutionInvoice::PROVIDER_SZAMLAZZ_HU => app(SzamlazzHuInvoiceProvider::class),
            InstitutionInvoice::PROVIDER_MANUAL => app(ManualInvoiceProvider::class),
            default => throw new \InvalidArgumentException('Unsupported invoice provider: '.$provider),
        };
    }

    /**
     * PDF-újratöltés a bizonylatot ténylegesen kiállító szolgáltatótól.
     *
     * FONTOS (2026-09-es bővítés): korábban ez a metódus ("reloadBillingoPdf"
     * néven) kizárólag Billingo-s bizonylatnál működött, mert közvetlenül a
     * BillingoInvoiceProvider::downloadExistingDocumentPdf() Billingo-specifikus
     * metódusát hívta. Mivel a SzamlazzHuInvoiceProvider mostantól szintén
     * implementálja a downloadExistingInvoicePdf()/downloadExistingCancellationPdf()
     * interfész-metódusokat (ld. SzamlazzHuInvoiceProvider), a szolgáltató-
     * feloldás mostantól a provider()-en (a store()/cancel() által is
     * használt, meglévő feloldási ponton) keresztül, generikusan történik -
     * ez a Billingo-s viselkedést NEM változtatja meg (ugyanaz a hívási lánc
     * fut le rá, csak most már nem hardcode-olt osztályon keresztül).
     */
    private function reloadProviderPdf(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        return DB::transaction(function () use ($institution, $invoice) {
            $invoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statement = $invoice->monthlyPaymentStatement()->first();
            if (! $statement) {
                $this->throwValidation(['invoice' => 'A számlához nem található fizetési kötelezettség, ezért a PDF nem tölthető újra.']);
            }

            $reloadableProviders = [InstitutionInvoice::PROVIDER_BILLINGO, InstitutionInvoice::PROVIDER_SZAMLAZZ_HU];
            if (! in_array($invoice->provider, $reloadableProviders, true)) {
                $this->throwValidation(['invoice' => 'PDF újratöltés jelenleg csak Billingo-s vagy Számlázz.hu-s bizonylatnál használható.']);
            }

            if (! in_array($invoice->status, [InstitutionInvoice::STATUS_ISSUED, InstitutionInvoice::STATUS_VOIDED], true)) {
                $this->throwValidation(['invoice' => 'A számla PDF-je csak már kiállított bizonylatnál tölthető újra.']);
            }

            if (! filled($invoice->provider_invoice_id)) {
                $this->throwValidation(['invoice' => 'A számlához nem tartozik szolgáltatói bizonylatazonosító, ezért a PDF nem tölthető újra.']);
            }

            if ($this->hasUsableInvoicePdf($invoice)) {
                return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'cancelledBy']);
            }

            $payload = new InvoiceProviderPayload($institution, $statement, $invoice);
            $result = $invoice->isCancellationDocument()
                ? $this->provider($invoice->provider)->downloadExistingCancellationPdf($payload)
                : $this->provider($invoice->provider)->downloadExistingInvoicePdf($payload);

            if (! filled($result->invoicePdfPath)) {
                $this->throwValidation(['invoice' => $result->errorMessage ?: 'A számla PDF-je nem tölthető újra a szolgáltatótól.']);
            }

            $invoice->invoice_pdf_path = $result->invoicePdfPath;
            $invoice->pdf_disk = 'local';
            $invoice->pdf_downloaded_at = now();
            $invoice->last_synced_at = now();
            $invoice->sync_error_message = null;
            $invoice->save();

            return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'cancelledBy']);
        });
    }

    private function providerState(Institution $institution, InstitutionSetting $settings, string $provider): array
    {
        if (! $settings->invoicing_enabled) {
            return [
                'ready' => false,
                'messages' => ['A számlázás nincs engedélyezve ennél az intézménynél.'],
            ];
        }

        $missing = [];

        if ($provider === InstitutionInvoice::PROVIDER_BILLINGO) {
            if (! $settings->hasBillingoApiKey()) {
                $missing[] = 'hiányzik a Billingo API-kulcs';
            }

            if (! filled($settings->billingo_document_block_id)) {
                $missing[] = 'hiányzik a Billingo bizonylattömb azonosító';
            }
        }

        if ($provider === InstitutionInvoice::PROVIDER_SZAMLAZZ_HU) {
            if (! $settings->hasSzamlazzHuAgentKey()) {
                $missing[] = 'hiányzik a Számlázz.hu agent kulcs';
            }

            if (! filled($settings->szamlazz_hu_invoice_prefix)) {
                $missing[] = 'hiányzik a Számlázz.hu számla előtag';
            }
        }

        return [
            'ready' => empty($missing),
            'messages' => empty($missing) ? [] : ['A kiválasztott szolgáltató beállítása hiányos: '.implode(', ', $missing).'.'],
        ];
    }

    private function billingSnapshot(Institution $institution, MonthlyPaymentStatement $statement): array
    {
        $profile = BillingProfile::query()
            ->select('billing_profiles.*')
            ->join('billing_profile_child', 'billing_profile_child.billing_profile_id', '=', 'billing_profiles.id')
            ->where('billing_profiles.institution_id', $institution->id)
            ->where('billing_profile_child.child_id', $statement->child_id)
            ->where('billing_profiles.active', true)
            ->orderByDesc('billing_profile_child.is_primary')
            ->orderByDesc('billing_profiles.id')
            ->first();

        $guardian = null;
        if ($profile?->guardian_id) {
            $guardian = $profile->guardian()->first();
        }

        if (! $guardian) {
            $guardian = $statement->child()
                ->first()
                ?->guardians()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->first();
        }

        $customerName = trim((string) ($profile->billing_name ?? $guardian?->full_name ?? ''));
        $customerEmail = trim((string) ($profile->email ?? $guardian?->email ?? ''));
        $customerTaxNumber = trim((string) ($profile->tax_number ?? ''));
        $postcode = trim((string) ($profile->postal_code ?? ''));
        $city = trim((string) ($profile->city ?? ''));
        $address = trim((string) ($profile->address ?? ''));

        $missingFields = [];
        if ($customerName === '') {
            $missingFields[] = 'vevő neve';
        }
        if ($postcode === '') {
            $missingFields[] = 'irányítószám';
        }
        if ($city === '') {
            $missingFields[] = 'város';
        }
        if ($address === '') {
            $missingFields[] = 'cím';
        }

        return [
            'guardian_id' => $guardian?->id,
            'guardian_name' => $guardian?->full_name ?: 'Nincs kapcsolt gondviselő',
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_tax_number' => $customerTaxNumber,
            'billing_postcode' => $postcode,
            'billing_city' => $city,
            'billing_address' => $address,
            'missing_fields' => $missingFields,
        ];
    }

    private function defaultDueDate(
        Institution $institution,
        InstitutionSetting $settings,
        string $provider,
        MonthlyPaymentStatement $statement,
        ?InstitutionPayment $sourcePayment = null
    ): Carbon {
        if ($sourcePayment && $this->isCashPaymentInvoiceSource($sourcePayment)) {
            return $sourcePayment->paid_at?->copy()->startOfDay()
                ?? now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay();
        }

        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay();
        $providerDueDays = match ($provider) {
            InstitutionInvoice::PROVIDER_BILLINGO => (int) $settings->billingo_due_days,
            InstitutionInvoice::PROVIDER_SZAMLAZZ_HU => (int) $settings->szamlazz_hu_due_days,
            default => 0,
        };

        if ($providerDueDays > 0) {
            return $today->copy()->addDays($providerDueDays);
        }

        $settingDueDay = (int) $settings->payment_due_day;
        if ($settingDueDay > 0) {
            $statementDue = Carbon::create($statement->year, $statement->month, min(28, $settingDueDay), 0, 0, 0, $today->timezone);

            return $statementDue->lt($today) ? $today : $statementDue;
        }

        $institutionDueDays = (int) $institution->billing_payment_due_days;
        if ($institutionDueDays > 0) {
            return $today->copy()->addDays($institutionDueDays);
        }

        return $today->copy()->addDays(8);
    }

    private function defaultFulfillmentDate(MonthlyPaymentStatement $statement): Carbon
    {
        return Carbon::create($statement->year, $statement->month, 1, 0, 0, 0, config('digifood.business_timezone', 'Europe/Budapest'))
            ->endOfMonth()
            ->startOfDay();
    }

    private function buildPreview(
        Institution $institution,
        MonthlyPaymentStatement $statement,
        string $provider,
        ?InstitutionPayment $sourcePayment = null
    ): array {
        abort_if($statement->institution_id !== $institution->id, 403);

        $statement->loadMissing('child');
        $settings = $this->settings($institution);
        $providerState = $this->providerState($institution, $settings, $provider);
        $billing = $this->billingSnapshot($institution, $statement);
        $existingInvoice = $this->existingInvoiceForStatement($institution, $statement->id);
        $periods = $this->periodHelper->fromStatement($statement);
        $dueDate = $this->defaultDueDate($institution, $settings, $provider, $statement, $sourcePayment);
        $fulfillmentDate = $sourcePayment?->paid_at?->copy()->startOfDay() ?? $this->defaultFulfillmentDate($statement);

        $errors = [];

        // Ld. store() azonos indoklású kommentjét: a számla előnézete is az
        // aktuális havi, ténylegesen számlázható összeget (invoiceable_amount)
        // nézi, nem a korábbi egyenleggel kombinált total_payable-t.
        if ($statement->invoiceable_amount <= 0) {
            $errors[] = '0 Ft vagy negatív összegű aktuális havi kötelezettségre nem készülhet számla.';
        }

        if ($existingInvoice) {
            $errors[] = 'Ehhez a fizetési kötelezettséghez már tartozik számla.';
        }

        if (! $providerState['ready']) {
            $errors = array_merge($errors, $providerState['messages']);
        }

        if (! empty($billing['missing_fields'])) {
            $errors[] = 'A számlázási adatok hiányosak: '.implode(', ', $billing['missing_fields']).'.';
        }

        return [
            'available' => empty($errors),
            'errors' => $errors,
            'settings_url' => route('dashboard.institution.settings.invoicing.edit'),
            'statement' => [
                ...$periods,
                'id' => $statement->id,
                'child_name' => $statement->child?->name,
                'guardian_name' => $billing['guardian_name'],
                'month_label' => $periods['payment_period_label'],
                // A ténylegesen számlázott összeg - ld. store() kommentjét:
                // KIZÁRÓLAG az aktuális havi rész, korábbi egyenleg nélkül.
                'gross_amount' => (int) $statement->invoiceable_amount,
                // Csak TÁJÉKOZTATÓ jellegű mezők az admin felület számára,
                // hogy jól látható legyen, miért térhet el a fenti
                // gross_amount a korábban megszokott (kombinált) összegtől -
                // ezek NEM kerülnek a számlára, és a store()-ban sem
                // használódnak fel semennyire.
                'previous_balance' => (int) $statement->previous_balance,
                'previous_balance_label' => SettlementAmountPresenter::previousBalanceLabel((int) $statement->previous_balance),
                'previous_balance_display_amount' => SettlementAmountPresenter::previousBalanceDisplayAmount((int) $statement->previous_balance),
                'total_payable_with_previous_balance' => (int) $statement->total_payable,
                'due_date' => $dueDate->toDateString(),
                'fulfillment_date' => $fulfillmentDate->toDateString(),
                'issue_date' => now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString(),
                'currency' => 'HUF',
                'item_name' => 'Étkezési térítési díj',
                'customer_name' => $billing['customer_name'],
                'customer_email' => $billing['customer_email'],
                'customer_tax_number' => $billing['customer_tax_number'],
                'billing_postcode' => $billing['billing_postcode'],
                'billing_city' => $billing['billing_city'],
                'billing_address' => $billing['billing_address'],
                'payment_method' => $sourcePayment ? InstitutionPayment::METHOD_CASH : $this->defaultPaymentMethod($settings, $provider),
                'guardian_id' => $billing['guardian_id'],
                'source_payment_id' => $sourcePayment?->id,
                'source_payment_reference' => $sourcePayment?->reference,
                'source_payment_amount' => $sourcePayment?->amount,
                'source_payment_paid_at' => $sourcePayment?->paid_at?->format('Y.m.d. H:i'),
            ],
        ];
    }

    private function existingInvoiceForStatement(Institution $institution, ?int $statementId): ?InstitutionInvoice
    {
        if (! $statementId) {
            return null;
        }

        return InstitutionInvoice::query()
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $statementId)
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
            ->first();
    }

    private function isCashPaymentInvoiceSource(InstitutionPayment $payment): bool
    {
        $statement = $payment->relationLoaded('monthlyPaymentStatement')
            ? $payment->monthlyPaymentStatement
            : $payment->monthlyPaymentStatement()->first();

        return $payment->status === InstitutionPayment::STATUS_COMPLETED
            && $payment->payment_method === InstitutionPayment::METHOD_CASH
            && filled($payment->monthly_payment_statement_id)
            && $statement !== null;
    }

    private function hasUsableLocalPdfPath(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return false;
        }

        $content = (string) $disk->get($path);

        return $content !== '' && str_starts_with($content, '%PDF-');
    }

    private function defaultPaymentMethod(InstitutionSetting $settings, string $provider): ?string
    {
        $method = match ($provider) {
            InstitutionInvoice::PROVIDER_BILLINGO => $settings->billingo_default_payment_method,
            InstitutionInvoice::PROVIDER_SZAMLAZZ_HU => $settings->szamlazz_hu_default_payment_method,
            default => null,
        };

        return filled($method) ? (string) $method : null;
    }

    private function effectiveStatusSql(string $today): string
    {
        // A sztornó (jóváíró) számla saját, negatív gross_amount-tal
        // rendelkezik, ezért a befizetés-alapú "paid"/"overdue" számítás
        // rá nem értelmezhető (a COALESCE(...) >= negatív_összeg
        // feltétel gyakorlatilag mindig igaz lenne) - a sztornó dokumentum
        // mindig a saját (tárolt) állapotát mutassa.
        return "CASE
            WHEN institution_invoices.document_type = '".InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION."' THEN institution_invoices.status
            WHEN institution_invoices.status IN ('draft', 'pending', 'failed', 'cancelled', 'voided') THEN institution_invoices.status
            WHEN COALESCE(completed_payments.completed_payments_total, 0) >= institution_invoices.gross_amount THEN 'paid'
            WHEN institution_invoices.due_date < '{$today}' THEN 'overdue'
            ELSE 'issued'
        END";
    }

    private function effectiveStatusForInvoice(InstitutionInvoice $invoice): string
    {
        // Lásd az effectiveStatusSql() megjegyzését: a sztornó dokumentum
        // negatív összege miatt a befizetés-alapú "paid"/"overdue" számítás
        // rá nem alkalmazható, mindig a saját állapotát mutatjuk.
        if ($invoice->isCancellationDocument()) {
            return $invoice->status;
        }

        $completedTotal = $this->completedPaymentsTotal($invoice->institution, $invoice);

        if (in_array($invoice->status, [
            InstitutionInvoice::STATUS_DRAFT,
            InstitutionInvoice::STATUS_PENDING,
            InstitutionInvoice::STATUS_FAILED,
            InstitutionInvoice::STATUS_CANCELLED,
            InstitutionInvoice::STATUS_VOIDED,
        ], true)) {
            return $invoice->status;
        }

        if ($completedTotal >= $invoice->gross_amount) {
            return InstitutionInvoice::STATUS_PAID;
        }

        if ($invoice->due_date && $invoice->due_date->lt(now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay())) {
            return InstitutionInvoice::STATUS_OVERDUE;
        }

        return InstitutionInvoice::STATUS_ISSUED;
    }

    private function completedPaymentsTotal(Institution $institution, InstitutionInvoice $invoice): int
    {
        return (int) InstitutionPayment::query()
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Egy kiállított számla sztornózásakor a hozzá tartozó, már teljesült
     * (completed) befizetést "törölve" (cancelled) állapotba állítjuk át.
     * Enélkül a szülői havi elszámolás (ld.
     * ParentMonthlySettlementService::buildChildCard() - fennmaradó összeg =
     * total_payable - SUM(completed InstitutionPayment)) a sztornózás UTÁN
     * is "kifizetettként" látná a hónapot, hiszen a korábbi befizetés
     * összege továbbra is levonásra kerülne - a szülő emiatt nem tudna újra
     * fizetni, miközben az admin oldalon a hónap ténylegesen újra
     * fizetendőnek számít.
     *
     * Csak akkor nyúlunk automatikusan a befizetéshez, ha az EGYÉRTELMŰEN
     * beazonosítható:
     *  - elsődlegesen a számla institution_payment_id mezőjén keresztül,
     *  - ha az nincs kitöltve (a mező a gyakorlatban nem mindig van
     *    kitöltve minden számlánál), csak akkor, ha a kimutatáshoz
     *    (monthly_payment_statement_id) PONTOSAN EGY teljesült befizetés
     *    tartozik.
     * Minden más esetben (nincs egyértelmű találat, vagy több teljesült
     * befizetés is van ugyanahhoz a kimutatáshoz) a befizetést szándékosan
     * NEM módosítjuk automatikusan - ilyenkor az intézmény
     * adminisztrátorának kézzel, a Befizetések felületen kell rendeznie,
     * hogy elkerüljük egy rossz befizetés véletlen "törlését".
     */
    public function reverseLinkedPaymentAfterCancellation(InstitutionInvoice $invoice): void
    {
        $payment = $invoice->institution_payment_id
            ? InstitutionPayment::query()
                ->where('id', $invoice->institution_payment_id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->first()
            : null;

        if (! $payment && $invoice->monthly_payment_statement_id) {
            $completedPayments = InstitutionPayment::query()
                ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->get();

            if ($completedPayments->count() === 1) {
                $payment = $completedPayments->first();
            }
        }

        if (! $payment) {
            return;
        }

        $payment->status = InstitutionPayment::STATUS_CANCELLED;
        $payment->note = trim(($payment->note ? $payment->note."\n" : '').sprintf(
            'Automatikusan törölve a(z) %s számla sztornózása miatt (%s).',
            $invoice->invoice_number ?: '#'.$invoice->id,
            now()->format('Y.m.d. H:i')
        ));
        $payment->save();
    }

    private function throwValidation(array $errors): void
    {
        throw new HttpResponseException(
            back()
                ->withInput()
                ->withErrors($errors)
        );
    }

    private function materializeLegacyCancellationInvoice(
        InstitutionInvoice $invoice,
        bool $persist = true
    ): ?InstitutionInvoice {
        if (! $invoice->cancellation_provider_document_id || ! $invoice->cancellation_invoice_number) {
            return null;
        }

        if (! $persist) {
            $legacy = $invoice->replicate([
                'provider_invoice_id',
                'invoice_number',
                'invoice_pdf_path',
                'status',
                'document_type',
            ]);
            $legacy->provider_invoice_id = $invoice->cancellation_provider_document_id;
            $legacy->invoice_number = $invoice->cancellation_invoice_number;
            $legacy->invoice_pdf_path = $invoice->cancellation_pdf_path;
            $legacy->document_type = InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION;

            return $legacy;
        }

        return DB::transaction(function () use ($invoice) {
            $existing = InstitutionInvoice::query()
                ->where('institution_id', $invoice->institution_id)
                ->where('provider', $invoice->provider)
                ->where('provider_invoice_id', $invoice->cancellation_provider_document_id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $created = new InstitutionInvoice;
            $created->fill([
                'institution_id' => $invoice->institution_id,
                'child_id' => $invoice->child_id,
                'guardian_id' => $invoice->guardian_id,
                'monthly_payment_statement_id' => $invoice->monthly_payment_statement_id,
                'original_invoice_id' => $invoice->id,
                'institution_payment_id' => null,
                'provider' => $invoice->provider,
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                'provider_invoice_id' => $invoice->cancellation_provider_document_id,
                'provider_original_invoice_id' => $invoice->provider_invoice_id,
                'invoice_number' => $invoice->cancellation_invoice_number,
                'status' => InstitutionInvoice::STATUS_ISSUED,
                'issue_date' => $invoice->cancelled_at?->toDateString() ?: $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'fulfillment_date' => $invoice->fulfillment_date?->toDateString(),
                // Lásd a fenti megjegyzést a cancel() metódusban: a
                // (legacy, utólag pótolt) sztornó-rekord összegeit is
                // negatívan kell rögzíteni, hogy az összesítésekben
                // nullázza az eredeti számlát.
                'net_amount' => -$invoice->net_amount,
                'vat_amount' => -$invoice->vat_amount,
                'gross_amount' => -$invoice->gross_amount,
                'currency' => $invoice->currency,
                'payment_method' => $invoice->payment_method,
                'customer_name' => $invoice->customer_name,
                'customer_email' => $invoice->customer_email,
                'customer_tax_number' => $invoice->customer_tax_number,
                'billing_postcode' => $invoice->billing_postcode,
                'billing_city' => $invoice->billing_city,
                'billing_address' => $invoice->billing_address,
                'invoice_pdf_path' => $invoice->cancellation_pdf_path,
                'pdf_disk' => $invoice->cancellation_pdf_path ? 'local' : null,
                'pdf_downloaded_at' => $invoice->cancellation_pdf_path ? now() : null,
                'note' => $invoice->cancellation_reason,
            ]);
            $created->created_by = $invoice->cancelled_by ?: $invoice->created_by;
            $created->save();

            return $created;
        });
    }
}
