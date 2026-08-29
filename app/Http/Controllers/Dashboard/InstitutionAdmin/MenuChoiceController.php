<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAbMenuSelectionNotificationJob;
use App\Models\AbMenuPlan;
use App\Models\AbMenuSelectionNotificationLog;
use App\Models\InstitutionSetting;
use App\Services\Meals\AbMenuSelectionNotificationService;
use App\Services\Meals\AbMenuSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MenuChoiceController extends Controller
{
    public function __construct(
        private readonly AbMenuSelectionService $selectionService,
        private readonly AbMenuSelectionNotificationService $notificationService
    ) {}

    public function index(): View
    {
        $institution = $this->currentAdminInstitution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $plans = AbMenuPlan::query()
            ->withCount('items')
            ->where('institution_id', $institution->id)
            ->latest('valid_from')
            ->latest('id')
            ->paginate(15)
            ->through(function (AbMenuPlan $plan) use ($setting) {
                $state = $this->selectionService->selectionState($plan, $setting);

                $plan->selection_state = $state;
                $plan->selection_state_label = $this->selectionService->selectionStateLabel($state);
                $plan->selection_deadline = $this->selectionService->selectionDeadlineForPlan($plan, $setting);
                $plan->selection_state_badge = match ($state) {
                    AbMenuSelectionService::STATE_ACTIVE => 'badge-success',
                    AbMenuSelectionService::STATE_CLOSED => 'badge-warning',
                    default => 'badge-secondary',
                };
                $plan->can_open_selection = $this->selectionService->canOpenSelection($plan);

                return $plan;
            });

        return view('dashboard.institution_admin.menus.choices.index', compact('plans'));
    }

    public function open(AbMenuPlan $abMenuPlan): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();

        abort_if((int) $abMenuPlan->institution_id !== (int) $institution->id, 403);

        if (! $this->selectionService->canOpenSelection($abMenuPlan)) {
            return back()->with('error', 'A menüválasztás csak aktív, feltöltött menütervnél indítható el.');
        }

        // A címzetteket MÉG a megnyitás (published_at beállítása) előtt kell
        // lekérdezni, amíg a canOpenSelection() még nem lát megnyitott
        // tervet - ez maga az adat nem változik a tranzakción belül, csak a
        // terv publikálási állapotát írjuk.
        $recipients = $this->notificationService->notificationRecipientsForPlan($abMenuPlan);

        DB::transaction(function () use ($abMenuPlan, $recipients) {
            $this->selectionService->openSelection($abMenuPlan, (int) auth()->id());

            foreach ($recipients as $recipient) {
                $log = AbMenuSelectionNotificationLog::query()->firstOrCreate(
                    [
                        'ab_menu_plan_id' => $abMenuPlan->id,
                        'user_id' => $recipient['user']->id,
                    ],
                    [
                        'institution_id' => $abMenuPlan->institution_id,
                        'guardian_id' => $recipient['guardian']?->id,
                        'recipient_email' => $recipient['recipient_email'],
                        'status' => AbMenuSelectionNotificationLog::STATUS_QUEUED,
                        'queued_at' => now(),
                    ]
                );

                if (in_array($log->status, [
                    AbMenuSelectionNotificationLog::STATUS_QUEUED,
                    AbMenuSelectionNotificationLog::STATUS_FAILED,
                ], true)) {
                    DB::afterCommit(fn () => dispatch(new SendAbMenuSelectionNotificationJob($log->id)));
                }
            }
        });

        $recipientCount = $recipients->count();

        return back()->with('success', sprintf(
            'A menüválasztási időszak sikeresen elindult. A szülők mostantól leadhatják választásukat. (%d szülő kap értesítő e-mailt.)',
            $recipientCount
        ));
    }

    public function close(): RedirectResponse
    {
        return back()->with('error', 'A menüválasztás lezárása jelenleg automatikusan, a határidő alapján történik.');
    }

    public function reopen(): RedirectResponse
    {
        return back()->with('error', 'Az újranyitás még nincs bevezetve ehhez a felülethez.');
    }

    public function export()
    {
        abort(501, 'Még nincs elkészítve.');
    }
}
