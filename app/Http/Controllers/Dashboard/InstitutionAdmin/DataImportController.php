<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\DataImport;
use App\Models\Guardian;
use App\Services\KindergartenImportService;
use App\Services\SchoolStandardImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DataImportController extends Controller
{
    public function index(): View
    {
        $institution = $this->currentAdminInstitution();
        $imports = DataImport::where('institution_id', $institution->id)
            ->with('creator')
            ->latest()
            ->paginate(15);

        $stats = [
            'children' => Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->where('source_type', '!=', 'manual')
                ->count(),
            'groups' => Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->where('source_type', '!=', 'manual')
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->distinct()
                ->count('group_name'),
            'guardians' => Guardian::where('institution_id', $institution->id)->count(),
            'imports' => DataImport::where('institution_id', $institution->id)->count(),
        ];

        return view('dashboard.institution_admin.imports.index', compact(
            'institution',
            'imports',
            'stats'
        ));
    }

    public function createSchoolStandard(): View
    {
        $institution = $this->currentAdminInstitution();
        abort_unless($institution->type === 'iskola', 403, 'Ez az importálás csak iskolai intézménynél használható.');

        $year = now()->month >= 8 ? now()->year : now()->year - 1;
        $defaultSchoolYear = $year.'/'.($year + 1);

        return view('dashboard.institution_admin.imports.school-standard', compact(
            'institution',
            'defaultSchoolYear'
        ));
    }

    public function storeSchoolStandard(
        Request $request,
        SchoolStandardImportService $importService
    ): RedirectResponse {
        $institution = $this->currentAdminInstitution();
        abort_unless($institution->type === 'iskola', 403, 'Ez az importálás csak iskolai intézménynél használható.');

        $validated = $request->validate([
            'group_name' => ['required', 'string', 'max:100'],
            'school_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'import_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'school_year.regex' => 'A tanév formátuma például 2026/2027 legyen.',
            'import_file.mimes' => 'Kizárólag .xlsx formátumú Excel-fájl tölthető fel.',
        ]);

        [$schoolYearStart, $schoolYearEnd] = array_map('intval', explode('/', $validated['school_year']));
        if ($schoolYearEnd !== $schoolYearStart + 1) {
            throw ValidationException::withMessages([
                'school_year' => 'A tanév két egymást követő évből álljon, például 2026/2027.',
            ]);
        }

        $file = $request->file('import_file');
        $storedName = now()->format('Ymd_His').'_'.bin2hex(random_bytes(5)).'.xlsx';
        $storedPath = $file->storeAs("imports/{$institution->id}", $storedName, 'local');

        $import = DataImport::create([
            'institution_id' => $institution->id,
            'created_by' => auth()->id(),
            'profile' => DataImport::PROFILE_SCHOOL_STANDARD,
            'status' => 'processing',
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_path' => $storedPath,
            'file_size' => $file->getSize(),
            'group_name' => $validated['group_name'],
            'school_year' => $validated['school_year'],
        ]);

        try {
            $importService->import($import, Storage::disk('local')->path($storedPath));
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'error_count' => 1,
                'errors' => [['row' => null, 'messages' => [$exception->getMessage()]]],
                'completed_at' => now(),
            ]);

            return redirect()
                ->route('dashboard.institution.imports.show', $import)
                ->with('error', 'Az importálás nem sikerült. A részleteket lent találod.');
        }

        return redirect()
            ->route('dashboard.institution.imports.show', $import)
            ->with('success', 'Az iskolai Excel feldolgozása befejeződött.');
    }

    public function createKindergarten(): View
    {
        $institution = $this->currentAdminInstitution();
        abort_unless($institution->type === 'ovoda', 403, 'Ez az importálás csak óvodai intézménynél használható.');

        $year = now()->month >= 8 ? now()->year : now()->year - 1;
        $defaultSchoolYear = $year.'/'.($year + 1);

        return view('dashboard.institution_admin.imports.kindergarten', compact(
            'institution',
            'defaultSchoolYear'
        ));
    }

    public function storeKindergarten(
        Request $request,
        KindergartenImportService $importService
    ): RedirectResponse {
        $institution = $this->currentAdminInstitution();
        abort_unless($institution->type === 'ovoda', 403, 'Ez az importálás csak óvodai intézménynél használható.');

        $validated = $request->validate([
            'school_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'import_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'school_year.regex' => 'A nevelési év formátuma például 2026/2027 legyen.',
            'import_file.mimes' => 'Kizárólag .xlsx formátumú Excel-fájl tölthető fel.',
        ]);

        [$schoolYearStart, $schoolYearEnd] = array_map('intval', explode('/', $validated['school_year']));
        if ($schoolYearEnd !== $schoolYearStart + 1) {
            throw ValidationException::withMessages([
                'school_year' => 'A nevelési év két egymást követő évből álljon, például 2026/2027.',
            ]);
        }

        $file = $request->file('import_file');
        $storedName = now()->format('Ymd_His').'_'.bin2hex(random_bytes(5)).'.xlsx';
        $storedPath = $file->storeAs("imports/{$institution->id}", $storedName, 'local');

        $import = DataImport::create([
            'institution_id' => $institution->id,
            'created_by' => auth()->id(),
            'profile' => DataImport::PROFILE_KINDERGARTEN,
            'status' => 'processing',
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_path' => $storedPath,
            'file_size' => $file->getSize(),
            'school_year' => $validated['school_year'],
        ]);

        try {
            $importService->import($import, Storage::disk('local')->path($storedPath));
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'error_count' => 1,
                'errors' => [['row' => null, 'messages' => [$exception->getMessage()]]],
                'completed_at' => now(),
            ]);

            return redirect()
                ->route('dashboard.institution.imports.show', $import)
                ->with('error', 'Az importálás nem sikerült. A részleteket lent találod.');
        }

        return redirect()
            ->route('dashboard.institution.imports.show', $import)
            ->with('success', 'Az óvodai Excel feldolgozása befejeződött.');
    }

    public function show(DataImport $import): View
    {
        $institution = $this->currentAdminInstitution();
        abort_if($import->institution_id !== $institution->id, 403);

        $import->load('creator');

        return view('dashboard.institution_admin.imports.show', compact('institution', 'import'));
    }
}
