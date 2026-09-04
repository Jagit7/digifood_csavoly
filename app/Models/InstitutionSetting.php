<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Finance\PaymentComponent;

class InstitutionSetting extends Model
{
    public const INVOICING_PROVIDER_MANUAL = 'manual';

    public const INVOICING_PROVIDER_BILLINGO = 'billingo';

    public const INVOICING_PROVIDER_SZAMLAZZ_HU = 'szamlazz_hu';

    public const CARD_PAYMENT_PROVIDER_BARION = 'barion';

    public const CARD_PAYMENT_PROVIDER_STRIPE = 'stripe';

    public const CARD_PAYMENT_PROVIDER_SIMPLEPAY = 'simplepay';

    public const CARD_PAYMENT_PROVIDER_CIB = 'cib';

    public const CARD_PAYMENT_PROVIDER_OTP = 'otp';

    public const CARD_PAYMENT_PROVIDER_ERSTE = 'erste';

    public const CARD_PAYMENT_PROVIDER_KH = 'kh';

    public const CARD_PAYMENT_PROVIDER_MBH = 'mbh';

    protected $fillable = [
        'institution_id',
        'send_kitchen_email',
        'kitchen_notification_emails',
        'daily_attendance_email_enabled',
        'daily_attendance_email_send_time',
        'daily_headcount_email_enabled',
        'daily_headcount_email_send_time',
        'payment_notification_enabled',
        'payment_notification_day',
        'payment_notification_subject',
        'payment_notification_body',
        'payment_due_day',
        'vat_rate',
        'ab_menu_choice_deadline_day',
        'ab_menu_notification_subject',
        'ab_menu_notification_body',
        'admin_browser_restriction_enabled',
        'barcode_entry_enabled',
        'barcode_kiosk_pin_hash',
        'barcode_kiosk_meal_type_id',
        'barcode_kiosk_control_token',
        'barcode_kiosk_control_token_hash',
        'barcode_kiosk_control_generated_at',
        'barcode_kiosk_device_token_hash',
        'barcode_kiosk_device_bound_at',
        'barcode_kiosk_device_last_used_at',
        'invoicing_enabled',
        'invoicing_provider',
        'card_payment_enabled',
        'card_payment_provider',
        'card_payment_test_mode',
        'bank_transfer_account_holder',
        'bank_transfer_account_number',
        'split_manual_transfer_enabled',
        'foundation_account_holder',
        'foundation_account_number',
        'foundation_transfer_reference',
        'kindergarten_account_holder',
        'kindergarten_account_number',
        'kindergarten_transfer_reference',
        'cib_terminal_id',
        'cib_secret_key',
        'billingo_api_key',
        'billingo_document_block_id',
        'billingo_default_payment_method',
        'billingo_due_days',
        'billingo_invoice_language',
        'billingo_e_invoice_enabled',
        'billingo_test_mode',
        'billingo_last_successful_sync_at',
        'billingo_sync_last_modified_at',
        'billingo_last_sync_error',
        'szamlazz_hu_agent_key',
        'szamlazz_hu_invoice_prefix',
        'szamlazz_hu_default_payment_method',
        'szamlazz_hu_due_days',
        'szamlazz_hu_invoice_language',
        'szamlazz_hu_e_invoice_enabled',
        'szamlazz_hu_test_mode',
    ];

    protected $casts = [
        'send_kitchen_email' => 'boolean',
        'kitchen_notification_emails' => 'array',
        'daily_attendance_email_enabled' => 'boolean',
        'daily_headcount_email_enabled' => 'boolean',
        'payment_notification_enabled' => 'boolean',
        'payment_notification_day' => 'integer',
        'payment_notification_subject' => 'string',
        'payment_notification_body' => 'string',
        'payment_due_day' => 'integer',
        'vat_rate' => 'decimal:2',
        'ab_menu_choice_deadline_day' => 'integer',
        'ab_menu_notification_subject' => 'string',
        'ab_menu_notification_body' => 'string',
        'admin_browser_restriction_enabled' => 'boolean',
        'barcode_entry_enabled' => 'boolean',
        'barcode_kiosk_meal_type_id' => 'integer',
        // A gyermek/dolgozó vonalkódokéval megegyező minta (ld.
        // EaterBarcodeService / 2026_08_17_160000_encrypt_barcode_tokens):
        // a nyers token titkosítva tárolódik, a beolvasásos kereséshez a
        // barcode_kiosk_control_token_hash (sima string) oszlop szolgál.
        'barcode_kiosk_control_token' => 'encrypted',
        'barcode_kiosk_control_generated_at' => 'datetime',
        'barcode_kiosk_device_bound_at' => 'datetime',
        'barcode_kiosk_device_last_used_at' => 'datetime',
        'invoicing_enabled' => 'boolean',
        'card_payment_enabled' => 'boolean',
        'card_payment_test_mode' => 'boolean',
        'split_manual_transfer_enabled' => 'boolean',
        'cib_secret_key' => 'encrypted',
        'billingo_api_key' => 'encrypted',
        'billingo_due_days' => 'integer',
        'billingo_e_invoice_enabled' => 'boolean',
        'billingo_test_mode' => 'boolean',
        'billingo_last_successful_sync_at' => 'datetime',
        'billingo_sync_last_modified_at' => 'datetime',
        'szamlazz_hu_agent_key' => 'encrypted',
        'szamlazz_hu_due_days' => 'integer',
        'szamlazz_hu_e_invoice_enabled' => 'boolean',
        'szamlazz_hu_test_mode' => 'boolean',
    ];

