document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.price-input').forEach(function (input) {
        input.style.textAlign = 'left';
        input.dir = 'ltr';

        input.addEventListener('input', function () {
            const value = this.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const parts = value.split('.');
            const integerPart = parts[0] || '';
            const decimalPart = parts.length > 1 ? '.' + parts.slice(1).join('').slice(0, 2) : '';
            this.value = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + decimalPart;
        });
    });

    document.getElementById('price-form')?.addEventListener('submit', function (event) {
        const form = this;
        const requiredSources = Number(form.dataset.requiredSources || 0);
        const rows = Array.from(form.querySelectorAll('tbody tr')).slice(0, requiredSources);
        let firstInvalid = null;

        form.querySelector('.client-validation-error')?.remove();

        rows.forEach(function (row) {
            row.querySelectorAll('.is-invalid').forEach(function (input) {
                input.classList.remove('is-invalid');
            });
        });

        rows.forEach(function (row) {
            const date = row.querySelector('.jalali-date');
            const source = row.querySelector('input[name*="[source]"]');
            const price = row.querySelector('.price-input');

            [date, source, price].forEach(function (input) {
                if (input && !input.value.replace(/,/g, '').trim()) {
                    input.classList.add('is-invalid');
                    if (!firstInvalid) firstInvalid = input;
                }
            });
        });

        if (firstInvalid) {
            event.preventDefault();

            const alert = document.createElement('div');
            alert.className = 'alert alert-warning client-validation-error mt-3';
            alert.setAttribute('role', 'alert');
            alert.textContent = 'لطفاً تمام آیتم‌های اجباری رصد بازار را تکمیل کنید.';

            const table = form.querySelector('.table-responsive');
            if (table) {
                table.insertAdjacentElement('beforebegin', alert);
            } else {
                form.prepend(alert);
            }

            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
            return;
        }

        form.querySelectorAll('.price-input').forEach(function (input) {
            input.value = input.value.replace(/,/g, '');
        });
    });
});
