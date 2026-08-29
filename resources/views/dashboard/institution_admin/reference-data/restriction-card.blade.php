<div class="card mb-4">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ $title }}</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('dashboard.institution.reference-data.restrictions.store') }}" class="mb-4">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="row align-items-end">
                <div class="col-md-7 mb-3">
                    <label class="form-label" for="{{ $inputId }}">Új megnevezés</label>
                    <input id="{{ $inputId }}" type="text" name="name" class="form-control" maxlength="100" required>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="active" value="1"
                               id="{{ $inputId }}_active" checked>
                        <label class="form-check-label" for="{{ $inputId }}_active">Aktív</label>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-plus me-1"></i>Hozzáadás
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th width="60">#</th>
                    <th>Megnevezés</th>
                    <th width="100">Állapot</th>
                    <th width="110" class="text-end">Műveletek</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->name }}</strong></td>
                        <td>
                            <span class="badge {{ $item->active ? 'badge-success' : 'badge-secondary' }} light">
                                {{ $item->active ? 'Aktív' : 'Inaktív' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-xs btn-outline-warning"
                                    data-bs-toggle="modal" data-bs-target="#restrictionModal{{ $item->id }}"
                                    title="Szerkesztés">
                                <i class="fa fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('dashboard.institution.reference-data.restrictions.destroy', $item) }}"
                                  class="d-inline delete-form"
                                  data-title="Biztosan törlöd ezt a törzsadatot?"
                                  data-text="Az elem eltűnik a választható listából.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Törlés">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">A lista még üres.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($items as $item)
    <div class="modal fade" id="restrictionModal{{ $item->id }}" tabindex="-1"
         aria-labelledby="restrictionModalLabel{{ $item->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('dashboard.institution.reference-data.restrictions.update', $item) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="restrictionModalLabel{{ $item->id }}">{{ $title }} szerkesztése</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Megnevezés</label>
                            <input type="text" name="name" class="form-control" maxlength="100"
                                   value="{{ $item->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lista</label>
                            <select name="type" class="form-control" required>
                                <option value="allergen" @selected($item->type === 'allergen')>Allergének</option>
                                <option value="intolerance" @selected($item->type === 'intolerance')>Ételérzékenységek</option>
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" value="1"
                                   id="restrictionActive{{ $item->id }}" @checked($item->active)>
                            <label class="form-check-label" for="restrictionActive{{ $item->id }}">Aktív</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                        <button type="submit" class="btn btn-primary">Mentés</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
