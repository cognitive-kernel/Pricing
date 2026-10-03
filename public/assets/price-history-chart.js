document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('price-history-chart');
    if (!canvas) return;

    const params = new URLSearchParams(window.location.search);
    const code = params.get('product_code') || '';
    if (!code) return;

    fetch('/pricing/public/prices/history-chart?product_code=' + encodeURIComponent(code), {
        headers: { 'Accept': 'application/json' }
    })
        .then(function (response) {
            if (!response.ok) throw new Error('chart request failed');
            return response.json();
        })
        .then(function (payload) {
            const points = Array.isArray(payload.points) ? payload.points : [];
            const empty = document.getElementById('price-history-chart-empty');
            if (!points.length) {
                canvas.classList.add('d-none');
                empty?.classList.remove('d-none');
                return;
            }

            const ctx = canvas.getContext('2d');
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            const width = Math.max(700, Math.floor(rect.width || 900));
            const height = 360;
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            canvas.style.height = height + 'px';
            ctx.scale(dpr, dpr);

            const pad = { left: 70, right: 25, top: 25, bottom: 55 };
            const chartW = width - pad.left - pad.right;
            const chartH = height - pad.top - pad.bottom;
            const prices = points.map(function (p) { return Number(p.price) || 0; });
            const min = Math.min.apply(null, prices);
            const max = Math.max.apply(null, prices);
            const range = max === min ? Math.max(1, max * 0.1) : max - min;
            const yMin = Math.max(0, min - range * 0.1);
            const yMax = max + range * 0.1;

            ctx.font = '12px sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';

            for (let i = 0; i <= 4; i++) {
                const value = yMin + ((yMax - yMin) * i / 4);
                const y = pad.top + chartH - (chartH * i / 4);
                ctx.beginPath();
                ctx.moveTo(pad.left, y);
                ctx.lineTo(width - pad.right, y);
                ctx.strokeStyle = '#e5e7eb';
                ctx.stroke();
                ctx.fillStyle = '#6b7280';
                ctx.fillText(value.toLocaleString('en-US', { maximumFractionDigits: 2 }), pad.left - 8, y);
            }

            ctx.beginPath();
            points.forEach(function (point, i) {
                const x = pad.left + (points.length === 1 ? chartW / 2 : chartW * i / (points.length - 1));
                const y = pad.top + chartH - ((Number(point.price) - yMin) / (yMax - yMin)) * chartH;
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.strokeStyle = '#0d6efd';
            ctx.lineWidth = 2;
            ctx.stroke();

            points.forEach(function (point, i) {
                const x = pad.left + (points.length === 1 ? chartW / 2 : chartW * i / (points.length - 1));
                const y = pad.top + chartH - ((Number(point.price) - yMin) / (yMax - yMin)) * chartH;
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, Math.PI * 2);
                ctx.fillStyle = '#0d6efd';
                ctx.fill();
            });

            ctx.fillStyle = '#6b7280';
            ctx.textAlign = 'center';
            const labels = Math.min(6, points.length);
            for (let i = 0; i < labels; i++) {
                const index = labels === 1 ? 0 : Math.round(i * (points.length - 1) / (labels - 1));
                const x = pad.left + (points.length === 1 ? chartW / 2 : chartW * index / (points.length - 1));
                const parts = String(points[index].date).slice(0, 10).split('-');
                ctx.fillText(parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : points[index].date, x, height - 20);
            }
        })
        .catch(function () {
            canvas.classList.add('d-none');
            document.getElementById('price-history-chart-empty')?.classList.remove('d-none');
        });
});
