<?php

namespace App\Models;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Support\Finance\PaymentComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstitutionPayment extends Model
{
    use SoftDeletes;

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CARD = 'card';

    public const METHOD_ONLINE = 'online';

    public const METHOD_OTHER = 'other';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'institution_id',
        'child_id',
        'guardian_id',
        'monthly_payment_statement_id',
        'payment_component',
        'invoice_number',
        'amount',
        'paid_at',
        'payment_method',
        'status',
        'reference',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public static function componentOptions(): array
    {
        return [
            PaymentComponent::FOUNDATION => PaymentComponent::transferLabels()[PaymentComponent::FOUNDATION],
            PaymentComponent::KINDERGARTEN => PaymentComponent::transferLabels()[PaymentComponent::KINDERGARTEN],
            PaymentComponent::LEGACY => PaymentComponent::labels()[PaymentComponent::LEGACY],
        ];
    }

    // Felhasználói kérés (Feladat #4): egyelőre nincs kártyás fizetés és
    // automatikus számlázás, a befizetés csak banki átutalással vagy
    // készpénzben rögzíthető - ezt az admin vezeti a táblázatban. A
    // METHOD_CARD/METHOD_ONLINE/METHOD_OTHER konstansok és a
    // paymentMethodMeta() alábbi ágai szándékosan megmaradtak (ld. a
    // korábban már rögzített, ilyen móddal mentett befizetések helyes
    // megjelenítéséhez, illetve hogy egy esetleges jövőbeli
    // visszakapcsoláshoz ne kelljen újra felépíteni) - csak a
    // KIVÁLASZTHATÓ (form/validáció) opciók köre szűkült.
    public static function paymentMethodOptions(): array
    {
        return [
            self::METHOD_CASH => 'Készpénz',
            self::METHOD_BANK_TRANSFER => 'Átutalás',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Függőben',
            self::STATUS_COMPLETED => 'Teljesült',
            self::STATUS_FAILED => 'Sikertelen',
            self::STATUS_REFUNDED => 'Visszatérítve',
            self::STATUS_CANCELLED => 'Törölve',
        ];
    }

    public static function paymentMethodMeta(?string $method): array
    {
        return match ($method) {
            self::METHOD_CASH => ['label' => 'Készpénz', 'icon' => 'fa-solid fa-money-bill-wave', 'class' => 'bg-success-subtle text-success'],
            self::METHOD_BANK_TRANSFER => ['label' => 'Átutalás', 'icon' => 'fa-solid fa-building-columns', 'class' => 'bg-primary-subtle text-primary'],
            self::METHOD_CARD => ['label' => 'Bankkártya', 'icon' => 'fa-regular fa-credit-card', 'class' => 'bg-info-subtle text-info'],
            self::METHOD_ONLINE => ['label' => 'Online fizetés', 'icon' => 'fa-solid fa-globe', 'class' => 'bg-warning-subtle text-warning'],
            self::METHOD_OTHER => ['label' => 'Egyéb', 'icon' => 'fa-solid fa-receipt', 'class' => 'bg-secondary-subtle text-secondary'],
            default => ['label' => 'Nincs megadva', 'icon' => 'fa-regular fa-circle', 'class' => 'bg-light text-muted border'],
        };
    }

    public static function statusMeta(?string $status): array
    {
        return match ($status) {
            self::STATUS_PENDING => ['label' => 'Függőben', 'class' => 'bg-warning text-dark'],
            self::STATUS_COMPLETED => ['label' => 'Teljesült', 'class' => 'bg-success'],
            self::STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger'],
            self::STATUS_REFUNDED => ['label' => 'Visszatérítve', 'class' => 'bg-info text-dark'],
            self::STATUS_CANCELLED => ['label' => 'Törölve', 'class' => 'bg-secondary'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
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

    public function invoice(): HasOne
    {
        return $this->hasOne(InstitutionInvoice::class)
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL);
    }

    public function allocations()
    {
        return $this->hasMany(InstitutionPaymentAllocation::class, 'institution_payment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
