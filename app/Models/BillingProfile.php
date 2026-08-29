<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BillingProfile extends Model
{
    // Ld. ParentController@edit()/update() és ChildController - közös
    // választéklisták, hogy ne kelljen máshol újra felsorolni őket.
    public const PAYER_TYPES = [
        'guardian' => 'Szülő / gondviselő',
        'employer' => 'Munkáltató',
        'organization' => 'Más szervezet',
        'municipality' => 'Önkormányzat / támogató',
    ];

    public const PAYMENT_METHODS = [
        'transfer' => 'Átutalás',
        'cash' => 'Készpénz',
        'card' => 'Bankkártya',
        'direct_debit' => 'Csoportos beszedés',
    ];

    protected $fillable = [
        'institution_id',
        'guardian_id',
        'payer_type',
        'billing_name',
        'tax_number',
        'postal_code',
        'city',
        'address',
        'email',
        'payment_method',
        'employer_reference',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'billing_profile_child')
            ->withPivot(['is_primary', 'valid_from', 'valid_to'])
            ->withTimestamps();
    }
}
