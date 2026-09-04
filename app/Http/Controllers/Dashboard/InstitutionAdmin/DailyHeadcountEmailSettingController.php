<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Mail\DailyHeadcountEmailMail;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Services\DailyHeadcount\DailyHeadcountEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A "Napi létszám e-mailek" admin oldala: közös napi küldési időpont,
 * osztályonkénti/csoportonkénti be-/kikapcsolás és címzettek, előnézet,
 * tesztküldés. Iskolai és óvodai intézménynél egyaránt elérhető (ld.
 * DailyHeadcountEmailService).
 *
 * Minden lekérdezés az aktuálisan bejelentkezett institution_admin saját
 * intézményére szűr (currentAdminInstitution()), így más intézmény
 * osztályai/csoportjai, címzettjei vagy naplói semmilyen módon nem érhetők
 * el ezeken a route-okon keresztül - még az osztály/csoport nevének
 * URL-ben történő módosításával sem, mert assertGroupBelongsToInstitution()
 * mindig ellenőrzi, hogy a megadott név ténylegesen a jelenlegi intézmény
 * egyik aktív osztálya/csoportja-e.
 */
class DailyHeadcountEmailSettingController extends Controller
{
    public function __construct(
        private readonly DailyHeadcountEmailService $dailyHeadcountEmail
    ) {}

    public function index(): View
    {
        $institution = $this->currentAdminInstitution();

        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        return view('dashboard.institution_admin.daily_headcount_emails.index', [
            'institution' => $institution,
            'setting' => $setting,
            'sendTime' => $this->dailyHeadcountEmail->sendTimeLabel($setting->daily_headcount_email_send_time),
            'groupNames' => $this->dailyHeadcountEmail->groupNames($institution->id),
            'groupEnabledMap' => $this->dailyHeadcountEmail->groupEnabledMap($institution->id),
            'recipientsByGroup' => $this->dailyHeadcountEmail->recipientsByGroup($institution->id),
        ]);
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        $validated = $request->validate([
            'daily_headcount_email_enabled' => ['nullable', 'boolean'],
            'daily_headcount_email_send_time' => ['required', 'date_format:H:i'],
        ]);

        $this->dailyHeadcountEmail->updateSchedule(
            $institution,
            $request->boolean('daily_headcount_email_enabled'),
            $validated['daily_headcount_email_send_time']
        );

        return redirect()
            ->route('dashboard.institution.daily-headcount-emails.index')
            ->with('success', 'A napi létszám e-mailek közös küldési időpontja frissítve lett.');
    }

    public function updateGroup(Request $request, string $groupName): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
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
                'emails' => 'Adj meg érvényes e-mail cím(ek)et a(z) "'.$groupName.'" '.$this->groupWord($institution).'hoz.',
            ]);
        }

        $this->dailyHeadcountEmail->updateGroupConfiguration(
            $institution->id,
            $groupName,
            $request->boolean('enabled'),
            $emails->all()
        );

        return redirect()
            ->route('dashboard.institution.daily-headcount-emails.index')
            ->with('success', 'A(z) "'.$groupName.'" beállításai frissültek.');
    }

    public function preview(string $groupName): View
    {
        $institution = $this->currentAdminInstitution();
        $this->assertGroupBelongsToInstitution($institution, $groupName);

        $summary = $this->dailyHeadcountEmail->buildGroupSummary(
            $institution,
            $groupName,
            $this->today()
        );

        return view('emails.daily-headcount', $summary);
    }

    public function testSend(Request $request, string $groupName): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        $this->assertGroupBelongsToInstitution($institution, $groupName);

        $validated = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:191'],
        ]);

        $summary = $this->dailyHeadcountEmail->buildGroupSummary(
            $institution,
            $groupName,
            $this->today()
        );

        // Szándékosan NEM a Job-on / naplózáson keresztül megy ki - a
        // tesztküldés nem kerülhet be úgy a napi kiküldési naplóba, mintha
        // az éles napi levél már ki lett volna küldve.
        Mail::to($validated['test_email'])->send(new DailyHeadcountEmailMail($summary));

        return redirect()
            ->route('dashboard.institution.daily-headcount-emails.index')
            ->with('success', 'Teszt e-mail elküldve ide: '.$validated['test_email'].' ("'.$groupName.'").');
    }

    private function assertGroupBelongsToInstitution(Institution $institution, string $groupName): void
    {
        abort_unless(
            $this->dailyHeadcountEmail->groupNames($institution->id)->contains($groupName),
            404
        );
    }

    private function groupWord(Institution $institution): string
    {
        return $institution->isKindergarten() ? 'csoport' : 'osztály';
    }

    private function today(): string
    {
        return now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
    }
}
