@extends('layouts.superadmin')

@section('title', 'Szülői fiók aktiválási meghívók')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Szülői fiók aktiválási meghívók',
        'subtitle' => 'Kommunikáció / Szülői fiók meghívók',
        'buttons' => [
            [
                'url' => route('dashboard.institution.communication.emails.index'),
                'text' => 'Küldési előzmények',
                'class' => 'btn btn-outline-primary',
                'icon' => 'fa-solid fa-clock-rotate-left',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Kiküldhető meghívók',
            'value' => $eligibleCount,
            'subtitle' => 'Még nem kaptak meghívót, még nincs fiókjuk',
            'icon' => 'fa-solid fa-paper-plane',
            'color' => 'green',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Korábban meghívva',
            'value' => $alreadyInvitedCount,
            'subtitle' => 'Már kaptak aktivációs e-mailt',
            'icon' => 'fa-solid fa-envelope-circle-check',
            'color' => 'blue',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktivált fiókok',
            'value' => $alreadyActivatedCount,
            'subtitle' => 'Már regisztráltak szülői fiókot',
            'icon' => 'fa-solid fa-user-check',
            'color' => 'orange',
        ])
    </div>

    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-1"></i>
        Ez a küldés kizárólag azoknak a gondviselőknek megy ki, akiknek van rögzített e-mail címük,
        még nem hoztak létre szülői fiókot, és korábban még nem kaptak ilyen aktivációs meghívót.
        Ha később új osztályt vagy gyermekeket rögzít az admin, az újonnan felvitt gondviselők
        automatikusan bekerülnek a következő kiküldésbe.
    </div>

    <div class="alert alert-light border">
        <i class="fa-solid fa-circle-question me-1"></i>
        <strong>Egyetlen, konkrét szülőnek/gondviselőnek 2 módon küldhető aktiválási lehetőség</strong>
        (pl. ha csak egy most felvitt új diák szülőjét szeretné meghívni, a lenti
        <em>"Egyedi meghívó egy adott szülőnek"</em> panelen):
        <ol class="mb-0 mt-2">
            <li>
                <strong>Egyedi e-mail küldése a rendszerből</strong> - a Digifood elküldi neki (és kizárólag neki)
                ugyanezt az aktivációs e-mailt, amit a fenti üzenet mezőben lát/szerkeszt.
            </li>
            <li>
                <strong>Másolható aktiváló link generálása</strong> - a rendszer nem küld e-mailt, hanem egy
                egyedi linket ad, amit Ön másol be egy saját (pl. Outlook/Gmail) levelébe, és úgy küldi ki a
                szülőnek. A szülő a linkre kattintva állíthatja be jelszavát, és léphet be azonnal.
            </li>
        </ol>
    </div>

    <form method="POST"
          action="{{ route('dashboard.institution.communication.parent-activation-invite.store') }}"
          id="invite-form"
          class="confirm-form"
          data-title="Biztosan elindítja a meghívók kiküldését?"
          data-text="A(z) {{ $eligibleCount }} érintett gondviselő e-mailben kap tájékoztatást a fiókja aktiválásáról."
          data-confirm-button-text="Küldés"
          data-cancel-button-text="Mégse">
        @csrf

        <div class="row">
            <div class="col-xl-8">

                <div class="card">

                    <div class="card-header">
                        <h4 class="card-title mb-0">Üzenet</h4>
                    </div>

                    <div class="card-body">

                        @error('recipients')
                            <div class="alert alert-danger">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="mb-4">
                            <label for="subject" class="form-label">
                                E-mail tárgya
                            </label>

                            <input
                                type="text"
                                id="subject"
                                name="subject"
                                class="form-control @error('subject') is-invalid @enderror"
                                value="{{ old('subject', $defaultSubject) }}"
                                maxlength="191"
                                required
                            >

                            @error('subject')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                E-mail szövege
                            </label>

                            <div class="custom-ekeditor mb-3">
                                <div id="ckeditor">{!! old('body', $defaultBody) !!}</div>
                            </div>

                            {{-- CKEditor tartalma ide kerül submit előtt --}}
                            <textarea
                                name="body"
                                id="body"
                                class="d-none"
                            >{{ old('body', $defaultBody) }}</textarea>

                            @error('body')
                                <div class="text-danger small">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                    </div>

                    <div class="card-footer text-end">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="invite-submit"
                            @disabled($eligibleCount === 0)
                        >
                            <i class="fa-solid fa-paper-plane me-1"></i>
                            {{ $eligibleCount }} meghívó küldése queue-ba
                        </button>
                    </div>

                </div>
            </div>

            {{-- JOBB OLDAL --}}
            <div class="col-xl-4">
                <div class="card">

                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            Használható helyőrzők
                        </h4>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <code>#SZULO_NEVE#</code>
                            <div class="small text-muted">
                                A gondviselő neve
                            </div>
                        </div>

                        <div class="mb-3">
                            <code>#GYERMEK_NEVE#</code>
                            <div class="small text-muted">
                                A gyermek neve, több gyermeknél vesszővel felsorolva
                            </div>
                        </div>

                        <div class="mb-3">
                            <code>#OSZTALY#</code>
                            <div class="small text-muted">
                                Az osztály neve, több osztálynál vesszővel felsorolva
                            </div>
                        </div>

                        <div class="mb-0">
                            <code>#INTEZMENY_NEVE#</code>
                            <div class="small text-muted">
                                Az intézmény neve
                            </div>
                        </div>

                    </div>

                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            Érintett gondviselők ({{ $eligibleCount }})
                        </h4>
                    </div>

                    <div class="card-body" style="max-height: 360px; overflow:auto;">
                        @forelse($eligibleRecipients as $recipient)
                            <div class="mb-2 pb-2 border-bottom">
                                <div><strong>{{ $recipient['recipient_name'] ?: $recipient['email'] }}</strong></div>
                                <div class="small text-muted">{{ $recipient['email'] }}</div>
                                @if(!empty($recipient['child_names']))
                                    <div class="small text-muted">
                                        {{ implode(', ', $recipient['child_names']) }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted">
                                Jelenleg nincs kiküldhető meghívó.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </form>

    <div class="card" id="individual-invite-card">

        <div class="card-header">
            <h4 class="card-title mb-0">Egyedi meghívó egy adott szülőnek</h4>
            <div class="text-muted small">
                Válasszon ki egy konkrét gondviselőt, majd döntse el, hogy a fenti üzenetet neki egyedül
                elküldi-e a rendszerből, vagy inkább egy másolható linket generál, amit saját maga küld ki.
            </div>
        </div>

        <div class="card-body">

            @error('guardian')
                <div class="alert alert-danger">
                    {{ $message }}
                </div>
            @enderror

            <div class="mb-3">
                <label for="individual-guardian-search" class="form-label">
                    Gondviselő keresése (név, e-mail vagy gyermek neve alapján)
                </label>
                <input
                    type="text"
                    id="individual-guardian-search"
                    class="form-control mb-2"
                    placeholder="Kezdjen el gépelni a szűkítéshez..."
                    autocomplete="off"
                >

                {{-- FONTOS: szándékosan NEM natív <select> - ezen az admin
                     felületen a natív <select> elemeket egy globális szkript
                     (bootstrap-select) automatikusan egy saját legördülő
                     widgetté alakítja, ami elnyelné/felülírná az alábbi
                     keresős szűrést. Ehelyett egy sima, kattintható listát
                     építünk, és egy rejtett mezőben tároljuk a kiválasztott
                     gondviselő azonosítóját. --}}
                <input type="hidden" id="individual-guardian-id" value="">

                <div
                    id="individual-guardian-list"
                    class="list-group"
                    style="max-height: 260px; overflow-y: auto;"
                >
                    @forelse($individualInviteGuardians as $option)
                        <button
                            type="button"
                            class="list-group-item list-group-item-action individual-guardian-option"
                            data-id="{{ $option['id'] }}"
                            data-search="{{ mb_strtolower($option['name'].' '.$option['email'].' '.implode(' ', $option['child_names'])) }}"
                        >
                            <div>
                                <strong>{{ $option['name'] }}</strong> — {{ $option['email'] }}
                            </div>
                            @if(!empty($option['child_names']))
                                <div class="small text-muted">
                                    {{ implode(', ', $option['child_names']) }}
                                </div>
                            @endif
                            @if($option['already_invited'])
                                <span class="badge bg-secondary">korábban meghívva</span>
                            @endif
                        </button>
                    @empty
                        <div class="text-muted p-2">
                            Jelenleg nincs olyan gondviselő, akinek még nincs aktivált szülői fiókja.
                        </div>
                    @endforelse
                </div>

                <div class="small text-muted mt-1" id="individual-guardian-empty-search" style="display:none;">
                    Nincs a keresésnek megfelelő gondviselő.
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <form
                    method="POST"
                    id="individual-send-form"
                    class="confirm-form"
                    data-title="Biztosan elküldi a meghívót ennek az egy gondviselőnek?"
                    data-text="A kiválasztott gondviselő e-mailben kapja meg a fenti üzenetet."
                    data-confirm-button-text="Küldés"
                    data-cancel-button-text="Mégse"
                >
                    @csrf
                    <input type="hidden" name="subject" id="individual-send-subject">
                    <textarea name="body" id="individual-send-body" class="d-none"></textarea>

                    <button type="submit" class="btn btn-outline-primary" id="individual-send-button" disabled>
                        <i class="fa-solid fa-paper-plane me-1"></i>
                        E-mail meghívó küldése neki
                    </button>
                </form>

                <form
                    method="POST"
                    id="individual-link-form"
                    class="confirm-form"
                    data-title="Aktiváló link generálása?"
                    data-text="A rendszer nem küld e-mailt - a linket saját levelében kell majd kiküldenie a szülőnek."
                    data-confirm-button-text="Generálás"
                    data-cancel-button-text="Mégse"
                >
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary" id="individual-link-button" disabled>
                        <i class="fa-solid fa-link me-1"></i>
                        Aktiváló link generálása (másolásra)
                    </button>
                </form>
            </div>

        </div>
    </div>
@endsection


@push('scripts')
    <script src="{{ asset('dashboard/vendor/ckeditor/ckeditor.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const bodyInput = document.getElementById('body');
            const form = document.getElementById('invite-form');
            const submitButton = document.getElementById('invite-submit');


            /**
             * CKEditor tartalmának átmásolása
             * a Laravel által elküldött body mezőbe.
             */
            function syncEditor() {
                if (
                    window.editor &&
                    bodyInput &&
                    typeof window.editor.getData === 'function'
                ) {
                    bodyInput.value = window.editor.getData();
                }
            }

            if (submitButton) {
                submitButton.addEventListener('click', function () {
                    syncEditor();
                });
            }

            if (form) {
                form.addEventListener(
                    'submit',
                    function () {
                        syncEditor();
                    },
                    true
                );
            }


            /**
             * Egyedi meghívó szekció: kereséssel szűrhető gondviselő-lista,
             * majd a kiválasztott gondviselőhöz igazítjuk a két mini-form
             * "action" URL-jét (guardian id behelyettesítése), illetve a
             * küldő mini-formba átmásoljuk a fenti üzenet aktuális
             * tárgyát/szövegét, mielőtt az elküldésre kerülne.
             */
            const searchInput = document.getElementById('individual-guardian-search');
            const guardianIdInput = document.getElementById('individual-guardian-id');
            const guardianOptions = Array.from(document.querySelectorAll('.individual-guardian-option'));
            const guardianEmptySearchNotice = document.getElementById('individual-guardian-empty-search');
            const sendForm = document.getElementById('individual-send-form');
            const linkForm = document.getElementById('individual-link-form');
            const sendButton = document.getElementById('individual-send-button');
            const linkButton = document.getElementById('individual-link-button');
            const subjectInput = document.getElementById('subject');
            const individualSendSubject = document.getElementById('individual-send-subject');
            const individualSendBody = document.getElementById('individual-send-body');

            const sendActionTemplate = @json(route('dashboard.institution.communication.parent-activation-invite.send-individual', ['guardian' => '__ID__']));
            const linkActionTemplate = @json(route('dashboard.institution.communication.parent-activation-invite.generate-link', ['guardian' => '__ID__']));

            if (searchInput && guardianOptions.length) {
                searchInput.addEventListener('input', function () {
                    const term = searchInput.value.trim().toLowerCase();
                    let visibleCount = 0;

                    guardianOptions.forEach(function (option) {
                        const matches = term === '' || (option.dataset.search || '').includes(term);
                        option.style.display = matches ? '' : 'none';

                        if (matches) {
                            visibleCount++;
                        }
                    });

                    if (guardianEmptySearchNotice) {
                        guardianEmptySearchNotice.style.display = visibleCount === 0 ? '' : 'none';
                    }
                });
            }

            function selectedGuardianId() {
                return guardianIdInput && guardianIdInput.value ? guardianIdInput.value : null;
            }

            guardianOptions.forEach(function (option) {
                option.addEventListener('click', function () {
                    guardianOptions.forEach(function (o) {
                        o.classList.remove('active');
                    });
                    option.classList.add('active');

                    if (guardianIdInput) {
                        guardianIdInput.value = option.dataset.id;
                    }

                    if (sendButton) {
                        sendButton.disabled = false;
                    }

                    if (linkButton) {
                        linkButton.disabled = false;
                    }
                });
            });

            // FONTOS: a globális .confirm-form kezelő (ld. layouts/partials/scripts.blade.php)
            // megerősítés után a natív HTMLFormElement.prototype.submit()-et hívja meg
            // közvetlenül, ami NEM vált ki újabb "submit" eseményt - ezért az action URL
            // beállítását és a mezők átmásolását nem lehet a form submit eseményéhez kötni
            // (az soha nem futna le), hanem a gomb kattintásához kötjük, ami mindig
            // megelőzi a submit eseményt (ugyanígy jár el fentebb a syncEditor() hívása is
            // a fő form "invite-submit" gombjánál).
            if (sendButton) {
                sendButton.addEventListener('click', function () {
                    const guardianId = selectedGuardianId();

                    if (guardianId && sendForm) {
                        sendForm.action = sendActionTemplate.replace('__ID__', guardianId);
                    }

                    syncEditor();

                    if (subjectInput && individualSendSubject) {
                        individualSendSubject.value = subjectInput.value;
                    }

                    if (bodyInput && individualSendBody) {
                        individualSendBody.value = bodyInput.value;
                    }
                });
            }

            if (linkButton) {
                linkButton.addEventListener('click', function () {
                    const guardianId = selectedGuardianId();

                    if (guardianId && linkForm) {
                        linkForm.action = linkActionTemplate.replace('__ID__', guardianId);
                    }
                });
            }

        });
    </script>
@endpush
