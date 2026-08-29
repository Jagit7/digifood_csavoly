@extends('layouts.superadmin')

@section('title', 'Kapcsolattartók')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Kapcsolattartók',
        'subtitle' => 'Intézményi kapcsolattartók és elérhetőségeik',
        'buttonText' => 'Új kapcsolattartó',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.contacts.create'),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes kapcsolattartó',
            'value' => $stats['total'],
            'subtitle' => 'Rögzített kapcsolatok',
            'icon' => 'fa-solid fa-address-book',
            'color' => 'blue'
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg használatban',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'green'
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Elsődleges',
            'value' => $stats['primary'],
            'subtitle' => 'Kiemelt kapcsolattartó',
            'icon' => 'fa-solid fa-star',
            'color' => 'orange'
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'E-maillel',
            'value' => $stats['with_email'],
            'subtitle' => 'Van rögzített e-mail cím',
            'icon' => 'fa-solid fa-envelope',
            'color' => 'purple'
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">Kapcsolattartó lista</h4>
                </div>
                <div class="card-body">
                    @if($contacts->count())
                        <div class="table-responsive">
                            <table class="table table-hover table-responsive-md align-middle">
                                <thead>
                                <tr>
                                    <th width="70">#</th>
                                    <th>Név</th>
                                    <th>Szerep</th>
                                    <th>E-mail</th>
                                    <th>Telefon</th>
                                    <th>Állapot</th>
                                    <th width="150" class="text-end">Műveletek</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($contacts as $contact)
                                    <tr>
                                        <td>{{ ($contacts->firstItem() ?? 0) + $loop->index }}</td>
                                        <td>
                                            <strong>{{ $contact->name }}</strong>
                                            @if($contact->is_primary)
                                                <div class="mt-1">
                                                    <span class="badge badge-warning light">Elsődleges</span>
                                                </div>
                                            @endif
                                            @if($contact->notes)
                                                <div class="small text-muted mt-1">{{ Str::limit($contact->notes, 70) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $contact->role ?: '—' }}</td>
                                        <td>{{ $contact->email ?: '—' }}</td>
                                        <td>{{ $contact->phone ?: '—' }}</td>
                                        <td>
                                            <span class="badge {{ $contact->is_active ? 'badge-success' : 'badge-secondary' }} light">
                                                {{ $contact->is_active ? 'Aktív' : 'Inaktív' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('dashboard.institution.contacts.edit', $contact) }}"
                                               class="btn btn-xs btn-outline-warning"
                                               title="Szerkesztés">
                                                <i class="fa fa-pen"></i>
                                            </a>
                                            <form method="POST"
                                                  action="{{ route('dashboard.institution.contacts.destroy', $contact) }}"
                                                  class="d-inline delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-xs btn-outline-danger"
                                                        title="Törlés">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $contacts->links('vendor.pagination.digifood') }}
                        </div>
                    @else
                        @include('layouts.partials.components.ui.empty-state', [
                            'icon' => 'fa-solid fa-address-book',
                            'title' => 'Még nincs kapcsolattartó',
                            'text' => 'Vedd fel az első intézményi kapcsolattartót.',
                            'buttonText' => 'Új kapcsolattartó',
                            'buttonIcon' => 'fa-solid fa-plus',
                            'buttonUrl' => route('dashboard.institution.contacts.create')
                        ])
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
