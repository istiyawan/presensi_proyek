import $ from 'jquery';
import { serverTable, reload } from '../core/table';
import { avatar, escapeHtml, promptNote, statusBadge, toast } from '../core/ui';

const DECISION = {
    pending: '<span class="badge-status soft-warning">Menunggu</span>',
    approved: '<span class="badge-status soft-success">Disetujui</span>',
    rejected: '<span class="badge-status soft-danger">Ditolak</span>',
    none: '<span class="badge-status soft-slate">Ditandai</span>',
};

export default function () {
    const status = () => $('#statusSelect').val();

    const person = (r) => `<div class="person">${avatar(r.full_name, 'avatar-sm')}<div class="min-w-0"><div class="person-name">${escapeHtml(r.name)}</div><div class="person-sub">${escapeHtml(r.position || '')}</div></div></div>`;

    const actions = (kind, r, pending) => (pending
        ? `<div class="d-inline-flex gap-1">
              <button class="btn btn-sm btn-brand-soft" data-decide="${kind}" data-id="${r.id}" data-action="approve" data-name="${escapeHtml(r.name)}"><i class="bi bi-check2 me-1"></i>Setujui</button>
              <button class="btn btn-sm btn-soft text-danger" data-decide="${kind}" data-id="${r.id}" data-action="reject" data-name="${escapeHtml(r.name)}"><i class="bi bi-x-lg"></i></button>
           </div>`
        : '');

    const leaves = serverTable('#tableLeaves', {
        url: '/approvals/leaves',
        filters: () => ({ status: status() }),
        order: [[2, 'desc']],
        columns: [
            { data: 'name', render: (_v, _t, r) => person(r) },
            { data: 'type', render: (v) => statusBadge(v) },
            { data: 'start_date', render: (_v, _t, r) => `<span class="fw-semibold text-ink">${r.range}</span><div class="fs-8 text-muted">${r.days} hari · diajukan ${r.submitted}</div>` },
            {
                data: 'reason',
                orderable: false,
                render: (v, _t, r) => `<div class="fs-7" style="max-width:320px">${escapeHtml(v)}</div>${r.attachment_url ? `<a href="${r.attachment_url}" target="_blank" class="fs-8 fw-semibold"><i class="bi bi-paperclip"></i> Lampiran</a>` : ''}`,
            },
            {
                data: 'status',
                render: (v, _t, r) => `${DECISION[v]}${r.approver_name ? `<div class="fs-8 text-muted mt-1">oleh ${escapeHtml(r.approver_name)}</div>` : ''}${r.approval_note ? `<div class="fs-8 text-muted">“${escapeHtml(r.approval_note)}”</div>` : ''}`,
            },
            { data: 'id', orderable: false, className: 'text-end', render: (_v, _t, r) => actions('leaves', r, r.status === 'pending') },
        ],
    });

    const offsite = serverTable('#tableOffsite', {
        url: '/approvals/offsite',
        filters: () => ({ status: status() === 'all' ? 'all' : status() === 'pending' ? 'pending' : status() }),
        order: [[0, 'desc']],
        columns: [
            { data: 'work_date', render: (_v, _t, r) => `<span class="fw-semibold text-ink text-nowrap">${r.date_label}</span>` },
            { data: 'name', render: (_v, _t, r) => person(r) },
            { data: 'in_time', render: (v, _t, r) => sideCell(v, r.check_in_mode, r.check_in_distance_m) },
            { data: 'out_time', render: (v, _t, r) => sideCell(v, r.check_out_mode, r.check_out_distance_m) },
            { data: 'check_in_note', orderable: false, render: (v, _t, r) => `<div class="fs-7" style="max-width:280px">${escapeHtml(v || r.check_out_note || '–')}</div>` },
            { data: 'offsite_approval', render: (v) => DECISION[v] || v },
            { data: 'id', orderable: false, className: 'text-end', render: (_v, _t, r) => actions('offsite', r, r.offsite_approval === 'pending') },
        ],
    });

    // Tabel di tab tersembunyi perlu menyesuaikan lebar saat ditampilkan
    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', () => {
        leaves.columns.adjust().responsive.recalc();
        offsite.columns.adjust().responsive.recalc();
    });

    $('#statusSelect').on('change', () => {
        reload(leaves, true);
        reload(offsite, true);
    });

    $(document).on('click', '[data-decide]', async function () {
        const { decide, id, action, name } = $(this).data();
        const approve = action === 'approve';
        const note = await promptNote({
            title: approve ? 'Setujui pengajuan?' : 'Tolak pengajuan?',
            text: `<strong>${name}</strong>`,
            confirmText: approve ? 'Setujui' : 'Tolak',
            danger: !approve,
            required: !approve,
            placeholder: approve ? 'Catatan (opsional)' : 'Alasan penolakan',
        });
        if (note === null) return;

        $.post(`/approvals/${decide}/${id}`, { action, note }).done((res) => {
            toast(res.message);
            reload(decide === 'leaves' ? leaves : offsite);
        });
    });
}

function sideCell(time, mode, distance) {
    if (!time) return '<span class="text-muted">–</span>';
    return `<span class="fw-bold text-ink tabular">${time}</span>
        ${mode === 'offsite' ? `<div class="fs-8 text-warning">Luar lokasi${distance ? ` · ${(distance / 1000).toFixed(1)} km` : ''}</div>` : '<div class="fs-8 text-success">Dalam lokasi</div>'}`;
}
