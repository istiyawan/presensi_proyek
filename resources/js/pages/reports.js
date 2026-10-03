import $ from 'jquery';
import flatpickr from 'flatpickr';
import { submitForm } from '../core/http';
import { confirmAction, escapeHtml, toast } from '../core/ui';

const STATUS = {
    queued: '<span class="badge-status soft-slate">Antre</span>',
    processing: '<span class="badge-status soft-info"><span class="spinner-border spinner-border-sm" style="width:.6rem;height:.6rem"></span>Diproses</span>',
    done: '<span class="badge-status soft-success">Siap</span>',
    failed: '<span class="badge-status soft-danger">Gagal</span>',
};

export default function () {
    const $form = $('#formReport');
    let pollTimer;

    // Jenis laporan → tampilkan opsi yang relevan
    const syncType = () => {
        const type = $form.find('[name=type]:checked').val();
        $form.find('[data-for-type]').each(function () {
            this.hidden = !this.dataset.forType.split(' ').includes(type);
        });
        if (type !== 'recap') $('#fmt-pdf').prop('checked', true);
    };
    $form.on('change', '[name=type]', syncType);
    syncType();

    // Periode vs rentang bebas
    $form.on('click', '[data-range]', function () {
        const mode = $(this).data('range');
        $form.find('[data-range]').removeClass('active');
        $(this).addClass('active');
        $form.find('[name=range_mode]').val(mode);
        $form.find('[data-range-pane]').each(function () {
            this.hidden = this.dataset.rangePane !== mode;
        });
    });

    const range = flatpickr('#reportRange', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'j M Y',
        conjunction: ' – ',
        onChange: (dates) => {
            $form.find('[name=from]').val(dates[0] ? range.formatDate(dates[0], 'Y-m-d') : '');
            $form.find('[name=to]').val(dates[1] ? range.formatDate(dates[1], 'Y-m-d') : '');
        },
    });

    $form.on('submit', (e) => {
        e.preventDefault();
        const data = new FormData($form[0]);
        data.set('photos', $form.find('[name=photos]').is(':checked') ? '1' : '0');

        submitForm($form, { data }).done((res) => {
            loadHistory();
            if (res.data?.queued) {
                toast(res.message, 'info');
                return;
            }
            toast(res.message);
            if (res.data?.view_url) window.open(res.data.view_url, '_blank');
            else if (res.data?.download_url) window.location = res.data.download_url;
        });
    });

    $('#btnRefresh').on('click', loadHistory);

    $(document).on('click', '[data-delete-report]', async function () {
        const ok = await confirmAction({ title: 'Hapus laporan?', text: 'Berkas laporan akan dihapus.', confirmText: 'Hapus', danger: true });
        if (!ok) return;
        $.ajax({ url: `/reports/${$(this).data('delete-report')}`, method: 'DELETE' }).done(() => loadHistory());
    });

    loadHistory();

    function loadHistory() {
        $.get('/reports/history').done((items) => {
            renderHistory(items);
            clearTimeout(pollTimer);
            if (items.some((i) => ['queued', 'processing'].includes(i.status))) {
                pollTimer = setTimeout(loadHistory, 4000);
            }
        });
    }

    function renderHistory(items) {
        if (!items.length) {
            $('#historyList').html(`<div class="empty-state"><div class="empty-icon"><i class="bi bi-file-earmark-text"></i></div>
                <h6>Belum ada laporan</h6><p>Laporan yang dibuat akan muncul di sini.</p></div>`);
            return;
        }

        $('#historyList').html(items.map((i) => {
            const icon = i.format === 'xlsx'
                ? '<span class="file-icon soft-success"><i class="bi bi-filetype-xlsx"></i></span>'
                : '<span class="file-icon soft-danger"><i class="bi bi-filetype-pdf"></i></span>';
            const meta = [i.type, i.by, i.ago, i.size, i.duration != null ? `${i.duration} dtk` : null].filter(Boolean).map(escapeHtml).join(' · ');
            const actions = i.status === 'done'
                ? `${i.view_url ? `<a class="btn btn-sm btn-soft" href="${i.view_url}" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>Lihat</a>` : ''}
                   <a class="btn btn-sm btn-brand-soft" href="${i.download_url}"><i class="bi bi-download me-1"></i>Unduh</a>`
                : '';
            const queuedHint = i.status === 'queued'
                ? '<div class="fs-8 text-muted mt-1">Menunggu antrean — diproses oleh cron/scheduler server tiap menit.</div>'
                : '';

            return `<div class="setting-row align-items-center">
                <div class="d-flex gap-3 align-items-center min-w-0">
                    ${icon}
                    <div class="min-w-0">
                        <div class="setting-title fs-7 text-truncate" title="${escapeHtml(i.title)}">${escapeHtml(i.title)}</div>
                        <div class="setting-desc">${meta}</div>
                        ${i.status === 'failed' ? `<div class="fs-8 text-danger mt-1">${escapeHtml(i.error || '')}</div>` : ''}
                        ${queuedHint}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    ${STATUS[i.status]}
                    ${actions}
                    <button class="btn btn-sm btn-soft btn-icon text-danger" data-delete-report="${i.id}" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                </div>
            </div>`;
        }).join(''));
    }
}
