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

    document.getElementById('price-form')?.addEventListener('submit', function () {
        this.querySelectorAll('.price-input').forEach(function (input) {
            input.value = input.value.replace(/,/g, '');
        });
    });
});
