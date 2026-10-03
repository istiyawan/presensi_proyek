import $ from 'jquery';
import { serverTable, reload } from '../core/table';
import { avatar, escapeHtml } from '../core/ui';
import { fillForm, resetForm } from '../core/shell';

export default function () {
    const $form = $('#formUser');
    const modal = window.bootstrap.Modal.getOrCreateInstance('#modalUser');

    const table = serverTable('#tableUsers', {
        url: '/users/data',
        order: [[0, 'asc']],
        columns: [
            {
                data: 'name',
                render: (v, _t, r) => `<div class="person">${avatar(v)}<div class="min-w-0"><div class="person-name">${escapeHtml(v)}${r.is_me ? ' <span class="chip soft-brand">Anda</span>' : ''}</div><div class="person-sub">@${escapeHtml(r.username)}${r.email ? ' · ' + escapeHtml(r.email) : ''}</div></div></div>`,
            },
            {
                data: 'role',
                orderable: false,
                render: (v) => (v === 'super_admin' ? '<span class="chip soft-violet"><i class="bi bi-shield-lock"></i>Super admin</span>' : '<span class="chip soft-brand"><i class="bi bi-building"></i>Admin proyek</span>'),
            },
            {
                data: 'projects',
                orderable: false,
                render: (v, _t, r) => (r.role === 'super_admin' ? '<span class="text-muted fs-8">Semua proyek</span>' : (v || []).map((c) => `<span class="chip">${escapeHtml(c)}</span>`).join(' ')),
            },
            { data: 'last_login', orderable: false, render: (v) => v || '<span class="text-muted fs-8">Belum pernah</span>' },
            { data: 'is_active', render: (v) => (v ? '<span class="badge-status soft-success">Aktif</span>' : '<span class="badge-status soft-slate">Nonaktif</span>') },
            { data: 'id', orderable: false, className: 'text-end', render: (id) => `<button class="btn btn-soft btn-sm" data-user="${id}"><i class="bi bi-pencil me-1"></i>Ubah</button>` },
        ],
    });

    const syncRole = () => $('#projectField').toggle($form.find('[name=role]:checked').val() === 'project_admin');
    $form.on('change', '[name=role]', syncRole);

    $('#btnAdd').on('click', () => {
        resetForm($form);
        $form.attr('action', '/users').attr('method', 'POST');
        $form.find('.modal-title').text('Tambah Admin');
        $form.find('.edit-only').hide();
        fillForm($form, { role: 'project_admin', is_active: true });
        syncRole();
        modal.show();
    });

    $(document).on('click', '[data-user]', function () {
        const id = $(this).data('user');
        $.get(`/users/${id}`).done((u) => {
            resetForm($form);
            $form.attr('action', `/users/${id}`).attr('method', 'PUT');
            $form.find('.modal-title').text('Ubah Admin');
            $form.find('.edit-only').show();
            fillForm($form, { ...u, password: '' });
            $form.find('[name="project_ids[]"]').val(u.project_ids.map(String)).trigger('change');
            syncRole();
            modal.show();
        });
    });

    $form.on('ajax:saved', () => reload(table));
}
