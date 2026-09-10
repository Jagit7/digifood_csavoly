<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionAdminInvitation;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\Billing\DigifoodMonthlyFeeOverviewService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SuperAdminOverviewController extends Controller
{
    private const INSTITUTION_TYPE_LABELS = [
        'ovoda' => 'Óvoda',
        'iskola' => 'Iskola',
        'bolcsode' => 'Bölcsőde',
    ];

    public function __construct(private readonly DigifoodMonthlyFeeOverviewService $digifoodFeeService) {}

    public function index(): View
    {
        $now = now(config('digifood.business_timezone', config('app.timezone')));
        $today = $now->toDateString();

        $institutionStats = $this->buildInstitutionStats();
        $accessStats = $this->buildAccessStats();
        $usageStats = $this->buildUsageStats($today);
        $digifoodFee = $this->digifoodFeeService->calculate($today);
        $recentInstitutions = $this->loadRecentInstitutions();
        $recentInvitations = $this->loadRecentInvitations();
        $recentEvents = $this->buildRecentEvents($recentInstitutions, $recentInvitations);
        $chart = $this->buildInstitutionGrowthChart($now);
        $systemStatus = $this->buildSystemStatus($now);

        $quickActions = collect([
            [
                'label' => 'Új intézmény',
                'icon' => 'fa-solid fa-plus',
                'url' => Route::has('dashboard.institutions.create') ? route('dashboard.institutions.create') : null,
                'class' => 'btn btn-primary',
            ],
            [
                'label' => 'Intézmények kezelése',
                'icon' => 'fa-solid fa-building',
                'url' => Route::has('dashboard.institutions.index') ? route('dashboard.institutions.index') : null,
                'class' => 'btn btn-outline-primary',
            ],
            [
                'label' => 'Admin meghívása',
                'icon' => 'fa-solid fa-user-plus',
                'url' => Route::has('dashboard.admin-access.invite.create') ? route('dashboard.admin-access.invite.create') : null,
                'class' => 'btn btn-outline-primary',
            ],
            [
                'label' => 'Adminisztrátorok kezelése',
                'icon' => 'fa-solid fa-users-gear',
                'url' => Route::has('dashboard.admin-access.index') ? route('dashboard.admin-access.index') : null,
                'class' => 'btn btn-outline-primary',
            ],
        ])->filter(fn (array $action) => filled($action['url']))->values();

        $statCards = [
            [
                'title' => 'Intézmények összesen',
                'value' => $institutionStats['total'],
                'subtitle' => 'Regisztrált intézmény a rendszerben',
                'icon' => 'fa-solid fa-building',
                'color' => 'blue',
            ],
            [
                'title' => 'Aktív intézmények',
                'value' => $institutionStats['active'],
                'subtitle' => 'Jelenleg használható intézmények',
                'icon' => 'fa-solid fa-circle-check',
                'color' => 'green',
            ],
            [
                'title' => 'Intézményi adminok',
                'value' => $accessStats['institution_admins'],
                'subtitle' => 'Intézményi admin szerepkörrel',
                'icon' => 'fa-solid fa-user-shield',
                'color' => 'purple',
            ],
            [
                'title' => 'Gyermekek',
                'value' => $usageStats['children_total'],
                'subtitle' => $digifoodFee['month_label'].' · SaaS '.($digifoodFee['is_snapshot'] ? 'mentett összesítő' : 'előnézet').': '
                    .number_format($digifoodFee['total'], 2, ',', ' ').' Ft'
                    .($digifoodFee['missing_rate_count'] ? ' · Hiányzó díjszabás: '.$digifoodFee['missing_rate_count'] : ''),
                'icon' => 'fa-solid fa-children',
                'color' => 'orange',
            ],
        ];

        $secondaryStats = [
            [
                'label' => 'Szülői fiókok',
                'value' => $usageStats['parent_accounts'],
                'help' => 'Belépéssel rendelkező szülő felhasználók',
                'icon' => 'fas fa-users',
            ],
            [
                'label' => 'Aktív étkezők',
                'value' => $usageStats['active_meal_participants'],
                'help' => 'Jelenleg érvényes étkezési beállítással',
                'icon' => 'fa-solid fa-utensils',
            ],
            [
                'label' => 'Aktív gyermekek',
                'value' => $usageStats['children_active'],
                'help' => 'Aktív státuszú gyermek rekordok',
                'icon' => 'fas fa-child',
            ],
            [
                'label' => 'Függő meghívások',
                'value' => $accessStats['pending_invitations'],
                'help' => 'Még el nem fogadott admin meghívók',
                'icon' => 'fa-solid fa-envelope-open-text',
            ],
        ];

        return view('dashboard.superadmin.index', [
            'pageDate' => $now,
            'pageTitle' => 'SuperAdmin áttekintés',
            'pageSubtitle' => 'A DigiFood rendszer legfontosabb adatai és aktuális állapota.',
            'statCards' => $statCards,
            'secondaryStats' => $secondaryStats,
            'institutionStats' => $institutionStats,
            'accessStats' => $accessStats,
            'usageStats' => $usageStats,
            'digifoodFee' => $digifoodFee,
            'recentInstitutions' => $recentInstitutions,
            'recentInvitations' => $recentInvitations,
            'recentEvents' => $recentEvents,
            'institutionChart' => $chart,
            'systemStatus' => $systemStatus,
            'quickActions' => $quickActions,
            'institutionsIndexUrl' => Route::has('dashboard.institutions.index') ? route('dashboard.institutions.index') : null,
            'adminAccessIndexUrl' => Route::has('dashboard.admin-access.index') ? route('dashboard.admin-access.index') : null,
            'adminInviteUrl' => Route::has('dashboard.admin-access.invite.create') ? route('dashboard.admin-access.invite.create') : null,
        ]);
    }

    private function buildInstitutionStats(): array
    {
        if (! Schema::hasTable('institutions')) {
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'recent_last_30_days' => 0,
                'type_breakdown' => collect(),
            ];
        }

        $total = Institution::query()->count();
        $active = Institution::query()->where('active', true)->count();
        $recentLast30Days = Institution::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $typeBreakdown = Institution::query()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('type')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'key' => $row->type,
                    'label' => self::INSTITUTION_TYPE_LABELS[$row->type] ?? ucfirst((string) $row->type),
                    'total' => (int) $row->total,
                ];
            });

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => max($total - $active, 0),
            'recent_last_30_days' => $recentLast30Days,
            'type_breakdown' => $typeBreakdown,
        ];
    }

    private function buildAccessStats(): array
    {
        $institutionAdmins = 0;
        $activeInstitutionAdmins = 0;
        $pendingInvitations = 0;

        if (Schema::hasTable('users')) {
            $institutionAdmins = User::query()
                ->where('role', User::ROLE_INSTITUTION_ADMIN)
                ->count();

            $activeInstitutionAdmins = User::query()
                ->where('role', User::ROLE_INSTITUTION_ADMIN)
                ->where('is_active', true)
                ->count();
        }

        if (Schema::hasTable('institution_admin_invitations')) {
            $pendingInvitations = InstitutionAdminInvitation::query()
                ->whereNull('accepted_at')
                ->count();
        }

        return [
            'institution_admins' => $institutionAdmins,
            'active_institution_admins' => $activeInstitutionAdmins,
            'pending_invitations' => $pendingInvitations,
        ];
    }

    private function buildUsageStats(string $today): array
    {
        $childrenTotal = 0;
        $childrenActive = 0;
        $parentAccounts = 0;
        $activeMealParticipants = 0;

        if (Schema::hasTable('children')) {
            $childrenTotal = Child::query()->count();
            $childrenActive = Child::query()->where('active', true)->count();
        }

        if (Schema::hasTable('users')) {
            $parentAccounts = User::query()
                ->where('role', User::ROLE_PARENT)
                ->count();
        }

        if (Schema::hasTable('student_meal_settings') && Schema::hasTable('children')) {
            $activeMealParticipants = StudentMealSetting::query()
                ->join('children', 'children.id', '=', 'student_meal_settings.student_id')
                ->whereDate('student_meal_settings.valid_from', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('student_meal_settings.valid_to')
                        ->orWhereDate('student_meal_settings.valid_to', '>=', $today);
                })
                ->where('children.active', true)
                ->distinct('student_meal_settings.student_id')
                ->count('student_meal_settings.student_id');
        }

        return [
            'children_total' => $childrenTotal,
            'children_active' => $childrenActive,
            'parent_accounts' => $parentAccounts,
            'active_meal_participants' => $activeMealParticipants,
        ];
    }

    private function loadRecentInstitutions(): Collection
    {
        if (! Schema::hasTable('institutions')) {
            return collect();
        }

        return Institution::query()
            ->withCount([
                'users as institution_admin_count' => fn ($query) => $query->where('role', User::ROLE_INSTITUTION_ADMIN),
            ])
            ->with([
                'users' => fn ($query) => $query
                    ->select('users.id', 'users.name')
                    ->where('role', User::ROLE_INSTITUTION_ADMIN)
                    ->orderBy('name'),
            ])
            ->latest()
            ->limit(6)
            ->get();
    }

    private function loadRecentInvitations(): Collection
    {
        if (! Schema::hasTable('institution_admin_invitations')) {
            return collect();
        }

        return InstitutionAdminInvitation::query()
            ->with('institution:id,name')
            ->whereNull('accepted_at')
            ->latest()
            ->limit(5)
            ->get();
    }

    private function buildRecentEvents(Collection $recentInstitutions, Collection $recentInvitations): Collection
    {
        $institutionEvents = $recentInstitutions->map(function (Institution $institution) {
            return [
                'type' => 'institution',
                'title' => 'Új intézmény létrehozva',
                'description' => $institution->name,
                'meta' => $institution->address_city ?: ($institution->institution_code ?: 'Intézménykód nélkül'),
                'date' => $institution->created_at,
                'icon' => 'fa-solid fa-building',
                'badge' => 'Intézmény',
                'badge_class' => 'bg-primary-subtle text-primary',
            ];
        });

        $invitationEvents = $recentInvitations->map(function (InstitutionAdminInvitation $invitation) {
            return [
                'type' => 'invitation',
                'title' => 'Admin meghívó létrehozva',
                'description' => $invitation->name.' · '.$invitation->email,
                'meta' => $invitation->institution?->name ?: 'Intézmény nélkül',
                'date' => $invitation->created_at,
                'icon' => 'fa-solid fa-paper-plane',
                'badge' => 'Meghívó',
                'badge_class' => 'bg-warning-subtle text-warning',
            ];
        });

        return $institutionEvents
            ->concat($invitationEvents)
            ->sortByDesc(fn (array $event) => $event['date']?->timestamp ?? 0)
            ->take(8)
            ->values();
    }

    private function buildInstitutionGrowthChart(Carbon $now): array
    {
        $months = collect(range(5, 1))->reverse()->map(function (int $offset) use ($now) {
            $month = $now->copy()->startOfMonth()->subMonths($offset);

            return [
                'key' => $month->format('Y-m'),
                'label' => $this->formatHungarianMonth($month),
                'start' => $month->copy()->startOfMonth(),
                'end' => $month->copy()->endOfMonth(),
            ];
        });

        $months = $months->push([
            'key' => $now->format('Y-m'),
            'label' => $this->formatHungarianMonth($now),
            'start' => $now->copy()->startOfMonth(),
            'end' => $now->copy()->endOfMonth(),
        ])->values();

        $counts = collect();

        if (Schema::hasTable('institutions')) {
            $rawCounts = Institution::query()
                ->select('created_at')
                ->where('created_at', '>=', $months->first()['start'])
                ->where('created_at', '<=', $months->last()['end'])
                ->get()
                ->groupBy(fn (Institution $institution) => $institution->created_at?->format('Y-m'))
                ->map(fn (Collection $items) => $items->count());

            $counts = $months->map(fn (array $month) => (int) ($rawCounts[$month['key']] ?? 0));
        } else {
            $counts = $months->map(fn () => 0);
        }

        return [
            'categories' => $months->pluck('label')->all(),
            'series' => $counts->all(),
            'has_data' => $counts->sum() > 0,
        ];
    }

    private function buildSystemStatus(Carbon $now): array
    {
        $databaseStatus = [
            'label' => 'Adatbázis kapcsolat',
            'value' => 'Ismeretlen',
            'status' => 'secondary',
        ];

        try {
            DB::connection()->getPdo();

            $databaseStatus = [
                'label' => 'Adatbázis kapcsolat',
                'value' => 'Rendben',
                'status' => 'success',
            ];
        } catch (Throwable) {
            $databaseStatus = [
                'label' => 'Adatbázis kapcsolat',
                'value' => 'Nem elérhető',
                'status' => 'danger',
            ];
        }

        return [
            $databaseStatus,
            [
                'label' => 'Környezet',
                'value' => app()->environment(),
                'status' => 'primary',
            ],
            [
                'label' => 'Laravel verzió',
                'value' => app()->version(),
                'status' => 'info',
            ],
            [
                'label' => 'PHP verzió',
                'value' => PHP_VERSION,
                'status' => 'info',
            ],
            [
                'label' => 'Időzóna',
                'value' => config('app.timezone'),
                'status' => 'secondary',
            ],
            [
                'label' => 'Adatok frissítve',
                'value' => $now->format('Y. m. d. H:i'),
                'status' => 'secondary',
            ],
        ];
    }

    private function formatHungarianMonth(Carbon $date): string
    {
        $labels = [
            1 => 'január',
            2 => 'február',
            3 => 'március',
            4 => 'április',
            5 => 'május',
            6 => 'június',
            7 => 'július',
            8 => 'augusztus',
            9 => 'szeptember',
            10 => 'október',
            11 => 'november',
            12 => 'december',
        ];

        return $labels[(int) $date->format('n')].' '.$date->format('Y');
    }
}
