<?php

namespace App\Services\Meals;

use App\Models\AbMenuPlan;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Collection;

class AbMenuSelectionNotificationService
{
    public function __construct(
        private readonly AbMenuSelectionService $selection,
        private readonly AbMenuNotificationTemplateService $templates
    ) {
    }

    /**
     * Azok a szülők (User + Guardian párok), akiket értesíteni kell egy adott
     * A/B menüterv megnyitásakor: az intézményhez tartozó, aktív szülői
     * fiókkal rendelkező gondviselők, akiknek van legalább egy aktív, NEM
     * kizárólag diétás gyermekük - a diétás gyermekeknél ugyanis nincs A/B
     * választás (ld. AbMenuSelectionService::isDietaryChild()), így az
     * őket egyedül nevelő szülőt felesleges lenne értesíteni.
     *
     * @return Collection<int, array{user: User, guardian: ?Guardian, recipient_email: string, children: Collection<int, Child>}>
     */
    public function notificationRecipientsForPlan(AbMenuPlan $plan): Collection
    {
        $institution = $plan->relationLoaded('institution')
            ? $plan->institution
            : $plan->institution()->firstOrFail();

        return $this->candidateUsers($institution)
            ->map(function (User $user) {
                $guardians = $user->guardians;
                $primaryGuardian = $guardians->first();

                $children = $guardians
                    ->flatMap(fn (Guardian $guardian) => $guardian->children)
                    ->unique('id')
                    ->reject(fn (Child $child) => $this->selection->isDietaryChild($child))
                    ->values();

                if ($children->isEmpty()) {
                    return null;
                }

                $recipientEmail = $this->recipientEmail($user, $primaryGuardian);

                if ($recipientEmail === null) {
                    return null;
                }

                return [
                    'user' => $user,
                    'guardian' => $primaryGuardian,
                    'recipient_email' => $recipientEmail,
                    'children' => $children,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param array{user: User, guardian: ?Guardian, recipient_email: string, children: Collection<int, Child>} $recipient
     */
    public function buildMailPayload(AbMenuPlan $plan, array $recipient): array
    {
        $institution = $plan->relationLoaded('institution')
            ? $plan->institution
            : $plan->institution()->firstOrFail();

        $setting = $institution->setting;
        $deadline = $this->selection->selectionDeadlineForPlan($plan, $setting);

        return [
            'institution' => $institution,
            'subject' => $this->templates->resolvedSubject($setting?->ab_menu_notification_subject),
            'custom_body_text' => $this->templates->resolvedBody($setting?->ab_menu_notification_body),
            'child_names' => $recipient['children']->pluck('name')->values()->all(),
            'period_label' => $this->periodLabel($plan),
            'deadline_label' => $deadline?->translatedFormat('Y. F j. H:i') ?? null,
            'menu_choice_url' => route('parent.menu-choices.index'),
        ];
    }

    private function candidateUsers(Institution $institution): Collection
    {
        return User::query()
            ->where('role', User::ROLE_PARENT)
            ->where('is_active', true)
            ->with(['guardians' => fn ($query) => $query
                ->where('institution_id', $institution->id)
                ->where('active', true)
                ->with(['children' => fn ($childQuery) => $childQuery
                    ->where('children.active', true)
                    ->with(['dietaryRestrictions' => fn ($q) => $q->where('active', true)])])])
            ->whereHas('guardians', function ($query) use ($institution) {
                $query->where('guardians.institution_id', $institution->id)
                    ->where('guardians.active', true)
                    ->whereHas('children', fn ($childQuery) => $childQuery
                        ->where('children.institution_id', $institution->id)
                        ->where('children.active', true));
            })
            ->orderBy('id')
            ->get();
    }

    private function periodLabel(AbMenuPlan $plan): string
    {
        $from = $plan->valid_from?->translatedFormat('Y. F j.');
        $to = $plan->valid_to?->translatedFormat('Y. F j.');

        return trim(implode(' - ', array_filter([$from, $to])));
    }

    private function recipientEmail(User $user, ?Guardian $guardian): ?string
    {
        foreach ([$user->email, $guardian?->email] as $email) {
            $normalized = $this->normalizeEmail($email);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }
}
