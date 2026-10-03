import $ from 'jquery';
import { modalCrud } from '../core/crud';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default function () {
    // Simpan tab aktif di URL agar refresh tetap di tab yang sama
    $('[data-tab]').on('shown.bs.tab', function () {
        const url = new URL(window.location);
        url.searchParams.set('tab', $(this).data('tab'));
        window.history.replaceState(null, '', url);
    });

    setupPeriodPreview();
    setupOffsiteToggle();

    modalCrud({
        modal: '#modalSignatory',
        form: '#formSignatory',
        storeUrl: '/signatories',
        baseUrl: '/signatories',
        titleCreate: 'Tambah Penandatangan',
        titleEdit: 'Ubah Penandatangan',
        defaults: { label: 'Dibuat', sort_order: $('#tab-signatories .col-md-6').length + 1, is_active: true },
        onSaved: () => {
            const url = new URL(window.location);
            url.searchParams.set('tab', 'signatories');
            window.location = url;
        },
    });

    $('#formSignatory [name=employee_id]').on('select2:select', (e) => {
        const opt = e.params.data.element;
        $('#formSignatory [name=name]').val(opt.dataset.name);
        $('#formSignatory [name=title]').val(opt.dataset.title);
    });
}

function setupOffsiteToggle() {
    const $allow = $('#formLocationRules [name=allow_offsite]');
    const sync = () => $('.offsite-options').toggleClass('opacity-50', !$allow.is(':checked'))
        .find('input, select').prop('disabled', !$allow.is(':checked'));
    $allow.on('change', sync);
    sync();
    // Field disabled tidak ikut terkirim → aktifkan sesaat sebelum submit
    $('#formLocationRules').on('submit', function () {
        $(this).find('.offsite-options :disabled').prop('disabled', false);
        setTimeout(sync, 0);
    });
}

/** Pratinjau 3 periode pertama — logika sama dengan PeriodService di server. */
function setupPeriodPreview() {
    const $form = $('#formPeriod');
    const $preview = $('#periodPreview');
    const contract = new Date(`${$preview.data('contract')}T00:00:00`);

    const dayIn = (y, m, d) => new Date(y, m, Math.min(d, new Date(y, m + 1, 0).getDate()));
    const fmt = (d) => `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;

    const render = () => {
        const startDay = Number($form.find('[name=period_start_day]').val()) || 1;
        const endDay = Number($form.find('[name=period_end_day]').val()) || 1;
        const nextMonth = $form.find('[name=period_end_next_month]').val() === '1';

        let first = dayIn(contract.getFullYear(), contract.getMonth(), startDay);
        if (first < contract) first = dayIn(contract.getFullYear(), contract.getMonth() + 1, startDay);

        const periods = [0, 1, 2].map((i) => {
            const s = dayIn(first.getFullYear(), first.getMonth() + i, startDay);
            const e = dayIn(s.getFullYear(), s.getMonth() + (nextMonth ? 1 : 0), endDay);
            return { s, e, days: Math.round((e - s) / 86400000) + 1 };
        });

        $preview.html(periods.map((p, i) => `
            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100" style="border-color: var(--line) !important">
                    <div class="fs-8 fw-bold text-brand text-uppercase">Bulan ke-${i + 1}</div>
                    <div class="fw-semibold text-ink mt-1">${fmt(p.s)}</div>
                    <div class="fs-7 text-muted">s/d ${fmt(p.e)} · ${p.days} hari</div>
                </div>
            </div>`).join(''));

        const overlap = periods[1].s <= periods[0].e;
        $('#periodOverlap').html(overlap
            ? '<i class="bi bi-info-circle text-warning"></i> Periode berurutan saling tumpang tindih di beberapa tanggal (sesuai format laporan acuan). Data presensi tidak tergandakan.'
            : '');
    };

    $form.on('input change', 'input, select', render);
    render();
}
