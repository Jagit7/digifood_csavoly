<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    // Ld. ParentController@update (a szülő/gondviselő rokonsági kapcsolat
    // szerkesztése) és ChildController@store (új gyermek felvitelekor
    // gondviselő hozzákapcsolása) - mindkét helyen ugyanezt a listát kell
    // felkínálni, ezért itt van egyetlen közös helyen definiálva.
    public const RELATIONSHIP_TYPES = [
        'Édesapa', 'Édesanya', 'Nevelő apa', 'Nevelő anya', 'Testvér', 'Nagyszülő',
        'Gyám', 'Hivatásos nevelőszülő', 'Gyermekotthon vezetője',
        'Egyéb rokoni kapcsolat', 'Egyéb nem rokoni kapcsolat', 'Gondnok', 'Kollégiumi nevelő',
    ];

    protected $fillable = [
        'institution_id', 'user_id', 'prefix', 'last_name', 'first_name', 'email', 'phone',
        'bank_account_holder', 'bank_account_number',
        'phone_type', 'address_type', 'country', 'postal_code', 'city',
        'street_name', 'street_type', 'house_number', 'floor', 'door', 'source_type', 'active',
        'activation_email_sent_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'bank_account_holder' => 'encrypted',
        'bank_account_number' => 'encrypted',
        'activation_email_sent_at' => 'datetime',
    ];

    /**
     * Amikor egy új guardian-rekord jön létre (bármelyik forrásból - admin
     * felület, import, vagy közvetlen kód), és van hozzá e-mail cím, de még
     * nincs user_id-ja: ha ehhez az e-mail címhez már tartozik AKTÍV,
     * ROLE_PARENT felhasználói fiók (mert a szülő korábban, akár egy másik
     * intézményben, már aktiválta magát), akkor ezt a guardian-rekordot
     * automatikusan hozzákapcsoljuk ahhoz a fiókhoz.
     *
     * Ez egyetlen, központi helyen garantálja az önjavítást MINDEN
     * guardian-létrehozási úton (ChildController két helye, valamint az
     * összes import-mechanizmus), ahelyett hogy minden egyes hívási helyen
     * külön-külön kellene rá emlékezni - egy jövőbeli, új guardian-létrehozási
     * pont is automatikusan helyesen fog viselkedni.
     *
     * Biztonságos: kizárólag akkor kapcsol, ha a guardian-rekordnak
     * jelenleg NINCS user_id-ja, és ilyenkor is csak a saját (illetve a
     * vele azonos e-mailű, ugyancsak gazdátlan) rekordokat érinti - egy már
     * MÁS user_id-hoz kötött guardian-rekordot soha nem ír felül.
     */
    protected static function booted(): void
    {
        static::created(function (self $guardian): void {
            if ($guardian->user_id !== null || $guardian->email === null) {
                return;
            }

            app(\App\Services\ParentPortal\ParentAccountActivationService::class)
                ->linkOrphanedGuardiansForEmail($guardian->email);
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_guardian')->withPivot([
            'relationship_type',
            'is_legal_representative',
            'has_no_custody',
            'is_emergency_contact',
            'receives_family_allowance',
        ])->withTimestamps();
    }

    public function billingProfiles(): HasMany
    {
        return $this->hasMany(BillingProfile::class);
    }

    public function bankAccountHistories(): HasMany
    {
        return $this->hasMany(GuardianBankAccountHistory::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(InstitutionInvoice::class);
    }

    public function syncBankAccountData(
        ?string $bankAccountHolder,
        ?string $bankAccountNumber,
        ?int $changedByUserId,
        string $changeSource,
        ?string $changeReason = null
    ): void {
        $currentHolder = $this->normalizeNullableString($this->bank_account_holder);
        $currentNumber = $this->normalizeNullableString($this->bank_account_number);
        $newHolder = $this->normalizeNullableString($bankAccountHolder);
        $newNumber = $this->normalizeNullableString($bankAccountNumber);

        if ($currentHolder === $newHolder && $currentNumber === $newNumber) {
            return;
        }

        $changedAt = now();
        $openHistory = $this->bankAccountHistories()
            ->whereNull('valid_until')
            ->latest('valid_from')
            ->first();

        if ($openHistory !== null) {
            $openHistory->forceFill([
                'valid_until' => $changedAt,
                'changed_by_user_id' => $changedByUserId,
                'change_source' => $changeSource,
                'change_reason' => $changeReason,
            ])->save();
        } elseif ($currentHolder !== null || $currentNumber !== null) {
            $this->bankAccountHistories()->create([
                'bank_account_holder' => $currentHolder,
                'bank_account_number' => $currentNumber,
                'changed_by_user_id' => $changedByUserId,
                'change_source' => $changeSource,
                'change_reason' => $changeReason,
                'valid_from' => $this->updated_at ?? $this->created_at ?? $changedAt,
                'valid_until' => $changedAt,
            ]);
        }

        $this->forceFill([
            'bank_account_holder' => $newHolder,
            'bank_account_number' => $newNumber,
        ])->save();

        if ($newHolder !== null || $newNumber !== null) {
            $this->bankAccountHistories()->create([
                'bank_account_holder' => $newHolder,
                'bank_account_number' => $newNumber,
                'changed_by_user_id' => $changedByUserId,
                'change_source' => $changeSource,
                'change_reason' => $changeReason,
                'valid_from' => $changedAt,
                'valid_until' => null,
            ]);
        }
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->prefix, $this->last_name, $this->first_name])));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
