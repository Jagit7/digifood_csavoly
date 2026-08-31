@extends('layouts.superadmin')

@section('title', 'Intézményi naptár')

@push('styles')
<style>
    .meal-calendar-toolbar { display:flex; gap:1rem; align-items:center; justify-content:space-between; flex-wrap:wrap; }
    .meal-calendar-nav { display:flex; align-items:center; gap:.5rem; }
    .meal-calendar-grid { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); border-left:1px solid #e7e7e7; border-top:1px solid #e7e7e7; }
    .meal-calendar-weekday { padding:.65rem; text-align:center; font-weight:600; background:#f8f8fb; border-right:1px solid #e7e7e7; border-bottom:1px solid #e7e7e7; }
    .meal-calendar-day { position:relative; min-height:170px; padding:.75rem; color:inherit; border-right:1px solid #e7e7e7; border-bottom:1px solid #e7e7e7; background:#fff; }
    .meal-calendar-day.outside { background:#fafafa; color:#9a9a9a; }
    .meal-calendar-day.non-service { background:#f6f7f9; }
    .meal-calendar-day.working-saturday { background:#fff8e8; }
    .meal-calendar-date { display:flex; align-items:center; justify-content:space-between; font-weight:700; margin-bottom:.55rem; }
    .meal-calendar-count { font-size:1.35rem; font-weight:700; color:#2d2d2d; }
    .meal-calendar-metric { display:flex; justify-content:space-between; gap:.5rem; font-size:.78rem; line-height:1.55; }
    .meal-calendar-label { display:block; margin-top:.35rem; padding:.25rem .4rem; border-radius:.25rem; font-size:.72rem; line-height:1.25; }
    .meal-calendar-label.holiday { color:#a11b30; background:#ffe9ed; }
    .meal-calendar-label.working { color:#805b00; background:#fff0bc; }
    .meal-calendar-label.break { color:#285a76; background:#e5f4fc; }
    .meal-calendar-actions { position:absolute; right:.6rem; bottom:.6rem; display:flex; gap:.35rem; }
    .meal-calendar-action { width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #d7deea; border-radius:.55rem; background:#fff; color:#5c6b84; box-shadow:0 2px 6px rgba(43, 52, 69, .08); transition:.15s ease; }
    .meal-calendar-action:hover { color:#244a86; background:#f4f8ff; border-color:#bfd0ee; }
    .meal-calendar-action.cancelled { color:#9f4b38; border-color:#edd2cb; background:#fff8f5; }
    .meal-calendar-action.cancelled:hover { color:#8b321f; background:#fff0eb; border-color:#e7b9ae; }
    .meal-calendar-modal-state { min-height:120px; display:flex; align-items:center; justify-content:center; }
    @media (max-width: 991px) {
        .meal-calendar-wrap { overflow-x:auto; }
        .meal-calendar-grid { min-width:910px; }
    }
</style>
@endpush

@section('content')
    <div class="container-fluid">
        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Intézményi naptár',
            'subtitle' => 'Napi étkezési létszám, A/B menü és diétás adagok heti vagy havi bontásban',
        ])

        <div class="row">
        {{-- 1. Étkezési napok --}}
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Étkezési napok',
            'value' => $stats['service_days'],
            'subtitle' => $periodLabel,
            'icon' => 'fa-solid fa-calendar-check',
            'color' => 'orange',
        ])

        {{-- 2. Összes adag --}}
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes adag',
            'value' => number_format($stats['total'], 0, ',', ' '),
            'subtitle' => 'Személy-adag a kiválasztott időszakban',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'green',
        ])

        @if($hasActiveAbMenuPlan)
            {{-- A menü --}}
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'A menü adag',
                'value' => number_format($stats['menu_a_count'], 0, ',', ' '),
                'subtitle' => 'Alapértelmezett vagy explicit A választás',
                'icon' => 'fa-solid fa-a',
                'color' => 'blue',
            ])

            {{-- B menü --}}
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'B menü adag',
                'value' => number_format($stats['menu_b_count'], 0, ',', ' '),
                'subtitle' => 'Explicit B választások',
                'icon' => 'fa-solid fa-b',
                'color' => 'purple',
            ])
        @endif

        {{-- 3. Diétás --}}
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Diétás adag',
            'value' => number_format($stats['dietary_count'], 0, ',', ' '),
            'subtitle' => 'Speciális diétás adagok',
            'icon' => 'fa-solid fa-leaf',
            'color' => 'green',
        ])

        {{-- 4. Lemondások --}}
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lemondott adag',
            'value' => number_format($stats['cancelled_count'] ?? 0, 0, ',', ' '),
            'subtitle' => 'Lemondások a kiválasztott időszakban',
            'icon' => 'fa-solid fa-ban',
            'color' => 'orange',
        ])
    </div>

    <div class="card">
        <div class="card-header meal-calendar-toolbar">
            <div class="btn-group" role="group" aria-label="Naptárnézet">
                <a class="btn btn-sm {{ $viewMode === 'week' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('dashboard.institution.school-breaks.calendar', ['view' => 'week', 'date' => $selectedDate->toDateString()]) }}">Heti</a>
                <a class="btn btn-sm {{ $viewMode === 'month' ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('dashboard.institution.school-breaks.calendar', ['view' => 'month', 'date' => $selectedDate->toDateString()]) }}">Havi</a>
            </div>

            <div class="meal-calendar-nav">
                <a class="btn btn-sm btn-light" title="Előző időszak"
                   href="{{ route('dashboard.institution.school-breaks.calendar', ['view' => $viewMode, 'date' => $previousDate->toDateString()]) }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <strong>{{ $periodLabel }}</strong>
                <a class="btn btn-sm btn-light" title="Következő időszak"
                   href="{{ route('dashboard.institution.school-breaks.calendar', ['view' => $viewMode, 'date' => $nextDate->toDateString()]) }}">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('dashboard.institution.school-breaks.calendar', ['view' => $viewMode, 'date' => now()->toDateString()]) }}">Ma</a>
        </div>

        <div class="card-body p-0 meal-calendar-wrap">
            <div class="meal-calendar-grid">
                @foreach(['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'] as $weekday)
                    <div class="meal-calendar-weekday">{{ $weekday }}</div>
                @endforeach

                @foreach($days as $day)
                    <div
                       class="meal-calendar-day {{ !$day['is_current_month'] ? 'outside' : '' }} {{ !$day['is_service_day'] ? 'non-service' : '' }} {{ $day['working_day'] ? 'working-saturday' : '' }}"
                       data-calendar-day="{{ $day['date']->toDateString() }}"
                       data-cancelled-count="{{ $day['cancelled'] }}">
                        <div class="meal-calendar-date">
                            <span>{{ $day['date']->day }}.</span>
                            @if($day['date']->isToday())<span class="badge badge-primary">Ma</span>@endif
                        </div>

                        @if($day['is_service_day'])
                            <div class="meal-calendar-count">{{ $day['total'] }} fő</div>
                            @if($day['has_ab_menu'])
                                <div class="meal-calendar-metric"><span>A</span><strong>{{ $day['menu_a_count'] }}</strong></div>
                                <div class="meal-calendar-metric"><span>B</span><strong>{{ $day['menu_b_count'] }}</strong></div>
                            @endif
                            <div class="meal-calendar-metric"><span>Diétás</span><strong>{{ $day['dietary_count'] }}</strong></div>
                            @if($day['cancelled'])
                                <div class="meal-calendar-metric text-muted"><span>Lemondva</span><strong>{{ $day['cancelled'] }}</strong></div>
                            @endif
                        @else
                            <div class="text-muted small">Nincs étkeztetés</div>
                        @endif

                        @if($day['holiday'])
                            <span class="meal-calendar-label holiday"><i class="fa-solid fa-flag me-1"></i>{{ $day['holiday'] }}</span>
                        @endif
                        @if($day['break'])
                            <span class="meal-calendar-label break"><i class="fa-solid fa-calendar-xmark me-1"></i>{{ $day['break']->title }}</span>
                        @endif
                        @if($day['working_day'])
                            <span class="meal-calendar-label working"><i class="fa-solid fa-briefcase me-1"></i>{{ $day['working_day']->name }}</span>
                        @endif

                        @if($day['is_service_day'])
                            <div class="meal-calendar-actions">
                                <a
                                    href="{{ route('dashboard.institution.school-breaks.calendar.day', $day['date']->toDateString()) }}"
                                    class="meal-calendar-action"
                                    title="Teljes napi lista"
                                    aria-label="Teljes napi lista">
                                    <i class="fa-solid fa-users"></i>
                                </a>
                                <button
                                    type="button"
                                    class="meal-calendar-action cancelled"
                                    title="Lemondottak"
                                    aria-label="Lemondottak"
                                    data-bs-toggle="modal"
                                    data-bs-target="#cancelledMealsModal"
                                    data-cancelled-url="{{ route('dashboard.institution.school-breaks.calendar.cancelled', $day['date']->toDateString()) }}"
                                    data-date-label="{{ $day['date']->format('Y.m.d.') }}">
                                    <i class="fa-solid fa-user-xmark"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer text-muted small">
            A napi blokkon belüli ikonokkal nyitható meg a teljes lista és a lemondottak nézete.
        </div>
    </div>

    <div class="modal fade" id="cancelledMealsModal" tabindex="-1" aria-labelledby="cancelledMealsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelledMealsModalLabel">Lemondott étkezések</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                </div>
                <div class="modal-body">
                    <div id="cancelledMealsModalContent" class="meal-calendar-modal-state text-muted">
                        Válasszon egy napot.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('.meal-calendar-action[title]').forEach(function (element) {
            new bootstrap.Tooltip(element);
        });
    }

    const modalElement = document.getElementById('cancelledMealsModal');
    if (!modalElement) {
        return;
    }

    const titleElement = document.getElementById('cancelledMealsModalLabel');
    const contentElement = document.getElementById('cancelledMealsModalContent');

    modalElement.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        const url = trigger.getAttribute('data-cancelled-url');
        const dateLabel = trigger.getAttribute('data-date-label');

        titleElement.textContent = 'Lemondott étkezések – ' + dateLabel;
        contentElement.innerHTML = '<div class="spinner-border text-primary" role="status" aria-hidden="true"></div><span class="ms-3">Betöltés...</span>';
        contentElement.className = 'meal-calendar-modal-state text-muted';

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('A lemondott lista betöltése sikertelen.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (!Array.isArray(payload.items) || payload.items.length === 0) {
                    contentElement.className = 'meal-calendar-modal-state text-muted';
                    contentElement.textContent = 'Ezen a napon nincs lemondott étkezés.';
                    return;
                }

                const rows = payload.items.map(function (item) {
                    return '<tr>'
                        + '<td>' + escapeHtml(item.group_label || '—') + '</td>'
                        + '<td>' + escapeHtml(item.name || '') + '</td>'
                        + '</tr>';
                }).join('');

                contentElement.className = '';
                contentElement.innerHTML = ''
                    + '<div class="table-responsive">'
                    + '  <table class="table table-sm table-hover align-middle mb-0">'
                    + '    <thead><tr><th>Csoport / osztály</th><th>Név</th></tr></thead>'
                    + '    <tbody>' + rows + '</tbody>'
                    + '  </table>'
                    + '</div>';
            })
            .catch(function () {
                contentElement.className = 'meal-calendar-modal-state text-danger';
                contentElement.textContent = 'A lemondott lista most nem tölthető be. Kérjük, próbálja újra.';
            });
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
@endpush
