<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionInvoiceSyncRun extends Model
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'institution_id',
        'initiated_by',
        'mode',
        'missing_pdfs_only',
        'status',
        'fetched_documents',
        'created_invoices',
        'updated_invoices',
        'downloaded_pdfs',
        'existing_pdfs',
        'unmatched_documents',
        'error_count',
        'last_error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'missing_pdfs_only' => 'boolean',
        'fetched_documents' => 'integer',
        'created_invoices' => 'integer',
        'updated_invoices' => 'integer',
        'downloaded_pdfs' => 'integer',
        'existing_pdfs' => 'integer',
        'unmatched_documents' => 'integer',
        'error_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
