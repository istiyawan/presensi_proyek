import '@fontsource-variable/plus-jakarta-sans';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../scss/app.scss';

// Tampilkan / sembunyikan password
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = document.querySelector(btn.dataset.togglePassword);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('.bi').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});

document.querySelector('form[data-loading]')?.addEventListener('submit', (e) => {
    const btn = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses…';
});
