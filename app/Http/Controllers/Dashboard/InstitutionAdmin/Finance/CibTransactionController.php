<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\Finance;

use App\Http\Controllers\Controller;
use App\Models\CibTransaction;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CibTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $institution = $this->institution();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:32'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = CibTransaction::query()
            ->with(['parentPayment.items.child', 'guardian', 'user'])
            ->where('institution_id', $institution->id)
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function (Builder $query) use ($search) {
                    $query->where('trid', 'like', "%{$search}%")
                        ->orWhere('anum', 'like', "%{$search}%")
                        ->orWhere('order_ref', 'like', "%{$search}%")
                        ->orWhereHas('guardian', function (Builder $guardianQuery) use ($search) {
                            $guardianQuery->where('last_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('parentPayment.items.child', fn (Builder $childQuery) => $childQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $transactions = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'count' => (clone $query)->count(),
            'amount_total' => (clone $query)->sum('amount'),
            'successful_count' => (clone $query)->where('status', CibTransaction::STATUS_SUCCESSFUL)->count(),
            'pending_count' => (clone $query)->where('status', CibTransaction::STATUS_PENDING)->count(),
        ];

        return view('dashboard.institution_admin.finance.cib-transactions.index', [
            'institution' => $institution,
            'transactions' => $transactions,
            'summary' => $summary,
            'statuses' => [
                CibTransaction::STATUS_PENDING => 'Függőben',
                CibTransaction::STATUS_SUCCESSFUL => 'Sikeres',
                CibTransaction::STATUS_FAILED => 'Sikertelen',
                CibTransaction::STATUS_CANCELLED => 'Megszakított',
                CibTransaction::STATUS_UNCERTAIN => 'Bizonytalan',
            ],
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }
}
