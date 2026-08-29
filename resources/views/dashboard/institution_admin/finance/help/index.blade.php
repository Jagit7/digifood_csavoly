@extends('layouts.superadmin')

@section('title', 'Pénzügyi folyamat és szabályok')

@push('styles')
<style>
    html { scroll-behavior: smooth; }
    .finance-help-anchor { scroll-margin-top: 110px; }
    .finance-help-hero {
        background: linear-gradient(135deg, #0f766e 0%, #0f4c81 100%);
        border-radius: 1.5rem;
        color: #fff;
        padding: 2rem;
        box-shadow: 0 18px 45px rgba(15, 76, 129, 0.18);
    }
    .finance-help-toc-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .9rem 1rem;
        border: 1px solid #e8edf5;
        border-radius: 1rem;
        text-decoration: none;
        color: #1f2937;
        font-weight: 600;
        background: #fff;
        transition: .2s ease;
    }
    .finance-help-toc-link:hover {
        border-color: #b6d4fe;
        background: #f8fbff;
        color: #0f4c81;
    }
    .finance-help-timeline.widget-timeline .timeline:before {
        left: 0.875rem;
    }
    .finance-help-timeline.widget-timeline .timeline > li {
        min-height: 2.5rem;
    }
    .finance-help-timeline.widget-timeline .timeline > li > .timeline-panel {
        width: calc(100% - 3.25rem);
        margin-left: 2.75rem;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number {
        left: 0.875rem;
        top: 0.5rem;
        width: 28px;
        height: 28px;
        min-width: 28px;
        min-height: 28px;
        padding: 0;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        box-sizing: border-box;
        border: 2px solid currentColor;
        border-radius: 50%;
        background: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        word-break: normal;
        overflow-wrap: normal;
        z-index: 2;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number::after {
        content: none !important;
        display: none !important;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number.primary {
        color: var(--primary);
        border-color: var(--primary);
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number.success {
        color: #09BD3C;
        border-color: #09BD3C;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number.info {
        color: #461EE7;
        border-color: #461EE7;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number.warning {
        color: #FE8024;
        border-color: #FE8024;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number.danger {
        color: #FF2E2E;
        border-color: #FF2E2E;
    }
    .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number.dark {
        color: #312a2a;
        border-color: #312a2a;
    }
    .finance-help-rule-title {
        font-size: 1rem;
        font-weight: 700;
    }
    .finance-help-setting-card {
        border: 1px solid #edf1f6;
        border-radius: 1rem;
        padding: 1rem;
        height: 100%;
        background: #fff;
    }
    .finance-help-setting-value {
        font-size: 1.05rem;
        font-weight: 700;
    }
    .finance-help-note-card {
        border-radius: 1rem;
        border: 0;
    }
    .finance-help-problem-card {
        border: 1px solid #edf1f6;
        border-radius: 1rem;
    }

    @media (max-width: 767.98px) {
        .finance-help-timeline.widget-timeline .timeline:before {
            left: 0.8125rem;
        }
        .finance-help-timeline.widget-timeline .timeline > li > .timeline-panel {
            width: calc(100% - 3rem);
            margin-left: 2.5rem;
        }
        .finance-help-timeline .timeline > li > .timeline-badge.timeline-step-number {
            left: 0.8125rem;
            width: 26px;
            height: 26px;
            min-width: 26px;
            min-height: 26px;
            font-size: 11px;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Pénzügyi folyamat és szabályok',
        'subtitle' => $institution->name . ' · intézményi pénzügyi súgó',
    ])

    <div class="finance-help-hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-xl-8">
                <span class="badge bg-white text-primary mb-3">Pénzügyi súgó</span>
                <h2 class="mb-3 text-white">Pénzügyi folyamat és szabályok</h2>
                <p class="mb-0 fs-5">
                    Ezen az oldalon megismerheti, hogyan készülnek a havi fizetési kötelezettségek, milyen események módosítják a fizetendő összeget, hogyan történik a hónap lezárása, valamint hogyan kapcsolódnak ehhez a befizetések, tartozások és számlák.
                </p>
            </div>
            <div class="col-xl-4">
                <div class="alert alert-light text-dark mb-0 border-0 shadow-sm">
                    <div class="d-flex align-items-start gap-3">
                        <i class="fa-solid fa-circle-info text-primary mt-1"></i>
                        <div>
                            <strong class="d-block mb-1">Fontos</strong>
                            <span>A számítás mindig az adott intézmény beállításai, az érvényes étkezési árak, a gyermek étkezési csomagja, kedvezményei és a rögzített lemondások alapján történik.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Tartalomjegyzék</h4>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($content['sections'] as $section)
                    <div class="col-lg-4 col-md-6">
                        <a href="#{{ $section['id'] }}" class="finance-help-toc-link">
                            <span>{{ $section['label'] }}</span>
                            <i class="fa-solid fa-arrow-down"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div id="folyamat" class="finance-help-anchor">
        @include('dashboard.institution_admin.finance.help._process_timeline', ['items' => $content['timeline_items']])
    </div>

    <div id="szabalyok" class="finance-help-anchor">
        @include('dashboard.institution_admin.finance.help._rules', ['rules' => $content['rules']])
    </div>

    <div id="tudnivalok" class="finance-help-anchor">
        @include('dashboard.institution_admin.finance.help._important_notes', ['notes' => $content['important_notes']])
    </div>

    <div id="problemak" class="finance-help-anchor">
        @include('dashboard.institution_admin.finance.help._common_problems', ['problems' => $content['common_problems']])
    </div>

    <div id="beallitasok" class="finance-help-anchor">
        @include('dashboard.institution_admin.finance.help._institution_settings', ['settingsBlock' => $content['institution_settings']])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Megjegyzések a jelenlegi implementációról</h4>
        </div>
        <div class="card-body">
            <ul class="mb-0 ps-3">
                @foreach($content['implementation_notes'] as $note)
                    <li class="mb-2">{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
