@extends('layouts.superadmin')

@section('title', 'Étkezési beállítás részletei')

@section('content')
@php
    $returnList = $returnList ?? null;
    $returnQuery = $returnQuery ?? '';
    $returnParams = array_filter([
        'return_list' => $returnList,
        'return_query' => $returnQuery,
    ], fn ($value) => filled($value));
    $mealSettingsReturnQuery = $returnParams !== [] ? '?'.http_build_query($returnParams) : '';
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Étkezési beállítás részletei',
        'subtitle' => $child->name . ' · ' . $mealSetting->valid_from->format('Y.m.d.'),
        'buttonText' => 'Vissza az előzményekhez',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.children.meal-settings.index', $child) . $mealSettingsReturnQuery,
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Beállítás adatai</h4>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Mód</dt>
                <dd class="col-sm-9">{{ $modeLabels[$mealSetting->mode] ?? $mealSetting->mode }}</dd>

                <dt class="col-sm-3">Csomag / étkezések</dt>
                <dd class="col-sm-9">
                    @include('dashboard.institution_admin.children.meal-settings.partials.summary', [
                        'mealSetting' => $mealSetting,
                        'defaultPackage' => $defaultPackage,
                    ])
                </dd>

                <dt class="col-sm-3">Érvényesség</dt>
                <dd class="col-sm-9">
                    {{ $mealSetting->valid_from->format('Y.m.d.') }}
                    – {{ $mealSetting->valid_to?->format('Y.m.d.') ?? 'nyitott' }}
                </dd>

                <dt class="col-sm-3">Létrehozta</dt>
                <dd class="col-sm-9">{{ $mealSetting->createdBy?->name ?? '—' }}</dd>

                @if($mealSetting->wasClosedManually())
                    <dt class="col-sm-3">Lezárás oka</dt>
                    <dd class="col-sm-9">{{ $mealSetting->closureReasonLabel() ?? '—' }}</dd>

                    <dt class="col-sm-3">Lezárta</dt>
                    <dd class="col-sm-9">{{ $mealSetting->closedBy?->name ?? '—' }}</dd>

                    <dt class="col-sm-3">Lezárás időpontja</dt>
                    <dd class="col-sm-9">{{ $mealSetting->closed_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i') ?? '—' }}</dd>

                    <dt class="col-sm-3">Lezárási megjegyzés</dt>
                    <dd class="col-sm-9">{{ $mealSetting->closure_note ?: '—' }}</dd>
                @endif

                <dt class="col-sm-3">Megjegyzés</dt>
                <dd class="col-sm-9">{{ $mealSetting->note ?: '—' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
