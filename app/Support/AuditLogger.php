<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(
        string $action,
        string $description,
        ?Model $subject = null,
        ?int $institutionId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'institution_id' => $institutionId,
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'old_values' => self::normalizeValues($oldValues),
                'new_values' => self::normalizeValues($newValues),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private static function normalizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $filtered = array_filter($values, static fn ($value) => $value !== null && $value !== '');

        return $filtered === [] ? null : $filtered;
    }
}
