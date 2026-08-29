<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Egy intézményi admin (User::ROLE_INSTITUTION_ADMIN) felhasználóhoz
 * tartozó, "gép szerinti" bejelentkezés-korlátozás egy eszköz-sora.
 *
 * A valódi fizikai gépet egy weboldal nem tudja azonosítani (a böngészők
 * ezt biztonsági okból nem adják ki), ezért ehelyett egy tartós,
 * HttpOnly sütiben tárolt, véletlenszerű tokenhez kötjük a bejelentkezést
 * - ez a gyakorlatban egy adott böngésző-példányt jelent ugyanazon a gépen.
 *
 * Felhasználónként legfeljebb MAX_DEVICES_PER_USER (jelenleg 2) sor
 * tartozhat, azaz legfeljebb ennyi egymástól független jóváhagyott/
 * függőben lévő eszköz lehet érvényes egyszerre. Egy új, ismeretlen
 * eszközről érkező bejelentkezési kísérlet nem írja felül automatikusan
 * egy már jóváhagyott eszközt, hanem - ha van még szabad hely - egy külön
 * sorban, "pending" (függőben lévő) kérelemként kerül eltárolásra, amíg a
 * superadmin jóvá nem hagyja (ld. approve()) - így egy ellopott jelszóval
 * sem lehet kizárni a jogos admint a saját, már jóváhagyott gépéről(ei)ről.
 * Ha a felhasználónak már MAX_DEVICES_PER_USER db foglalt (jóváhagyott
 * vagy függőben lévő) sora van, egy újabb, ismeretlen eszközről érkező
 * próbálkozás egyszerűen elutasításra kerül, amíg a superadmin fel nem
 * szabadít egy helyet (ld. AuthController::verifyInstitutionAdminDevice()).
 */
class InstitutionAdminDevice extends Model
{
    /**
     * Hány jóváhagyott/függőben lévő eszköz-sor tartozhat egyszerre egy
     * intézményi admin felhasználóhoz.
     */
    public const MAX_DEVICES_PER_USER = 2;

    protected $fillable = [
        'user_id',
        'approved_token_hash',
        'approved_ip',
        'approved_user_agent',
        'approved_at',
        'approved_by',
        'last_used_at',
        'pending_token_hash',
        'pending_ip',
        'pending_user_agent',
        'pending_requested_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'last_used_at' => 'datetime',
        'pending_requested_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function hasPendingRequest(): bool
    {
        return filled($this->pending_token_hash);
    }

    public function hasApprovedDevice(): bool
    {
        return filled($this->approved_token_hash);
    }

    /**
     * Igaz, ha ez a sor jelenleg sem jóváhagyott eszközt, sem függőben lévő
     * kérelmet nem tárol - azaz egy új eszköz-kérelem nyugodtan felhasználhatja
     * ezt a sort ahelyett, hogy egy vadonatúj sort kellene létrehozni.
     */
    public function isFreeSlot(): bool
    {
        return ! $this->hasApprovedDevice() && ! $this->hasPendingRequest();
    }

    /**
     * A függőben lévő eszközt jóváhagyottá teszi - ez a sor mostantól egy
     * érvényes, jóváhagyott eszközt képvisel. A felhasználó esetleges MÁSIK
     * sorát (a másik eszköz-helyét) ez nem érinti - legfeljebb
     * MAX_DEVICES_PER_USER db jóváhagyott eszköz lehet egyszerre érvényes.
     */
    public function approvePendingRequest(User $approver): void
    {
        $this->forceFill([
            'approved_token_hash' => $this->pending_token_hash,
            'approved_ip' => $this->pending_ip,
            'approved_user_agent' => $this->pending_user_agent,
            'approved_at' => now(),
            'approved_by' => $approver->id,
            'last_used_at' => now(),
            'pending_token_hash' => null,
            'pending_ip' => null,
            'pending_user_agent' => null,
            'pending_requested_at' => null,
        ])->save();
    }

    /**
     * A függőben lévő kérelmet elutasítja/törli, a jóváhagyott eszközt
     * (ha van) nem érinti.
     */
    public function rejectPendingRequest(): void
    {
        $this->forceFill([
            'pending_token_hash' => null,
            'pending_ip' => null,
            'pending_user_agent' => null,
            'pending_requested_at' => null,
        ])->save();
    }

    /**
     * A jelenleg jóváhagyott eszközt visszavonja (pl. elveszett/lecserélt
     * gép esetén) - a felhasználó ettől a gépről a következő bejelentkezésnél
     * új jóváhagyási kérelmet fog indítani (vagy, ha van másik jóváhagyott
     * eszköze is, azzal továbbra is be tud lépni).
     */
    public function revokeApprovedDevice(): void
    {
        $this->forceFill([
            'approved_token_hash' => null,
            'approved_ip' => null,
            'approved_user_agent' => null,
            'approved_at' => null,
            'approved_by' => null,
            'last_used_at' => null,
        ])->save();
    }
}
