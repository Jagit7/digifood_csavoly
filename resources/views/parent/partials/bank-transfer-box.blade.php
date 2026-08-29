{{--
    Felhasználói kérés: ha az intézménynél nincs bekapcsolva a kártyás
    fizetés, de van megadott bankszámlaszáma (ld.
    InstitutionSetting::hasBankTransferAccount() /
    ParentMonthlySettlementService::bankTransferInfo()), a szülői felület
    ezt a vágólapra másolható átutalási tájékoztatót jelenítse meg a
    fizetendő összeggel - a "Kereskedői összesítő" / CIB kártya helyett,
    ami csak kártyás fizetéshez értelmezhető.

    Elvárt paraméter: $bankTransfer (a ParentMonthlySettlementService::
    bankTransferInfo() által visszaadott tömb, 'available' => true esetén).
--}}
<div class="rounded-4 border p-4 df-bank-transfer-box">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="fa-solid fa-building-columns text-primary"></i>
        <h5 class="mb-0">Fizetés banki átutalással</h5>
    </div>
    <p class="text-muted small mb-3">
        Az intézménynél jelenleg nincs bekapcsolva a bankkártyás fizetés. Kérjük, a fizetendő összeget banki átutalással
        rendezze az alábbi számlára.
    </p>

    <div class="row g-3">
        <div class="col-sm-6">
            <div class="small text-muted mb-1">Kedvezményezett</div>
            <div class="d-flex align-items-center justify-content-between gap-2 bg-white border rounded-3 px-3 py-2">
                <span class="fw-semibold">{{ $bankTransfer['account_holder'] ?? 'Az intézmény' }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary df-copy-btn" data-copy-value="{{ $bankTransfer['account_holder'] ?? '' }}" title="Másolás vágólapra">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="small text-muted mb-1">Bankszámlaszám</div>
            <div class="d-flex align-items-center justify-content-between gap-2 bg-white border rounded-3 px-3 py-2">
                <span class="fw-semibold font-monospace">{{ $bankTransfer['account_number'] }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary df-copy-btn" data-copy-value="{{ $bankTransfer['account_number'] }}" title="Másolás vágólapra">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="small text-muted mb-1">Utalandó összeg</div>
            <div class="d-flex align-items-center justify-content-between gap-2 bg-white border rounded-3 px-3 py-2">
                <span class="fw-semibold text-danger">{{ number_format($bankTransfer['amount'], 0, ',', ' ') }} Ft</span>
                <button type="button" class="btn btn-sm btn-outline-secondary df-copy-btn" data-copy-value="{{ $bankTransfer['amount'] }}" title="Másolás vágólapra">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="small text-muted mb-1">Közlemény</div>
            <div class="d-flex align-items-center justify-content-between gap-2 bg-white border rounded-3 px-3 py-2">
                <span class="fw-semibold">{{ $bankTransfer['reference'] }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary df-copy-btn" data-copy-value="{{ $bankTransfer['reference'] }}" title="Másolás vágólapra">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@once
    <style>
        .df-bank-transfer-box {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        }
        .df-copy-btn.df-copy-btn-done {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }
    </style>
    <script>
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.df-copy-btn');

            if (!button) {
                return;
            }

            var value = button.getAttribute('data-copy-value') || '';
            var markCopied = function () {
                var icon = button.querySelector('i');
                button.classList.add('df-copy-btn-done');

                if (icon) {
                    icon.classList.remove('fa-copy');
                    icon.classList.add('fa-check');
                }

                setTimeout(function () {
                    button.classList.remove('df-copy-btn-done');

                    if (icon) {
                        icon.classList.remove('fa-check');
                        icon.classList.add('fa-copy');
                    }
                }, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(markCopied);

                return;
            }

            var helper = document.createElement('textarea');
            helper.value = value;
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.focus();
            helper.select();

            try {
                document.execCommand('copy');
                markCopied();
            } finally {
                document.body.removeChild(helper);
            }
        });
    </script>
@endonce
