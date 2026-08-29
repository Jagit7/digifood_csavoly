<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\Children\ChildBarcodeSelectionRequest;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\StudentMealSetting;
use App\Services\Children\ChildBarcodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChildBarcodeController extends Controller
{
    public function __construct(
        private readonly ChildBarcodeService $barcodeService
    ) {}

    public function index(Request $request): View
    {
        $institution = $this->institution();
        $today = $this->today();

        $groups = $this->activeParticipantQuery($institution, $today)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        $children = $this->activeParticipantQuery($institution, $today)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('educational_identifier', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('group_name'), function ($query) use ($request) {
                $query->where('group_name', $request->string('group_name')->toString());
            })
            ->when(in_array($request->input('barcode_status'), ['missing', 'active', 'disabled'], true), function ($query) use ($request) {
                match ($request->input('barcode_status')) {
                    'missing' => $query->whereNull('barcode_token'),
                    'active' => $query->whereNotNull('barcode_token')->whereNull('barcode_disabled_at'),
                    'disabled' => $query->whereNotNull('barcode_token')->whereNotNull('barcode_disabled_at'),
                };
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $currentMealSettings = $this->currentMealSettingsForChildren(
            $institution->id,
            $children->getCollection()->pluck('id'),
            $today
        );

        $defaultMealPackage = $this->defaultMealPackage($institution);

        $children->getCollection()->transform(function (Child $child) use ($currentMealSettings) {
            $child->setRelation('currentMealSetting', $currentMealSettings->get($child->id));

            return $child;
        });

        $stats = [
            'participants' => $this->activeParticipantQuery($institution, $today)->count(),
            'active_barcodes' => Child::query()
                ->where('institution_id', $institution->id)
                ->whereNotNull('barcode_token')
                ->whereNull('barcode_disabled_at')
                ->count(),
            'disabled_barcodes' => Child::query()
                ->where('institution_id', $institution->id)
                ->whereNotNull('barcode_token')
                ->whereNotNull('barcode_disabled_at')
                ->count(),
            'missing_barcodes' => $this->activeParticipantQuery($institution, $today)
                ->whereNull('barcode_token')
                ->count(),
        ];

        return view('dashboard.institution_admin.children.barcodes.index', [
            'institution' => $institution,
            'children' => $children,
            'groups' => $groups,
            'stats' => $stats,
            'defaultMealPackage' => $defaultMealPackage,
        ]);
    }

    public function store(Child $child): RedirectResponse
    {
        $this->authorizeChild($child);

        if (! $this->barcodeService->generateForChild($child)) {
            return back()->with('error', 'Ehhez a gyermekhez mar tartozik vonalkod.');
        }

        return back()->with('success', 'A vonalkod sikeresen letrejott.');
    }

    public function destroy(Child $child): RedirectResponse
    {
        $this->authorizeChild($child);

        if (! $this->barcodeService->disableForChild($child)) {
            return back()->with('error', 'Csak aktiv vonalkod tilthato le.');
        }

        return back()->with('success', 'A vonalkod letiltasa megtortent.');
    }

    public function regenerate(Child $child): RedirectResponse
    {
        $this->authorizeChild($child);
        $this->barcodeService->regenerateForChild($child);

        return back()->with('success', 'A vonalkod ujrageneralasa sikeresen megtortent.');
    }

    public function bulkGenerate(ChildBarcodeSelectionRequest $request): RedirectResponse
    {
        $children = $this->selectedParticipants($request->validated('child_ids'));

        if ($children->isEmpty()) {
            return back()->with('error', 'Csak aktiv, etkezo gyermekekhez generalhato tomegesen vonalkod.');
        }

        $generatedCount = DB::transaction(
            fn () => $this->barcodeService->generateMissingForChildren($children)
        );
        $skippedCount = $children->count() - $generatedCount;
        $printBatchToken = $this->storePrintBatch($children->pluck('id')->all());

        return redirect()
            ->route('dashboard.institution.children.barcodes.print-batch', ['token' => $printBatchToken])
            ->with(
                'success',
                "A tomeges generalas elkeszult: {$generatedCount} uj vonalkod, {$skippedCount} kihagyva."
            );
    }

    public function generateMissing(): RedirectResponse
    {
        $institution = $this->institution();
        $today = $this->today();

        $children = $this->activeParticipantQuery($institution, $today)
            ->whereNull('barcode_token')
            ->get();

        $generatedCount = DB::transaction(
            fn () => $this->barcodeService->generateMissingForChildren($children)
        );

        return back()->with('success', "Az osszes hianyzo vonalkod letrehozasa megtortent: {$generatedCount} uj kartya.");
    }

    public function printSelected(ChildBarcodeSelectionRequest $request): RedirectResponse
    {
        $children = $this->selectedParticipants($request->validated('child_ids'));

        if ($children->isEmpty()) {
            return back()->with('error', 'Nincs nyomtathato gyermek a kijelolesben.');
        }

        if ($children->contains(fn (Child $child) => ! $child->hasActiveBarcode())) {
            return back()->with(
                'error',
                'A kijelolesben van olyan gyermek, akinek nincs aktiv vonalkodja, ezert a nyomtatas nem indult el.'
            );
        }

        $printBatchToken = $this->storePrintBatch($children->pluck('id')->all());

        return redirect()->route('dashboard.institution.children.barcodes.print-batch', ['token' => $printBatchToken]);
    }

    public function printBatch(string $token): View|RedirectResponse
    {
        $batch = session("barcode_print_batches.{$token}");

        if (! is_array($batch) || empty($batch['child_ids']) || empty($batch['expires_at'])) {
            return redirect()
                ->route('dashboard.institution.children.barcodes.index')
                ->with('error', 'A nyomtatasi elonezet mar nem erheto el. Kerlek, jelold ki ujra a gyermekeket.');
        }

        if (now()->greaterThan($batch['expires_at'])) {
            session()->forget("barcode_print_batches.{$token}");

            return redirect()
                ->route('dashboard.institution.children.barcodes.index')
                ->with('error', 'A nyomtatasi elonezet lejart. Kerlek, jelold ki ujra a gyermekeket.');
        }

        $children = $this->selectedParticipants($batch['child_ids']);

        return $this->printViewForChildren($children);
    }

    public function printChild(Child $child): View|RedirectResponse
    {
        $this->authorizeChild($child);

        return $this->printViewForChildren(collect([$child]));
    }

    public function printAllActive(): View|RedirectResponse
    {
        $institution = $this->institution();
        $today = $this->today();

        $children = $this->activeParticipantQuery($institution, $today)
            ->whereNotNull('barcode_token')
            ->whereNull('barcode_disabled_at')
            ->orderBy('name')
            ->get();

        return $this->printViewForChildren($children);
    }

    private function printViewForChildren(Collection $children): View|RedirectResponse
    {
        $institution = $this->institution();

        if ($children->isEmpty()) {
            return back()->with('error', 'Nincs nyomtathato gyermek a kijelolesben.');
        }

        if ($children->contains(fn (Child $child) => ! $child->hasActiveBarcode())) {
            return back()->with(
                'error',
                'A kijelolesben van olyan gyermek, akinek nincs aktiv vonalkodja, ezert a nyomtatas nem indult el.'
            );
        }

        $cards = $children->values()->map(function (Child $child) {
            return [
                'child' => $child,
                'barcode_svg' => $this->barcodeService->renderSvg($child->barcode_token),
            ];
        });

        return view('dashboard.institution_admin.children.barcodes.print', [
            'institution' => $institution,
            'cards' => $cards,
        ]);
    }

    private function selectedParticipants(array $childIds): Collection
    {
        $institution = $this->institution();
        $today = $this->today();

        $children = $this->activeParticipantQuery($institution, $today)
            ->whereIn('id', $childIds)
            ->orderBy('name')
            ->get();

        $currentMealSettings = $this->currentMealSettingsForChildren(
            $institution->id,
            $children->pluck('id'),
            $today
        );

        return $children->map(function (Child $child) use ($currentMealSettings) {
            $child->setRelation('currentMealSetting', $currentMealSettings->get($child->id));

            return $child;
        });
    }

    private function activeParticipantQuery(Institution $institution, string $today)
    {
        return Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereExists(function ($subQuery) use ($institution, $today) {
                $subQuery->selectRaw('1')
                    ->from('student_meal_settings')
                    ->whereColumn('student_meal_settings.student_id', 'children.id')
                    ->where('student_meal_settings.institution_id', $institution->id)
                    ->whereDate('student_meal_settings.valid_from', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('student_meal_settings.valid_to')
                            ->orWhereDate('student_meal_settings.valid_to', '>=', $today);
                    });
            });
    }

    private function currentMealSettingsForChildren(int $institutionId, Collection $childIds, string $today): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with(['mealPackage:id,name', 'mealTypes.mealType:id,name'])
            ->whereIn('student_id', $childIds)
            ->where('institution_id', $institutionId)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    private function defaultMealPackage(Institution $institution): ?InstitutionMealPackage
    {
        return InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->first();
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeChild(Child $child): void
    {
        abort_if($child->institution_id !== $this->institution()->id, 403);
    }

    private function today(): string
    {
        return now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
    }

    private function storePrintBatch(array $childIds): string
    {
        $token = Str::random(32);

        session()->put("barcode_print_batches.{$token}", [
            'child_ids' => array_values(array_unique($childIds)),
            'expires_at' => now()->addMinutes(15),
        ]);

        return $token;
    }
}
