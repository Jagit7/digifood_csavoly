<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentController extends Controller
{
    public function index(Request $request): View
    {
        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $stats = [
            'total' => Guardian::query()->count(),
            'active_accounts' => Guardian::query()
                ->whereHas('user', fn ($query) => $query
                    ->where('role', User::ROLE_PARENT)
                    ->where('is_active', true))
                ->count(),
            'without_account' => Guardian::query()
                ->where(function ($query) {
                    $query->whereNull('user_id')
                        ->orWhereDoesntHave('user', fn ($query) => $query->withTrashed());
                })
                ->count(),
            'multiple_children' => Guardian::query()
                ->has('children', '>', 1)
                ->count(),
        ];

        $guardians = Guardian::query()
            ->with([
                'institution:id,name',
                'user' => fn ($query) => $query->withTrashed(),
                'children' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount('children')
            ->when($request->filled('institution_id'), function ($query) use ($request) {
                $query->where('institution_id', (int) $request->integer('institution_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($request->input('account_status'), ['active', 'inactive', 'none'], true), function ($query) use ($request) {
                $status = $request->input('account_status');

                if ($status === 'active') {
                    $query->whereHas('user', fn ($query) => $query
                        ->where('role', User::ROLE_PARENT)
                        ->where('is_active', true));

                    return;
                }

                if ($status === 'inactive') {
                    $query->whereHas('user', fn ($query) => $query
                        ->withTrashed()
                        ->where('role', User::ROLE_PARENT)
                        ->where(function ($query) {
                            $query->where('is_active', false)
                                ->orWhereNotNull('deleted_at');
                        }));

                    return;
                }

                $query->where(function ($query) {
                    $query->whereNull('user_id')
                        ->orWhereDoesntHave('user', fn ($query) => $query->withTrashed());
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.superadmin.parents.index', compact(
            'guardians',
            'institutions',
            'stats'
        ));
    }

    public function show(Guardian $parent): View
    {
        $parent->load([
            'institution:id,name',
            'user' => fn ($query) => $query->withTrashed(),
            'children' => fn ($query) => $query->orderBy('name'),
        ]);

        return view('dashboard.superadmin.parents.show', [
            'guardian' => $parent,
        ]);
    }
}
