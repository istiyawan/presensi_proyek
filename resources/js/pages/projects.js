import { modalCrud } from '../core/crud';

export default function () {
    modalCrud({
        modal: '#modalProject',
        form: '#formProject',
        storeUrl: '/projects',
        baseUrl: '/projects',
        titleCreate: 'Proyek Baru',
        titleEdit: 'Ubah Proyek',
        defaults: { timezone: 'Asia/Jakarta', is_active: true },
        onSaved: (res) => {
            window.location = res?.data?.redirect || window.location.href;
        },
    });
}
