import $ from 'jquery';
import flatpickr from 'flatpickr';
import { serverTable, reload } from '../core/table';
import { avatar, escapeHtml, flagIcons, modeBadge, statusBadge, FLAGS, STATUS } from '../core/ui';
import { fillForm, resetForm } from '../core/shell';
import { createMap, pinIcon, projectCircle, fitTo, COLORS, L } from '../core/map';

export default function () {
    const $filter = $('#filterForm');
    const $range = $('#rangeInput');
    let from = $range.data('from');
    let to = $range.data('to');

    const picker = flatpickr($range[0], {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'j M Y',
        conjunction: ' – ',
        defaultDate: [from, to],
        onClose: (dates) => {
            if (dates.length === 2) {
                from = picker.formatDate(dates[0], 'Y-m-d');
                to = picker.formatDate(dates[1], 'Y-m-d');
                $('#periodSelect').val('');
                reload(table, true);
            }
        },
    });

    // Pilih "Bulan ke-N" bila rentang awal cocok dengan suatu periode
    $('#periodSelect option').each(function () {
        if (this.value === `${from}|${to}`) $(this).prop('selected', true);
    });

    const table = serverTable('#tableAttendances', {
        url: '/attendances/data',
        order: [[0, 'desc']],
        filters: () => ({
            from,
            to,
            employee_id: $filter.find('[name=employee_id]').val(),
            status: $filter.find('[name=status]').val(),
            mode: $filter.find('[name=mode]').val(),
            flag: $filter.find('[name=flag]').val(),
        }),
        // Kolom sama dengan laporan PDF → semua selalu tampil, tabel digeser horizontal bila layar sempit
        responsive: false,
        scrollX: true,
        columns: [
            { data: 'work_date', render: (_v, _t, r) => `<span class="fw-semibold text-ink text-nowrap">${r.date_label}</span>${r.is_holiday_work ? '<div class="fs-8 text-danger">Hari libur</div>' : ''}` },
            {
                data: 'name',
                render: (v, _t, r) => `<div class="person">${avatar(r.full_name, 'avatar-sm')}<div class="min-w-0"><div class="person-name">${escapeHtml(v)}</div></div></div>`,
            },
            { data: 'position', orderable: false, searchable: false, render: textCell },
            { data: 'shift_name', orderable: false, searchable: false, className: 'text-nowrap', render: textCell },
            { data: 'in_time', searchable: false, render: (v, _t, r) => timeCell(v, r.check_in_mode, r.check_in_offline) },
            { data: 'out_time', searchable: false, render: (v, _t, r) => timeCell(v, r.check_out_mode, r.check_out_offline, r.check_in_mode && !v, r.out_next_day) },
            { data: 'coord_in', orderable: false, searchable: false, render: coordCell },
            { data: 'coord_out', orderable: false, searchable: false, render: coordCell },
            {
                data: 'status',
                render: (v, _t, r) => {
                    let html = statusBadge(v);
                    if (v === 'terlambat' && r.late_minutes) html += `<div class="fs-8 text-muted mt-1">+${r.late_minutes} menit</div>`;
                    if (r.offsite_approval === 'pending') html += '<div class="fs-8 text-warning mt-1">Menunggu approval</div>';
                    if (r.offsite_approval === 'rejected') html += '<div class="fs-8 text-danger mt-1">Luar lokasi ditolak</div>';
                    return html;
                },
            },
            { data: 'duration_minutes', searchable: false, render: (_v, _t, r) => (r.duration_label ? `<span class="tabular text-nowrap">${r.duration_label}</span>` : '<span class="text-muted">–</span>') },
            {
                data: 'photo_in',
                orderable: false,
                searchable: false,
                render: (_v, _t, r) => `<div class="photo-pair">${photoThumb(r.photo_in, 'CI')}${photoThumb(r.photo_out, 'CO')}</div>`,
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: (id, _t, r) => `
                    <span class="me-2">${flagIcons(r.flags)}${r.is_manual ? ' <i class="bi bi-pencil-square text-info" data-bs-toggle="tooltip" title="Dikoreksi manual"></i>' : ''}</span>
                    <button class="btn btn-soft btn-sm" data-detail="${id}">Detail</button>`,
            },
        ],
        createdRow: (row, data) => $(row).attr('role', 'button').attr('data-detail', data.id),
    });

    $filter.on('change', 'select:not(#periodSelect)', () => reload(table, true));
    $('#periodSelect').on('change', function () {
        if (!this.value) return;
        [from, to] = this.value.split('|');
        picker.setDate([from, to], false);
        reload(table, true);
    });
    $('#btnReset').on('click', () => {
        $filter.find('select:not(#periodSelect)').val('').trigger('change.select2');
        $('#periodSelect').prop('selectedIndex', 1).trigger('change');
    });

    setupDetail(() => reload(table));
}

