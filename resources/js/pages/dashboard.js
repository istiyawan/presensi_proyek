import $ from 'jquery';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';
import { createMap, dotIcon, projectCircle, fitTo, COLORS, L } from '../core/map';
import { escapeHtml } from '../core/ui';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// Palet tervalidasi (dataviz validator: lolos CVD & normal-vision untuk urutan tumpukan ini).
// Terlambat (amber) < 3:1 kontras → dikompensasi legenda, tooltip, dan tampilan tabel.
const SERIES = [
    { key: 'hadir', label: 'Hadir', color: '#0ca30c' },
    { key: 'leave', label: 'Izin / Sakit / Cuti', color: '#2a78d6' },
    { key: 'terlambat', label: 'Terlambat', color: '#eda100' },
    { key: 'alpha', label: 'Alpha', color: '#d03b3b' },
];

export default function () {
    const data = window.DashboardData;

    $('#formDate input').on('change', function () {
        if (this.value) $('#formDate').trigger('submit');
    });

    renderTrend(data.trend);
    renderMap(data.points, data.locations);

    $('[data-view]').on('click', function () {
        const view = $(this).data('view');
        $('[data-view]').removeClass('active');
        $(this).addClass('active');
        $('#trendChartWrap').prop('hidden', view !== 'chart');
        $('#trendTableWrap').prop('hidden', view !== 'table');
    });
}

function renderTrend(trend) {
    $('#trendLegend').html(SERIES.map((s) => `<span><span class="swatch" style="background:${s.color}"></span>${s.label}</span>`).join(''));

    const lastIndex = SERIES.length - 1;
    const datasets = SERIES.map((s, i) => ({
        label: s.label,
        data: trend.map((t) => t[s.key]),
        backgroundColor: s.color,
        borderColor: '#ffffff',
        borderWidth: { top: 2, bottom: 0, left: 0, right: 0 },
        // Sudut membulat hanya di ujung atas tumpukan
        borderRadius: (ctx) => {
            const idx = ctx.dataIndex;
            const topmost = SERIES.map((_, j) => j).filter((j) => trend[idx][SERIES[j].key] > 0).pop();
            return topmost === i ? { topLeft: 4, topRight: 4 } : 0;
        },
        borderSkipped: false,
        maxBarThickness: 28,
        stack: 'status',
        order: lastIndex - i,
    }));

    const tooltipEl = document.getElementById('trendTooltip');
    const chartBox = document.getElementById('trendChartWrap');

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: { labels: trend.map((t) => t.label), datasets },
        options: {
            maintainAspectRatio: false,
            animation: { duration: 400 },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    border: { color: '#cbd5e1' },
                    ticks: { color: '#64748b', font: { size: 11, family: 'inherit' }, maxRotation: 0, autoSkipPadding: 12 },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grid: { color: '#eef1f5' },
                    border: { display: false },
                    ticks: { color: '#94a3b8', font: { size: 11 }, precision: 0 },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: false,
                    external: ({ chart, tooltip }) => {
                        if (tooltip.opacity === 0) {
                            tooltipEl.style.opacity = 0;
                            return;
                        }
                        const i = tooltip.dataPoints[0].dataIndex;
                        const t = trend[i];
                        const total = SERIES.reduce((sum, s) => sum + t[s.key], 0);
                        tooltipEl.innerHTML = `
                            <div class="tt-title">${escapeHtml(t.weekday)}, ${escapeHtml(t.label)}</div>
                            ${SERIES.map((s) => `<div class="tt-row"><span class="tt-swatch" style="background:${s.color}"></span>${s.label}<strong>${t[s.key]}</strong></div>`).join('')}
                            <div class="tt-row border-top border-secondary mt-1 pt-1">Total tercatat<strong>${total}</strong></div>`;
                        const x = tooltip.caretX;
                        const left = Math.min(Math.max(x - tooltipEl.offsetWidth / 2, 0), chartBox.clientWidth - tooltipEl.offsetWidth);
                        tooltipEl.style.left = `${left}px`;
                        tooltipEl.style.top = `${Math.max(tooltip.caretY - tooltipEl.offsetHeight - 12, 0)}px`;
                        tooltipEl.style.opacity = 1;
                        chart.canvas.style.cursor = 'default';
                    },
                },
            },
        },
    });
}

function renderMap(points, locations) {
    const el = document.getElementById('dashboardMap');
    if (!el) return;

    const map = createMap(el);
    const layers = [];

    locations.forEach((loc) => {
        const circle = projectCircle(loc).addTo(map).bindPopup(`<strong>${escapeHtml(loc.name)}</strong><br>Radius ${loc.radius_m} m`);
        layers.push(circle);
    });

    points.forEach((p) => {
        const marker = L.marker([p.lat, p.lng], { icon: dotIcon(p.offsite ? COLORS.offsite : COLORS.onsite) })
            .addTo(map)
            .bindPopup(`<strong>${escapeHtml(p.name)}</strong><br>Check-in ${p.time}${p.offsite ? ' · <span class="text-warning fw-semibold">Luar lokasi</span>' : ''}`);
        layers.push(marker);
    });

    fitTo(map, layers);
    setTimeout(() => map.invalidateSize(), 200);
}
