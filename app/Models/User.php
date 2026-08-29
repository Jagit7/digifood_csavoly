<?php

namespace App\Models;

use App\Support\AdminInstitutionContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_INSTITUTION_ADMIN = 'institution_admin';

    public const ROLE_INSTITUTION_SECRETARY = 'institution_secretary';

    public const ROLE_KITCHEN = 'kitchen';

    public const ROLE_MUNICIPALITY = 'municipality';

    public const ROLE_MEAL_KIOSK = 'meal_kiosk';

    public const ROLE_PARENT = 'parent';

    public const ROLE_EMPLOYEE = 'employee';

    /**
     * A "role", "institution_id", "permissions" és "is_active" mezők
     * SZÁNDÉKOSAN NINCSENEK a fillable listában, annak ellenére, hogy a
     * kódbázis több helyen is beállítja ezeket - jogosultsági/eszkalációs
     * mezőkről van szó, amiket csak explicit, a fejlesztő által
     * kontrollált módon szabad írni, sosem tömeges (mass assignment)
     * hozzárendeléssel. Azok a helyek, ahol ezeket ténylegesen be kell
     * állítani (pl. CreateSuperAdmin, AuthController::register(),
     * AdminInstitutionAccessController, ParentAccountActivationService),
     * ezért forceCreate()-et / forceFill()->save()-et használnak a sima
     * create()/update() helyett - ez a minta már eddig is jelen volt a
     * kódbázisban (ld. MealKioskAccessController).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'invited_at',
        'accepted_invitation_at',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'invited_at' => 'datetime',
        'accepted_invitation_at' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (empty($user->role)) {
                $user->role = self::ROLE_INSTITUTION_ADMIN;
            }
        });
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function institutions()
    {
        $relation = $this->belongsToMany(\App\Models\Institution::class, 'institution_user')
            ->withTimestamps()
            ->withPivot(['scope_role']);

        $selectedInstitutionId = app(AdminInstitutionContext::class)->selectedInstitutionIdFor($this);
        $legacyInstitutionId = $this->institution_id !== null ? (int) $this->institution_id : null;

        if ($selectedInstitutionId !== null) {
            $relation->orderByRaw(
                'CASE WHEN institutions.id = ? THEN 0 ELSE 1 END',
                [$selectedInstitutionId]
            );
        } elseif ($legacyInstitutionId !== null) {
            $relation->orderByRaw(
                'CASE WHEN institutions.id = ? THEN 0 ELSE 1 END',
                [$legacyInstitutionId]
            );
        }

        return $relation
            ->orderBy('institutions.name')
            ->orderBy('institutions.id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(InstitutionEmployee::class);
    }

    /**
     * A "gép szerinti" bejelentkezés-korlátozás eszköz-sorai (csak
     * intézményi adminoknál relevánsak, ld. AuthController::
     * verifyInstitutionAdminDevice() és InstitutionAdminDeviceController).
     * Legfeljebb InstitutionAdminDevice::MAX_DEVICES_PER_USER (jelenleg 2)
     * sora lehet egy felhasználónak.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(InstitutionAdminDevice::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->contextRole() === self::ROLE_INSTITUTION_ADMIN;
    }

    public function isKitchen(): bool
    {
        return $this->contextRole() === self::ROLE_KITCHEN;
    }

    public function isInstitutionSecretary(): bool
    {
        return $this->contextRole() === self::ROLE_INSTITUTION_SECRETARY;
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isMealKiosk(): bool
    {
        return $this->contextRole() === self::ROLE_MEAL_KIOSK;
    }

    public function isParent(): bool
    {
        return $this->contextRole() === self::ROLE_PARENT;
    }

    public function isEmployee(): bool
    {
        return $this->contextRole() === self::ROLE_EMPLOYEE;
    }

    public function contextRole(): ?string
    {
        return app(AdminInstitutionContext::class)->roleFor($this);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isInstitutionAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return ! empty($permissions[$permission]);
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Szuperadminisztrátor',
            self::ROLE_INSTITUTION_ADMIN => 'Intézményi adminisztrátor',
            self::ROLE_INSTITUTION_SECRETARY => 'Intézményi titkár',
            self::ROLE_KITCHEN => 'Konyhai felhasználó',
            self::ROLE_MUNICIPALITY => 'Önkormányzati felhasználó',
            self::ROLE_MEAL_KIOSK => 'Étkezési terminál',
            self::ROLE_PARENT => 'Szülő',
            self::ROLE_EMPLOYEE => 'Dolgozó',
            default => 'Felhasználó',
        };
    }
}
