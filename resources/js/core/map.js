import L from 'leaflet';

export const COLORS = {
    onsite: '#0f9d58',
    offsite: '#e79a00',
    project: getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#1e5aa8',
};

export function createMap(el, { center = [-2.5, 118], zoom = 5 } = {}) {
    const map = L.map(el, { zoomControl: true, attributionControl: true, scrollWheelZoom: false }).setView(center, zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    map.on('focus', () => map.scrollWheelZoom.enable());
    map.on('blur', () => map.scrollWheelZoom.disable());

    return map;
}

export function pinIcon(color, label = '') {
    return L.divIcon({
        className: '',
        html: `<div class="map-pin" style="--pin:${color}"><span>${label}</span></div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -28],
    });
}

export function dotIcon(color) {
    return L.divIcon({
        className: '',
        html: `<div class="map-dot" style="--pin:${color}"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

export function projectCircle(location) {
    return L.circle([location.latitude, location.longitude], {
        radius: location.radius_m,
        color: COLORS.project,
        weight: 2,
        fillColor: COLORS.project,
        fillOpacity: 0.08,
        dashArray: '6 6',
    });
}

export function fitTo(map, layers, fallbackZoom = 16) {
    const group = L.featureGroup(layers.filter(Boolean));
    if (!group.getLayers().length) return;
    const bounds = group.getBounds();
    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.25), { maxZoom: fallbackZoom });
    }
}

export { L };
