@extends('layouts.employee')

@section('page_title', 'Étkezéseim és lemondásaim')

@push('styles')
    <style>
        #restoreSelectedButton {
            min-width: 220px;
            color: #17643d;
            border-color: #17643d;
            background-color: #fff;
        }

        #restoreSelectedButton:hover:not(:disabled) {
            color: #fff;
            background-color: #17643d;
            border-color: #17643d;
        }

        #restoreSelectedButton:disabled {
            color: #6c757d !important;
            background-color: #f8f9fa !important;
            border-color: #ced4da !important;
            opacity: 1 !important;
        }

        #cancelSelectedButton {
            min-width: 240px;
        }


        /* Prémium lemondási modal */
        #mealCancellationModal .modal-dialog {
            max-width: 860px;
        }

        #mealCancellationModal .modal-content {
            border: 0;
        }

        #mealCancellationModal + .modal-backdrop,
        .modal-backdrop.show {
            opacity: .58;
            backdrop-filter: blur(3px);
        }

        .parent-meal-modal {
            overflow: hidden;
            border-radius: 24px;
            background: #f7f9fc;
            box-shadow: 0 28px 70px rgba(30, 42, 70, .28), 0 8px 24px rgba(30, 42, 70, .14);
        }

        .parent-meal-modal-header {
            position: relative;
            display: block;
            padding: 0;
            border-bottom: 1px solid rgba(50, 66, 94, .08);
            background: linear-gradient(135deg, #edf9fb 0%, #f5f8ff 55%, #ffffff 100%);
        }

        .parent-meal-modal-header-bar {
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, #4d9fb0, #5269c8);
        }

        .parent-meal-modal-header-bar--school {
            background: linear-gradient(90deg, #35b5bf 0%, #4378d1 100%);
        }

        .parent-meal-modal-header-bar--weekend {
            background: linear-gradient(90deg, #7a5fd0 0%, #a85fc4 100%);
        }

        .parent-meal-modal-header-bar--break {
            background: linear-gradient(90deg, #ee9b3d 0%, #ef6f61 100%);
        }

        .parent-meal-modal-header-bar--holiday {
            background: linear-gradient(90deg, #e55782 0%, #d94a4a 100%);
        }

        .parent-meal-modal-header-content {
            padding: 26px 30px 24px;
        }

        .parent-meal-modal-hero {
            display: flex;
            align-items: flex-start;
            gap: 18px;
        }

        .parent-meal-modal-icon {
            flex: 0 0 58px;
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            color: #fff;
            font-size: 24px;
            background: linear-gradient(135deg, #35b5bf, #4378d1);
            box-shadow: 0 10px 24px rgba(67, 120, 209, .28);
        }

        .parent-meal-modal-icon--weekend {
            background: linear-gradient(135deg, #7a5fd0, #a85fc4);
            box-shadow: 0 10px 24px rgba(122, 95, 208, .28);
        }

        .parent-meal-modal-icon--break {
            background: linear-gradient(135deg, #ee9b3d, #ef6f61);
            box-shadow: 0 10px 24px rgba(238, 155, 61, .28);
        }

        .parent-meal-modal-icon--holiday {
            background: linear-gradient(135deg, #e55782, #d94a4a);
            box-shadow: 0 10px 24px rgba(229, 87, 130, .26);
        }

        .parent-meal-modal-heading {
            min-width: 0;
            flex: 1;
        }

        .parent-meal-modal-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .parent-meal-modal-eyebrow {
            color: #3e7b91;
            font-size: .78rem;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .parent-meal-modal-title {
            color: #26324d;
            font-size: 1.48rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .parent-meal-modal-subtitle {
            margin-top: 7px;
            color: #6d7587;
            font-size: .95rem;
            line-height: 1.55;
        }

        .parent-meal-modal-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 22px;
        }

        .parent-meal-modal-summary-card {
            position: relative;
            padding: 14px 16px 14px 48px;
            border: 1px solid rgba(64, 105, 158, .14);
            border-radius: 16px;
            background: rgba(255, 255, 255, .82);
            box-shadow: 0 8px 20px rgba(61, 79, 112, .07);
        }

        .parent-meal-modal-summary-card::before {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #4f78b8;
            font-size: 1rem;
            content: "\f073";
        }

        .parent-meal-modal-summary-card--deadline::before {
            color: #d26e38;
            content: "\f017";
        }

        .parent-meal-modal-summary-label {
            display: block;
            margin-bottom: 2px;
            color: #8a92a2;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .parent-meal-modal-summary-value {
            display: block;
            color: #303b55;
            font-size: .94rem;
            font-weight: 700;
        }

        .parent-meal-modal-close {
            position: absolute;
            top: 21px;
            right: 22px;
            z-index: 3;
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, .88);
            box-shadow: 0 6px 16px rgba(45, 58, 88, .12);
            opacity: .72;
        }

        .parent-meal-modal-close:hover {
            opacity: 1;
            transform: rotate(4deg);
        }

        .parent-meal-modal-body {
            padding: 24px 30px 26px;
            background:
                radial-gradient(circle at top right, rgba(73, 155, 181, .08), transparent 34%),
                #f7f9fc;
        }

        .parent-meal-modal-rows {
            display: grid;
            gap: 15px;
        }

        .parent-modal-row {
            overflow: hidden;
            border: 1px solid #e1e7f0;
            border-left: 5px solid #42a97d;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 9px 26px rgba(50, 68, 104, .08);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .parent-modal-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 13px 30px rgba(50, 68, 104, .12);
        }

        .parent-modal-row.is-cancelled {
            border-left-color: #df6f63;
            background: linear-gradient(90deg, rgba(223, 111, 99, .07), #fff 22%);
        }

        .parent-modal-row.is-locked,
        .parent-modal-row.is-disabled {
            border-left-color: #a7afbe;
            background: linear-gradient(90deg, rgba(167, 175, 190, .08), #fff 22%);
        }

        .parent-modal-row-top {
            padding: 18px 20px 15px;
        }

        .parent-modal-row-title {
            color: #2f3950;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .parent-modal-row-meta {
            margin-top: 3px;
            color: #727b8d;
            font-size: .9rem;
        }

        .parent-modal-row-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .parent-modal-row-badge::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }

        .parent-modal-row-badge.is-active {
            color: #16734a;
            background: #e8f7ef;
        }

        .parent-modal-row-badge.is-cancelled {
            color: #b5483f;
            background: #fff0ee;
        }

        .parent-modal-row-badge.is-locked {
            color: #687184;
            background: #eef1f5;
        }

        .parent-modal-row-status {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 11px;
            color: #687184;
            font-size: .82rem;
            background: #f5f7fa;
        }

        .parent-modal-row-separator {
            margin: 0 6px;
            color: #aab1bd;
        }

        .parent-modal-row-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-height: 58px;
            padding: 12px 20px;
            border-top: 1px solid #edf0f5;
            background: linear-gradient(90deg, #fbfcfe, #f5f8fc);
        }

        .parent-modal-row-actions.is-empty {
            justify-content: flex-start;
        }

        .parent-modal-row-actions:not(.is-empty) {
            background: linear-gradient(90deg, #fff4ec, #fff8f2);
            border-top-color: #f3d9c4;
        }

        .parent-modal-row-actions .parent-meal-switch-control-label {
            font-size: .98rem;
        }

        .parent-modal-row-note {
            color: #8a92a2;
            font-size: .86rem;
        }

        .parent-meal-switch-control {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .parent-meal-switch-control input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .parent-meal-switch-control-track {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 27px;
            border: 1px solid #cdd5df;
            border-radius: 999px;
            background: #e9edf2;
            transition: .2s ease;
        }

        .parent-meal-switch-control-track::after {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 19px;
            height: 19px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 2px 6px rgba(27, 40, 65, .24);
            transition: .2s ease;
            content: "";
        }

        .parent-meal-switch-control input:checked + .parent-meal-switch-control-track {
            border-color: #db724b;
            background: linear-gradient(135deg, #eb8b50, #d85c48);
            box-shadow: 0 0 0 4px rgba(223, 105, 72, .12);
        }

        .parent-meal-switch-control input:checked + .parent-meal-switch-control-track::after {
            transform: translateX(21px);
        }

        .parent-meal-switch-control-label {
            color: #3e485d;
            font-size: .9rem;
            font-weight: 700;
        }

        .parent-meal-modal-footer {
            padding: 18px 30px 22px;
            border-top: 1px solid #e6eaf0;
            background: #fff;
            box-shadow: 0 -8px 24px rgba(41, 57, 91, .05);
        }

        .parent-meal-modal-secondary,
        .parent-meal-modal-primary {
            min-height: 46px;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 750;
        }

        .parent-meal-modal-primary {
            border: 0;
            background: linear-gradient(135deg, #e9864d, #d75d49);
            box-shadow: 0 9px 20px rgba(215, 93, 73, .25);
        }

        .parent-meal-modal-primary:hover:not(:disabled) {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #df7840, #c94f3e);
            box-shadow: 0 12px 24px rgba(201, 79, 62, .3);
        }

        .parent-meal-modal-primary:disabled {
            border: 1px solid #dfe4eb;
            color: #9aa2af;
            background: #edf0f4;
            box-shadow: none;
            opacity: 1;
        }

        #restoreSelectedButton {
            border-width: 1px;
            border-radius: 12px;
        }

        @media (max-width: 767.98px) {
            #mealCancellationModal .modal-dialog {
                margin: .6rem;
            }

            .parent-meal-modal {
                border-radius: 19px;
            }

            .parent-meal-modal-header-content,
            .parent-meal-modal-body,
            .parent-meal-modal-footer {
                padding-left: 18px;
                padding-right: 18px;
            }

            .parent-meal-modal-hero {
                gap: 12px;
            }

            .parent-meal-modal-icon {
                flex-basis: 48px;
                width: 48px;
                height: 48px;
                border-radius: 15px;
                font-size: 20px;
            }

            .parent-meal-modal-title {
                font-size: 1.25rem;
            }

            .parent-meal-modal-title-row {
                display: block;
            }

            .parent-meal-modal-summary {
                grid-template-columns: 1fr;
            }

            .parent-modal-row-actions {
                justify-content: flex-start;
            }

            .parent-meal-modal-footer .d-flex {
                flex-direction: column-reverse;
            }

            .parent-meal-modal-footer .btn,
            #restoreSelectedButton,
            #cancelSelectedButton {
                width: 100%;
                min-width: 0;
            }
        }
    </style>
@endpush

@section('content')

<div class="row">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h2 class="mb-1">Étkezéseim és lemondásaim</h2>
                <p class="mb-0 text-muted">A naptárban a saját étkezései és a még határidőn belül módosítható napok kezelhetők.</p>
            </div>
        </div>
    </div>
</div>

@if($employees->isEmpty())
    <div class="alert alert-warning">
        Jelenleg nincs aktív dolgozói jogviszonya rögzítve, ezért étkezés-lemondás nem érhető el.
    </div>
@else

<div class="card">
        <div class="card-header parent-meal-toolbar">
        @if($employees->count() > 1)
            <form method="GET" action="{{ route('employee.meal-cancellations') }}" class="parent-meal-filter-form">
                <input type="hidden" name="view" value="{{ $viewMode }}">
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                <label for="employee_id" class="mb-0 fw-semibold">Jogviszony:</label>
                <select name="employee_id" id="employee_id" class="form-control parent-meal-select" onchange="this.form.submit()">
                    @foreach($employees as $option)
                        <option value="{{ $option->id }}" @selected((string) $option->id === $selectedEmployeeId)>
                            {{ $option->name }} ({{ $option->institution->name ?? '' }})
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        <div class="parent-meal-switch">
            <div class="btn-group" role="group" aria-label="Naptárnézet">
                <a class="btn {{ $viewMode === 'week' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('employee.meal-cancellations', ['view' => 'week', 'date' => $selectedDate->toDateString(), 'employee_id' => $selectedEmployeeId]) }}">Heti nézet</a>
                <a class="btn {{ $viewMode === 'month' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('employee.meal-cancellations', ['view' => 'month', 'date' => $selectedDate->toDateString(), 'employee_id' => $selectedEmployeeId]) }}">Havi nézet</a>
            </div>
        </div>

            <div class="parent-meal-nav">
            <a class="btn btn-light"
               aria-label="Előző időszak"
               href="{{ route('employee.meal-cancellations', ['view' => $viewMode, 'date' => $previousDate->toDateString(), 'employee_id' => $selectedEmployeeId]) }}">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <strong class="parent-meal-period">{{ $periodLabel }}</strong>
            <a class="btn btn-light"
               aria-label="Következő időszak"
               href="{{ route('employee.meal-cancellations', ['view' => $viewMode, 'date' => $nextDate->toDateString(), 'employee_id' => $selectedEmployeeId]) }}">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <a class="btn btn-outline-secondary"
               href="{{ route('employee.meal-cancellations', ['view' => $viewMode, 'date' => $today->toDateString(), 'employee_id' => $selectedEmployeeId]) }}">Mai nap</a>
            </div>

            <div class="parent-meal-help">
                <button
                    type="button"
                    class="btn btn-light btn-sm parent-meal-help-button"
                    data-bs-toggle="popover"
                    data-bs-trigger="focus"
                    data-bs-placement="bottom"
                    data-bs-custom-class="parent-meal-help-popover"
                    data-bs-title="Hogyan működik?"
                    data-bs-content="A napkártya vagy a Kezelés gomb megnyitja az adott nap étkezését. Itt a még határidőn belüli tétel lemondható vagy visszaállítható."
                    aria-label="Hogyan működik a naptár?"
                >
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Hogyan működik?</span>
                </button>
            </div>
        </div>

    <div class="card-body">
        <div class="parent-meal-legend mb-3">
            @foreach($legend as $item)
                <span class="parent-meal-legend-item">
                    <span class="parent-meal-dot {{ $item['class'] }}"></span>
                    {{ $item['label'] }}
                </span>
            @endforeach
        </div>

        <div class="parent-meal-grid parent-meal-grid--{{ $viewMode }}">
            @foreach($weekdayLabels as $label)
                <div class="parent-meal-weekday">{{ $label }}</div>
            @endforeach

            @foreach($days as $day)
                <div class="parent-meal-day {{ $day['tone_class'] }} {{ !$day['is_current_month'] ? 'outside' : '' }} {{ $day['is_actionable'] ? 'is-actionable' : '' }}"
                     @if($day['is_actionable'])
                         data-calendar-day="{{ $day['date']->toDateString() }}"
                         data-employee-filter="{{ $selectedEmployeeId }}"
                         role="button"
                         tabindex="0"
                     @endif>
                    <div class="parent-meal-head">
                        <div class="parent-meal-date-block">
                            <span class="parent-meal-weekday-mobile">{{ $day['date']->locale('hu')->isoFormat('dddd') }}</span>
                            <div class="parent-meal-date" style="text-align:center;">{{ $day['date']->locale('hu')->isoFormat('MMMM D.') }}</div>
                            <div class="parent-meal-type {{ $day['type_class'] }}">
                                <i class="fa-regular fa-calendar"></i>
                                <span class="parent-meal-type-text">{{ $day['type_label'] }}</span>
                            </div>
                        </div>
                        <span class="parent-meal-state">
                            <i class="{{ $day['state_icon'] }}"></i>
                            <span class="parent-meal-state-text">{{ $day['state_badge'] }}</span>
                        </span>
                    </div>

                    <div class="parent-meal-children">
                        @foreach($day['rows'] as $row)
                            <div class="parent-meal-child">
                                <div class="parent-meal-child-meta">{{ !empty($row['meal_types']) ? implode(', ', $row['meal_types']) : $row['meal_label'] }}</div>
                                <span class="parent-meal-status
                                    {{ $row['can_cancel'] ? 'status-active' : '' }}
                                    {{ $row['is_cancelled'] ? 'status-cancelled' : '' }}
                                    {{ (!$row['can_cancel'] && !$row['can_restore'] && !$row['is_cancelled']) ? 'status-expired' : '' }}">
                                    <i class="fa-solid {{ $row['is_cancelled'] ? 'fa-ban' : ($row['can_cancel'] ? 'fa-hand-pointer' : 'fa-circle-info') }}"></i>
                                    <span class="parent-meal-status-text">{{ $row['status'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="parent-meal-footer">
                        @if($day['deadline_label'])
                            <div class="parent-meal-deadline">{{ $day['deadline_label'] }}</div>
                        @endif
                        @if($day['actionable_count'] > 0)
                            <button type="button" class="btn btn-outline-primary btn-sm parent-meal-open-day" data-open-day aria-label="Napi étkezés kezelése: {{ $day['date']->locale('hu')->isoFormat('YYYY. MMMM D.') }}">
                                <i class="fa-solid fa-hand-pointer"></i>
                                <span>Kezelés</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="modal fade" id="mealCancellationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content parent-meal-modal">
            <div class="modal-header parent-meal-modal-header">
                <div class="parent-meal-modal-header-bar" id="mealCancellationModalHeaderBar"></div>
                <div class="parent-meal-modal-header-content">
                    <div class="parent-meal-modal-hero">
                        <div class="parent-meal-modal-icon" id="mealCancellationModalIcon" aria-hidden="true">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                        <div class="parent-meal-modal-heading">
                            <div class="parent-meal-modal-title-row">
                                <div>
                                    <p class="parent-meal-modal-eyebrow mb-1" id="mealCancellationModalDayType">Nap típusa</p>
                                    <h5 class="modal-title parent-meal-modal-title" id="mealCancellationModalTitle">Étkezés kezelése</h5>
                                </div>
                            </div>
                            <div class="parent-meal-modal-subtitle" id="mealCancellationModalSubtitle"></div>
                        </div>
                    </div>
                    <div class="parent-meal-modal-summary">
                        <div class="parent-meal-modal-summary-card">
                            <span class="parent-meal-modal-summary-label">Dátum</span>
                            <span class="parent-meal-modal-summary-value" id="mealCancellationModalDateLabel"></span>
                        </div>
                        <div class="parent-meal-modal-summary-card parent-meal-modal-summary-card--deadline">
                            <span class="parent-meal-modal-summary-label">Lemondási határidő</span>
                            <span class="parent-meal-modal-summary-value" id="mealCancellationModalDeadline"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close parent-meal-modal-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
            </div>
            <div class="modal-body parent-meal-modal-body">
                <div id="mealCancellationModalRows" class="parent-meal-modal-rows"></div>
            </div>
            <div class="modal-footer parent-meal-modal-footer">
                <div class="d-flex gap-2 flex-wrap justify-content-end w-100">
                    <button type="button" class="btn btn-light parent-meal-modal-secondary" data-bs-dismiss="modal">Mégse</button>
                    <button type="button" class="btn btn-outline-primary parent-meal-modal-secondary" id="restoreSelectedButton" disabled>
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>Étkezés visszaállítása</span>
                    </button>
                    <button type="button" class="btn btn-primary parent-meal-modal-primary" id="cancelSelectedButton" disabled>
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Lemondás mentése</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('employee.meal-cancellations.store') }}" id="cancelMealForm" class="d-none">
    @csrf
    <input type="hidden" name="service_date" id="cancelMealDate">
    <input type="hidden" name="employee_id" id="cancelMealEmployeeId">
</form>

<form method="POST" action="{{ route('employee.meal-cancellations.restore') }}" id="restoreMealForm" class="d-none">
    @csrf
    <input type="hidden" name="service_date" id="restoreMealDate">
    <input type="hidden" name="employee_id" id="restoreMealEmployeeId">
</form>

@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('parent-meal-cancellation-page');
    document.documentElement.classList.add('parent-meal-cancellation-page-root');

    const modalElement = document.getElementById('mealCancellationModal');
    if (!modalElement) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const title = document.getElementById('mealCancellationModalTitle');
    const subtitle = document.getElementById('mealCancellationModalSubtitle');
    const headerBar = document.getElementById('mealCancellationModalHeaderBar');
    const headerIcon = document.getElementById('mealCancellationModalIcon');
    const dayType = document.getElementById('mealCancellationModalDayType');
    const dateLabel = document.getElementById('mealCancellationModalDateLabel');
    const deadline = document.getElementById('mealCancellationModalDeadline');
    const rowsContainer = document.getElementById('mealCancellationModalRows');
    const cancelButton = document.getElementById('cancelSelectedButton');
    const restoreButton = document.getElementById('restoreSelectedButton');
    const cancelForm = document.getElementById('cancelMealForm');
    const restoreForm = document.getElementById('restoreMealForm');
    const helpPopoverButtons = Array.from(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const helpPopovers = helpPopoverButtons.map(function (button) {
        return new bootstrap.Popover(button, {
            container: 'body',
            html: false,
            sanitize: true,
            trigger: 'focus'
        });
    });
    let currentDate = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getDayTheme(dayTypeLabel) {
        const label = (dayTypeLabel || '').toLowerCase();

        if (label.includes('hétvége')) {
            return {
                modifier: 'weekend',
                icon: 'fa-umbrella-beach',
                eyebrow: 'Hétvégi nap'
            };
        }

        if (label.includes('szünet')) {
            return {
                modifier: 'break',
                icon: 'fa-mug-hot',
                eyebrow: 'Szüneti rend'
            };
        }

        if (label.includes('ünnep')) {
            return {
                modifier: 'holiday',
                icon: 'fa-star',
                eyebrow: 'Ünnepnap'
            };
        }

        if (label.includes('tanítási') || label.includes('nevelési') || label.includes('intézményi')) {
            return {
                modifier: 'school',
                icon: 'fa-school',
                eyebrow: 'Munkanap'
            };
        }

        return {
            modifier: 'default',
            icon: 'fa-calendar-day',
            eyebrow: 'Napi állapot'
        };
    }

    function getRowStatus(row) {
        if (row.is_cancelled) {
            return {
                className: 'is-cancelled',
                label: 'Lemondva'
            };
        }

        if (row.can_cancel || row.can_restore) {
            return {
                className: 'is-active',
                label: 'Aktív'
            };
        }

        return {
            className: 'is-locked',
            label: 'Nem módosítható'
        };
    }

    function refreshButtons() {
        const cancelChecks = rowsContainer.querySelectorAll('input[data-action="cancel"]:checked');
        const restoreChecks = rowsContainer.querySelectorAll('input[data-action="restore"]:checked');
        cancelButton.disabled = cancelChecks.length === 0;
        restoreButton.disabled = restoreChecks.length === 0;
    }

    function openDayModal(dayCell) {
        const date = dayCell.dataset.calendarDay;
        const employeeFilter = dayCell.dataset.employeeFilter;
        const url = new URL(@json(route('employee.meal-cancellations.day', ['date' => '__DATE__'])).replace('__DATE__', date), window.location.origin);
        if (employeeFilter) {
            url.searchParams.set('employee_id', employeeFilter);
        }

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            })
            .then(function (payload) {
                if (!payload) {
                    return;
                }

                currentDate = date;
                const theme = getDayTheme(payload.day_type);
                title.textContent = 'Étkezés kezelése';
                subtitle.textContent = 'Az aznapi étkezése és a lemondási állapota egy helyen kezelhető.';
                dayType.textContent = theme.eyebrow + ' • ' + payload.day_type;
                dateLabel.textContent = payload.date_label;
                deadline.textContent = payload.deadline_label || 'Nincs elérhető határidő ehhez a naphoz.';
                headerBar.className = 'parent-meal-modal-header-bar parent-meal-modal-header-bar--' + theme.modifier;
                headerIcon.className = 'parent-meal-modal-icon parent-meal-modal-icon--' + theme.modifier;
                headerIcon.innerHTML = '<i class="fa-solid ' + theme.icon + '"></i>';
                rowsContainer.innerHTML = '';

                payload.rows.forEach(function (row) {
                    const wrapper = document.createElement('div');
                    const status = getRowStatus(row);
                    const actionMarkup = [];

                    wrapper.className = 'parent-modal-row ' + status.className + ((row.can_cancel || row.can_restore) ? '' : ' is-disabled');
                    const mealTypesLine = row.meal_types.join(', ');
                    const showMealTypesLine = mealTypesLine.length > 0 && mealTypesLine !== row.meal_label;
                    const showStatusBox = Boolean(row.reason) || row.status !== status.label;

                    if (row.can_cancel) {
                        actionMarkup.push(`
                            <label class="parent-meal-switch-control" for="cancel-employee-${row.employee_id}">
                                <input id="cancel-employee-${row.employee_id}"
                                        type="checkbox"
                                        value="${escapeHtml(row.employee_id)}"
                                        data-action="cancel">
                                <span class="parent-meal-switch-control-track"></span>
                                <span class="parent-meal-switch-control-label">Étkezés lemondása</span>
                            </label>
                        `);
                    }

                    if (row.can_restore) {
                        actionMarkup.push(`
                            <label class="parent-meal-switch-control" for="restore-employee-${row.employee_id}">
                                <input id="restore-employee-${row.employee_id}"
                                        type="checkbox"
                                        value="${escapeHtml(row.employee_id)}"
                                        data-action="restore">
                                <span class="parent-meal-switch-control-track"></span>
                                <span class="parent-meal-switch-control-label">Lemondás visszavonása</span>
                            </label>
                        `);
                    }

                    wrapper.innerHTML = `
                        <div class="parent-modal-row-top">
                            <div class="parent-modal-row-content">
                                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                    <div>
                                        <div class="parent-modal-row-title">${escapeHtml(row.meal_label)}</div>

                                        ${showMealTypesLine
                                            ? `<div class="parent-modal-row-meta">${escapeHtml(mealTypesLine)}</div>`
                                            : ''
                                        }
                                    </div>

                                    <span class="parent-modal-row-badge ${status.className}">
                                        ${status.label}
                                    </span>
                                </div>
                            </div>
                            ${showStatusBox
                                ? `<div class="parent-modal-row-status">${escapeHtml(row.status)}${row.reason ? '<span class="parent-modal-row-separator">•</span>' + escapeHtml(row.reason) : ''}</div>`
                                : ''
                            }
                        </div>
                        <div class="parent-modal-row-actions ${actionMarkup.length ? '' : 'is-empty'}">
                            ${actionMarkup.length ? actionMarkup.join('') : '<div class="parent-modal-row-note">Ehhez a naphoz jelenleg nincs elérhető módosítás.</div>'}
                        </div>
                    `;

                    rowsContainer.appendChild(wrapper);
                });

                cancelButton.classList.toggle(
                    'd-none',
                    !payload.can_submit_cancellation
                );

                restoreButton.classList.toggle(
                    'd-none',
                    !payload.can_submit_restore
                );

                refreshButtons();
                modal.show();
            });
    }

    rowsContainer.addEventListener('change', function (event) {
        if (event.target.matches('input[type="checkbox"]')) {
            refreshButtons();
        }
    });

    document.querySelectorAll('[data-calendar-day]').forEach(function (dayCell) {
        dayCell.addEventListener('click', function () {
            openDayModal(dayCell);
        });

        dayCell.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDayModal(dayCell);
            }
        });
    });

    document.querySelectorAll('[data-open-day]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const dayCell = button.closest('[data-calendar-day]');
            if (dayCell) {
                openDayModal(dayCell);
            }
        });
    });

    helpPopoverButtons.forEach(function (button, index) {
        button.addEventListener('show.bs.popover', function () {
            helpPopovers.forEach(function (popover, otherIndex) {
                if (otherIndex !== index) {
                    popover.hide();
                }
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-bs-toggle="popover"]') || event.target.closest('.popover')) {
            return;
        }

        helpPopovers.forEach(function (popover) {
            popover.hide();
        });
    });

    function submitAction(form, employeeIdInputId, dateInputId, selector, swalOptions) {
        const selected = Array.from(rowsContainer.querySelectorAll(selector + ':checked')).map((input) => input.value);
        if (!selected.length) {
            return;
        }

        Swal.fire({
            title: swalOptions.title,
            text: swalOptions.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: swalOptions.confirmText,
            cancelButtonText: 'Mégsem',
            confirmButtonColor: swalOptions.confirmColor,
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            document.getElementById(dateInputId).value = currentDate;
            document.getElementById(employeeIdInputId).value = selected[0];

            form.submit();
        });
    }

    cancelButton.addEventListener('click', function () {
        submitAction(cancelForm, 'cancelMealEmployeeId', 'cancelMealDate', 'input[data-action="cancel"]', {
            title: 'Biztosan lemondja ezt az étkezést?',
            text: 'A mentés után a naptár frissül, és a lemondott állapot azonnal látható lesz.',
            confirmText: 'Igen, mentem',
            confirmColor: '#d96d33'
        });
    });

    restoreButton.addEventListener('click', function () {
        submitAction(restoreForm, 'restoreMealEmployeeId', 'restoreMealDate', 'input[data-action="restore"]', {
            title: 'Biztosan visszaállítja ezt az étkezést?',
            text: 'Csak a még határidőn belüli lemondás állítható vissza.',
            confirmText: 'Igen, visszaállítom',
            confirmColor: '#17643d'
        });
    });
});
</script>
@endpush
