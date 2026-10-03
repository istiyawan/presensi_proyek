import { modalCrud } from '../core/crud';
import { localTable } from '../core/table';

export default function () {
    localTable('#tablePositions', { order: [[0, 'asc']], columnDefs: [{ targets: 'no-sort', orderable: false }] });

    modalCrud({
        modal: '#modalPosition',
        form: '#formPosition',
        storeUrl: '/positions',
        baseUrl: '/positions',
        titleCreate: 'Tambah Jabatan',
        titleEdit: 'Ubah Jabatan',
    });
}
