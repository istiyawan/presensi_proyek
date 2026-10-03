import $ from 'jquery';
import { modalCrud } from '../core/crud';
import { createMap, pinIcon, projectCircle, fitTo, COLORS, L } from '../core/map';
import { escapeHtml, toast } from '../core/ui';

export default function () {
    const locations = window.Locations || [];
    const overview = createMap(document.getElementById('locationsMap'));
    const layers = {};

    locations.forEach((loc) => {
        const color = loc.is_active ? COLORS.project : '#94a3b8';
        const circle = projectCircle(loc).setStyle({ color, fillColor: color }).addTo(overview);
        const marker = L.marker([loc.latitude, loc.longitude], { icon: pinIcon(color) })
            .addTo(overview)
            .bindPopup(`<strong>${escapeHtml(loc.name)}</strong><br>Radius ${loc.radius_m} m`);
        layers[loc.id] = { circle, marker };
    });
    fitTo(overview, Object.values(layers).map((l) => l.circle), 17);

    $('[data-focus]').on('click', function (e) {
        if ($(e.target).closest('.dropdown').length) return;
        const l = layers[$(this).data('focus')];
        overview.fitBounds(l.circle.getBounds().pad(0.4));
        l.marker.openPopup();
    });

    // ---- Pemilih titik di modal ----
    const $form = $('#formLocation');
    let picker;
    let marker;
    let circle;

    const ensurePicker = () => {
        if (picker) return;
        picker = createMap(document.getElementById('pickerMap'));
        picker.scrollWheelZoom.enable();
        picker.on('click', (e) => setPoint(e.latlng.lat, e.latlng.lng, false));
    };

    const radius = () => Number($form.find('[name=radius_m]').val()) || 100;

    function setPoint(lat, lng, fly = true) {
        lat = Number(lat);
        lng = Number(lng);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        $form.find('[name=latitude]').val(lat.toFixed(7));
        $form.find('[name=longitude]').val(lng.toFixed(7));

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true, icon: pinIcon(COLORS.project) }).addTo(picker);
            marker.on('drag', (e) => {
                const p = e.target.getLatLng();
                circle.setLatLng(p);
                $form.find('[name=latitude]').val(p.lat.toFixed(7));
                $form.find('[name=longitude]').val(p.lng.toFixed(7));
            });
            circle = projectCircle({ latitude: lat, longitude: lng, radius_m: radius() }).addTo(picker);
        }
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]).setRadius(radius());
        if (fly) picker.fitBounds(circle.getBounds().pad(0.6));
    }

    function setRadius(value) {
        $form.find('[name=radius_m]').val(value);
        $('#radiusRange').val(Math.min(value, 1000));
        circle?.setRadius(Number(value));
    }

    modalCrud({
        modal: '#modalLocation',
        form: '#formLocation',
        storeUrl: '/locations',
        baseUrl: '/locations',
        titleCreate: 'Tambah Titik Lokasi',
        titleEdit: 'Ubah Titik Lokasi',
        defaults: { radius_m: 100, is_active: true },
        onOpen: (data) => {
            $('#pasteCoord').val('');
            $('#modalLocation').one('shown.bs.modal', () => {
                ensurePicker();
                picker.invalidateSize();
                if (marker) {
                    picker.removeLayer(marker);
                    picker.removeLayer(circle);
                    marker = null;
                }
                setRadius(data?.radius_m ?? 100);
                const base = data || locations.find((l) => l.is_active);
                if (data) {
                    setPoint(data.latitude, data.longitude);
                } else if (base) {
                    picker.setView([base.latitude, base.longitude], 16);
                } else {
                    picker.setView([-2.5, 118], 5);
                }
            });
        },
    });

    $('#radiusRange').on('input', function () {
        setRadius(this.value);
    });
    $form.find('[name=radius_m]').on('input', function () {
        setRadius(this.value);
    });
    $form.find('[name=latitude], [name=longitude]').on('change', () => {
        setPoint($form.find('[name=latitude]').val(), $form.find('[name=longitude]').val());
    });

    $('#btnPaste').on('click', () => {
        const m = $('#pasteCoord').val().match(/(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)/);
        if (!m) {
            toast('Format koordinat tidak dikenali. Contoh: -7.3154, 112.6855', 'warning');
            return;
        }
        setPoint(m[1], m[2]);
    });
}
