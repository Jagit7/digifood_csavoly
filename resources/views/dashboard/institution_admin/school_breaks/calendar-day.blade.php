@extends('layouts.superadmin')

@section('title', 'Napi étkezési lista')

@push('styles')
<style>
    .daily-list-note { border-left:4px solid #886cc0; background:#f8f6ff; padding:.85rem 1rem; border-radius:.25rem; }
    .restriction-badge { display:inline-block; padding:.25rem .45rem; margin:.1rem .15rem .1rem 0; border-radius:.25rem; font-size:.75rem; }
    .restriction-badge.allergen { background:#ffe8ec; color:#a61e34; }
    .restriction-badge.other { background:#e7f5ed; color:#237244; }
    @page { size:A4 landscape; margin:10mm; }
    @media print {
        #main-wrapper > .nav-header, #main-wrapper > .header, #main-wrapper > .deznav, .footer,
        .no-print, #preloader { display:none !important; }
        html, body, #main-wrapper, .content-body, .content-body > .container-fluid {
            width:100% !important;
            max-width:none !important;
            min-width:0 !important;
            margin:0 !important;
            padding-left:0 !important;
            padding-right:0 !important;
        }
        .content-body { padding-top:0 !important; }
        .card { box-shadow:none !important; border:1px solid #ddd; }
        .table-responsive {
            display:block !important;
            width:100% !important;
            max-width:none !important;
            overflow:visible !important;
        }
        .table-responsive table {
            width:100% !important;
            min-width:0 !important;
            table-layout:fixed;
            font-size:9pt;
        }
        .table-responsive th,
        .table-responsive td {
            padding:.3rem .4rem !important;
            white-space:normal !important;
            overflow-wrap:anywhere;
            word-break:normal;
        }
        .table-responsive th:nth-child(1) { width:6% !important; }
        .table-responsive th:nth-child(2) { width:24% !important; }
        .table-responsive th:nth-child(3) { width:19% !important; }
        .table-responsive th:nth-child(4) { width:24% !important; }
        .table-responsive th:nth-child(5) { width:27% !important; }
        .restriction-badge { white-space:normal; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">Napi étkezési lista</h2>
            <p class="text-muted mb-0">
                {{ $selectedDate->format('Y. m. d.') }} ·
                {{ ['Vasárnap', 'Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat'][$selectedDate->dayOfWeek] }}
            </p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('dashboard.institution.school-breaks.calendar', ['view' => 'month', 'date' => $selectedDate->toDateString()]) }}"
               class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Vissza a naptárhoz
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Nyomtatás
            </button>
        </div>
    </div>

    @if($summary['holiday'] || $summary['break'] || $summary['working_day'])
        <div class="daily-list-note mb-4">
            @if($summary['holiday'])
                <strong><i class="fa-solid fa-flag me-1 text-danger"></i>{{ $summary['holiday'] }}</strong>
            @endif
            @if($summary['break'])
                <span class="{{ $summary['holiday'] ? 'ms-3' : '' }}"><i class="fa-solid fa-calendar-xmark me-1"></i>{{ $summary['break']->title }}</span>
            @endif
            @if($summary['working_day'])
                <strong class="{{ $summary['holiday'] || $summary['break'] ? 'ms-3' : '' }}"><i class="fa-solid fa-briefcase me-1"></i>{{ $summary['working_day']->name }}</strong>
            @endif
        </div>
    @endif

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Étkezik', 'value' => $summary['total'],
            'subtitle' => $summary['is_service_day'] ? 'Összes megrendelt adag' : 'Ezen a napon nincs étkeztetés',
            'icon' => 'fa-solid fa-users', 'color' => 'green',
        ])
        @if($hasActiveAbMenuPlan)
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'A menü adag', 'value' => $summary['menu_a_count'],
                'subtitle' => 'Alapértelmezett vagy explicit A választás', 'icon' => 'fa-solid fa-a', 'color' => 'blue',
            ])
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'B menü adag', 'value' => $summary['menu_b_count'],
                'subtitle' => 'Explicit B választások', 'icon' => 'fa-solid fa-b', 'color' => 'orange',
            ])
        @else
            {{-- Nincs aktív A-B menüterv az intézménynél - az A/B menü
                 kártyák helyett is 4 kártya maradjon a soron, ezért itt
                 két másik, a névsorból is levezethető adatot mutatunk. --}}
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Lemondások száma', 'value' => $summary['cancelled'],
                'subtitle' => 'Ezen a napon lemondott étkezések', 'icon' => 'fa-solid fa-calendar-xmark', 'color' => 'red',
            ])
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Sima adag', 'value' => $summary['standard'],
                'subtitle' => 'Étrendi megkötés nélküli étkezők', 'icon' => 'fa-solid fa-utensils', 'color' => 'blue',
            ])
        @endif
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Diétás adag', 'value' => $summary['dietary_count'],
            'subtitle' => 'Allergénnel vagy egyéb étrendi megkötéssel étkezők', 'icon' => 'fa-solid fa-leaf', 'color' => 'purple',
        ])
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Teljes intézményi névsor</h4>
            <span class="text-muted">
                @if($roster->total() > 0)
                    {{ $roster->firstItem() }}-{{ $roster->lastItem() }} / {{ $roster->total() }} rekord
                @else
                    0 rekord
                @endif
            </span>
        </div>
        <div class="card-body p-3">
            @if($roster->total() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-auto mb-0" style="min-width: 100%;">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>Név</th>
                            <th>Osztály / csoport</th>
                            <th>Allergének</th>
                            <th>Egyéb étrendi megkötés</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($roster as $row)
                            <tr class="{{ !$row['is_eating'] ? 'table-light text-muted' : '' }}">
                                <td>{{ $roster->firstItem() + $loop->index }}</td>
                                <td><strong>{{ $row['child']->name }}</strong></td>
                                <td>{{ $row['child']->group_name ?: '—' }}</td>
                                <td>
                                    @forelse($row['allergens'] as $allergen)
                                        <span class="restriction-badge allergen">{{ $allergen }}</span>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse($row['other_restrictions'] as $restriction)
                                        <span class="restriction-badge other">{{ $restriction }}</span>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3 no-print">
                    <small class="text-muted">
                        {{ $roster->firstItem() }}-{{ $roster->lastItem() }} / {{ $roster->total() }} rekord
                    </small>
                    {{ $roster->onEachSide(1)->withQueryString()->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-users',
                    'title' => 'Nincs aktív gyermek az intézményben',
                    'text' => 'A névsor a gyermekek importálása után jelenik meg.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
