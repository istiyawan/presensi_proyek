import $ from 'jquery';
import { modalCrud } from '../core/crud';
import { confirmAction, escapeHtml, toast, tooltips } from '../core/ui';

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const DOW = ['S', 'S', 'R', 'K', 'J', 'S', 'M'];

export default function () {
    const { year, holidays, today } = window.HolidayData;
    renderYear(year, holidays, today);

    modalCrud({
        modal: '#modalHoliday',
        form: '#formHoliday',
        storeUrl: '/holidays',
        baseUrl: '/holidays',
        titleCreate: 'Tambah Hari Libur',
        titleEdit: 'Ubah Hari Libur',
        defaults: { type: 'project' },
    });

    $('#btnImport').on('click', async function () {
        const y = $(this).data('year');
        const ok = await confirmAction({
            title: `Impor libur nasional ${y}?`,
            text: 'Data libur nasional &amp; cuti bersama akan ditambahkan untuk semua proyek. Data yang sama tidak akan terduplikasi.<br><br><strong>Periksa kembali dengan SKB 3 Menteri resmi.</strong>',
            confirmText: 'Impor',
        });
        if (!ok) return;
        $.post('/holidays/import', { year: y }).done((res) => {
            toast(res.message);
            setTimeout(() => window.location.reload(), 800);
        });
    });
}

function renderYear(year, holidays, today) {
    const byDate = Object.fromEntries(holidays.map((h) => [h.date, h]));
    const pad = (n) => String(n).padStart(2, '0');
    let html = '';

    for (let m = 0; m < 12; m++) {
        const first = new Date(year, m, 1);
        const offset = (first.getDay() + 6) % 7; // Senin = 0
        const days = new Date(year, m + 1, 0).getDate();
        let cells = DOW.map((d) => `<div class="dow">${d}</div>`).join('');
        cells += '<div></div>'.repeat(offset);

        for (let d = 1; d <= days; d++) {
            const key = `${year}-${pad(m + 1)}-${pad(d)}`;
            const h = byDate[key];
            const sunday = (offset + d - 1) % 7 === 6;
            const cls = ['d', h ? h.type : '', sunday ? 'sun' : '', key === today ? 'today' : ''].join(' ');
            const tip = h ? ` data-bs-toggle="tooltip" title="${escapeHtml(h.name)}"` : '';
            cells += `<div class="${cls}"${tip}>${d}</div>`;
        }

        html += `<div class="col-6 col-md-4"><div class="mini-cal"><div class="mini-cal-title">${MONTHS[m]}</div><div class="mini-cal-grid">${cells}</div></div></div>`;
    }

    const el = document.getElementById('yearCalendar');
    el.innerHTML = html;
    tooltips(el);
}