function textCell(v) {
    return v ? escapeHtml(v) : '<span class="text-muted">–</span>';
}

function coordCell(v) {
    if (!v) return '<span class="text-muted">–</span>';
    return `<a href="https://www.google.com/maps?q=${encodeURIComponent(v.replace(' ', ''))}" target="_blank" rel="noopener" class="tabular text-nowrap fs-7">${v}</a>`;
}

/** Thumbnail foto; klik membuka foto ukuran penuh di tab baru (tidak membuka panel detail). */
function photoThumb(url, label) {
    const inner = url
        ? `<img src="${url}?thumb=1" alt="Foto ${label}" loading="lazy">`
        : '<div class="photo-empty"><i class="bi bi-camera"></i></div>';
    const tile = `<span class="photo-tile photo-tile-sm">${inner}<span class="photo-tag">${label}</span></span>`;
    return url ? `<a href="${url}" target="_blank" rel="noopener">${tile}</a>` : tile;
}

function timeCell(time, mode, offline, missing = false, nextDay = false) {
    if (!time) {
        return missing ? '<span class="chip soft-danger">Belum check-out</span>' : '<span class="text-muted">–</span>';
    }
    const icons = [];
    if (nextDay) icons.push('<span class="chip soft-violet" data-bs-toggle="tooltip" title="Check-out keesokan harinya (lewat tengah malam)">+1 hari</span>');
    if (mode === 'offsite') icons.push('<span class="chip soft-warning" title="Luar lokasi"><i class="bi bi-geo"></i>Luar</span>');
    if (offline) icons.push('<i class="bi bi-wifi-off text-muted" data-bs-toggle="tooltip" title="Dikirim offline"></i>');

    return `<span class="fw-bold text-ink tabular me-1">${time}</span>${icons.join(' ')}`;
}