    protected $hidden = [
        'billingo_api_key',
        'szamlazz_hu_agent_key',
        'cib_secret_key',
    ];

    public static function defaults(): array
    {
        return [
            'send_kitchen_email' => false,
            'kitchen_notification_emails' => [],
            'daily_attendance_email_enabled' => false,
            'daily_attendance_email_send_time' => '07:30:00',
            'daily_headcount_email_enabled' => false,
            'daily_headcount_email_send_time' => '07:30:00',
            'payment_notification_enabled' => false,
            'payment_notification_day' => 5,
            'payment_notification_subject' => null,
            'payment_notification_body' => null,
            'payment_due_day' => 5,
            'vat_rate' => 0,
            'ab_menu_choice_deadline_day' => 20,
            'ab_menu_notification_subject' => null,
            'ab_menu_notification_body' => null,
            // Jelenleg MINDEN intézménynél aktív a max. 2 böngészős
            // admin-korlátozás (ld. InstitutionAdminDevice), ezért az
            // alapértelmezésnek is bekapcsolva kell lennie - így egy
            // meglévő intézmény viselkedése a mező bevezetésével nem
            // változik. Ld. még: adminBrowserRestrictionEnabled() lent.
            'admin_browser_restriction_enabled' => true,
            'barcode_entry_enabled' => false,
            'barcode_kiosk_pin_hash' => null,
            'barcode_kiosk_control_token' => null,
            'barcode_kiosk_control_token_hash' => null,
            'barcode_kiosk_control_generated_at' => null,
            'barcode_kiosk_device_token_hash' => null,
            'barcode_kiosk_device_bound_at' => null,
            'barcode_kiosk_device_last_used_at' => null,
            'invoicing_enabled' => false,
            'invoicing_provider' => null,
            'card_payment_enabled' => false,
            'card_payment_provider' => null,
            'card_payment_test_mode' => true,
            'bank_transfer_account_holder' => null,
            'bank_transfer_account_number' => null,
            'split_manual_transfer_enabled' => false,
            'foundation_account_holder' => null,
            'foundation_account_number' => null,
            'foundation_transfer_reference' => null,
            'kindergarten_account_holder' => null,
            'kindergarten_account_number' => null,
            'kindergarten_transfer_reference' => null,
            'cib_terminal_id' => null,
            'cib_secret_key' => null,
            'billingo_api_key' => null,
            'billingo_document_block_id' => null,
            'billingo_default_payment_method' => null,
            'billingo_due_days' => null,
            'billingo_invoice_language' => null,
            'billingo_e_invoice_enabled' => false,
            'billingo_test_mode' => true,
            'billingo_last_successful_sync_at' => null,
            'billingo_sync_last_modified_at' => null,
            'billingo_last_sync_error' => null,
            'szamlazz_hu_agent_key' => null,
            'szamlazz_hu_invoice_prefix' => null,
            'szamlazz_hu_default_payment_method' => null,
            'szamlazz_hu_due_days' => null,
            'szamlazz_hu_invoice_language' => null,
            'szamlazz_hu_e_invoice_enabled' => false,
            'szamlazz_hu_test_mode' => true,
        ];
    }

    public static function invoicingProviderOptions(): array
    {
        return self::normalizeProviderOptions(config('integrations.invoice_providers', []));
    }

    public static function cardPaymentProviderOptions(): array
    {
        return self::normalizeProviderOptions(config('integrations.payment_providers', []));
    }

    public function hasBillingoApiKey(): bool
    {
        return filled($this->billingo_api_key);
    }

    public function hasSzamlazzHuAgentKey(): bool
    {
        return filled($this->szamlazz_hu_agent_key);
    }

    public function hasCibCredentials(): bool
    {
        return filled($this->cib_terminal_id) && filled($this->cib_secret_key);
    }

    /**
     * Van-e a szülői felület számára megjeleníthető, banki átutalásra
     * használható számlaszám - ettől teljesen függetlenül attól, hogy az
     * intézmény használ-e kártyás fizetést vagy számlázást (ld.
     * ParentMonthlySettlementService::bankTransferInfo()).
     */
    public function hasBankTransferAccount(): bool
    {
        return filled($this->bank_transfer_account_number);
    }

