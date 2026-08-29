<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-1">Lemondás adatai</h4>
        <div class="text-muted small">
            A rögzített időszakban a kiválasztott {{ $isKindergarten ? 'csoport' : 'osztály' }} minden aktív gyermekének étkezése lemondottnak számít.
        </div>
    </div>
    <div class="card-body">
        @if($classGroups->count())
            <form method="POST" action="{{ $formAction }}">
                @csrf
                @if($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="class_group_id" class="form-label">
                            {{ $isKindergarten ? 'Csoport' : 'Osztály' }}
                        </label>
                        <select name="class_group_id" id="class_group_id" class="form-control" required>
                            <option value="">Válassz...</option>
                            @foreach($classGroups as $classGroup)
                                <option value="{{ $classGroup->id }}"
                                    @selected((string) old('class_group_id', $classCancellation?->class_group_id) === (string) $classGroup->id)>
                                    {{ $classGroup->name }} – {{ $classGroup->schoolYear?->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="date_from" class="form-label">Kezdete</label>
                        <input type="date" name="date_from" id="date_from" class="form-control"
                               min="{{ now()->toDateString() }}"
                               value="{{ old('date_from', $classCancellation?->date_from?->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="date_to" class="form-label">Vége</label>
                        <input type="date" name="date_to" id="date_to" class="form-control"
                               min="{{ now()->toDateString() }}"
                               value="{{ old('date_to', $classCancellation?->date_to?->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-12 mb-3">
                        <label for="reason" class="form-label">Indok / megjegyzés</label>
                        <textarea name="reason" id="reason" rows="4" maxlength="191" class="form-control"
                                  placeholder="Pl. osztálykirándulás, erdei iskola, külső program...">{{ old('reason', $classCancellation?->reason) }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('dashboard.institution.class-cancellations.index') }}" class="btn btn-light">Mégsem</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>Mentés
                    </button>
                </div>
            </form>
        @else
            @include('layouts.partials.components.ui.empty-state', [
                'icon' => 'fa-solid fa-users',
                'title' => 'Nincs választható '.($isKindergarten ? 'csoport' : 'osztály'),
                'text' => 'Lemondás rögzítéséhez előbb aktív csoportot vagy osztályt kell létrehozni.',
            ])
        @endif
    </div>
</div>
