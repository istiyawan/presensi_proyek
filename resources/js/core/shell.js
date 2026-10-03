import $ from 'jquery';
import flatpickr from 'flatpickr';
import { avatar, toast, tooltips } from './ui';
import { submitForm } from './http';

export function setupShell() {
    // Form AJAX generik: <form data-ajax-form> → toast, tutup modal, event "ajax:saved"
    $(document).on('submit', 'form[data-ajax-form]', function (e) {
        e.preventDefault();
        const $form = $(this);
        submitForm($form).done((res) => {
            toast(res.message || 'Tersimpan.');
            const modal = $form.closest('.modal')[0] || $(`#${$form.attr('id')}`).closest('.modal')[0];
            if (modal && $form.data('keep-open') === undefined) {
                window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            }
            if ($form.attr('id') === 'formPassword') {
                $form[0].reset();
            }
            $form.trigger('ajax:saved', [res]);
        });
    });

    // Modal: inisialisasi input setelah tampil
    $(document).on('shown.bs.modal', '.modal', function () {
        initInputs(this);
    });

    // Sidebar mobile
    $(document).on('click', '[data-sidebar-toggle], .sidebar-backdrop', () => {
        document.body.classList.toggle('sidebar-open');
    });

    // Jam topbar (zona waktu proyek)
    const clock = document.querySelector('[data-clock]');
    if (clock) {
        const tz = clock.dataset.clock;
        const fmt = new Intl.DateTimeFormat('id-ID', { timeZone: tz, hour: '2-digit', minute: '2-digit' });
        const day = new Intl.DateTimeFormat('id-ID', { timeZone: tz, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const tick = () => {
            const now = new Date();
            clock.innerHTML = `${day.format(now)} · <strong>${fmt.format(now).replace('.', ':')}</strong> ${clock.dataset.tzLabel || ''}`;
        };
        tick();
        setInterval(tick, 15000);
    }

    initInputs(document);
    renderAvatars(document);
    tooltips();
}

/** <span class="avatar" data-avatar="Nama"> → inisial + warna konsisten per nama */
export function renderAvatars(root) {
    root.querySelectorAll('[data-avatar]').forEach((el) => {
        const tmp = document.createElement('div');
        tmp.innerHTML = avatar(el.dataset.avatar, el.className.replace('avatar', '').trim());
        el.replaceWith(tmp.firstChild);
    });
}

/** Inisialisasi input khusus di dalam root (dipanggil ulang setelah modal dibuka). */
export function initInputs(root) {
    $(root).find('select.select2:not(.select2-hidden-accessible)').each(function () {
        const $el = $(this);
        $el.select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $el.closest('.modal').length ? $el.closest('.modal') : $(document.body),
            placeholder: $el.data('placeholder') || 'Pilih…',
            allowClear: $el.data('allow-clear') ?? false,
            minimumResultsForSearch: $el.data('search') === false ? Infinity : 6,
            language: { noResults: () => 'Tidak ditemukan', searching: () => 'Mencari…' },
        });
    });

    // Input tampilan (altInput) buatan flatpickr ikut membawa class aslinya,
    // jadi ditandai data-fp-alt agar tidak diinisialisasi ulang.
    const pick = (selector, options) => root.querySelectorAll?.(`${selector}:not(.flatpickr-input):not([data-fp-alt])`).forEach((el) => {
        const fp = flatpickr(el, options);
        if (fp.altInput) fp.altInput.dataset.fpAlt = '1';
    });

    pick('input.datepicker', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'j M Y' });
    pick('input.timepicker', { enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true, allowInput: true });
    pick('input.daterange', { mode: 'range', dateFormat: 'Y-m-d', altInput: true, altFormat: 'j M Y', conjunction: ' – ' });
}

/** Isi form dari objek data (name → value). */
export function fillForm($form, data) {
    Object.entries(data).forEach(([key, value]) => {
        const $field = $form.find(`[name="${key}"]`);
        if (!$field.length) return;

        if ($field.is(':checkbox')) {
            $field.prop('checked', !!value);
        } else if ($field.is(':radio')) {
            $field.filter(`[value="${value}"]`).prop('checked', true);
        } else {
            $field.val(value ?? '');
            if ($field.hasClass('select2-hidden-accessible')) $field.trigger('change');
            if ($field[0]._flatpickr) $field[0]._flatpickr.setDate(value || null, false);
        }
    });
}

export function resetForm($form) {
    $form[0].reset();
    $form.find('input[type=hidden]:not([name=_token])').val('');
    $form.find('select.select2-hidden-accessible').val(null).trigger('change');
    $form.find('input.flatpickr-input').each(function () {
        this._flatpickr?.clear();
    });
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').remove();
}
