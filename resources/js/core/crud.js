import $ from 'jquery';
import { confirmAction, toast } from './ui';
import { fillForm, resetForm } from './shell';

/**
 * Pola CRUD sederhana berbasis modal:
 * - #btnAdd membuka modal kosong (POST ke storeUrl)
 * - [data-edit='{json}'] membuka modal terisi (PUT ke `${baseUrl}/${id}`)
 * - [data-delete="url"] menghapus setelah konfirmasi
 */
export function modalCrud({ modal, form, storeUrl, baseUrl, titleCreate, titleEdit, defaults = {}, onOpen, onSaved = () => window.location.reload() }) {
    const $modal = $(modal);
    const $form = $(form);
    const instance = window.bootstrap.Modal.getOrCreateInstance($modal[0]);

    const open = (data = null) => {
        resetForm($form);
        fillForm($form, data || defaults);
        $form.attr('action', data ? `${baseUrl}/${data.id}` : storeUrl);
        $form.attr('method', data ? 'PUT' : 'POST');
        $modal.find('.modal-title').text(data ? titleEdit : titleCreate);
        onOpen?.(data, $form);
        instance.show();
    };

    $('#btnAdd').on('click', () => open());
    $(document).on('click', '[data-edit]', function () {
        const data = $(this).data('edit');
        if (typeof data === 'object') open(data);
    });

    $(document).on('click', '[data-delete]', async function () {
        const ok = await confirmAction({
            title: 'Hapus data?',
            text: `<strong>${$(this).data('name') || ''}</strong> akan dihapus permanen.`,
            confirmText: 'Hapus',
            danger: true,
        });
        if (!ok) return;
        $.ajax({ url: $(this).data('delete'), method: 'DELETE' }).done((res) => {
            toast(res.message);
            onSaved(res);
        });
    });

    $form.on('ajax:saved', (_e, res) => onSaved(res));

    return { open };
}
