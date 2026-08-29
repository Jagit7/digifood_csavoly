<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignEmailJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Services\EmployeePortal\EmployeeAccountActivationService;
use App\Support\Html\CampaignHtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 1:1 mása az App\Http\Controllers\Dashboard\InstitutionAdmin\ParentActivationInviteController-nek,
 * Guardian -> InstitutionEmployee cserével. Ld. ott a részletes megjegyzéseket.
 * A legfontosabb eltérés: a dolgozóknak nincs gyermek/osztály relációjuk, ezért
 * a "child_names"/"class_group_names" mezők itt mindig üresek maradnak, és a
 * dolgozói eligibility-szűrésnek sincs "van aktív gyereke" feltétele - csak
 * annyi számít, hogy a dolgozó aktív-e.
 */
class EmployeeActivationInviteController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();

        $eligibleEmployees = $this->eligibleEmployeesQuery($institution)->get();
        $recipientPayloads = $this->buildRecipientPayloads($eligibleEmployees);

        $alreadyInvitedCount = InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('activation_email_sent_at')
            ->count();

        $alreadyActivatedCount = InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('user_id')
            ->count();

        return view('dashboard.institution_admin.communication.employee-activation-invite.index', [
            'institution' => $institution,
            'eligibleCount' => $recipientPayloads->count(),
            'eligibleRecipients' => $recipientPayloads,
            'alreadyInvitedCount' => $alreadyInvitedCount,
            'alreadyActivatedCount' => $alreadyActivatedCount,
            'defaultSubject' => $this->defaultSubject(),
            'defaultBody' => $this->defaultBody(),
            'individualInviteEmployees' => $this->buildIndividualInviteOptions($institution),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();

        $validated = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
        ], [
            'subject.max' => 'Az e-mail tárgya legfeljebb 191 karakter lehet.',
        ])->validate();
        $validated['body'] = CampaignHtmlSanitizer::clean($validated['body']);

        $eligibleEmployees = $this->eligibleEmployeesQuery($institution)->get();
        $recipientPayloads = $this->buildRecipientPayloads($eligibleEmployees);

        if ($recipientPayloads->isEmpty()) {
            throw ValidationException::withMessages([
                'recipients' => 'Jelenleg nincs olyan dolgozó, aki e-mail címmel rendelkezik, még nem hozott létre dolgozói fiókot, és még nem kapott aktivációs meghívót.',
            ]);
        }

        $campaign = $this->queueCampaign($institution, $validated, $recipientPayloads);

        return redirect()
            ->route('dashboard.institution.communication.emails.show', $campaign)
            ->with('success', 'A fiókaktiválási meghívók sorba állítva.');
    }

    public function sendIndividual(Request $request, InstitutionEmployee $employee): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($employee->institution_id !== $institution->id, 404);

        $validated = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
        ], [
            'subject.max' => 'Az e-mail tárgya legfeljebb 191 karakter lehet.',
        ])->validate();
        $validated['body'] = CampaignHtmlSanitizer::clean($validated['body']);

        $normalizedEmail = $this->normalizeEmail($employee->email);

        if ($normalizedEmail === null || $employee->user_id !== null || ! $employee->active) {
            throw ValidationException::withMessages([
                'employee' => 'Ehhez a dolgozóhoz jelenleg nem küldhető aktiválási meghívó (nincs e-mail címe, vagy már aktiválta a fiókját).',
            ]);
        }

        $relatedEmployees = $this->activatableEmployeesQuery($institution)
            ->get()
            ->filter(fn (InstitutionEmployee $candidate) => $this->normalizeEmail($candidate->email) === $normalizedEmail)
            ->values();

        $recipientPayloads = $this->buildRecipientPayloads($relatedEmployees);

        if ($recipientPayloads->isEmpty()) {
            throw ValidationException::withMessages([
                'employee' => 'Ehhez a dolgozóhoz jelenleg nem küldhető aktiválási meghívó.',
            ]);
        }

        $this->queueCampaign($institution, $validated, $recipientPayloads);

        return redirect()
            ->route('dashboard.institution.communication.employee-activation-invite.index')
            ->with('success', "Aktivációs meghívó elküldve ide: {$normalizedEmail}.");
    }

    public function generateLink(InstitutionEmployee $employee, EmployeeAccountActivationService $activationService): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($employee->institution_id !== $institution->id, 404);

        $normalizedEmail = $this->normalizeEmail($employee->email);

        if ($normalizedEmail === null || $employee->user_id !== null || ! $employee->active) {
            return redirect()
                ->route('dashboard.institution.communication.employee-activation-invite.index')
                ->with('error', 'Ehhez a dolgozóhoz jelenleg nem generálható aktiváló link (nincs e-mail címe, vagy már aktiválta a fiókját).');
        }

        $result = $activationService->createManualActivationLink($normalizedEmail);

        if ($result['status'] !== 'activation_created') {
            return redirect()
                ->route('dashboard.institution.communication.employee-activation-invite.index')
                ->with('error', match ($result['status']) {
                    'non_employee_user_exists' => 'Ehhez az e-mail címhez már más típusú felhasználói fiók tartozik, link nem generálható.',
                    'already_active_employee' => 'Ez a dolgozó már aktiválta a fiókját, link nem generálható.',
                    default => 'Ehhez a dolgozóhoz jelenleg nem generálható aktiváló link.',
                });
        }

        InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNull('activation_email_sent_at')
            ->update(['activation_email_sent_at' => now()]);

        return redirect()
            ->route('dashboard.institution.communication.employee-activation-invite.index')
            ->with('manual_activation_url', $result['activation_url'])
            ->with('manual_activation_expires_at', $result['expires_at']->translatedFormat('Y. m. d. H:i'))
            ->with('manual_activation_recipient', ($employee->name ?: $normalizedEmail).' ('.$normalizedEmail.')');
    }

    /**
     * @param  array{subject: string, body: string}  $validated
     */
    private function queueCampaign(Institution $institution, array $validated, Collection $recipientPayloads): EmailCampaign
    {
        $campaign = null;

        DB::transaction(function () use ($institution, $validated, $recipientPayloads, &$campaign) {
            $campaign = EmailCampaign::query()->create([
                'institution_id' => $institution->id,
                'created_by' => auth()->id(),
                'type' => EmailCampaign::TYPE_EMPLOYEE_ACTIVATION_INVITE,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => EmailCampaign::STATUS_QUEUED,
                'recipient_count' => $recipientPayloads->count(),
                'sent_count' => 0,
                'failed_count' => 0,
                'queued_at' => now(),
            ]);

            $employeeIds = [];

            foreach ($recipientPayloads as $payload) {
                $campaign->recipients()->create([
                    'institution_employee_id' => $payload['institution_employee_id'],
                    'email' => $payload['email'],
                    'recipient_name' => $payload['recipient_name'],
                    'child_names' => [],
                    'class_group_names' => [],
                    'status' => EmailCampaignRecipient::STATUS_QUEUED,
                ]);

                foreach ($payload['employee_ids'] as $employeeId) {
                    $employeeIds[] = $employeeId;
                }
            }

            InstitutionEmployee::query()
                ->whereIn('id', array_values(array_unique($employeeIds)))
                ->update(['activation_email_sent_at' => now()]);

            DB::afterCommit(function () use ($campaign) {
                $campaign->recipients()
                    ->pluck('id')
                    ->each(fn (int $recipientId) => SendCampaignEmailJob::dispatch($recipientId));
            });
        });

        return $campaign;
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function activatableEmployeesQuery(Institution $institution): Builder
    {
        return InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name');
    }

    private function eligibleEmployeesQuery(Institution $institution): Builder
    {
        return $this->activatableEmployeesQuery($institution)
            ->whereNull('activation_email_sent_at');
    }

    private function buildIndividualInviteOptions(Institution $institution): Collection
    {
        return $this->activatableEmployeesQuery($institution)
            ->get()
            ->map(fn (InstitutionEmployee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name ?: $employee->email,
                'email' => $employee->email,
                'already_invited' => $employee->activation_email_sent_at !== null,
            ])
            ->values();
    }

    /**
     * Egy címzett (payload) egyedi e-mail cím szerint jön létre - elméletileg
     * több dolgozói rekord is megoszthat egy e-mail címet, ezért minden
     * érintett dolgozó azonosítóját megőrizzük az "employee_ids" alatt (ld.
     * ParentActivationInviteController::buildRecipientPayloads() "guardian_ids"
     * mezője ugyanezzel a céllal).
     */
    private function buildRecipientPayloads(Collection $employees): Collection
    {
        $recipients = collect();

        foreach ($employees as $employee) {
            $normalizedEmail = $this->normalizeEmail($employee->email);

            if ($normalizedEmail === null) {
                continue;
            }

            $existing = $recipients->get($normalizedEmail, [
                'institution_employee_id' => $employee->id,
                'employee_ids' => [],
                'email' => $normalizedEmail,
                'recipient_name' => $employee->name ?: null,
            ]);

            $existing['employee_ids'][] = $employee->id;

            $recipients->put($normalizedEmail, $existing);
        }

        return $recipients->values();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalizedEmail = mb_strtolower(trim((string) $email));

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $normalizedEmail;
    }

    private function defaultSubject(): string
    {
        return 'Digifood dolgozói fiók létrehozása – első lépések';
    }

    private function defaultBody(): string
    {
        $activationUrl = '#DOLGOZOI_FIOK_AKTIVALASA_URL#';

        return <<<HTML
        <p>Kedves Munkatárs!</p>

        <p>
            Örömmel értesítjük, hogy a(z) <strong>#INTEZMENY_NEVE#</strong> intézmény bevezette a Digifood
            online étkezés-nyilvántartó rendszerét. Ezen keresztül egyszerűen nyomon követheti saját
            étkezéseit, kényelmesen intézheti a lemondásokat, és elérheti a befizetésekkel kapcsolatos
            információkat is - mindezt egy helyen, otthonról.
        </p>

        <p><strong>Az első belépéshez mindössze néhány lépésre van szükség:</strong></p>

        <ol>
            <li>Kattintson az alábbi "Fiók aktiválása" gombra.</li>
            <li>Adja meg azt az e-mail címet, amelyen ezt az üzenetet kapta.</li>
            <li>Ellenőrizze postafiókját - hamarosan kap egy jelszó-létrehozó linket tartalmazó levelet.</li>
            <li>A linkre kattintva állítsa be jelszavát - ezzel a fiókja aktiválódik, és azonnal használhatja is.</li>
        </ol>

        <p style="text-align:center; margin: 24px 0;">
            <a href="{$activationUrl}"
               style="background-color:#d94a16; color:#ffffff; padding:12px 28px; border-radius:30px; text-decoration:none; font-weight:bold; display:inline-block;">
                Fiók aktiválása
            </a>
        </p>

        <p>
            Amennyiben a gomb nem működne, másolja be az alábbi linket böngészője címsorába:<br>
            <a href="{$activationUrl}">{$activationUrl}</a>
        </p>

        <p>
            Kérdés esetén forduljon bizalommal az intézményi adminisztrációhoz.
        </p>

        <p>Üdvözlettel:<br>#INTEZMENY_NEVE#</p>
        HTML;
    }
}
