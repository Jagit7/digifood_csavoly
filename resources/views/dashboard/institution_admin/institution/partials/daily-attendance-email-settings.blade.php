<div class="card mb-4" id="napi-jelenleti-iv">
    <div class="card-header">
        <div>
            <h4 class="card-title mb-1">Napi jelenléti ív e-mailben</h4>
            <div class="text-muted small">Automatikus napi jelenléti összesítő csoportonként - kizárólag óvodai intézményeknél elérhető.</div>
            <div class="text-muted small mt-1">Ez a blokk külön űrlapokon menthető, nem az oldal felső közös mentés gombjával.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('dashboard.institution.settings.daily-attendance.update') }}">
            @csrf
            <div class="row align-items-end">
                <div class="col-lg-6 mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="daily_attendance_email_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="daily_attendance_email_enabled" name="daily_attendance_email_enabled" value="1"
                               @checked((bool) old('daily_attendance_email_enabled', $setting->daily_attendance_email_enabled))>
                        <label class="form-check-label" for="daily_attendance_email_enabled">
                            Napi jelenléti ív automatikus kiküldése
                        </label>
                    </div>
                </div>
                <div class="col-lg-3 mb-3">
                    <label class="form-label" for="daily_attendance_email_send_time">Kiküldés időpontja</label>
                    <input id="daily_attendance_email_send_time" type="time" name="daily_attendance_email_send_time"
                           class="form-control @error('daily_attendance_email_send_time') is-invalid @enderror"
                           value="{{ old('daily_attendance_email_send_time', $dailyAttendanceSendTime) }}" required>
                    @error('daily_attendance_email_send_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 mb-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Napi jelenléti e-mail beállítás mentése
                    </button>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <h5 class="mb-3">Csoportok és címzettek</h5>

        @if($dailyAttendanceGroups->isEmpty())
            <div class="alert alert-info mb-0">
                Ehhez az intézményhez jelenleg nincs aktív gyermekhez rendelt csoport, ezért nincs mit beállítani. Amint egy gyermeknek csoport lesz megadva, a csoport automatikusan megjelenik itt.
            </div>
        @else
            @foreach($dailyAttendanceGroups as $groupName)
                @php
                    $groupHasRecipientError = old('group_name') === $groupName && $errors->has('emails');
                    $groupEmails = old('group_name') === $groupName
                        ? collect(old('emails', []))
                        : ($dailyAttendanceRecipients->get($groupName) ?? collect());
                    $groupHasTestError = old('test_group_name') === $groupName && $errors->has('test_email');
                @endphp
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h6 class="mb-0">{{ $groupName }}</h6>
                        <a href="{{ route('dashboard.institution.settings.daily-attendance.preview', ['groupName' => $groupName]) }}"
                           target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-eye me-1"></i>Előnézet
                        </a>
                    </div>

                    <form method="POST" action="{{ route('dashboard.institution.settings.daily-attendance.recipients.update', ['groupName' => $groupName]) }}">
                        @csrf
                        <input type="hidden" name="group_name" value="{{ $groupName }}">
                        <div class="daily-attendance-email-rows" data-group="{{ $groupName }}">
                            @forelse($groupEmails as $email)
                                <div class="input-group mb-2 daily-attendance-email-row">
                                    <input type="email" name="emails[]"
                                           class="form-control @if($groupHasRecipientError) is-invalid @endif"
                                           value="{{ $email }}" maxlength="191">
                                    <button type="button" class="btn btn-outline-danger daily-attendance-remove-row"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            @empty
                                <div class="input-group mb-2 daily-attendance-email-row">
                                    <input type="email" name="emails[]" class="form-control" value="" maxlength="191" placeholder="pl. ovono1@example.hu">
                                    <button type="button" class="btn btn-outline-danger daily-attendance-remove-row"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            @endforelse
                        </div>
                        @if($groupHasRecipientError)
                            <div class="text-danger small mb-2">{{ $errors->first('emails') }}</div>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary daily-attendance-add-row">
                                <i class="fa-solid fa-plus me-1"></i>Új e-mail cím
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Csoport címzettjeinek mentése
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('dashboard.institution.settings.daily-attendance.test-send', ['groupName' => $groupName]) }}" class="mt-3 pt-3 border-top">
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.daily-attendance-add-row').forEach(function (button) {
                button.addEventListener('click', function () {
                    var container = button.closest('form').querySelector('.daily-attendance-email-rows');
                    var row = document.createElement('div');
                    row.className = 'input-group mb-2 daily-attendance-email-row';
                    row.innerHTML = '<input type="email" name="emails[]" class="form-control" value="" maxlength="191" placeholder="pl. ovono1@example.hu">'
                        + '<button type="button" class="btn btn-outline-danger daily-attendance-remove-row"><i class="fa-solid fa-trash"></i></button>';
                    container.appendChild(row);
                    row.querySelector('input').focus();
                });
            });

            document.addEventListener('click', function (event) {
                var removeButton = event.target.closest('.daily-attendance-remove-row');
                if (!removeButton) {
                    return;
                }
                var row = removeButton.closest('.daily-attendance-email-row');
                var container = removeButton.closest('.daily-attendance-email-rows');
                if (row && container && container.querySelectorAll('.daily-attendance-email-row').length > 1) {
                    row.remove();
                } else if (row) {
                    row.querySelector('input').value = '';
                }
            });
        });
    </script>
@endpush
