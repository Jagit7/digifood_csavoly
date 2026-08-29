<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\AbMenuItem;
use App\Models\AbMenuPlan;
use App\Models\InstitutionSetting;
use App\Services\InstitutionMealCalendarService;
use App\Services\Meals\AbMenuSelectionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AbMenuController extends Controller
{
    public function __construct(
        private readonly AbMenuSelectionService $selectionService
    ) {}

    public function index(): View
    {
        $institution = $this->currentAdminInstitution();

        // A menüválasztás tényleges indítása (a "published_at" beállítása,
        // ami a szülők felé e-mail értesítést is kivált) a
        // MenuChoiceController::open()-ön keresztül, a "Menüválasztások"
        // oldalon (dashboard.institution.menus.choices.index) történik -
        // ITT, az importált tervek listáján csak MEGJELENÍTJÜK ugyanazt az
        // állapotot (ld. AbMenuSelectionService::selectionState()), hogy az
        // admin már ezen az oldalon is jól lássa, ha egy importált terv még
        // nincs elindítva a szülők felé.
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $plans = AbMenuPlan::query()
            ->with('creator')
            ->withCount('items')
            ->where('institution_id', $institution->id)
            ->latest()
            ->paginate(15)
            ->through(function (AbMenuPlan $plan) use ($setting) {
                $state = $this->selectionService->selectionState($plan, $setting);

                $plan->selection_state = $state;
                $plan->selection_state_label = $this->selectionService->selectionStateLabel($state);
                $plan->selection_state_badge = match ($state) {
                    AbMenuSelectionService::STATE_ACTIVE => 'badge-success',
                    AbMenuSelectionService::STATE_CLOSED => 'badge-warning',
                    default => 'badge-danger',
                };

                return $plan;
            });

        $activePlans = AbMenuPlan::where('institution_id', $institution->id)
            ->where('active', true)
            ->count();

        $totalItems = AbMenuPlan::where('institution_id', $institution->id)
            ->withCount('items')
            ->get()
            ->sum('items_count');

        $nextMonthLabel = now()->addMonth()->translatedFormat('Y. F');

        $latestImportLabel = AbMenuPlan::where('institution_id', $institution->id)
            ->latest()
            ->value('created_at')?->format('Y.m.d. H:i') ?? '-';

        return view('dashboard.institution_admin.menus.ab.index', compact(
            'plans',
            'activePlans',
            'totalItems',
            'nextMonthLabel',
            'latestImportLabel'
        ));
    }

    public function create(): View
    {
        return view('dashboard.institution_admin.menus.ab.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());

                    if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                        $fail('A fájl kizárólag xlsx, xls vagy csv lehet.');
                    }
                },
            ],
        ]);

        $institution = $this->currentAdminInstitution();
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $rows[] = array_map(fn ($value) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $value)), $data);
            }

            fclose($handle);
        } else {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($sheet->toArray(null, true, true, false) as $row) {
                $rows[] = array_map(fn ($value) => trim((string) $value), $row);
            }
        }

        if (count($rows) < 2) {
            return back()->with('error', 'A fájl nem tartalmaz importálható sorokat.');
        }

        $header = array_map(function ($value) {
            $value = (string) $value;

            // UTF-8 BOM eltávolítás
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            // idézőjelek, szóközök, sortörések eltávolítása
            $value = trim($value);
            $value = trim($value, "\"' \t\n\r\0\x0B");

            return mb_strtolower($value, 'UTF-8');
        }, $rows[0]);

        $expected = [
            'dátum',
            'a menü',
            'b menü',
            'diétás menü',
            'megjegyzés',
        ];

        if (array_slice($header, 0, 5) !== $expected) {
            return back()->with('error', 'Hibás oszlopfejlécek. Kapott fejléc: '.implode(' | ', $header));
        }

        $importedCount = 0;

        DB::transaction(function () use ($rows, $institution, &$importedCount) {
            $firstDate = null;
            $lastDate = null;

            foreach (array_slice($rows, 1) as $row) {
                if (! empty($row[0])) {
                    $dateValue = $row[0];

                    if (is_numeric($dateValue)) {
                        $date = Carbon::instance(ExcelDate::excelToDateTimeObject($dateValue))->toDateString();
                    } else {
                        $date = Carbon::parse(str_replace('.', '-', trim($dateValue, '.')))->toDateString();
                    }

                    $firstDate = $firstDate ? min($firstDate, $date) : $date;
                    $lastDate = $lastDate ? max($lastDate, $date) : $date;
                }
            }

            $plan = AbMenuPlan::create([
                'institution_id' => $institution->id,
                'created_by' => auth()->id(),
                'title' => 'A/B menü import - '.now()->format('Y.m.d. H:i'),
                'valid_from' => $firstDate,
                'valid_to' => $lastDate,
                'active' => true,
            ]);

            foreach (array_slice($rows, 1) as $row) {
                $dateValue = $row[0] ?? null;

                if (empty($dateValue)) {
                    continue;
                }

                if (is_numeric($dateValue)) {
                    $menuDate = Carbon::instance(ExcelDate::excelToDateTimeObject($dateValue))->toDateString();
                } else {
                    $menuDate = Carbon::parse(str_replace('.', '-', trim($dateValue, '.')))->toDateString();
                }

                $plan->items()->create([
                    'menu_date' => $menuDate,
                    'menu_a' => $row[1] ?? null,
                    'menu_b' => $row[2] ?? null,
                    'menu_dietary' => $row[3] ?? null,
                    'note' => $row[4] ?? null,
                ]);

                $importedCount++;
            }
        });

        return redirect()
            ->route('dashboard.institution.menus.ab.index')
            ->with('success', $importedCount.' menüsor sikeresen importálva.');
    }

    public function show(AbMenuPlan $abMenuPlan): View
    {
        $institution = $this->currentAdminInstitution();

        abort_if($abMenuPlan->institution_id !== $institution->id, 403);

        $abMenuPlan->load([
            'creator',
            'items' => function ($query) {
                $query->orderBy('menu_date');
            },
        ]);
        $dailySummaries = app(InstitutionMealCalendarService::class)
            ->period($abMenuPlan->institution_id, $abMenuPlan->valid_from, $abMenuPlan->valid_to);

        return view('dashboard.institution_admin.menus.ab.show', [
            'abMenu' => $abMenuPlan,
            'dailySummaries' => $dailySummaries,
        ]);
    }

    public function edit(AbMenuPlan $abMenuPlan): View
    {
        $institution = $this->currentAdminInstitution();

        abort_if($abMenuPlan->institution_id !== $institution->id, 403);

        return view('dashboard.institution_admin.menus.ab.edit', [
            'abMenu' => $abMenuPlan,
        ]);
    }

    public function update(Request $request, AbMenuPlan $abMenuPlan): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if($abMenuPlan->institution_id !== $institution->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
        ]);

        $abMenuPlan->update([
            'title' => $validated['title'],
            'valid_from' => $validated['valid_from'],
            'valid_to' => $validated['valid_to'],
            'active' => $request->boolean('active'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.institution.menus.ab.index')
            ->with('success', 'Az A/B menü sikeresen módosítva.');
    }

    public function destroy(AbMenuPlan $abMenuPlan): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if($abMenuPlan->institution_id !== $institution->id, 403);

        if ($abMenuPlan->file_path && Storage::disk('public')->exists($abMenuPlan->file_path)) {
            Storage::disk('public')->delete($abMenuPlan->file_path);
        }

        $abMenuPlan->delete();

        return redirect()
            ->route('dashboard.institution.menus.ab.index')
            ->with('success', 'Az A/B menü törölve.');
    }

    public function sample(): Response
    {
        $rows = [
            ['Dátum', 'A menü', 'B menü', 'Diétás menü', 'Megjegyzés'],
            ['2026.09.01', 'Rántott sertésszelet, rizs', 'Rakott karfiol', 'Gluténmentes csirkemell', ''],
            ['2026.09.02', 'Paradicsomleves, tészta', 'Zöldséges rizottó', 'Laktózmentes főétel', ''],
        ];

        $content = "\xEF\xBB\xBF"; // UTF-8 BOM Excelhez

        foreach ($rows as $row) {
            $content .= implode(';', array_map(fn ($value) => '"'.str_replace('"', '""', $value).'"', $row))."\r\n";
        }

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Digifood_ab-menu-minta.csv"',
        ]);
    }

    public function export()
    {
        abort(501, 'Még nincs elkészítve.');
    }

    public function updateItem(Request $request, AbMenuItem $abMenuItem): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $abMenuItem->load('plan');

        abort_if($abMenuItem->plan->institution_id !== $institution->id, 403);

        $validated = $request->validate([
            'menu_date' => ['required', 'date'],
            'menu_a' => ['nullable', 'string'],
            'menu_b' => ['nullable', 'string'],
            'menu_dietary' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $abMenuItem->update($validated);

        return back()->with('success', 'A menüsor módosítva.');
    }

    public function destroyItem(AbMenuItem $abMenuItem): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $abMenuItem->load('plan');

        abort_if($abMenuItem->plan->institution_id !== $institution->id, 403);

        $abMenuItem->delete();

        return back()->with('success', 'A menüsor törölve.');
    }

    public function rowEdit(AbMenuItem $abMenuItem): View
    {
        $institution = $this->currentAdminInstitution();

        $abMenuItem->load('plan');

        abort_if($abMenuItem->plan->institution_id !== $institution->id, 403);

        return view('dashboard.institution_admin.menus.ab.row_edit', [
            'item' => $abMenuItem,
            'abMenu' => $abMenuItem->plan,
        ]);
    }

    public function rowUpdate(Request $request, AbMenuItem $abMenuItem): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $abMenuItem->load('plan');

        abort_if($abMenuItem->plan->institution_id !== $institution->id, 403);

        $validated = $request->validate([
            'menu_date' => ['required', 'date'],
            'menu_a' => ['nullable', 'string'],
            'menu_b' => ['nullable', 'string'],
            'menu_dietary' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $abMenuItem->update($validated);

        return redirect()
            ->route('dashboard.institution.menus.ab.show', $abMenuItem->plan)
            ->with('success', 'A menüsor sikeresen módosítva.');
    }

    public function rowDestroy(AbMenuItem $abMenuItem): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $abMenuItem->load('plan');

        abort_if($abMenuItem->plan->institution_id !== $institution->id, 403);

        $plan = $abMenuItem->plan;

        $abMenuItem->delete();

        return redirect()
            ->route('dashboard.institution.menus.ab.show', $plan)
            ->with('success', 'A menüsor törölve.');
    }
}
