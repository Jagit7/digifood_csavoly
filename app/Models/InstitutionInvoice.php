<?php

namespace App\Models;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstitutionInvoice extends Model
{
    public const DOCUMENT_TYPE_ORIGINAL = 'invoice';

    public const DOCUMENT_TYPE_CANCELLATION = 'cancellation';

    public const PROVIDER_BILLINGO = 'billingo';

    public const PROVIDER_SZAMLAZZ_HU = 'szamlazz_hu';

    public const PROVIDER_MANUAL = 'manual';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'institution_id',
        'child_id',
        'guardian_id',
        'monthly_payment_statement_id',
        'original_invoice_id',
        'institution_payment_id',
        'provider',
        'document_type',
        'provider_invoice_id',
        'provider_original_invoice_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'fulfillment_date',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'currency',
        'payment_method',
        'customer_name',
        'customer_email',
        'customer_tax_number',
        'billing_postcode',
        'billing_city',
        'billing_address',
        'invoice_pdf_path',
        'pdf_disk',
        'pdf_downloaded_at',
        'pdf_size',
        'last_synced_at',
        'sync_error_message',
        'invoice_url',
        'error_message',
        'note',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cancellation_provider_document_id',
        'cancellation_invoice_number',
        'cancellation_pdf_path',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'fulfillment_date' => 'date',
        'net_amount' => 'integer',
        'vat_amount' => 'integer',
        'gross_amount' => 'integer',
        'cancelled_at' => 'datetime',
        'pdf_downloaded_at' => 'datetime',
        'pdf_size' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Sztornózható-e a számla jelenlegi státusza szerint - csak ténylegesen
     * kiállított (a szolgáltatónál is létező) bizonylat sztornózható. A
     * "paid"/"overdue" csak számított (effective_status), az adatbázisban
     * tárolt "status" mező ezekben az esetekben is "issued" marad, ld.
     * App\Services\Finance\InstitutionInvoiceService::effectiveStatusForInvoice()
     * és ::cancel().
     */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    /**
     * Törölhető-e a helyi számla-rekord. Csak "failed" státuszban - ott a
     * szolgáltatónál (Billingónál/Számlázz.hu-nál) sosem jött létre valódi
     * bizonylat, csak maga a próbálkozás bukott el (pl. hibás
     * konfiguráció miatt), ezért nincs mit sztornózni. A
     * monthly_payment_statement_id oszlopon lévő DB-szintű unique
     * megkötés miatt egy sikertelen rekord törlés nélkül örökre blokkolná
     * az adott fizetési kötelezettséghez tartozó újbóli számlázási
     * próbálkozást.
     */
    public function isDeletable(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public static function providerOptions(): array
    {
        return [
            self::PROVIDER_BILLINGO => 'Billingo',
            self::PROVIDER_SZAMLAZZ_HU => 'Számlázz.hu',
            self::PROVIDER_MANUAL => 'Kézi',
        ];
    }

    public static function documentTypeOptions(): array
    {
        return [
            self::DOCUMENT_TYPE_ORIGINAL => 'Eredeti számla',
            self::DOCUMENT_TYPE_CANCELLATION => 'Sztornószámla',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Piszkozat',
            self::STATUS_PENDING => 'Folyamatban',
            self::STATUS_ISSUED => 'Kiállítva',
            self::STATUS_PAID => 'Kifizetve',
            self::STATUS_OVERDUE => 'Lejárt',
            self::STATUS_CANCELLED => 'Törölve',
            self::STATUS_VOIDED => 'Sztornózva',
            self::STATUS_FAILED => 'Sikertelen',
        ];
    }

    public static function statusMeta(string $status): array
    {
        return match ($status) {
            self::STATUS_DRAFT => ['label' => 'Piszkozat', 'class' => 'bg-secondary text-white'],
            self::STATUS_PENDING => ['label' => 'Folyamatban', 'class' => 'bg-warning text-dark'],
            self::STATUS_ISSUED => ['label' => 'Kiállítva', 'class' => 'bg-primary text-white'],
            self::STATUS_PAID => ['label' => 'Kifizetve', 'class' => 'bg-success text-white'],
            self::STATUS_OVERDUE => ['label' => 'Lejárt', 'class' => 'bg-danger text-white'],
            self::STATUS_CANCELLED => ['label' => 'Törölve', 'class' => 'bg-secondary text-white'],
            self::STATUS_VOIDED => ['label' => 'Sztornózva', 'class' => 'bg-dark text-white'],
            self::STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger text-white'],
            default => ['label' => $status, 'class' => 'bg-light text-dark border'],
        };
    }

    public static function providerMeta(string $provider): array
    {
        return match ($provider) {
            self::PROVIDER_BILLINGO => ['label' => 'Billingo', 'class' => 'bg-success-subtle text-success border'],
            self::PROVIDER_SZAMLAZZ_HU => ['label' => 'Számlázz.hu', 'class' => 'bg-info-subtle text-info border'],
            self::PROVIDER_MANUAL => ['label' => 'Kézi', 'class' => 'bg-secondary-subtle text-secondary border'],
            default => ['label' => $provider, 'class' => 'bg-light text-dark border'],
        };
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function monthlyPaymentStatement(): BelongsTo
    {
        return $this->belongsTo(MonthlyPaymentStatement::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    public function cancellationInvoice(): HasOne
    {
        return $this->hasOne(self::class, 'original_invoice_id');
    }

    public function institutionPayment(): BelongsTo
    {
        return $this->belongsTo(InstitutionPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isOriginalDocument(): bool
    {
        return $this->document_type === self::DOCUMENT_TYPE_ORIGINAL;
    }

    public function isCancellationDocument(): bool
    {
        return $this->document_type === self::DOCUMENT_TYPE_CANCELLATION;
    }
}
