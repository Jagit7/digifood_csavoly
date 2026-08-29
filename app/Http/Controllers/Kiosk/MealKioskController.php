<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\AbMenuItem;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealType;
use App\Models\InstitutionSetting;
use App\Models\MealCheckIn;
use App\Services\Kiosk\KioskControlCardService;
use App\Services\Meals\MealKioskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MealKioskController extends Controller
{
    public function __construct(
        private readonly MealKioskService $kioskService,
        private readonly KioskControlCardService $controlCardService
    ) {}

    public function show(Request $request): View
    {
        $institution = $this->institution();
        $mealTypes = $this->activeMealTypes($institution);
        $this->ensureSessionStarted($request);
        $selectedMealType = $this->resolveActiveMealType($request, $institution, $mealTypes);

        return view('kiosk.meal-kiosk', [
            'institution' => $institution,
            'mealTypes' => $mealTypes,
            'selectedMealTypeId' => $selectedMealType?->id,
            'sessionPayload' => $this->sessionPayload($request),
        ]);
    }

    public function updateMealType(Request $request): JsonResponse
    {
        $institution = $this->institution();
        $mealType = $this->findMealType($institution, (int) $request->validate([
            'meal_type_id' => ['required', 'integer'],
        ])['meal_type_id']);

        abort_if($mealType === null, 403, 'Az étkezéstípus nem érhető el ebben a kioszkban.');

        $request->session()->put('meal_kiosk.active_meal_type_id', $mealType->id);
        $this->rememberActiveMealType($institution, $mealType->id);
        $this->ensureSessionStarted($request);

        return response()->json([
            'message' => 'Az étkezéstípus kiválasztva.',
            'session' => $this->sessionPayload($request),
        ]);
    }

    public function sessionData(Request $request): JsonResponse
    {
        $this->ensureSessionStarted($request);

        return response()->json($this->sessionPayload($request));
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode_token' => ['required', 'string', 'max:64'],
        ]);

        $institution = $this->institution();
        $token = trim($validated['barcode_token']);
        $setting = $this->institutionSetting($institution);

        // A kioszk "vezérlő kártyája" - ha ez lett beolvasva, nem
        // étkezés-ellenőrzés történik, hanem a kioszk zárolt/aktív
        // állapota vált (billentyűzet és érintés nélküli aktiválás/
        // lezárás, ld. KioskControlCardService). A fizetési/számlázási
        // logikát ez egyáltalán nem érinti.
        if ($this->controlCardService->matches($setting, $token)) {
            $this->ensureSessionStarted($request);
            $locked = ! $this->isLocked($request);
            $this->setLocked($request, $locked);

            return response()->json([
                'result' => 'control',
                'locked' => $locked,
                'message' => $locked ? 'A kioszk zárolva.' : 'A kioszk aktiválva.',
                'session' => $this->sessionPayload($request),
            ]);
        }

        if ($this->isLocked($request)) {
            return response()->json([
                'result' => 'locked',
                'locked' => true,
                'message' => 'A kioszk zárolva van. Olvasd be a vezérlő kártyát az aktiváláshoz.',
                'session' => $this->sessionPayload($request),
            ], 423);
        }

        $mealType = $this->resolveActiveMealType($request, $institution);

        if ($mealType === null) {
            return response()->json([
                'result' => 'rejected',
                'message' => 'Először válassz étkezéstípust.',
                'code' => 'missing_meal_type',
            ], 422);
        }

        $this->ensureSessionStarted($request);

        $result = $this->kioskService->scan(
            $institution,
            $request->user(),
            $mealType,
            $token
        );

        return response()->json([
            ...$result,
            'event' => $this->eventPayload($result),
            'session' => $this->sessionPayload($request),
        ]);
    }

    public function lock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_pin' => ['required', 'string', 'max:32'],
        ]);

        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $this->institution()->id],
            InstitutionSetting::defaults()
        );

        if (! filled($setting->barcode_kiosk_pin_hash) || ! Hash::check($validated['admin_pin'], $setting->barcode_kiosk_pin_hash)) {
            return redirect()
                ->route('kiosk.show')
                ->with('error', 'Hibás admin PIN-kód.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('auth.login')
            ->with('success', 'A kioszk lezárása megtörtént.');
    }

    private function sessionPayload(Request $request): array
    {
        $institution = $this->institution();
        $mealType = $this->resolveActiveMealType($request, $institution);
        $startedAt = $request->session()->get('meal_kiosk.started_at');
        $recentItems = $this->recentItems($request, (int) ($mealType?->id ?? 0));
        $abMode = $this->abModeEnabled($institution);

        return [
            'institution_name' => $institution->name,
            'selected_meal_type' => $mealType?->mealType?->name,
            'selected_meal_type_id' => $mealType?->id,
            'ab_mode' => $abMode,
            'started_at' => $startedAt,
            'latest_event_id' => $recentItems->first()?->id,
            'locked' => $this->isLocked($request),
            'recent' => $abMode
                ? $this->recentItemsForAbMode($recentItems)
                : [
                    'default' => $this->serializeRecent($recentItems->take(8)),
                ],
        ];
    }

    private function isLocked(Request $request): bool
    {
        return (bool) $request->session()->get('meal_kiosk.locked', false);
    }

    private function setLocked(Request $request, bool $locked): void
    {
        $request->session()->put('meal_kiosk.locked', $locked);
    }

    private function recentItems(Request $request, int $mealTypeId)
    {
        $startedAt = $request->session()->get('meal_kiosk.started_at');

        $items = MealCheckIn::query()
            ->with(['child', 'eater'])
            ->where('kiosk_user_id', $request->user()->id)
            ->when($mealTypeId > 0, fn ($query) => $query->where('institution_meal_type_id', $mealTypeId))
            ->when($startedAt, fn ($query) => $query->where('scanned_at', '>=', $startedAt))
            ->latest('scanned_at')
            ->limit(20)
            ->get();

        $items->loadMorph('eater', [
            Child::class => [
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ],
            InstitutionEmployee::class => [
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ],
        ]);

        return $items;
    }

    private function serializeRecent($items): array
    {
        return $items->map(function (MealCheckIn $item) {
            $eater = $this->eaterForItem($item);
            $dietary = $this->dietaryLabelsForEater($eater);
            $result = $this->eventResultFromRecord($item);
            $code = $item->status === MealCheckIn::STATUS_REJECTED ? $item->rejection_reason : null;

            return [
                'id' => $item->id,
                'child_name' => $this->displayNameForEater($eater),
                'group_name' => $this->groupNameForEater($eater),
                'eater_type' => $item->eater_type,
                'is_employee' => $eater instanceof InstitutionEmployee,
                'result' => $result,
                'code' => $code,
                'headline' => $this->eventHeadline($result, $code),
                'message' => $this->eventMessage($result, $code),
                'icon' => $this->eventIcon($result, $code),
                'status_class' => $this->eventStatusClass($result, $code),
                'menu_choice' => $item->menu_choice,
                'display_menu_choice' => $dietary !== [] ? 'DIETARY' : $item->menu_choice,
                'scanned_at' => $item->scanned_at?->timezone(config('app.timezone'))?->format('H:i:s'),
                'dietary' => $dietary,
                'is_dietary' => $dietary !== [],
            ];
        })->values()->all();
    }

    private function eventPayload(array $result): array
    {
        $child = $result['child'] ?? null;
        $eater = $result['eater'] ?? $child;
        $existing = $result['existing'] ?? null;
        $dietary = $this->dietaryLabelsForEater($eater);
        $code = $result['code'] ?? null;

        return [
            'id' => $result['record']?->id,
            'result' => $result['result'],
            'message' => $result['message'],
            'code' => $code,
            'headline' => $this->eventHeadline($result['result'], $code),
            'icon' => $this->eventIcon($result['result'], $code),
            'status_class' => $this->eventStatusClass($result['result'], $code),
            'child_name' => $this->displayNameForEater($eater),
            'group_name' => $this->groupNameForEater($eater),
            'eater_type' => $result['eater_type'] ?? $eater?->getMorphClass(),
            'is_employee' => $eater instanceof InstitutionEmployee,
            'menu_choice' => $result['menu_choice'] ?? null,
            'display_menu_choice' => $dietary !== [] ? 'DIETARY' : ($result['menu_choice'] ?? null),
            // A 'meal_type' kulcs csak sikeres/duplikált beolvasásnál létezik
            // (ld. MealKioskService::scan() elutasított ága) - enélkül a
            // null-coalesce nélkül minden elutasított beolvasás 500-as hibát
            // dobott ("Undefined array key"), és a kioszk nem frissült élőben.
            'meal_type' => ($result['meal_type'] ?? null)?->mealType?->name,
            'dietary' => $dietary,
            'is_dietary' => $dietary !== [],
            'first_scanned_at' => $existing?->scanned_at?->timezone(config('app.timezone'))?->format('H:i:s'),
            'scanned_at' => $result['record']?->scanned_at?->timezone(config('app.timezone'))?->format('H:i:s'),
        ];
    }

    private function recentItemsForAbMode($items): array
    {
        return [
            'A' => $this->serializeRecent(
                $items->filter(fn (MealCheckIn $item) => ! $this->shouldAppearInBColumn($item))->take(5)
            ),
            'B' => $this->serializeRecent(
                $items->filter(fn (MealCheckIn $item) => $this->shouldAppearInBColumn($item))->take(5)
            ),
        ];
    }

    private function hasDietaryRestrictions(MealCheckIn $item): bool
    {
        $eater = $this->eaterForItem($item);

        return ($eater?->dietaryRestrictions?->isNotEmpty()) === true;
    }

    private function shouldAppearInBColumn(MealCheckIn $item): bool
    {
        if ($this->hasDietaryRestrictions($item)) {
            return true;
        }

        return $item->menu_choice === 'B';
    }

    private function eventResultFromRecord(MealCheckIn $item): string
    {
        return match ($item->status) {
            MealCheckIn::STATUS_SUCCESS => 'success',
            MealCheckIn::STATUS_DUPLICATE => 'duplicate',
            default => 'rejected',
        };
    }

    private function eventHeadline(string $result, ?string $code): string
    {
        if ($result === 'success') {
            return 'ÉTKEZÉSRE JOGOSULT';
        }

        if ($result === 'duplicate') {
            return 'MÁR BEOLVASVA';
        }

        return match ($code) {
            'cancelled' => 'ÉTKEZÉSE LEMONDVA',
            'manual_review_required' => 'KÉZI ELLENŐRZÉS SZÜKSÉGES',
            default => 'NEM JOGOSULT ÉTKEZÉSRE',
        };
    }

    private function eventIcon(string $result, ?string $code): string
    {
        if ($result === 'success') {
            return 'fas fa-check-circle';
        }

        if ($result === 'duplicate') {
            return 'fas fa-history';
        }

        return $code === 'manual_review_required'
            ? 'fas fa-exclamation-triangle'
            : 'fas fa-times-circle';
    }

    private function eventMessage(string $result, ?string $code): string
    {
        if ($result === 'success') {
            return 'A beolvasás sikeres, a gyermek étkezésre jogosult.';
        }

        if ($result === 'duplicate') {
            return 'Ez a gyermek már be lett olvasva ebben az étkezéstípusban.';
        }

        return match ($code) {
            'cancelled' => 'Az étkezést korábban lemondták.',
            'manual_review_required' => 'Az A/B menü választása nem állapítható meg, kézi ellenőrzés szükséges.',
            'missing_meal_type' => 'Először válassz étkezéstípust.',
            default => 'A gyermek jelenleg nem jogosult erre az étkezésre.',
        };
    }

    private function eventStatusClass(string $result, ?string $code): string
    {
        if ($result === 'success') {
            return 'is-success';
        }

        if ($result === 'duplicate') {
            return 'is-duplicate';
        }

        return $code === 'manual_review_required' ? 'is-review' : 'is-rejected';
    }

    private function eaterForItem(MealCheckIn $item): Child|InstitutionEmployee|null
    {
        $eater = $item->eater;

        if ($eater instanceof Child || $eater instanceof InstitutionEmployee) {
            return $eater;
        }

        return $item->child;
    }

    private function dietaryLabelsForEater(Child|InstitutionEmployee|null $eater): array
    {
        return $eater?->dietaryRestrictions?->pluck('name')->values()->all() ?? [];
    }

    private function displayNameForEater(Child|InstitutionEmployee|null $eater): ?string
    {
        return $eater?->name;
    }

    private function groupNameForEater(Child|InstitutionEmployee|null $eater): ?string
    {
        return $eater instanceof Child ? $eater->group_name : null;
    }

    private function ensureSessionStarted(Request $request): void
    {
        if (! $request->session()->has('meal_kiosk.started_at')) {
            $request->session()->put('meal_kiosk.started_at', now()->toDateTimeString());
        }
    }

    private function resolveActiveMealType(
        Request $request,
        Institution $institution,
        $mealTypes = null
    ): ?InstitutionMealType {
        $mealTypes ??= $this->activeMealTypes($institution);

        if ($mealTypes->isEmpty()) {
            $request->session()->forget('meal_kiosk.active_meal_type_id');

            return null;
        }

        $sessionMealTypeId = (int) $request->session()->get('meal_kiosk.active_meal_type_id');
        $sessionMealType = $mealTypes->firstWhere('id', $sessionMealTypeId);

        if ($sessionMealType !== null) {
            return $sessionMealType;
        }

        $selectedMealType = $this->defaultMealType($mealTypes, $this->institutionSetting($institution));

        if ($selectedMealType !== null) {
            $request->session()->put('meal_kiosk.active_meal_type_id', $selectedMealType->id);
        }

        return $selectedMealType;
    }

    private function defaultMealType($mealTypes, InstitutionSetting $setting): ?InstitutionMealType
    {
        if ($mealTypes->count() === 1) {
            return $mealTypes->first();
        }

        $rememberedMealType = $mealTypes->firstWhere('id', (int) $setting->barcode_kiosk_meal_type_id);

        if ($rememberedMealType !== null) {
            return $rememberedMealType;
        }

        $lunchMealType = $mealTypes->first(function (InstitutionMealType $mealType) {
            return mb_strtolower(trim((string) $mealType->mealType?->name)) === 'ebéd';
        });

        return $lunchMealType ?? $mealTypes->first();
    }

    private function rememberActiveMealType(Institution $institution, int $mealTypeId): void
    {
        $setting = $this->institutionSetting($institution);

        if ((int) $setting->barcode_kiosk_meal_type_id === $mealTypeId) {
            return;
        }

        $setting->forceFill([
            'barcode_kiosk_meal_type_id' => $mealTypeId,
        ])->save();
    }

    private function institutionSetting(Institution $institution): InstitutionSetting
    {
        return InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
    }

    private function institution(): Institution
    {
        $user = auth()->user();

        return $user->institutions()->first()
            ?? $user->institution()->firstOrFail();
    }

    private function activeMealTypes(Institution $institution)
    {
        return InstitutionMealType::query()
            ->with('mealType')
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    private function findMealType(Institution $institution, int $mealTypeId): ?InstitutionMealType
    {
        return InstitutionMealType::query()
            ->with('mealType')
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->whereKey($mealTypeId)
            ->first();
    }

    private function abModeEnabled(Institution $institution): bool
    {
        $date = now()->toDateString();

        $item = AbMenuItem::query()
            ->select('ab_menu_items.*')
            ->join('ab_menu_plans', 'ab_menu_plans.id', '=', 'ab_menu_items.ab_menu_plan_id')
            ->where('ab_menu_plans.institution_id', $institution->id)
            ->where('ab_menu_plans.active', true)
            ->whereDate('ab_menu_plans.valid_from', '<=', $date)
            ->whereDate('ab_menu_plans.valid_to', '>=', $date)
            ->whereDate('ab_menu_items.menu_date', $date)
            ->orderByDesc('ab_menu_plans.published_at')
            ->orderByDesc('ab_menu_plans.id')
            ->first();

        return $item !== null && filled($item->menu_b);
    }
}