    public function usesSplitManualTransfer(): bool
    {
        return (bool) $this->split_manual_transfer_enabled;
    }

    public function splitManualTransferConfigurationIssues(): array
    {
        if (! $this->usesSplitManualTransfer()) {
            return [];
        }

        $issues = [];

        foreach ([
            PaymentComponent::FOUNDATION => [
                'account_holder' => $this->foundation_account_holder,
                'account_number' => $this->foundation_account_number,
            ],
            PaymentComponent::KINDERGARTEN => [
                'account_holder' => $this->kindergarten_account_holder,
                'account_number' => $this->kindergarten_account_number,
            ],
        ] as $component => $data) {
            $label = PaymentComponent::labels()[$component] ?? $component;

            if (! filled($data['account_holder'])) {
                $issues[] = $label.': hiányzik a kedvezményezett neve';
            }

            if (! filled($data['account_number'])) {
                $issues[] = $label.': hiányzik a bankszámlaszám';
            }
        }

        return $issues;
    }

    /**
     * A CIB titkos kulcs tényleges eredete az admin felület számára:
     * - 'institution': az intézményhez elmentett saját kulcs van használatban;
     * - 'global_fallback': nincs intézményi kulcs, de a CardPaymentService
     *   a .env-ben beállított globális fallback kulcsot használja (lásd
     *   config/cib.php: default_secret_key_base64 / default_secret_key_file);
     * - 'none': se intézményi, se globális kulcs nincs beállítva.
     * A kulcs tényleges értékét ez a metódus sosem adja vissza.
     */
    public function cibSecretKeySource(): string
    {
        if (filled($this->cib_secret_key)) {
            return 'institution';
        }

        if (filled(config('cib.default_secret_key_base64')) || filled(config('cib.default_secret_key_file'))) {
            return 'global_fallback';
        }

        return 'none';
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Az intézmény ÁFA-kulcsa százalékban (pl. 27.0 vagy 5.0). Alapból 0,
     * ha az intézmény még nem állította be - ekkor a nettó és a bruttó
     * (ÁFával növelt) összeg megegyezik.
     */
    public function vatRatePercent(): float
    {
        return (float) $this->vat_rate;
    }

    public function hasVatRate(): bool
    {
        return $this->vatRatePercent() > 0;
    }

    /**
     * Egy nettó (ÁFA nélküli) forintösszeget növel meg az intézmény
     * beállított ÁFA-kulcsával, kerekítve a legközelebbi forintra. A
     * fizetési kötelezettségek (havi elszámolások) nettó alapösszegeit ez
     * alakítja át a ténylegesen fizetendő, ÁFával növelt összeggé.
     */
    public function grossAmount(int $netAmount): int
    {
        return (int) round($netAmount * (1 + $this->vatRatePercent() / 100));
    }

    public function barcodeEntryEnabled(): bool
    {
        return (bool) $this->barcode_entry_enabled;
    }

    /**
     * Központi eldöntési pont: aktív-e ennél az intézménynél az
     * intézményi adminokra vonatkozó "gép szerinti" böngésző-korlátozás
     * (max. InstitutionAdminDevice::MAX_DEVICES_PER_USER jóváhagyott
     * eszköz, superadmin jóváhagyással). Ezt hívja meg az
     * AuthController::login() - ha ez false-t ad vissza, a meglévő
     * eszköz-jóváhagyási logika (verifyInstitutionAdminDevice) teljesen
     * kimarad, de a korábban jóváhagyott/függő eszköz-sorok
     * (InstitutionAdminDevice) nem törlődnek, csak figyelmen kívül
     * maradnak - visszakapcsoláskor ugyanazok az engedélyek élednek újra.
     */
    public function adminBrowserRestrictionEnabled(): bool
    {
        return (bool) $this->admin_browser_restriction_enabled;
    }

    public function hasKioskControlCard(): bool
    {
        return filled($this->barcode_kiosk_control_token_hash);
    }

    public function hasKioskDevice(): bool
    {
        return filled($this->barcode_kiosk_device_token_hash);
    }

    public function kitchenNotificationEmails(): array
    {
        $emails = $this->kitchen_notification_emails;

        if (! is_array($emails)) {
            return [];
        }

        return collect($emails)
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    public function dailyAttendanceEmailSendTimeLabel(): string
    {
        return substr((string) ($this->daily_attendance_email_send_time ?: '07:30:00'), 0, 5);
    }

    public function dailyHeadcountEmailSendTimeLabel(): string
    {
        return substr((string) ($this->daily_headcount_email_send_time ?: '07:30:00'), 0, 5);
    }

    private static function normalizeProviderOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $value => $label) {
            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            if (! is_string($label) && ! is_numeric($label)) {
                continue;
            }

            $normalized[(string) $value] = trim((string) $label);
        }

        return array_filter($normalized, static fn (string $label): bool => $label !== '');
    }
}
