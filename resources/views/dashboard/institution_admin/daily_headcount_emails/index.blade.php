@extends('layouts.superadmin')

@section('title', 'Napi létszám e-mailek')

@section('content')
@php
    $isKindergarten = $institution->type === 'ovoda';
    $groupWord = $isKindergarten ? 'csoport' : 'osztály';
    $groupWordCap = $isKindergarten ? 'Csoportok' : 'Osztályok';
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Napi létszám e-mailek',
        'subtitle' => 'Automatikus, '.$groupWord.'onkénti napi étkezési létszám kiküldés – '.$institution->name,
    ])

    <div class="card mb-4">
        <div class="card-header">
            <div>
                <h4 class="card-title mb-1">Közös küldési időpont</h4>
                <div class="text-muted small">Ez az időpont minden bekapcsolt {{ $groupWord }}ra vonatkozik – nincs szükség {{ $groupWord }}onkénti külön időpontra.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.daily-headcount-emails.schedule.update') }}">
                @csrf
                <div class="row align-items-end">
                    <div class="col-lg-6 mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="daily_headcount_email_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="daily_headcount_email_enabled" name="daily_headcount_email_enabled" value="1"
                                   @checked((bool) old('daily_headcount_email_enabled', $setting->daily_headcount_email_enabled))>
                            <label class="form-check-label" for="daily_headcount_email_enabled">
                                Napi létszám e-mailek automatikus kiküldése
                            </label>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="daily_headcount_email_send_time">Kiküldés időpontja</label>
                        <input id="daily_headcount_email_send_time" type="time" name="daily_headcount_email_send_time"
                               class="form-control @error('daily_headcount_email_send_time') is-invalid @enderror"
                               value="{{ old('daily_headcount_email_send_time', $sendTime) }}" required>
                        @error('daily_headcount_email_send_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Időpont mentése
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h4 class="card-title mb-1">{{ $groupWordCap }} és címzettek</h4>
                <div class="text-muted small">Csak a jelenleg aktív gyermekhez rendelt {{ $groupWord }}ok jelennek meg. Egy {{ $groupWord }}ra csak akkor megy e-mail, ha be van kapcsolva ÉS van legalább egy megadott címzettje.</div>
            </div>
        </div>
        <div class="card-body">
            @if($groupNames->isEmpty())
                <div class="alert alert-info mb-0">
                    Ehhez az intézményhez jelenleg nincs aktív gyermekhez rendelt {{ $groupWord }}, ezért nincs mit beállítani. Amint egy gyermeknek {{ $groupWord }} lesz megadva, a(z) {{ $groupWord }} automatikusan megjelenik itt.
                </div>
            @else
                @foreach($groupNames as $groupName)
                    @php
                        $groupHasError = old('group_name') === $groupName && ($errors->has('emails') || $errors->has('test_email'));
                        $groupEmails = old('group_name') === $groupName
                            ? collect(old('emails', []))
                            : ($recipientsByGroup->get($groupName) ?? collect());
                        $groupEnabled = old('group_name') === $groupName
                            ? old('enabled', false)
                            : (bool) ($groupEnabledMap->get($groupName) ?? false);
                        $groupHasTestError = old('test_group_name') === $groupName && $errors->has('test_email');
                    @endphp
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h6 class="mb-0">{{ $groupName }}</h6>
                            <a href="{{ route('dashboard.institution.daily-headcount-emails.groups.preview', ['groupName' => $groupName]) }}"
                               target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye me-1"></i>Előnézet
                            </a>
                        </div>

                        <form method="POST" action="{{ route('dashboard.institution.daily-headcount-emails.groups.update', ['groupName' => $groupName]) }}">
                            @csrf
                            <input type="hidden" name="group_name" value="{{ $groupName }}">

                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="enabled_{{ $loop->index }}" name="enabled" value="1"
                                       @checked($groupEnabled)>
                                <label class="form-check-label" for="enabled_{{ $loop->index }}">
                                    Napi létszám e-mail küldése
                                </label>
                            </div>

                            <div class="daily-headcount-email-rows" data-group="{{ $groupName }}">
                                @forelse($groupEmails as $email)
                                    <div class="input-group mb-2 daily-headcount-email-row">
                                        <input type="email" name="emails[]"
                                               class="form-control @if($groupHasError) is-invalid @endif"
                                               value="{{ $email }}" maxlength="191">
                                        <button type="button" class="btn btn-outline-danger daily-headcount-remove-row"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @empty
                                    <div class="input-group mb-2 daily-headcount-email-row">
                                        <input type="email" name="emails[]" class="form-control" value="" maxlength="191" placeholder="pl. osztalyfonok@example.hu">
                                        <button type="button" class="btn btn-outline-danger daily-headcount-remove-row"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                @endforelse
                            </div>
                            @if($groupHasError && $errors->has('emails'))
                                <div class="text-danger small mb-2">{{ $errors->first('emails') }}</div>
                            @endif
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary daily-headcount-add-row">
                                    <i class="fa-solid fa-plus me-1"></i>Új e-mail cím
                                </button>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-floppy-disk me-1"></i>Beállítások mentése
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('dashboard.institution.daily-headcount-emails.groups.test-send', ['groupName' => $groupName]) }}" class="mt-3 pt-3 border-top">
                            @csrf
                            <input type="hidden" name="test_group_name" value="{{ $groupName }}">
                            <label class="form-label small text-muted" for="test_email_{{ $loop->index }}">
                                Teszt e-mail küldése - NEM a fenti címzetteknek megy ki, csak az alább megadott címre
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="email" id="test_email_{{ $loop->index }}" name="test_email"
                                       class="form-control @if($groupHasTestError) is-invalid @endif"
                                       placeholder="teszt@example.hu" required maxlength="191">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-paper-plane me-1"></i>Teszt e-mail küldése
                                </button>
                            </div>
                            @if($groupHasTestError)
                                <div class="text-danger small mt-1">{{ $errors->first('test_email') }}</div>
                            @endif
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.daily-headcount-add-row').forEach(function (button) {
                button.addEventListener('click', function () {
                    var container = button.closest('form').querySelector('.daily-headcount-email-rows');
                    var row = document.createElement('div');
                    row.className = 'input-group mb-2 daily-headcount-email-row';
                    row.innerHTML = '<input type="email" name="emails[]" class="form-control" value="" maxlength="191" placeholder="pl. osztalyfonok@example.hu">'
                        + '<button type="button" class="btn btn-outline-danger daily-headcount-remove-row"><i class="fa-solid fa-trash"></i></button>';
                    container.appendChild(row);
                    row.querySelector('input').focus();
                });
            });

            document.addEventListener('click', function (event) {
                var removeButton = event.target.closest('.daily-headcount-remove-row');
                if (!removeButton) {
                    return;
                }
                var row = removeButton.closest('.daily-headcount-email-row');
                var container = removeButton.closest('.daily-headcount-email-rows');
                if (row && container && container.querySelectorAll('.daily-headcount-email-row').length > 1) {
                    row.remove();
                } else if (row) {
                    row.querySelector('input').value = '';
                }
            });
        });
    </script>
@endpush
@endsection
