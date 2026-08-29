<?php

namespace App\Models;

use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionEmployeePayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public const METHOD_CASH = 'cash';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_CARD = 'card';
    public const METHOD_ONLINE = 'online';
    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'institution_id',
        'institution_employee_id',
        'employee_monthly_payment_statement_id',
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

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Feldolgozás alatt',
            self::STATUS_COMPLETED => 'Teljesítve',
            self::STATUS_FAILED => 'Sikertelen',
            self::STATUS_REFUNDED => 'Visszatérítve',
        ];
    }

    public static function paymentMethodOptions(): array
    {
        return [
            self::METHOD_CASH => 'Készpénz',
            self::METHOD_TRANSFER => 'Átutalás',
            self::METHOD_CARD => 'Bankkártya',
            self::METHOD_ONLINE => 'Online',
            self::METHOD_OTHER => 'Egyéb',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(InstitutionEmployee::class, 'institution_employee_id');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(EmployeeMonthlyPaymentStatement::class, 'employee_monthly_payment_statement_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
