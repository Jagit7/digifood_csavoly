<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use App\Models\InstitutionPaymentComponentRate;
use App\Models\InstitutionSetting;
use App\Services\DailyAttendance\DailyAttendanceEmailService;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Services\Meals\AbMenuNotificationTemplateService;
use App\Services\PaymentNotifications\PaymentNotificationTemplateService;
use App\Support\Finance\PaymentComponent;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstitutionSettingController extends Controller
{
    public function __construct(
        private readonly DailyAttendanceEmailService $dailyAttendanceEmail,
        private readonly InstitutionPaymentComponentService $componentService,
    ) {}

    public function edit(
        PaymentNotificationTemplateService $paymentTemplates,
        AbMenuNotificationTemplateService $abMenuTemplates
    ): View {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
        $mealSetting = InstitutionMealSetting::query()
            ->where('institution_id', $institution->id)
            ->first();
        $kitchenSendTime = $this->kitchenSendTime($mealSetting);
        $isKindergarten = $institution->isKindergarten();
        $rateHistory = InstitutionPaymentComponentRate::query()
            ->where('institution_id', $institution->id)
            ->orderBy('component')
            ->orderByDesc('valid_from')
            ->get()
            ->groupBy('component');

        return view('dashboard.institution_admin.institution.settings', [
            'institution' => $institution,
            'setting' => $setting,
            'mealSetting' => $mealSetting,
            'kitchenRecipientsText' => implode("\n", $setting->kitchenNotificationEmails()),
            'kitchenSendTime' => $kitchenSendTime,
            // A napi jelenléti ív e-mail szekció kizárólag óvodai
            // intézménynél jelenik meg - iskolánál a lekérdezéseket sem
            // futtatjuk le feleslegesen.
            'isKindergarten' => $isKindergarten,
            'dailyAttendanceGroups' => $isKindergarten
                ? $this->dailyAttendanceEmail->groupNames($institution->id)
                : collect(),
            'dailyAttendanceRecipients' => $isKindergarten
                ? $this->dailyAttendanceEmail->recipientsByGroup($institution->id)
                : collect(),
            'dailyAttendanceSendTime' => $setting->dailyAttendanceEmailSendTimeLabel(),
            'paymentNotificationSubject' => old(
                'payment_notification_subject',
                $paymentTemplates->resolvedSubject($setting->payment_notification_subject)
            ),
            'paymentNotificationBody' => old(
                'payment_notification_body',
                $paymentTemplates->resolvedBody($setting->payment_notification_body)
            ),
            'paymentNotificationDefaultSubject' => $paymentTemplates->defaultSubject(),
            'paymentNotificationDefaultBody' => $paymentTemplates->defaultBody(),
            'abMenuNotificationSubject' => old(
                'ab_menu_notification_subject',
                $abMenuTemplates->resolvedSubject($setting->ab_menu_notification_subject)
            ),
            'abMenuNotificationBody' => old(
                'ab_menu_notification_body',
                $abMenuTemplates->resolvedBody($setting->ab_menu_notification_body)
            ),
            'abMenuNotificationDefaultSubject' => $abMenuTemplates->defaultSubject(),
            'abMenuNotificationDefaultBody' => $abMenuTemplates->defaultBody(),
            'enabledSettingCount' => collect([
                $setting->send_kitchen_email,
                ! empty($setting->kitchenNotificationEmails()),
                $setting->payment_notification_enabled,
                filled($setting->ab_menu_choice_deadline_day),
                filled($setting->payment_due_day),
                $setting->usesSplitManualTransfer(),
            ])->filter()->count(),
            'splitPaymentConfigurationIssues' => $this->componentService->configurationIssues($institution),
            'rateHistory' => $rateHistory,
            'paymentComponentLabels' => PaymentComponent::labels(),
        ]);
    }

    public function update(
        Request $request,
        PaymentNotificationTemplateService $paymentTemplates,
        AbMenuNotificationTemplateService $abMenuTemplates
    ): RedirectResponse {
        $institution = $this->institution();

        $validated = $request->validate([
            'send_kitchen_email' => ['nullable', 'boolean'],
            'kitchen_notification_emails' => ['nullable', 'string', 'max:2000'],
            'payment_notification_enabled' => ['nullable', 'boolean'],
            'payment_notification_day' => ['required', 'integer', 'between:1,31'],
            'payment_notification_subject' => ['nullable', 'string', 'max:191'],
            'payment_notification_body' => ['nullable', 'string'],
            'payment_due_day' => ['required', 'integer', 'between:1,28'],
            'ab_menu_choice_deadline_day' => ['required', 'integer', 'between:1,31'],
            'ab_menu_notification_subject' => ['nullable', 'string', 'max:191'],
            'ab_menu_notification_body' => ['nullable', 'string'],
            'split_manual_transfer_enabled' => ['nullable', 'boolean'],
            'foundation_account_holder' => ['nullable', 'string', 'max:191'],
            'foundation_account_number' => ['nullable', 'string', 'max:64'],
            'foundation_transfer_reference' => ['nullable', 'string', 'max:191'],
            'kindergarten_account_holder' => ['nullable', 'string', 'max:191'],
            'kindergarten_account_number' => ['nullable', 'string', 'max:64'],
            'kindergarten_transfer_reference' => ['nullable', 'string', 'max:191'],
            'foundation_rate_amount' => ['nullable', 'integer', 'min:0'],
            'foundation_rate_valid_from' => ['nullable', 'date'],
            'kindergarten_rate_amount' => ['nullable', 'integer', 'min:0'],
            'kindergarten_rate_valid_from' => ['nullable', 'date'],
        ]);

        $splitEnabled = $request->boolean('split_manual_transfer_enabled');

        if ($splitEnabled) {
            foreach ([
                'foundation_account_holder' => 'A Zsárica kedvezményezett neve',
                'foundation_account_number' => 'A Zsárica bankszámlaszáma',
                'kindergarten_account_holder' => 'Az óvodai kedvezményezett neve',
                'kindergarten_account_number' => 'Az óvodai bankszámlaszám',
            ] as $field => $label) {
                if (! filled($validated[$field] ?? null) && ! filled($setting->{$field})) {
                    throw ValidationException::withMessages([$field => $label.' kötelező, ha a kétbankszámlás átutalás aktív.']);
                }
            }

            foreach ([
                PaymentComponent::FOUNDATION => ['amount' => 'foundation_rate_amount', 'valid_from' => 'foundation_rate_valid_from'],
                PaymentComponent::KINDERGARTEN => ['amount' => 'kindergarten_rate_amount', 'valid_from' => 'kindergarten_rate_valid_from'],
            ] as $component => $fields) {
                $hasExistingRate = $this->componentService->rateForDate(
                    $institution->id,
                    $component,
                    now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay()
                ) !== null;
                $hasNewRate = filled($validated[$fields['amount']] ?? null) && filled($validated[$fields['valid_from']] ?? null);

                if (! $hasExistingRate && ! $hasNewRate) {
                    throw ValidationException::withMessages([
                        $fields['amount'] => 'A kétbankszámlás modell bekapcsolásához mindkét komponenshez legalább egy napi díjat meg kell adni.',
                    ]);
                }
            }
        }

        // InstitutionSetting::defaults() kizárólag ÚJ rekord létrehozásakor
        // kerül felhasználásra (firstOrCreate). A meglévő rekordot ezután
        // kizárólag ennek az űrlapnak a mezőivel frissítjük ->update()-tel,
        // hogy ez a mentés semmilyen más (pl. CIB/Billingo) beállítást ne
        // tudjon véletlenül alapértékre/NULL-ra állítani.
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $setting->update([
            'send_kitchen_email' => $request->boolean('send_kitchen_email'),
            'kitchen_notification_emails' => $this->parseKitchenEmails($validated['kitchen_notification_emails'] ?? null),
            'payment_notification_enabled' => $request->boolean('payment_notification_enabled'),
            'payment_notification_day' => $validated['payment_notification_day'],
            'payment_notification_subject' => $paymentTemplates->normalizeSubject($validated['payment_notification_subject'] ?? null),
            'payment_notification_body' => $paymentTemplates->normalizeBody($validated['payment_notification_body'] ?? null),
            'payment_due_day' => $validated['payment_due_day'],
            'ab_menu_choice_deadline_day' => $validated['ab_menu_choice_deadline_day'],
            'ab_menu_notification_subject' => $abMenuTemplates->normalizeSubject($validated['ab_menu_notification_subject'] ?? null),
            'ab_menu_notification_body' => $abMenuTemplates->normalizeBody($validated['ab_menu_notification_body'] ?? null),
            'split_manual_transfer_enabled' => $splitEnabled,
            'foundation_account_holder' => trim((string) ($validated['foundation_account_holder'] ?? '')) ?: null,
            'foundation_account_number' => trim((string) ($validated['foundation_account_number'] ?? '')) ?: null,
            'foundation_transfer_reference' => trim((string) ($validated['foundation_transfer_reference'] ?? '')) ?: null,
            'kindergarten_account_holder' => trim((string) ($validated['kindergarten_account_holder'] ?? '')) ?: null,
            'kindergarten_account_number' => trim((string) ($validated['kindergarten_account_number'] ?? '')) ?: null,
            'kindergarten_transfer_reference' => trim((string) ($validated['kindergarten_transfer_reference'] ?? '')) ?: null,
        ]);

        foreach ([
            PaymentComponent::FOUNDATION => ['amount' => 'foundation_rate_amount', 'valid_from' => 'foundation_rate_valid_from'],
            PaymentComponent::KINDERGARTEN => ['amount' => 'kindergarten_rate_amount', 'valid_from' => 'kindergarten_rate_valid_from'],
        ] as $component => $fields) {
            $amount = $validated[$fields['amount']] ?? null;
            $validFrom = $validated[$fields['valid_from']] ?? null;

            if ($amount === null && $validFrom === null) {
                continue;
            }

            if ($amount === null || $validFrom === null) {
                throw ValidationException::withMessages([
                    $fields['amount'] => 'Új díj rögzítéséhez összeg és kezdődátum is szükséges.',
                ]);
            }

            $this->componentService->createRate(
                institutionId: $institution->id,
                component: $component,
                amount: (int) $amount,
                validFrom: CarbonImmutable::parse($validFrom)->startOfDay(),
                createdBy: $request->user()?->id
            );
        }

        return redirect()
            ->route('dashboard.institution.settings.edit')
            ->with('success', 'Az intézményi beállítások frissítve lettek.');
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function parseKitchenEmails(?string $value): array
    {
        $emails = collect(preg_split('/[\r\n,;]+/', (string) $value) ?: [])
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values();

        $validator = validator(
            ['emails' => $emails->all()],
            ['emails.*' => ['email:rfc', 'max:191']]
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'kitchen_notification_emails' => 'Adj meg érvényes e-mail címet vagy címzetteket.',
            ]);
        }

        return $emails->all();
    }

    private function kitchenSendTime(?InstitutionMealSetting $mealSetting): ?string
    {
        if ($mealSetting?->cancellation_hour === null || $mealSetting->cancellation_minute === null) {
            return null;
        }

        return CarbonImmutable::create(
            2000,
            1,
            1,
            $mealSetting->cancellation_hour,
            $mealSetting->cancellation_minute,
            0,
            config('digifood.business_timezone', 'Europe/Budapest')
        )->addMinute()->format('H:i');
    }
}
