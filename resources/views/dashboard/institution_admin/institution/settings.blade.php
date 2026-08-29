@extends('layouts.superadmin')

@section('title', 'Intézmény beállítások')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Intézmény beállítások',
        'subtitle' => $institution->name . ' · értesítések és A/B menü választási szabályok',
    ])

    <div class="row g-4 mb-4">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív kapcsolók',
            'value' => $enabledSettingCount,
            'subtitle' => 'Bekapcsolt működési beállítás',
            'icon' => 'fa-solid fa-toggle-on',
            'color' => 'blue',
            'colClass' => 'col-xl col-lg-4 col-md-6',
            'bodyClass' => 'position-relative overflow-hidden h-100',
            'contentClass' => 'pe-5',
            'iconClass' => 'position-absolute top-50 end-0 translate-middle-y me-4',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Konyhai e-mail',
            'value' => $setting->send_kitchen_email ? 'Igen' : 'Nem',
            'subtitle' => $kitchenSendTime
                ? 'Küldés ideje: '.$kitchenSendTime
                : 'A lemondási határidő alapján számolódik',
            'icon' => 'fa-solid fa-envelope',
            'color' => $setting->send_kitchen_email ? 'green' : 'orange',
            'size' => 'small',
            'colClass' => 'col-xl col-lg-4 col-md-6',
            'bodyClass' => 'position-relative overflow-hidden h-100',
            'contentClass' => 'pe-5',
            'iconClass' => 'position-absolute top-50 end-0 translate-middle-y me-4',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Fizetési értesítés',
            'value' => $setting->payment_notification_enabled ? 'Igen' : 'Nem',
            'subtitle' => $setting->payment_notification_enabled
                ? 'Kiküldés napja: ' . $setting->payment_notification_day . '.'
                : 'Automatikus kiküldés kikapcsolva',
            'icon' => 'fa-solid fa-bell',
            'color' => 'purple',
            'size' => 'small',
            'colClass' => 'col-xl col-lg-4 col-md-6',
            'bodyClass' => 'position-relative overflow-hidden h-100',
            'contentClass' => 'pe-5',
            'iconClass' => 'position-absolute top-50 end-0 translate-middle-y me-4',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Fizetési határnap',
            'value' => $setting->payment_due_day . '.',
            'subtitle' => 'Fizetési határidő napja',
            'icon' => 'fa-solid fa-calendar-day',
            'color' => 'green',
            'size' => 'small',
            'colClass' => 'col-xl col-lg-4 col-md-6',
            'bodyClass' => 'position-relative overflow-hidden h-100',
            'contentClass' => 'pe-5',
            'iconClass' => 'position-absolute top-50 end-0 translate-middle-y me-4',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'A/B határnap',
            'value' => $setting->ab_menu_choice_deadline_day . '.',
            'subtitle' => 'Eddig választható a havi A/B menü',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'orange',
            'size' => 'small',
            'colClass' => 'col-xl col-lg-4 col-md-6',
            'bodyClass' => 'position-relative overflow-hidden h-100',
            'contentClass' => 'pe-5',
            'iconClass' => 'position-absolute top-50 end-0 translate-middle-y me-4',
        ])
    </div>

    <form method="POST" action="{{ route('dashboard.institution.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h4 class="card-title mb-1">Konyhai értesítések</h4>
                    <div class="text-muted small">Automatikus napi összesítő a következő tényleges étkezési napról.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="send_kitchen_email" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="send_kitchen_email" name="send_kitchen_email" value="1"
                                   @checked((bool) old('send_kitchen_email', $setting->send_kitchen_email))>
                            <label class="form-check-label" for="send_kitchen_email">
                                Konyhai értesítés engedélyezve
                            </label>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label class="form-label" for="kitchen_notification_emails">Konyhai e-mail cím(ek)</label>
                        <textarea
                            id="kitchen_notification_emails"
                            name="kitchen_notification_emails"
                            rows="4"
                            class="form-control @error('kitchen_notification_emails') is-invalid @enderror"
                            placeholder="konyha@example.hu&#10;etkeztetes@example.hu"
                        >{{ old('kitchen_notification_emails', $kitchenRecipientsText) }}</textarea>
                        <div class="form-text">Egy vagy több címzett megadható. Használj új sort, vesszőt vagy pontosvesszőt az elválasztáshoz.</div>
                        @error('kitchen_notification_emails') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <div class="fw-semibold mb-1">Küldési idő</div>
                    <div class="small">
                        A konyhai összesítő a következő napi lemondási határidő után 1 perccel kerül kiküldésre.
                        @if($mealSetting?->cancellation_hour !== null && $mealSetting->cancellation_minute !== null && $kitchenSendTime)
                            Jelenlegi lemondási határidő:
                            <strong>{{ sprintf('%02d:%02d', $mealSetting->cancellation_hour, $mealSetting->cancellation_minute) }}</strong>,
                            így a konyhai összesítő küldése:
                            <strong>{{ $kitchenSendTime }}</strong>.
                        @else
                            A pontos idő a „Kedvezmények és szabályok” oldalon beállított lemondási határidőből számolódik.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h4 class="card-title mb-1">Fizetési időszak értesítés</h4>
                    <div class="text-muted small">Automatikus családi értesítés a lezárt és ténylegesen fizetendő havi elszámolásokról.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-lg-6 mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="payment_notification_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="payment_notification_enabled" name="payment_notification_enabled" value="1"
                                   @checked((bool) old('payment_notification_enabled', $setting->payment_notification_enabled))>
                            <label class="form-check-label" for="payment_notification_enabled">
                                E-mail értesítés fizetési időszakról
                            </label>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="payment_notification_day">Kiküldés napja</label>
                        <div class="input-group">
                            <input id="payment_notification_day" type="number" name="payment_notification_day"
                                   class="form-control @error('payment_notification_day') is-invalid @enderror"
                                   value="{{ old('payment_notification_day', $setting->payment_notification_day) }}"
                                   min="1" max="31" required>
                            <span class="input-group-text">nap</span>
                        </div>
                        @error('payment_notification_day') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="payment_notification_subject">E-mail tárgya</label>
                    <input
                        id="payment_notification_subject"
                        type="text"
                        name="payment_notification_subject"
                        class="form-control @error('payment_notification_subject') is-invalid @enderror"
                        value="{{ $paymentNotificationSubject }}"
                        maxlength="191"
                    >
                    @error('payment_notification_subject') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="payment_notification_body">E-mail szövege</label>
                    <textarea
                        name="payment_notification_body"
                        id="payment_notification_body"
                        rows="8"
                        class="form-control @error('payment_notification_body') is-invalid @enderror"
                    >{{ $paymentNotificationBody }}</textarea>
                    <div class="form-text">
                        Az e-mailben a fizetendő összeg, a fizetési határidő és a havi elszámolás linkje automatikusan megjelenik, ezeket nem szükséges a fenti szövegben megadni.
                    </div>
                    @error('payment_notification_body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="reset-payment-notification-template"
                        data-default-subject="{{ e($paymentNotificationDefaultSubject) }}"
                        data-default-body="{{ e($paymentNotificationDefaultBody) }}"
                    >
                        <i class="fa-solid fa-rotate-left me-1"></i>Alapértelmezett szöveg visszaállítása
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h4 class="card-title mb-1">Fizetési határnap</h4>
                    <div class="text-muted small">A havi fizetési kötelezettségekhez eddig a napig rögzített lemondás számít azonnali levonásnak.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-lg-4 mb-3">
                        <label class="form-label" for="payment_due_day">Határnap</label>
                        <div class="input-group">
                            <input id="payment_due_day" type="number" name="payment_due_day"
                                   class="form-control @error('payment_due_day') is-invalid @enderror"
                                   value="{{ old('payment_due_day', $setting->payment_due_day) }}"
                                   min="1" max="28" required>
                            <span class="input-group-text">nap</span>
                        </div>
                        @error('payment_due_day') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h4 class="card-title mb-1">Kétbankszámlás havi elszámolás</h4>
                    <div class="text-muted small">Külön Zsárica Alapítvány és óvodai átutalási adatok, időszakos napi díjakkal.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-4">
                    <input type="hidden" name="split_manual_transfer_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="split_manual_transfer_enabled" name="split_manual_transfer_enabled" value="1"
                           @checked((bool) old('split_manual_transfer_enabled', $setting->split_manual_transfer_enabled))>
                    <label class="form-check-label" for="split_manual_transfer_enabled">
                        Két külön bankszámlára bontott kézi átutalás aktív
                    </label>
                </div>

                @if($splitPaymentConfigurationIssues !== [])
                    <div class="alert alert-warning">
                        <div class="fw-semibold mb-1">Hiányzó beállítások</div>
                        <ul class="mb-0 ps-3">
                            @foreach($splitPaymentConfigurationIssues as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-xl-6">
                        <div class="border rounded-4 p-4 h-100">
                            <h5 class="mb-3">Zsárica Alapítvány rész</h5>
                            <div class="mb-3">
                                <label class="form-label" for="foundation_account_holder">Kedvezményezett neve</label>
                                <input id="foundation_account_holder" type="text" name="foundation_account_holder"
                                       class="form-control @error('foundation_account_holder') is-invalid @enderror"
                                       value="{{ old('foundation_account_holder', $setting->foundation_account_holder) }}" maxlength="191">
                                @error('foundation_account_holder') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="foundation_account_number">Bankszámlaszám</label>
                                <input id="foundation_account_number" type="text" name="foundation_account_number"
                                       class="form-control @error('foundation_account_number') is-invalid @enderror"
                                       value="{{ old('foundation_account_number', $setting->foundation_account_number) }}" maxlength="64">
                                @error('foundation_account_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="foundation_transfer_reference">Közlemény sablon</label>
                                <input id="foundation_transfer_reference" type="text" name="foundation_transfer_reference"
                                       class="form-control @error('foundation_transfer_reference') is-invalid @enderror"
                                       value="{{ old('foundation_transfer_reference', $setting->foundation_transfer_reference) }}" maxlength="191"
                                       placeholder="{children} - {month}">
                                <div class="form-text">Használható helyettesítők: <code>{children}</code>, <code>{month}</code>.</div>
                                @error('foundation_transfer_reference') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="foundation_rate_amount">Új napi díj (Ft)</label>
                                    <input id="foundation_rate_amount" type="number" min="0" step="1" name="foundation_rate_amount"
                                           class="form-control @error('foundation_rate_amount') is-invalid @enderror"
                                           value="{{ old('foundation_rate_amount') }}">
                                    @error('foundation_rate_amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="foundation_rate_valid_from">Érvényes ettől</label>
                                    <input id="foundation_rate_valid_from" type="date" name="foundation_rate_valid_from"
                                           class="form-control @error('foundation_rate_valid_from') is-invalid @enderror"
                                           value="{{ old('foundation_rate_valid_from') }}">
                                    @error('foundation_rate_valid_from') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="small text-muted fw-semibold mb-2">Díjtörténet</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Érvényes ettől</th>
                                        <th class="text-end">Napi díj</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse(($rateHistory[\App\Support\Finance\PaymentComponent::FOUNDATION] ?? collect()) as $rate)
                                        <tr>
                                            <td>{{ $rate->valid_from?->format('Y.m.d.') }}</td>
                                            <td class="text-end">{{ number_format($rate->amount, 0, ',', ' ') }} Ft</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-muted">Még nincs rögzített díj.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="border rounded-4 p-4 h-100">
                            <h5 class="mb-3">Óvodai étkezési díj rész</h5>
                            <div class="mb-3">
                                <label class="form-label" for="kindergarten_account_holder">Kedvezményezett neve</label>
                                <input id="kindergarten_account_holder" type="text" name="kindergarten_account_holder"
                                       class="form-control @error('kindergarten_account_holder') is-invalid @enderror"
                                       value="{{ old('kindergarten_account_holder', $setting->kindergarten_account_holder) }}" maxlength="191">
                                @error('kindergarten_account_holder') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="kindergarten_account_number">Bankszámlaszám</label>
                                <input id="kindergarten_account_number" type="text" name="kindergarten_account_number"
                                       class="form-control @error('kindergarten_account_number') is-invalid @enderror"
                                       value="{{ old('kindergarten_account_number', $setting->kindergarten_account_number) }}" maxlength="64">
                                @error('kindergarten_account_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="kindergarten_transfer_reference">Közlemény sablon</label>
                                <input id="kindergarten_transfer_reference" type="text" name="kindergarten_transfer_reference"
                                       class="form-control @error('kindergarten_transfer_reference') is-invalid @enderror"
                                       value="{{ old('kindergarten_transfer_reference', $setting->kindergarten_transfer_reference) }}" maxlength="191"
                                       placeholder="{children} - {month}">
                                <div class="form-text">Használható helyettesítők: <code>{children}</code>, <code>{month}</code>.</div>
                                @error('kindergarten_transfer_reference') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="kindergarten_rate_amount">Új napi díj (Ft)</label>
                                    <input id="kindergarten_rate_amount" type="number" min="0" step="1" name="kindergarten_rate_amount"
                                           class="form-control @error('kindergarten_rate_amount') is-invalid @enderror"
                                           value="{{ old('kindergarten_rate_amount') }}">
                                    @error('kindergarten_rate_amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="kindergarten_rate_valid_from">Érvényes ettől</label>
                                    <input id="kindergarten_rate_valid_from" type="date" name="kindergarten_rate_valid_from"
                                           class="form-control @error('kindergarten_rate_valid_from') is-invalid @enderror"
                                           value="{{ old('kindergarten_rate_valid_from') }}">
                                    @error('kindergarten_rate_valid_from') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="small text-muted fw-semibold mb-2">Díjtörténet</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Érvényes ettől</th>
                                        <th class="text-end">Napi díj</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse(($rateHistory[\App\Support\Finance\PaymentComponent::KINDERGARTEN] ?? collect()) as $rate)
                                        <tr>
                                            <td>{{ $rate->valid_from?->format('Y.m.d.') }}</td>
                                            <td class="text-end">{{ number_format($rate->amount, 0, ',', ' ') }} Ft</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-muted">Még nincs rögzített díj.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h4 class="card-title mb-1">A/B menü választás</h4>
                    <div class="text-muted small">A havi A/B menü kiválasztásának határideje.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-lg-4 mb-3">
                        <label class="form-label" for="ab_menu_choice_deadline_day">Választási határnap</label>
                        <div class="input-group">
                            <input id="ab_menu_choice_deadline_day" type="number" name="ab_menu_choice_deadline_day"
                                   class="form-control @error('ab_menu_choice_deadline_day') is-invalid @enderror"
                                   value="{{ old('ab_menu_choice_deadline_day', $setting->ab_menu_choice_deadline_day) }}"
                                   min="1" max="31" required>
                            <span class="input-group-text">nap</span>
                        </div>
                        @error('ab_menu_choice_deadline_day') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="ab_menu_notification_subject">Menüválasztási értesítő e-mail tárgya</label>
                    <input
                        id="ab_menu_notification_subject"
                        type="text"
                        name="ab_menu_notification_subject"
                        class="form-control @error('ab_menu_notification_subject') is-invalid @enderror"
                        value="{{ $abMenuNotificationSubject }}"
                        maxlength="191"
                    >
                    @error('ab_menu_notification_subject') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ab_menu_notification_body">Menüválasztási értesítő e-mail szövege</label>
                    <textarea
                        name="ab_menu_notification_body"
                        id="ab_menu_notification_body"
                        rows="8"
                        class="form-control @error('ab_menu_notification_body') is-invalid @enderror"
                    >{{ $abMenuNotificationBody }}</textarea>
                    <div class="form-text">
                        Ez a szöveg jelenik meg abban az e-mailben, amit a szülők automatikusan kapnak, amikor egy A/B menütervhez elindítod a menüválasztást. Az érintett gyermek(ek), az időszak és a választási határidő automatikusan megjelenik, ezeket nem szükséges a fenti szövegben megadni.
                    </div>
                    @error('ab_menu_notification_body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3 mb-4">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="reset-ab-menu-notification-template"
                        data-default-subject="{{ e($abMenuNotificationDefaultSubject) }}"
                        data-default-body="{{ e($abMenuNotificationDefaultBody) }}"
                    >
                        <i class="fa-solid fa-rotate-left me-1"></i>Alapértelmezett szöveg visszaállítása
                    </button>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-start py-3 px-4">
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Összes fenti beállítás mentése
                    </button>
                    <div class="form-text mt-2 mb-0">
                        Ez a gomb együtt menti a konyhai értesítéseket, a fizetési időszak értesítést, a fizetési határnapot és az A/B menü választási beállításokat.
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if($isKindergarten)
        @include('dashboard.institution_admin.institution.partials.daily-attendance-email-settings')
    @endif
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const templateResets = [
                {
                    subjectId: 'payment_notification_subject',
                    bodyId: 'payment_notification_body',
                    buttonId: 'reset-payment-notification-template',
                },
                {
                    subjectId: 'ab_menu_notification_subject',
                    bodyId: 'ab_menu_notification_body',
                    buttonId: 'reset-ab-menu-notification-template',
                },
            ];

            templateResets.forEach(function (config) {
                const subjectInput = document.getElementById(config.subjectId);
                const bodyInput = document.getElementById(config.bodyId);
                const resetButton = document.getElementById(config.buttonId);

                if (!resetButton) {
                    return;
                }

                resetButton.addEventListener('click', function () {
                    Swal.fire({
                        title: 'Visszaállítod az alapértelmezett szöveget?',
                        text: 'A jelenlegi tárgy és szöveg helyére a Digifood alapértelmezett értesítőszövege kerül.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Igen, visszaállítom',
                        cancelButtonText: 'Mégse',
                        confirmButtonColor: '#886CC0',
                        cancelButtonColor: '#6c757d'
                    }).then(function (result) {
                        if (!result.isConfirmed) {
                            return;
                        }

                        if (subjectInput) {
                            subjectInput.value = resetButton.dataset.defaultSubject || '';
                        }

                        if (bodyInput) {
                            bodyInput.value = resetButton.dataset.defaultBody || '';
                        }
                    });
                });
            });
        });
    </script>
@endpush
