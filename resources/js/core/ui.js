import Swal from 'sweetalert2';
import $ from 'jquery';

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3200,
    timerProgressBar: true,
});

const buttons = {
    customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-soft',
        denyButton: 'btn btn-danger',
    },
    buttonsStyling: false,
    reverseButtons: true,
};

export function toast(title, icon = 'success') {
    return Toast.fire({ icon, title });
}

export async function confirmAction({ title, text, confirmText = 'Ya, lanjutkan', danger = false, icon = 'question' }) {
    const result = await Swal.fire({
        ...buttons,
        customClass: { ...buttons.customClass, confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary' },
        title,
        html: text,
        icon: danger ? 'warning' : icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Batal',
    });

    return result.isConfirmed;
}

/** Konfirmasi dengan catatan opsional/wajib. Mengembalikan string catatan, atau null bila batal. */
export async function promptNote({ title, text, confirmText = 'Simpan', required = false, danger = false, placeholder = 'Catatan (opsional)' }) {
    const result = await Swal.fire({
        ...buttons,
        customClass: { ...buttons.customClass, confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary' },
        title,
        html: text,
        input: 'textarea',
        inputPlaceholder: placeholder,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Batal',
        inputValidator: (v) => (required && !v?.trim() ? 'Catatan wajib diisi.' : undefined),
    });

    return result.isConfirmed ? result.value || '' : null;
}

export function alertInfo(title, html) {
    return Swal.fire({ ...buttons, title, html, icon: 'info', confirmButtonText: 'Mengerti' });
}

export function initials(name = '') {
    return name
        .replace(/,.*$/, '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');
}

const avatarGradients = [
    ['#2a78d6', '#1c5cab'], ['#0f9d58', '#0b7a43'], ['#7c5cdb', '#4a3aa7'], ['#e8743b', '#c2410c'],
    ['#0e9fb0', '#0b7285'], ['#d6457a', '#a61e4d'], ['#5b6b84', '#334155'], ['#c99400', '#a86400'],
];

export function avatar(name, cls = '') {
    let hash = 0;
    for (const c of name || '') hash = (hash * 31 + c.charCodeAt(0)) >>> 0;
    const [a, b] = avatarGradients[hash % avatarGradients.length];

    return `<span class="avatar ${cls}" style="background:linear-gradient(135deg,${a},${b})">${escapeHtml(initials(name))}</span>`;
}

export function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

export const STATUS = {
    hadir: { label: 'Hadir', tone: 'success' },
    terlambat: { label: 'Terlambat', tone: 'warning' },
    izin: { label: 'Izin', tone: 'info' },
    sakit: { label: 'Sakit', tone: 'violet' },
    cuti: { label: 'Cuti', tone: 'brand' },
    alpha: { label: 'Alpha', tone: 'danger' },
    libur: { label: 'Libur', tone: 'slate' },
};

export function statusBadge(status) {
    const s = STATUS[status] || { label: status, tone: 'slate' };

    return `<span class="badge-status soft-${s.tone}">${s.label}</span>`;
}

export function modeBadge(mode) {
    if (!mode) return '';

    return mode === 'offsite'
        ? '<span class="chip soft-warning"><i class="bi bi-geo"></i>Luar Lokasi</span>'
        : '<span class="chip soft-success"><i class="bi bi-geo-alt-fill"></i>Dalam Lokasi</span>';
}

export const FLAGS = {
    missing_checkout: { label: 'Lupa check-out', icon: 'bi-door-open' },
    time_suspicious: { label: 'Waktu perangkat meragukan', icon: 'bi-clock-history' },
    low_accuracy: { label: 'Akurasi GPS rendah', icon: 'bi-reception-1' },
};

export function flagIcons(flags = []) {
    return (flags || [])
        .map((f) => FLAGS[f] ? `<i class="bi ${FLAGS[f].icon} text-warning" data-bs-toggle="tooltip" title="${FLAGS[f].label}"></i>` : '')
        .join(' ');
}

export function tooltips(root = document) {
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => window.bootstrap.Tooltip.getOrCreateInstance(el));
}
