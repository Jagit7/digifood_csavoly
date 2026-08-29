<div class="card border-0 shadow-sm">
    <div class="card-body">

        <div class="row">

            <div class="col-lg-6 mb-3">
                <label class="form-label">Étlap címe</label>
                <input type="text"
                       name="title"
                       class="form-control"
                       value="{{ old('title', $menu->title ?? '') }}"
                       placeholder="Pl. 2026. július 6–11. heti étlap"
                       required>
            </div>

            <div class="col-lg-2 mb-3">
                <label class="form-label">Hét kezdete</label>
                <input type="date"
                       name="week_start"
                       class="form-control"
                       value="{{ old('week_start', isset($menu) ? $menu->week_start->format('Y-m-d') : '') }}"
                       required>
                <small class="text-muted">Hétfő</small>
            </div>

            <div class="col-lg-2 mb-3">
                <label class="form-label">Hét vége</label>
                <input type="date"
                       name="week_end"
                       class="form-control"
                       value="{{ old('week_end', isset($menu) ? $menu->week_end->format('Y-m-d') : '') }}"
                       required>
                <small class="text-muted">Péntek vagy szombat</small>
            </div>

            <div class="col-lg-2 mb-3">
                <label class="form-label">Típus</label>
                <select name="type" class="form-control" required>
                    <option value="weekly" {{ old('type', $menu->type ?? 'weekly') == 'weekly' ? 'selected' : '' }}>
                        Heti
                    </option>

                    <option value="dietary" {{ old('type', $menu->type ?? '') == 'dietary' ? 'selected' : '' }}>
                        Diétás
                    </option>

                    <option value="ab" {{ old('type', $menu->type ?? '') == 'ab' ? 'selected' : '' }}>
                        A/B menü
                    </option>
                </select>
            </div>

        </div>

        <div class="row">

            <div class="col-lg-10 mb-3">
                <label class="form-label">Étlap fájl</label>

                <input type="file"
                       name="menu_file"
                       class="form-control"
                       accept=".pdf,.jpg,.jpeg,.png,.webp"
                       {{ isset($menu) ? '' : 'required' }}>

                @isset($menu)
                    <small class="text-muted">
                        Jelenlegi fájl:
                        <strong>{{ $menu->file_name }}</strong>
                        (Csak akkor válassz újat, ha cserélni szeretnéd.)
                        <a href="{{ asset('storage/'.$menu->file_path) }}" target="_blank" rel="noopener" class="ms-1">
                            <i class="fa fa-up-right-from-square"></i> Megnyitás
                        </a>
                    </small>
                @else
                    <small class="text-muted">
                        PDF vagy kép (JPG, PNG, WEBP), maximum 10 MB.
                    </small>
                @endisset
            </div>

            <div class="col-lg-2 mb-3">
                <label class="form-label">Állapot</label>

                <div class="form-check mt-2">
                    <input type="checkbox"
                           class="form-check-input"
                           id="active"
                           name="active"
                           value="1"
                           {{ old('active', $menu->active ?? true) ? 'checked' : '' }}>

                    <label class="form-check-label" for="active">
                        Aktív
                    </label>
                </div>
            </div>

        </div>

    </div>

    <div class="card-footer bg-white d-flex justify-content-end gap-2">

        <a href="{{ route('dashboard.institution.menus.index') }}"
           class="btn btn-light">
            Mégsem
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i>

            @isset($menu)
                Módosítás mentése
            @else
                Étlap feltöltése
            @endisset
        </button>

    </div>
</div>