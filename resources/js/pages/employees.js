import $ from 'jquery';
import Swal from 'sweetalert2';
import { serverTable, reload } from '../core/table';
import { avatar, confirmAction, escapeHtml, toast } from '../core/ui';
import { fillForm, resetForm } from '../core/shell';

const routes = {
    data: '/employees/data',
    show: (id) => `/employees/${id}`,
    update: (id) => `/employees/${id}`,
    resetPassword: (id) => `/employees/${id}/reset-password`,
    resetDevice: (id) => `/employees/${id}/reset-device`,
    detach: (id) => `/employees/${id}/assignment`,
};

export default function () {
    let status = 'active';
    const $modal = $('#modalEmployee');
    const $form = $('#formEmployee');
    const modal = window.bootstrap.Modal.getOrCreateInstance($modal[0]);

    const table = serverTable('#tableEmployees', {
        url: routes.data,
        filters: () => ({ status }),
        order: [[0, 'asc']],
        columns: [
            {
                data: 'name',
                render: (v, _t, r) => `
                    <a href="#" class="person text-reset" data-edit="${r.employee_id}">
                        ${avatar(r.full_name)}
                        <span class="min-w-0">
                            <span class="person-name d-block">${escapeHtml(v)}</span>
                            <span class="person-sub d-block">@${escapeHtml(r.username || '—')}${r.nik ? ' · ' + escapeHtml(r.nik) : ''}</span>
                        </span>
                    </a>`,
            },
            { data: 'position', render: (v) => (v ? escapeHtml(v) : '<span class="text-muted">—</span>') },
            { data: 'shift', orderable: false, render: (v) => (v ? `<span class="chip">${escapeHtml(v)}</span>` : '<span class="text-muted">Default</span>') },
            {
                data: 'start',
                render: (v, _t, r) => `<span class="fs-7">${v || '—'}</span><div class="fs-8 text-muted">${r.end ? 's/d ' + r.end : 'masih bertugas'}</div>`,
            },
            {
                data: 'is_team_leader',
                orderable: false,
                render: (_v, _t, r) => {
                    const chips = [];
                    if (r.is_team_leader) chips.push('<span class="chip soft-warning"><i class="bi bi-star-fill"></i>Team Leader</span>');
                    if (r.allow_offsite === true) chips.push('<span class="chip soft-violet"><i class="bi bi-geo"></i>Luar lokasi</span>');
                    if (r.allow_offsite === false) chips.push('<span class="chip soft-slate">Hanya dalam lokasi</span>');
                    return chips.join(' ') || '<span class="text-muted fs-8">Standar</span>';
                },
            },
            {
                data: 'device',
                orderable: false,
                render: (v) => (v ? `<span class="fs-7"><i class="bi bi-phone text-success me-1"></i>${escapeHtml(v)}</span>` : '<span class="text-muted fs-8">Belum login</span>'),
            },
            {
                data: 'is_active',
                orderable: false,
                render: (v) => (v ? '<span class="badge-status soft-success">Aktif</span>' : '<span class="badge-status soft-slate">Nonaktif</span>'),
            },
            {
                data: 'employee_id',
                orderable: false,
                className: 'text-end',
                render: (id, _t, r) => `
                    <div class="dropdown">
                        <button class="btn btn-soft btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Aksi"><i class="bi bi-three-dots"></i></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <button class="dropdown-item" data-edit="${id}"><i class="bi bi-pencil"></i>Ubah data</button>
                            <button class="dropdown-item" data-reset-password="${id}" data-name="${escapeHtml(r.name)}"><i class="bi bi-key"></i>Reset password</button>
                            <button class="dropdown-item" data-reset-device="${id}" data-name="${escapeHtml(r.name)}" ${r.device ? '' : 'disabled'}><i class="bi bi-phone-flip"></i>Reset perangkat</button>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item text-danger" data-detach="${id}" data-name="${escapeHtml(r.name)}"><i class="bi bi-person-dash"></i>Keluarkan dari proyek</button>
                        </div>
                    </div>`,
            },
        ],
    });

    $('#statusFilter').on('click', '[data-status]', function (e) {
        e.preventDefault();
        $('#statusFilter .nav-link').removeClass('active');
        $(this).addClass('active');
        status = $(this).data('status');
        reload(table, true);
    });

    // Jabatan: pilih atau ketik baru
    $form.find('.select2-tags').select2({
        theme: 'bootstrap-5',
        width: '100%',
        tags: true,
        dropdownParent: $modal,
        placeholder: 'Pilih atau ketik jabatan baru',
        allowClear: true,
    });

    $('#btnAdd').on('click', () => {
        resetForm($form);
        setMode('create');
        $form.find('[name=start_date]')[0]._flatpickr?.setDate(new Date());
        modal.show();
    });

    $(document).on('click', '[data-edit]', function (e) {
        e.preventDefault();
        const id = $(this).data('edit');
        $.get(routes.show(id)).done((data) => {
            resetForm($form);
            setMode('edit', id);
            fillForm($form, { ...data, password: '' });
            modal.show();
        });
    });

    $('#btnGenPassword').on('click', () => {
        const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        const pwd = Array.from(crypto.getRandomValues(new Uint32Array(10)), (n) => chars[n % chars.length]).join('');
        $form.find('[name=password]').val(pwd);
    });

    // Username otomatis dari nama saat menambah
    $form.find('[name=full_name]').on('input', function () {
        if ($form.data('mode') !== 'create' || $form.data('username-touched')) return;
        const parts = this.value.toLowerCase().replace(/[^a-z\s]/g, '').trim().split(/\s+/).filter(Boolean);
        $form.find('[name=username]').val(parts.length > 1 ? `${parts[0]}.${parts[parts.length - 1]}` : parts[0] || '');
    });
    $form.find('[name=username]').on('input', () => $form.data('username-touched', true));

    $form.on('ajax:saved', () => reload(table));

    // Tambah dari proyek lain
    $('#selectExisting').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#modalAttach'),
        placeholder: 'Ketik nama karyawan…',
        ajax: { url: '/employees/search', delay: 250, data: (p) => ({ q: p.term }) },
        language: { noResults: () => 'Tidak ada karyawan lain', searching: () => 'Mencari…', inputTooShort: () => 'Ketik nama' },
    });
    $('#formAttach').on('ajax:saved', function () {
        $('#selectExisting').val(null).trigger('change');
        reload(table);
    });

    $(document).on('click', '[data-reset-password]', async function () {
        const id = $(this).data('reset-password');
        const ok = await confirmAction({
            title: 'Reset password?',
            text: `Password baru akan dibuat untuk <strong>${$(this).data('name')}</strong> dan sesi di HP akan keluar.`,
            confirmText: 'Reset password',
        });
        if (!ok) return;
        $.post(routes.resetPassword(id)).done((res) => {
            Swal.fire({
                icon: 'success',
                title: 'Password baru',
                html: `Sampaikan ke karyawan:<div class="mt-3 p-3 rounded-3 bg-light text-start fs-7">
                        Username: <strong>${escapeHtml(res.data.username)}</strong><br>
                        Password: <strong class="font-monospace fs-6">${escapeHtml(res.data.password)}</strong></div>`,
                confirmButtonText: 'Selesai',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false,
            });
        });
    });

    $(document).on('click', '[data-reset-device]', async function () {
        const ok = await confirmAction({
            title: 'Reset perangkat?',
            text: `<strong>${$(this).data('name')}</strong> akan keluar dari HP lama dan bisa login di HP baru.`,
            confirmText: 'Reset perangkat',
        });
        if (!ok) return;
        $.post(routes.resetDevice($(this).data('reset-device'))).done((res) => {
            toast(res.message);
            reload(table);
        });
    });

    $(document).on('click', '[data-detach]', async function () {
        const ok = await confirmAction({
            title: 'Keluarkan dari proyek?',
            text: `<strong>${$(this).data('name')}</strong> tidak bisa presensi di proyek ini lagi. Riwayat presensi tetap tersimpan.`,
            confirmText: 'Keluarkan',
            danger: true,
        });
        if (!ok) return;
        $.ajax({ url: routes.detach($(this).data('detach')), method: 'DELETE' }).done((res) => {
            toast(res.message);
            reload(table);
        });
    });

    function setMode(mode, id = null) {
        $form.data('mode', mode).data('username-touched', mode === 'edit');
        const $title = $form.find('.modal-title');
        $title.text($title.data(mode === 'edit' ? 'title-edit' : 'title-create'));
        $form.attr('action', mode === 'edit' ? routes.update(id) : '/employees');
        $form.attr('method', mode === 'edit' ? 'PUT' : 'POST');
        $form.find('.edit-only').toggle(mode === 'edit');
        $form.find('.create-only').toggle(mode === 'create');
    }
}
