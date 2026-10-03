import $ from 'jquery';
import { modalCrud } from '../core/crud';

export default function () {
    const $late = $('[name=late_enabled]');
    const toggleLate = () => $('#lateFields').toggle($late.is(':checked'));

    modalCrud({
        modal: '#modalShift',
        form: '#formShift',
        storeUrl: '/shifts',
        baseUrl: '/shifts',
        titleCreate: 'Tambah Shift',
        titleEdit: 'Ubah Shift',
        defaults: { start_time: '08:00', end_time: '17:00', late_tolerance_min: 0, is_active: true },
        onOpen: toggleLate,
    });

    $late.on('change', toggleLate);
}