function setupDetail(onChanged) {
    const panel = window.bootstrap.Offcanvas.getOrCreateInstance('#detailPanel');
    const $modal = $('#modalCorrection');
    const $form = $('#formCorrection');
    const modal = window.bootstrap.Modal.getOrCreateInstance($modal[0]);
    let map;
    let current;

    $(document).on('click', '[data-detail]', function (e) {
        if ($(e.target).closest('a, .dropdown, [data-bs-toggle="tooltip"]').length && !$(e.target).is('[data-detail]')) return;
        e.stopPropagation();
        open($(this).data('detail'));
    });

    function open(id) {
        $('#detailBody').html('<div class="text-center py-5"><span class="spinner-border text-secondary"></span></div>');
        panel.show();
        $.get(`/attendances/${id}`).done((d) => {
            current = d;
            render(d);
        });
    }

    function render(d) {
        $('#detailHead').html(`${avatar(d.employee.full_name, 'avatar-lg')}
            <div class="min-w-0"><div class="person-name fs-6">${escapeHtml(d.employee.name)}</div>
            <div class="person-sub">${escapeHtml(d.employee.position || '')} · ${d.date_label}</div></div>`);

        const photo = (side, label) => `
            <div class="photo-tile">
                ${side?.photo ? `<a href="${side.photo}" target="_blank" rel="noopener"><img src="${side.photo}?thumb=0" alt="Foto ${label}" loading="lazy"></a>` : `<div class="photo-empty"><div><i class="bi bi-camera fs-3 d-block mb-1"></i>${side ? 'Tanpa foto' : 'Belum ' + label.toLowerCase()}</div></div>`}
                <span class="photo-tag">${label === 'Check-in' ? 'CI' : 'CO'}</span>
            </div>`;

        const sideInfo = (s, label) => {
            if (!s) return `<div class="text-muted fs-7">Belum ${label.toLowerCase()}.</div>`;
            return `<dl class="detail-list">
                <dt>Waktu</dt><dd class="tabular">${s.time}${s.next_day ? ` <span class="chip soft-violet">${s.date}</span>` : ''}</dd>
                <dt>Lokasi</dt><dd>${modeBadge(s.mode)}${s.distance != null ? ` <span class="fs-8 text-muted">${s.distance} m dari titik</span>` : ''}</dd>
                <dt>Koordinat</dt><dd class="tabular">${s.lat != null ? `${Number(s.lat).toFixed(6)}, ${Number(s.lng).toFixed(6)}` : '–'}${s.accuracy ? ` <span class="fs-8 text-muted">±${Math.round(s.accuracy)} m</span>` : ''}</dd>
                ${s.note ? `<dt>Keterangan</dt><dd>${escapeHtml(s.note)}</dd>` : ''}
                <dt>Pengiriman</dt><dd>${s.offline ? '<span class="chip soft-slate"><i class="bi bi-wifi-off"></i>Offline</span>' : 'Online'}${s.received_at ? `<div class="fs-8 text-muted fw-normal">diterima ${s.received_at}</div>` : ''}</dd>
                ${s.device?.model ? `<dt>Perangkat</dt><dd>${escapeHtml(s.device.model)} ${escapeHtml(s.device.os || '')}</dd>` : ''}
                ${s.is_mock ? '<dt>Peringatan</dt><dd class="text-danger">Lokasi palsu terdeteksi</dd>' : ''}
            </dl>`;
        };

        const flags = (d.flags || []).map((f) => `<span class="chip soft-warning"><i class="bi ${FLAGS[f]?.icon || 'bi-flag'}"></i>${FLAGS[f]?.label || f}</span>`).join(' ');

        const logs = d.logs.length
            ? `<ul class="activity-list">${d.logs.map((l) => `<li><span class="stat-icon soft-slate m-0" style="width:2rem;height:2rem;border-radius:.6rem;display:grid;place-items:center"><i class="bi bi-${l.event === 'created' ? 'plus' : 'pencil'}"></i></span>
                <div class="min-w-0"><div class="fs-7 fw-semibold text-ink">${l.event === 'created' ? 'Dibuat' : 'Diubah'} oleh ${escapeHtml(l.by)}</div>
                <div class="fs-8 text-muted">${describeChanges(l.changes)}</div></div><div class="time fw-normal text-muted fs-8">${l.at}</div></li>`).join('')}</ul>`
            : '<div class="text-muted fs-7">Belum ada riwayat perubahan.</div>';

        $('#detailBody').html(`
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                ${statusBadge(d.status)}
                ${d.late_minutes ? `<span class="chip soft-warning">Terlambat ${d.late_minutes} menit</span>` : ''}
                ${d.duration_label ? `<span class="chip"><i class="bi bi-hourglass-split"></i>${d.duration_label}</span>` : ''}
                ${d.shift ? `<span class="chip"><i class="bi bi-clock"></i>${escapeHtml(d.shift.name)}</span>` : ''}
                ${d.is_holiday_work ? '<span class="chip soft-danger">Hari libur</span>' : ''}
                ${d.is_manual ? '<span class="chip soft-info"><i class="bi bi-pencil-square"></i>Koreksi manual</span>' : ''}
                ${d.offsite_approval === 'pending' ? '<span class="chip soft-warning">Luar lokasi menunggu approval</span>' : ''}
                ${flags}
            </div>
            ${d.leave ? `<div class="alert-soft info mb-3"><i class="bi bi-envelope-paper"></i><div><strong>${STATUS[d.leave.type]?.label || d.leave.type}:</strong> ${escapeHtml(d.leave.reason)}</div></div>` : ''}
            ${d.note ? `<div class="alert-soft warning mb-3"><i class="bi bi-chat-left-text"></i><div>${escapeHtml(d.note)}</div></div>` : ''}

            <div class="row g-3 mb-4">
                <div class="col-6">${photo(d.check_in, 'Check-in')}</div>
                <div class="col-6">${photo(d.check_out, 'Check-out')}</div>
            </div>

            <div class="form-section">
                <div class="form-section-title">Peta</div>
                <div id="detailMap" class="map-box" style="height:240px"></div>
            </div>
            <div class="row g-4">
                <div class="col-md-6"><div class="form-section"><div class="form-section-title">Check-in</div>${sideInfo(d.check_in, 'Check-in')}</div></div>
                <div class="col-md-6"><div class="form-section"><div class="form-section-title">Check-out</div>${sideInfo(d.check_out, 'Check-out')}</div></div>
            </div>
            <div class="form-section">
                <div class="form-section-title">Riwayat perubahan</div>
                ${logs}
            </div>
            ${d.can_edit ? '<button class="btn btn-primary w-100 mt-2" id="btnCorrect"><i class="bi bi-pencil-square me-1"></i>Koreksi presensi</button>' : ''}
        `);

        renderMap(d);
    }

    function renderMap(d) {
        if (map) {
            map.remove();
            map = null;
        }
        const el = document.getElementById('detailMap');
        map = createMap(el);
        const layers = d.locations.map((loc) => projectCircle(loc).addTo(map).bindPopup(escapeHtml(loc.name)));
        [['check_in', 'CI', COLORS.onsite], ['check_out', 'CO', '#2a78d6']].forEach(([key, label, color]) => {
            const s = d[key];
            if (s?.lat == null) return;
            layers.push(L.marker([s.lat, s.lng], { icon: pinIcon(s.mode === 'offsite' ? COLORS.offsite : color, label) }).addTo(map)
                .bindPopup(`${label === 'CI' ? 'Check-in' : 'Check-out'} ${s.time}`));
        });
        setTimeout(() => {
            map.invalidateSize();
            fitTo(map, layers, 17);
        }, 250);
    }

    function describeChanges(changes) {
        const attrs = changes?.attributes || {};
        const labels = {
            work_date: 'tanggal', status: 'status', check_in_at: 'check-in', check_out_at: 'check-out', offsite_approval: 'approval luar lokasi', note: 'catatan',
            check_in_lat: 'koordinat check-in', check_out_lat: 'koordinat check-out', check_in_photo: 'foto check-in', check_out_photo: 'foto check-out',
        };
        // Log hanya berisi kolom yang berubah → lat & lng digabung jadi satu entri koordinat
        const keys = [...new Set(Object.keys(attrs).map((k) => k.replace(/_lng$/, '_lat')))].filter((k) => labels[k]);
        if (!keys.length) return 'Data presensi';
        return keys.map((k) => {
            let v = attrs[k];
            if (k.endsWith('_at') && v) v = String(v).slice(11, 16);
            if (k === 'work_date' && v) v = String(v).slice(0, 10);
            if (k.endsWith('_lat')) v = (attrs[k] ?? attrs[k.replace(/_lat$/, '_lng')]) != null ? 'diubah' : 'dihapus';
            if (k.endsWith('_photo')) v = v ? 'diganti' : 'dihapus';
            if (k === 'status') v = STATUS[v]?.label || v;
            return `${labels[k]}: <strong>${escapeHtml(v ?? '–')}</strong>`;
        }).join(' · ');
    }

    // ---- Koreksi & presensi manual ----
    const toggleTimes = () => {
        const needsTime = ['hadir', 'terlambat'].includes($form.find('[name=status]').val());
        $form.find('.time-field').toggle(needsTime);
        if (!needsTime) {
            $form.find('[name=check_in_time], [name=check_out_time]').each(function () {
                this._flatpickr?.clear();
            });
        }
    };
    $form.find('[name=status]').on('change', toggleTimes);

    $(document).on('click', '#btnCorrect', () => {
        resetForm($form);
        $form.attr('action', `/attendances/${current.id}`).attr('method', 'PUT');
        $form.find('.manual-only').hide();
        $form.find('.correction-only').show();
        $modal.find('.modal-title').text('Koreksi Presensi');
        $('#correctionSubtitle').text(`${current.employee.name} · ${current.date_label}`);
        fillForm($form, {
            work_date: current.work_date,
            status: current.status,
            check_in_time: current.check_in?.time?.slice(0, 5) || '',
            check_out_time: current.check_out?.time?.slice(0, 5) || '',
            check_in_lat: current.check_in?.lat ?? '',
            check_in_lng: current.check_in?.lng ?? '',
            check_out_lat: current.check_out?.lat ?? '',
            check_out_lng: current.check_out?.lng ?? '',
        });
        ['check_in', 'check_out'].forEach((p) => {
            const url = current[p]?.photo;
            $form.find(`.photo-current[data-side=${p}]`).html(url
                ? `Foto saat ini: <a href="${url}" target="_blank" rel="noopener">lihat</a>. Kosongkan bila tidak diganti.`
                : 'Belum ada foto.');
        });
        toggleTimes();
        modal.show();
    });

    $('#btnManual').on('click', () => {
        resetForm($form);
        $form.attr('action', '/attendances').attr('method', 'POST');
        $form.find('.manual-only').show();
        $form.find('.correction-only').hide();
        $modal.find('.modal-title').text('Presensi Manual');
        $('#correctionSubtitle').text('Untuk karyawan yang tidak bisa presensi lewat aplikasi.');
        $form.find('.photo-current').empty();
        fillForm($form, { status: 'hadir', check_in_time: '08:00', check_out_time: '17:00' });
        toggleTimes();
        modal.show();
    });

    $form.on('ajax:saved', () => {
        onChanged();
        if ($form.attr('method') === 'PUT' && current) open(current.id);
    });
}
