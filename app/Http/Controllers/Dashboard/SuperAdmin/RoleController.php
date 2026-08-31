<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roleDefinitions = collect($this->roleDefinitions());
        $roles = $roleDefinitions->pluck('key')->all();

        $userStats = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_total')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_total')
            ->whereIn('role', $roles)
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        $sampleUsers = User::query()
            ->with(['institution:id,name', 'institutions:id,name'])
            ->whereIn('role', $roles)
            ->orderBy('name')
            ->orderBy('email')
            ->get()
            ->groupBy('role')
            ->map(fn (Collection $users) => [
                'total' => $users->count(),
                'items' => $users->take(10),
            ]);

        $roleCards = $roleDefinitions
            ->filter(fn (array $definition) => isset($userStats[$definition['key']]) || $definition['key'] === User::ROLE_MEAL_KIOSK)
            ->map(function (array $definition) use ($userStats, $sampleUsers) {
                $stats = $userStats[$definition['key']] ?? null;
                $users = $sampleUsers[$definition['key']] ?? ['total' => 0, 'items' => collect()];

                return [
                    ...$definition,
                    'total' => (int) ($stats->total ?? 0),
                    'active_total' => (int) ($stats->active_total ?? 0),
                    'inactive_total' => (int) ($stats->inactive_total ?? 0),
                    'users_total' => (int) $users['total'],
                    'users' => $users['items'] instanceof Collection ? $users['items'] : collect(),
                ];
            })
            ->values();

        $stats = [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('is_active', true)->count(),
            'institution_roles' => User::query()
                ->whereIn('role', [
                    User::ROLE_INSTITUTION_ADMIN,
                    User::ROLE_INSTITUTION_SECRETARY,
                    User::ROLE_KITCHEN,
                    User::ROLE_MUNICIPALITY,
                    User::ROLE_MEAL_KIOSK,
                ])
                ->count(),
            'parent_accounts' => User::query()->where('role', User::ROLE_PARENT)->count(),
        ];

        return view('dashboard.superadmin.roles.index', [
            'stats' => $stats,
            'roleCards' => $roleCards,
        ]);
    }

    private function roleDefinitions(): array
    {
        return [
            [
                'key' => User::ROLE_SUPER_ADMIN,
                'label' => 'Superadmin',
                'description' => 'Globális rendszerfelügyelet a superadmin route-csoporton keresztül.',
                'institution_bound' => false,
                'access_level' => 'Globális',
                'areas' => ['Intézmények', 'Felhasználók'],
            ],
            [
                'key' => User::ROLE_INSTITUTION_ADMIN,
                'label' => 'Intézményi adminisztrátor',
                'description' => 'Teljes intézményi operatív és pénzügyi hozzáférés a dashboard intézményi felületén.',
                'institution_bound' => true,
                'access_level' => 'Intézményi',
                'areas' => ['Napi létszám', 'Lemondások', 'Étlapok', 'Felhasználók', 'Pénzügyek', 'Riportok', 'Intézmény'],
            ],
            [
                'key' => User::ROLE_INSTITUTION_SECRETARY,
                'label' => 'Intézményi titkár',
                'description' => 'Intézményi operatív hozzáférés pénzügyi jogosultságok nélkül az intézményi dashboardon.',
                'institution_bound' => true,
                'access_level' => 'Intézményi',
                'areas' => ['Napi létszám', 'Lemondások', 'Gyermekek', 'Szülők', 'Riportok', 'Intézmény'],
            ],
            [
                'key' => User::ROLE_KITCHEN,
                'label' => 'Konyhai felhasználó',
                'description' => 'Napi működési és menükezelési hozzáférés az intézményi felületen.',
                'institution_bound' => true,
                'access_level' => 'Intézményi',
                'areas' => ['Napi létszám', 'Étlapok'],
            ],
            [
                'key' => User::ROLE_MUNICIPALITY,
                'label' => 'Önkormányzati felhasználó',
                'description' => 'Intézményi szintű napi és pénzügyi betekintés korlátozottabb műveleti jogokkal.',
                'institution_bound' => true,
                'access_level' => 'Intézményi',
                'areas' => ['Napi létszám', 'Diétás étkezők', 'Pénzügyek', 'Riportok'],
            ],
            [
                'key' => User::ROLE_PARENT,
                'label' => 'Szülő / gondviselő',
                'description' => 'Saját gyermekekhez és a kapcsolódó szülői felület adataihoz fér hozzá.',
                'institution_bound' => true,
                'access_level' => 'Saját adatok',
                'areas' => ['Saját gyermekek', 'Lemondások', 'Befizetések', 'Számlák', 'Fiókom'],
            ],
            [
                'key' => User::ROLE_MEAL_KIOSK,
                'label' => 'Étkezési kioszk',
                'description' => 'Vonalkódos beléptetésre és étkezési szkennelésre szolgáló kioszk hozzáférés.',
                'institution_bound' => true,
                'access_level' => 'Intézményi',
                'areas' => ['Kioszk'],
            ],
        ];
    }
}
