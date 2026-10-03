import $ from 'jquery';

export default function () {
    const $form = $('#formApp');
    const $hex = $form.find('[name=primary_color]');

    const applyColor = (hex) => {
        if (!/^#[0-9a-f]{6}$/i.test(hex)) return;
        const rgb = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(', ');
        document.documentElement.style.setProperty('--brand', hex);
        document.documentElement.style.setProperty('--brand-rgb', rgb);
    };

    $('#colorPicker').on('input', function () {
        $hex.val(this.value);
        applyColor(this.value);
    });
    $hex.on('input', function () {
        if (/^#[0-9a-f]{6}$/i.test(this.value)) {
            $('#colorPicker').val(this.value);
            applyColor(this.value);
        }
    });
    $('#swatches').on('click', '[data-color]', function () {
        const c = $(this).data('color');
        $hex.val(c);
        $('#colorPicker').val(c);
        applyColor(c);
    });

    $form.find('[name=logo]').on('change', function () {
        const file = this.files[0];
        if (!file) return;
        $form.find('[name=remove_logo]').val('0');
        const reader = new FileReader();
        reader.onload = (e) => $('#logoPreview').html(`<img src="${e.target.result}" alt="Logo">`);
        reader.readAsDataURL(file);
    });

    $('#btnRemoveLogo').on('click', () => {
        $form.find('[name=remove_logo]').val('1');
        $form.find('[name=logo]').val('');
        $('#logoPreview').html('<i class="bi bi-image"></i>');
    });

    $form.on('ajax:saved', () => setTimeout(() => window.location.reload(), 600));
}
