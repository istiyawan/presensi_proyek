import $ from 'jquery';
import { toast } from './ui';

export function setupHttp() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            Accept: 'application/json',
        },
    });

    // Error global yang tidak ditangani form
    $(document).ajaxError((_e, xhr, settings) => {
        if (settings.silent || xhr.status === 422 || xhr.statusText === 'abort') {
            return;
        }
        if (xhr.status === 401 || xhr.status === 419) {
            toast('Sesi berakhir, silakan login ulang.', 'warning');
            setTimeout(() => window.location.reload(), 1200);
            return;
        }
        toast(errorMessage(xhr), 'error');
    });
}

export function errorMessage(xhr) {
    return xhr.responseJSON?.message || `Terjadi kesalahan (${xhr.status}).`;
}

/** Tampilkan error validasi Laravel di bawah field yang sesuai. */
export function showErrors($form, errors = {}) {
    clearErrors($form);
    Object.entries(errors).forEach(([field, messages]) => {
        const name = field.replace(/\.(\w+)/g, '[$1]');
        let $input = $form.find(`[name="${name}"], [name="${name}[]"]`).first();
        if (!$input.length) {
            $input = $form.find(`[data-error-for="${field}"]`);
        }
        $input.addClass('is-invalid');
        const $target = $input.closest('.input-group, .select2-wrap').length ? $input.closest('.input-group, .select2-wrap') : $input;
        $target.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
    });
    $form.find('.is-invalid').first().trigger('focus');
}

export function clearErrors($form) {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').remove();
}

/**
 * Kirim form via AJAX (mendukung file). Mengembalikan Promise berisi respons JSON.
 */
export function submitForm($form, { url, method, data } = {}) {
    const $btn = $form.find('[type=submit]').length ? $form.find('[type=submit]') : $(`[form="${$form.attr('id')}"][type=submit]`);
    const original = $btn.html();
    const payload = data ?? new FormData($form[0]);

    // Checkbox tak tercentang tidak terkirim → kirim 0 eksplisit
    if (payload instanceof FormData) {
        $form.find('input[type=checkbox][data-bool]').each(function () {
            payload.set(this.name, this.checked ? '1' : '0');
        });
    }

    clearErrors($form);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan…');

    const spoof = (method || $form.attr('method') || 'POST').toUpperCase();
    if (payload instanceof FormData && spoof !== 'POST') {
        payload.set('_method', spoof);
    }

    return $.ajax({
        url: url || $form.attr('action'),
        method: payload instanceof FormData ? 'POST' : spoof,
        data: payload,
        processData: !(payload instanceof FormData),
        contentType: payload instanceof FormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
    })
        .fail((xhr) => {
            if (xhr.status === 422) {
                showErrors($form, xhr.responseJSON?.errors);
                toast(xhr.responseJSON?.message || 'Periksa kembali isian form.', 'warning');
            }
        })
        .always(() => $btn.prop('disabled', false).html(original));
}
