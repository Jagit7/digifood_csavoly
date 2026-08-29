<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Mail\DailyAttendanceEmailMail;
use App\Models\Institution;
use App\Services\DailyAttendance\DailyAttendanceEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Az óvodai "napi jelenléti ív e-mailben" beállítási felület (kapcsoló,
 * küldési idő, csoportonkénti címzettek, előnézet, tesztküldés). Kizárólag
 * óvoda típusú intézménynél érhető el - ld. Institution::isKindergarten().
 *
 * Minden lekérdezés az aktuálisan bejelentkezett institution_admin saját
 * intézményére szűr (kindergartenInstitution()), így más intézmény
 * csoportjai, címzettjei vagy gyermekei semmilyen módon nem érhetők el
 * ezeken a route-okon keresztül.
 */
class DailyAttendanceEmailSettingController extends Controller
{
    public function __construct(
        private readonly DailyAttendanceEmailService $dailyAttendanceEmail
    ) {}

    public function updateSchedule(Request $request): RedirectResponse
    {
        $institution = $this->kindergartenInstitution();

        $validated = $request->validate([
            'daily_attendance_email_enabled' => ['nullable', 'boolean'],
            'daily_attendance_email_send_time' => ['required', 'date_format:H:i'],
        ]);

        $this->dailyAttendanceEmail->updateSchedule(
            $institution,
            $request->boolean('daily_attendance_email_enabled'),
            $validated['daily_attendance_email_send_time']
        );

        return redirect()
            ->route('dashboard.institution.settings.edit')
            ->with('success', 'A napi jelenléti ív e-mail beállításai frissítve lettek.');
    }

    public function updateRecipients(Request $request, string $groupName): RedirectResponse
    {
        $institution = $this->kindergartenInstitution();
        $this->assertGroupBelongsToInstitution($institution, $groupName);

        $emails = collect($request->input('emails', []))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values();

        $validator = validator(
            ['emails' => $emails->all()],
            ['emails.*' => ['email:rfc', 'max:191']]
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'emails' => 'Adj meg érvényes e-mail cím(ek)et a(z) "'.$groupName.'" csoporthoz.',
            ]);
        }

        $this->dailyAttendanceEmail->syncGroupRecipients($institution->id, $groupName, $emails->all());

        return redirect()
            ->route('dashboard.institution.settings.edit')
            ->with('success', 'A(z) "'.$groupName.'" csoport címzettjei frissítve lettek.');
    }

    public function preview(string $groupName): View
    {
        $institution = $this->kindergartenInstitution();
        $this->assertGroupBelongsToInstitution($institution, $groupName);

        $summary = $this->dailyAttendanceEmail->buildGroupSummary(
            $institution,
            $groupName,
            $this->today()
        );

        return view('emails.daily-attendance', $summary);
    }

    public function testSend(Request $request, string $groupName): RedirectResponse
    {
        $institution = $this->kindergartenInstitution();
        $this->assertGroupBelongsToInstitution($institution, $groupName);

        $validated = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:191'],
        ]);

        $summary = $this->dailyAttendanceEmail->buildGroupSummary(
            $institution,
            $groupName,
            $this->today()
        );

        // Szándékosan NEM a Job-on / naplózáson keresztül megy ki - a
        // tesztküldés nem kerülhet be úgy a napi kiküldési naplóba, mintha
        // az éles napi levél már ki lett volna küldve.
        Mail::to($validated['test_email'])->send(new DailyAttendanceEmailMail($summary));

        return redirect()
            ->route('dashboard.institution.settings.edit')
            ->with('success', 'Teszt e-mail elküldve ide: '.$validated['test_email'].' ("'.$groupName.'" csoport).');
    }

    private function kindergartenInstitution(): Institution
    {
        // Ugyanaz az intézmény-feloldási minta, mint az
        // InstitutionSettingController::institution() metódusában.
        $institution = $this->currentAdminInstitution();
        abort_unless($institution->isKindergarten(), 404);

        return $institution;
    }

    private function assertGroupBelongsToInstitution(Institution $institution, string $groupName): void
    {
        abort_unless(
            $this->dailyAttendanceEmail->groupNames($institution->id)->contains($groupName),
            404
        );
    }

    private function today(): string
    {
        return now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
    }
}
