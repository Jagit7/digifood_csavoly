<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\WorkingDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkingDayController extends Controller
{
    private function types(): array
    {
        return [
            'school_saturday' => 'Tanítási szombat',
            'kindergarten_day' => 'Óvodai nevelési nap',
            'extra_working_day' => 'Rendkívüli munkanap',
            'other' => 'Egyéb',
        ];
    }

    public function index(): View
    {
        $institution = $this->currentAdminInstitution();

        $workingDays = WorkingDay::query()
            ->where('institution_id', $institution->id)
            ->orderBy('date')
            ->paginate(15);

        $stats = [
            'total' => WorkingDay::where('institution_id', $institution->id)->count(),

            'this_year' => WorkingDay::where('institution_id', $institution->id)
                ->whereYear('date', now()->year)
                ->count(),

            'upcoming' => WorkingDay::where('institution_id', $institution->id)
                ->whereDate('date', '>=', now()->toDateString())
                ->count(),

            'past' => WorkingDay::where('institution_id', $institution->id)
                ->whereDate('date', '<', now()->toDateString())
                ->count(),
        ];

        return view('dashboard.institution_admin.working_days.index', compact(
            'institution',
            'workingDays',
            'stats'
        ));
    }

    public function create(): View
    {
        $types = $this->types();

        return view('dashboard.institution_admin.working_days.create', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:school_saturday,kindergarten_day,extra_working_day,other'],
            'description' => ['nullable', 'string'],
        ]);

        $data['institution_id'] = $institution->id;

        WorkingDay::create($data);

        return redirect()
            ->route('dashboard.institution.school-breaks.working-days.index')
            ->with('success', 'Tanítási / óvodai munkanap sikeresen létrehozva.');
    }

    public function edit(WorkingDay $workingDay): View
    {
        $institution = $this->currentAdminInstitution();

        abort_if($workingDay->institution_id !== $institution->id, 403);

        $types = $this->types();

        return view('dashboard.institution_admin.working_days.edit', compact(
            'workingDay',
            'types'
        ));
    }

    public function update(Request $request, WorkingDay $workingDay): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if($workingDay->institution_id !== $institution->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:school_saturday,kindergarten_day,extra_working_day,other'],
            'description' => ['nullable', 'string'],
        ]);

        $workingDay->update($data);

        return redirect()
            ->route('dashboard.institution.school-breaks.working-days.index')
            ->with('success', 'Tanítási / óvodai munkanap sikeresen módosítva.');
    }

    public function destroy(WorkingDay $workingDay): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if($workingDay->institution_id !== $institution->id, 403);

        $workingDay->delete();

        return redirect()
            ->route('dashboard.institution.school-breaks.working-days.index')
            ->with('success', 'Tanítási / óvodai munkanap törölve.');
    }
}
