<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $actions = [
            AuditLog::ACTION_INSTITUTION_CREATED => 'Intézmény létrehozása',
            AuditLog::ACTION_INSTITUTION_UPDATED => 'Intézmény módosítása',
            AuditLog::ACTION_PARTNER_BILLING_STATUS_CHANGED => 'Partneri havi számlázási státusz módosítása',
            AuditLog::ACTION_INSTITUTION_ADMIN_INVITATION_RESENT => 'Intézményi admin meghívó újraküldése',
        ];

        $auditLogs = AuditLog::query()
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
                'institution' => fn ($query) => $query->withTrashed(),
            ])
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            })
            ->when($request->filled('institution_id'), function ($query) use ($request) {
                $query->where('institution_id', (int) $request->input('institution_id'));
            })
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', (string) $request->input('action'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->withTrashed()
                                ->where(function ($nestedQuery) use ($search) {
                                    $nestedQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.superadmin.audit_logs.index', [
            'auditLogs' => $auditLogs,
            'institutions' => $institutions,
            'actions' => $actions,
        ]);
    }
}
