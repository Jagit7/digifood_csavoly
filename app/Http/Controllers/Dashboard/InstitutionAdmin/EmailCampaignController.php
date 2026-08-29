<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\EmailCampaign;
use App\Support\Html\CampaignHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailCampaignController extends Controller
{
    public function index(): View
    {
        $institution = $this->institution();

        $campaigns = EmailCampaign::query()
            ->with('creator')
            ->where('institution_id', $institution->id)
            ->latest()
            ->paginate(15);

        return view('dashboard.institution_admin.communication.emails.index', compact(
            'institution',
            'campaigns'
        ));
    }

    public function create(Request $request): View
    {
        $institution = $this->institution();
        $selectedChildIds = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereIn('id', array_map('intval', (array) $request->input('child_ids', [])))
            ->pluck('id')
            ->all();

        $classGroups = ClassGroup::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->withCount(['children' => fn ($query) => $query->where('children.active', true)])
            ->orderBy('name')
            ->get();

        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->with([
                'guardians' => fn ($query) => $query
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->orderBy('last_name')
                    ->orderBy('first_name'),
                'classGroups' => fn ($query) => $query->where('class_groups.active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        return view('dashboard.institution_admin.communication.emails.create', compact(
            'institution',
            'classGroups',
            'children',
            'selectedChildIds'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $selectedClassGroupIds = array_values(array_unique(array_map('intval', (array) $request->input('class_group_ids', []))));
        $selectedChildIds = array_values(array_unique(array_map('intval', (array) $request->input('child_ids', []))));

        $validator = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
            'class_group_ids' => ['array'],
            'class_group_ids.*' => ['integer'],
            'child_ids' => ['array'],
            'child_ids.*' => ['integer'],
        ], [
            'subject.max' => 'Az e-mail tárgya legfeljebb 191 karakter lehet.',
        ]);

        $validator->after(function ($validator) use ($selectedClassGroupIds, $selectedChildIds) {
            if ($selectedClassGroupIds === [] && $selectedChildIds === []) {
                $validator->errors()->add('recipients', 'Válassz ki legalább egy osztályt vagy gyermeket.');
            }
        });

        $validated = $validator->validate();
        // Az admin által a WYSIWYG szerkesztőben beírt szöveg tárolás
        // előtt itt is tisztításra kerül (tárolt XSS elleni védelem) -
        // ld. App\Support\Html\CampaignHtmlSanitizer.
        $validated['body'] = CampaignHtmlSanitizer::clean($validated['body']);

        $classGroups = ClassGroup::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereIn('id', $selectedClassGroupIds)
            ->get();

        if ($classGroups->count() !== count($selectedClassGroupIds)) {
            throw ValidationException::withMessages([
                'class_group_ids' => 'Csak a saját intézmény aktív osztályai választhatók.',
            ]);
        }

        $directChildren = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->whereIn('id', $selectedChildIds)
            ->get();

        if ($directChildren->count() !== count($selectedChildIds)) {
            throw ValidationException::withMessages([
                'child_ids' => 'Csak a saját intézmény aktív gyermekei választhatók.',
            ]);
        }

        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->where(function ($query) use ($selectedChildIds, $selectedClassGroupIds) {
                if ($selectedChildIds !== []) {
                    $query->whereIn('children.id', $selectedChildIds);
                }

                if ($selectedClassGroupIds !== []) {
                    $query->orWhereHas('classGroups', function ($classGroupQuery) use ($selectedClassGroupIds) {
                        $classGroupQuery
                            ->whereIn('class_groups.id', $selectedClassGroupIds)
                            ->where('class_groups.active', true);
                    });
                }
            })
            ->with([
                'guardians' => fn ($query) => $query
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->orderBy('last_name')
                    ->orderBy('first_name'),
                'classGroups' => fn ($query) => $query
                    ->where('class_groups.active', true)
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();

        $recipientPayloads = $this->buildRecipientPayloads($children);

        if ($recipientPayloads->isEmpty()) {
            throw ValidationException::withMessages([
                'recipients' => 'A kiválasztott gyermekekhez nem található használható gondviselői e-mail cím.',
            ]);
        }

        $campaign = null;

        DB::transaction(function () use ($institution, $validated, $recipientPayloads, &$campaign) {
            $campaign = EmailCampaign::query()->create([
                'institution_id' => $institution->id,
                'created_by' => auth()->id(),
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => EmailCampaign::STATUS_QUEUED,
                'recipient_count' => $recipientPayloads->count(),
                'sent_count' => 0,
                'failed_count' => 0,
                'queued_at' => now(),
            ]);

            foreach ($recipientPayloads as $payload) {
                $campaign->recipients()->create([
                    'guardian_id' => $payload['guardian_id'],
                    'email' => $payload['email'],
                    'recipient_name' => $payload['recipient_name'],
                    'child_names' => $payload['child_names'],
                    'class_group_names' => $payload['class_group_names'],
                    'status' => \App\Models\EmailCampaignRecipient::STATUS_QUEUED,
                ]);
            }

            DB::afterCommit(function () use ($campaign) {
                $campaign->recipients()
                    ->pluck('id')
                    ->each(fn (int $recipientId) => SendCampaignEmailJob::dispatch($recipientId));
            });
        });

        return redirect()
            ->route('dashboard.institution.communication.emails.show', $campaign)
            ->with('success', 'Az e-mail kampány sorba állítva.');
    }

    public function show(EmailCampaign $emailCampaign): View
    {
        $institution = $this->institution();
        abort_if($emailCampaign->institution_id !== $institution->id, 403);

        $emailCampaign->load(['creator', 'institution']);
        $recipients = $emailCampaign->recipients()->latest()->paginate(50);

        return view('dashboard.institution_admin.communication.emails.show', [
            'institution' => $institution,
            'campaign' => $emailCampaign,
            'recipients' => $recipients,
        ]);
    }

    private function institution()
    {
        return $this->currentAdminInstitution();
    }

    private function buildRecipientPayloads(Collection $children): Collection
    {
        $recipients = collect();

        foreach ($children as $child) {
            $childClassGroupNames = $child->classGroups
                ->pluck('name')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            foreach ($child->guardians as $guardian) {
                $normalizedEmail = $this->normalizeEmail($guardian->email);

                if ($normalizedEmail === null) {
                    continue;
                }

                $existing = $recipients->get($normalizedEmail, [
                    'guardian_id' => $guardian->id,
                    'email' => $normalizedEmail,
                    'recipient_name' => $guardian->full_name ?: null,
                    'child_names' => [],
                    'class_group_names' => [],
                ]);

                $existing['child_names'] = collect($existing['child_names'])
                    ->push($child->name)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $existing['class_group_names'] = collect($existing['class_group_names'])
                    ->merge($childClassGroupNames)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $recipients->put($normalizedEmail, $existing);
            }
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
}
