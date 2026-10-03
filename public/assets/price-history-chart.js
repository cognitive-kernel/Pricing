document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('price-history-chart');
    const barCanvas = document.getElementById('price-history-bar-chart');
    if (!canvas || !barCanvas) return;

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
                barCanvas.classList.add('d-none');
                empty?.classList.remove('d-none');
                return;
            }

            const ctx = canvas.getContext('2d');
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            const width = Math.max(700, Math.floor(rect.width || 900));
            const height = 390;
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            canvas.style.height = height + 'px';
            ctx.scale(dpr, dpr);

            const pad = { left: 70, right: 25, top: 25, bottom: 80 };
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

            drawBarChart(barCanvas, points, yMin, yMax);

            ctx.fillStyle = '#6b7280';
            ctx.textAlign = 'center';
            const labels = Math.min(6, points.length);
            for (let i = 0; i < labels; i++) {
                const index = labels === 1 ? 0 : Math.round(i * (points.length - 1) / (labels - 1));
                const x = pad.left + (points.length === 1 ? chartW / 2 : chartW * index / (points.length - 1));
                ctx.fillText(String(points[index].date), x, height - 30);
            }
        })
        .catch(function () {
            canvas.classList.add('d-none');
            document.getElementById('price-history-chart-empty')?.classList.remove('d-none');
        });
});

function drawBarChart(canvas, points, yMin, yMax) {
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const width = Math.max(420, Math.floor(rect.width || 600));
    const height = 360;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    canvas.style.height = height + 'px';

    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const pad = { left: 70, right: 25, top: 25, bottom: 55 };
    const chartW = width - pad.left - pad.right;
    const chartH = height - pad.top - pad.bottom;

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

    const maxBars = 20;
    const step = Math.max(1, Math.ceil(points.length / maxBars));
    const visible = points.filter(function (_, i) { return i % step === 0; });
    const gap = 6;
    const barWidth = Math.max(4, (chartW - gap * Math.max(0, visible.length - 1)) / Math.max(1, visible.length));

    visible.forEach(function (point, i) {
        const value = Number(point.price) || 0;
        const x = pad.left + i * (barWidth + gap);
        const y = pad.top + chartH - ((value - yMin) / (yMax - yMin)) * chartH;
        ctx.fillStyle = '#6c757d';
        ctx.fillRect(x, y, barWidth, Math.max(1, pad.top + chartH - y));
    });

    ctx.fillStyle = '#6b7280';
    ctx.textAlign = 'center';
    const labels = Math.min(6, visible.length);
    for (let i = 0; i < labels; i++) {
        const index = labels === 1 ? 0 : Math.round(i * (visible.length - 1) / (labels - 1));
        const x = pad.left + index * (barWidth + gap) + barWidth / 2;
        ctx.fillText(String(visible[index].date), x, height - 30);
    }
}
