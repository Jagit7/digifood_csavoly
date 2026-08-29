@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('invite_url'))
    <div class="alert alert-info alert-dismissible fade show">
        <strong>Meghívó link:</strong><br>

        <a href="{{ session('invite_url') }}" target="_blank">
            {{ session('invite_url') }}
        </a>

        <div class="small mt-2">
            A link 24 óráig érvényes.
        </div>

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('manual_activation_url'))
    <div class="alert alert-info alert-dismissible fade show">
        <strong>
            Aktiváló link{{ session('manual_activation_recipient') ? ' — '.session('manual_activation_recipient') : '' }}:
        </strong>

        <div class="input-group my-2" style="max-width: 640px;">
            <input
                type="text"
                id="manual-activation-url-input"
                class="form-control"
                value="{{ session('manual_activation_url') }}"
                readonly
                onclick="this.select()"
            >
            <button type="button" class="btn btn-outline-secondary" id="manual-activation-url-copy">
                Másolás
            </button>
        </div>

        <div class="small mt-2">
            A link{{ session('manual_activation_expires_at') ? ' '.session('manual_activation_expires_at').'-ig' : '' }} érvényes.
            Ezt a linket saját e-mailben küldheti ki a címzettnek - a rendszer ebben az esetben nem küld
            automatikus e-mailt.
        </div>

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const copyBtn = document.getElementById('manual-activation-url-copy');
            const urlInput = document.getElementById('manual-activation-url-input');

            if (!copyBtn || !urlInput) {
                return;
            }

            copyBtn.addEventListener('click', function () {
                urlInput.select();
                urlInput.setSelectionRange(0, 99999);

                const showResult = function (label) {
                    const original = copyBtn.textContent;
                    copyBtn.textContent = label;
                    setTimeout(function () {
                        copyBtn.textContent = original;
                    }, 2000);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(urlInput.value)
                        .then(function () { showResult('Másolva!'); })
                        .catch(function () { showResult('Nem sikerült'); });

                    return;
                }

                try {
                    document.execCommand('copy');
                    showResult('Másolva!');
                } catch (e) {
                    showResult('Nem sikerült');
                }
            });
        });
    </script>
@endif

@if(isset($errors) && $errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
