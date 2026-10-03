import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import { tooltips } from './ui';

export const language = {
    processing: 'Memuat data…',
    search: '',
    searchPlaceholder: 'Cari…',
    lengthMenu: '_MENU_ per halaman',
    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data',
    infoFiltered: '(disaring dari _MAX_ data)',
    zeroRecords: '<div class="empty-state"><div class="empty-icon"><i class="bi bi-inbox"></i></div><h6>Tidak ada data</h6><p>Coba ubah filter atau kata kunci pencarian.</p></div>',
    emptyTable: '<div class="empty-state"><div class="empty-icon"><i class="bi bi-inbox"></i></div><h6>Belum ada data</h6></div>',
    paginate: { first: '«', last: '»', next: '›', previous: '‹' },
    loadingRecords: 'Memuat…',
};

/**
 * DataTable server-side dengan gaya & bahasa seragam.
 * `filters` = fungsi yang mengembalikan parameter tambahan untuk setiap request.
 */
export function serverTable(selector, { url, columns, order = [[0, 'desc']], filters = () => ({}), ...options }) {
    return new DataTable(selector, {
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order,
        language,
        ajax: {
            url,
            data: (d) => Object.assign(d, filters()),
        },
        columns,
        layout: {
            topStart: 'search',
            topEnd: 'pageLength',
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        drawCallback() {
            tooltips(this.api().table().container());
        },
        ...options,
    });
}

/** Tabel lokal (data sudah di HTML / array). */
export function localTable(selector, options = {}) {
    return new DataTable(selector, {
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        language,
        layout: { topStart: 'search', topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        ...options,
    });
}

export function reload(table, resetPaging = false) {
    table.ajax.reload(null, resetPaging);
}

export { $ };
