@extends('layouts.superadmin')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Eszköz-jóváhagyások</h4>
                    <p class="text-muted mb-0">
                        Intézményi adminisztrátorok bejelentkezése gépenként korlátozva. Felhasználónként
                        egyszerre akár {{ $maxDevices }} jóváhagyott eszköz (böngésző) is érvényes lehet.
                    </p>
                </div>
            </div>
            <div class="card-body">
                @if($users->isEmpty())
                    <div class="text-center py-5">
                        <h5 class="mb-2">Nincs intézményi adminisztrátor felhasználó.</h5>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Felhasználó</th>
                                    <th>Intézmény</th>
                                    <th>Eszközök</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                    @php($devices = $user->devices)
                                    @php($occupiedCount = $devices->filter(fn ($d) => $d->hasApprovedDevice() || $d->hasPendingRequest())->count())
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $user->name }}</div>
                                            <div class="text-muted small">{{ $user->email }}</div>
                                        </td>
                                        <td>{{ $user->institution?->name ?? '—' }}</td>
                                        <td>
                                            @if($occupiedCount === 0)
                                                <span class="badge bg-secondary">Nincs beállított eszköz</span>
                                            @else
                                                <div class="d-flex flex-wrap gap-2">
                                                    @forelse($devices as $index => $device)
                                                        @if($device->hasApprovedDevice() || $device->hasPendingRequest())
                                                            <div class="border rounded-3 p-2" style="width: 15rem;">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <span class="fw-semibold small text-muted">{{ $index + 1 }}. eszköz</span>
                                                                    @if($device->hasApprovedDevice())
                                                                        <span class="badge bg-success">Aktív</span>
                                                                    @elseif($device->hasPendingRequest())
                                                                        <span class="badge bg-warning text-dark">Jóváhagyásra vár</span>
                                                                    @endif
                                                                </div>

                                                                @if($device->hasApprovedDevice())
                                                                    <dl class="small text-muted mb-2 row gx-1 gy-0 mb-2">
                                                                        <dt class="col-5 fw-normal">IP</dt>
                                                                        <dd class="col-7 text-end">{{ $device->approved_ip ?: '—' }}</dd>
                                                                        <dt class="col-5 fw-normal">Jóváhagyva</dt>
                                                                        <dd class="col-7 text-end">{{ $device->approved_at?->format('Y.m.d. H:i') }}</dd>
                                                                        @if($device->approver)
                                                                            <dt class="col-5 fw-normal">Jóváhagyta</dt>
                                                                            <dd class="col-7 text-end">{{ $device->approver->name }}</dd>
                                                                        @endif
                                                                        <dt class="col-5 fw-normal">Utolsó haszn.</dt>
                                                                        <dd class="col-7 text-end">{{ $device->last_used_at?->format('Y.m.d. H:i') ?? '—' }}</dd>
                                                                    </dl>
                                                                @endif

                                                                @if($device->hasPendingRequest())
                                                                    <dl class="small text-muted row gx-1 gy-0 mb-2">
                                                                        <dt class="col-5 fw-normal">IP</dt>
                                                                        <dd class="col-7 text-end">{{ $device->pending_ip ?: '—' }}</dd>
                                                                        <dt class="col-5 fw-normal">Kérve</dt>
                                                                        <dd class="col-7 text-end">{{ $device->pending_requested_at?->format('Y.m.d. H:i') }}</dd>
                                                                    </dl>
                                                                @endif

                                                                <div class="d-flex flex-wrap gap-1">
                                                                    @if($device->hasPendingRequest())
                                                                        <form method="POST" action="{{ route('dashboard.superadmin.institution-admin-devices.approve', $device) }}">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-success">Jóváhagyás</button>
                                                                        </form>
                                                                        <form method="POST" action="{{ route('dashboard.superadmin.institution-admin-devices.reject', $device) }}">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Elutasítás</button>
                                                                        </form>
                                                                    @endif
                                                                    @if($device->hasApprovedDevice())
                                                                        <form method="POST" action="{{ route('dashboard.superadmin.institution-admin-devices.revoke', $device) }}" onsubmit="return confirm('Biztosan visszavonod ennek a felhasználónak ezt a jóváhagyott eszközét? Legközelebb erről a gépről is csak jóváhagyás után tud majd belépni.');">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Visszavonás</button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @empty
                                                    @endforelse
                                                </div>

                                                @if($occupiedCount < $maxDevices)
                                                    <div class="text-muted small mt-2">Szabad hely: még {{ $maxDevices - $occupiedCount }} eszköz regisztrálható.</div>
                                                @else
                                                    <div class="text-muted small mt-2">Nincs több szabad hely ({{ $maxDevices }}/{{ $maxDevices }} foglalt).</div>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
