<script src="{{ asset('dashboard/vendor/global/global.min.js') }}"></script>
<script src="{{ asset('dashboard/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ asset('dashboard/vendor/chart-js/chart.bundle.min.js') }}"></script>
<!-- Chart piety plugin files -->
<script src="{{ asset('dashboard/vendor/peity/jquery.peity.min.js') }}"></script>
<!-- Apex Chart -->
<script src="{{ asset('dashboard/vendor/apexchart/apexchart.js') }}"></script>
<!-- Dashboard 1 -->
<!-- <script src="{{ asset('dashboard/js/dashboard/dashboard-1.js') }}"></script> -->
<script src="{{ asset('dashboard/js/custom.min.js') }}"></script>
<script src="{{ asset('dashboard/js/deznav-init.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.confirm-form, .delete-form').forEach(function (form) {

        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;

                return;
            }

            if (!form.checkValidity()) {
                form.reportValidity();

                return;
            }

            e.preventDefault();
            const isDelete = form.classList.contains('delete-form');

            Swal.fire({
                title: form.dataset.title || (isDelete ? 'Biztosan törölni szeretnéd?' : 'Biztosan folytatod?'),
                text: form.dataset.text || (isDelete ? 'A művelet nem vonható vissza.' : 'Ellenőrizd az adatokat a folytatás előtt.'),
                icon: form.dataset.icon || 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButtonText || (isDelete ? 'Igen, törlöm' : 'Igen, folytatom'),
                cancelButtonText: form.dataset.cancelButtonText || 'Mégsem',
                confirmButtonColor: form.dataset.confirmButtonColor || (isDelete ? '#dc3545' : '#886CC0'),
                cancelButtonColor: '#6c757d'
            }).then((result) => {

                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    HTMLFormElement.prototype.submit.call(form);
                }

            });

        });

    });

});
</script>
