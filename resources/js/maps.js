// RSYNC maps: shared helpers for the home page, the room page and the landlord's pin picker.
// Leaflet itself is loaded from a CDN in the page <head> (partials/leaflet.blade.php), so it is the global `L`.
// Map tiles come from OpenStreetMap.
import '../css/maps.css';

const L = window.L;

// Where maps open when there is nothing to show yet: Baguio City.
const DEFAULT_CENTER = [16.4023, 120.5960];
const DEFAULT_ZOOM = 13;

function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function baseMap(el, options = {}) {
    const map = L.map(el, { scrollWheelZoom: false, ...options });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(map);
    // Don't hijack the page scroll: the wheel only zooms after the map has been clicked.
    map.on('click', () => map.scrollWheelZoom.enable());
    map.on('mouseout', () => map.scrollWheelZoom.disable());
    return map;
}

function unavailable(el) {
    el.classList.add('map-unavailable');
    el.textContent = 'The map could not be loaded. Please check your internet connection.';
}

// ── Home page: every listed room that has a pin ──
// rooms: [{ id, name, price, slots_left, image, lat, lng }]
export function initRoomsMap(el, rooms, roomUrl) {
    if (!el) return null;
    if (!L) { unavailable(el); return null; }

    const map = baseMap(el).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

    // Rooms in the same building share one pin; its popup lists all of them.
    const groups = new Map();
    rooms.forEach(r => {
        const key = Number(r.lat).toFixed(6) + ',' + Number(r.lng).toFixed(6);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(r);
    });

    const points = [];
    groups.forEach(list => {
        const ll = [Number(list[0].lat), Number(list[0].lng)];
        points.push(ll);
        const html = '<div class="map-pop-list">' + list.map(r => `
            <div class="map-pop">
                ${r.image ? `<img src="${esc(r.image)}" alt="">` : '<div class="map-pop-noimg"></div>'}
                <div>
                    <a class="map-pop-name" href="${esc(roomUrl)}/${encodeURIComponent(r.id)}">${esc(r.name)}</a>
                    <div class="map-pop-meta">₱${Number(r.price).toLocaleString()} / month</div>
                    <div class="map-pop-meta">${esc(r.slots_left)} slot${Number(r.slots_left) === 1 ? '' : 's'} open</div>
                </div>
            </div>`).join('') + '</div>';
        L.marker(ll).addTo(map).bindPopup(html, { maxWidth: 280 });
    });

    if (points.length === 1) map.setView(points[0], 16);
    else if (points.length > 1) map.fitBounds(points, { padding: [40, 40], maxZoom: 16 });
    return map;
}

// ── Room page: one pin, not movable ──
export function initRoomMap(el, lat, lng, name) {
    if (!el) return null;
    if (!L) { unavailable(el); return null; }

    const map = baseMap(el).setView([lat, lng], 16);
    L.marker([lat, lng]).addTo(map).bindPopup(`<strong>${esc(name)}</strong>`);
    return map;
}

// ── Landlord: Add Room / Edit Room pin picker ──
// root = the [data-picker] block (partials/location-picker.blade.php).
// Click the map, drag the pin, search a place, or use the device location.
export function createPicker(root) {
    if (!root) return null;
    const el = root.querySelector('.pick-map');
    if (!L) { unavailable(el); return null; }

    const latIn = root.querySelector('.pick-lat');
    const lngIn = root.querySelector('.pick-lng');
    const hint = root.querySelector('.pick-hint');
    const q = root.querySelector('.pick-q');
    const noPin = hint.textContent;
    let map = null;
    let marker = null;

    function ensureMap() {
        if (map) return;
        map = baseMap(el).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
        map.on('click', e => place(e.latlng.lat, e.latlng.lng));
    }

    function setInputs(lat, lng) {
        latIn.value = lat.toFixed(7);
        lngIn.value = lng.toFixed(7);
        hint.textContent = 'Pin set: ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' (drag it to adjust)';
    }

    function place(lat, lng, zoom) {
        ensureMap();
        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => { const p = marker.getLatLng(); setInputs(p.lat, p.lng); });
        } else {
            marker.setLatLng([lat, lng]);
        }
        setInputs(lat, lng);
        if (zoom) map.setView([lat, lng], zoom);
    }

    function clear() {
        if (marker) { marker.remove(); marker = null; }
        latIn.value = ''; lngIn.value = '';
        hint.textContent = noPin;
    }

    function search() {
        const text = q.value.trim();
        if (!text) return;
        hint.textContent = 'Searching…';
        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=ph&q=' + encodeURIComponent(text), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(res => {
                if (!res.length) { hint.textContent = 'Nothing found. Try a more general place, or click the map.'; return; }
                place(Number(res[0].lat), Number(res[0].lon), 17);
            })
            .catch(() => { hint.textContent = 'Search is not available right now. Click the map instead.'; });
    }

    root.querySelector('.pick-go').addEventListener('click', search);
    q.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); search(); } });   // Enter must not submit the room form
    root.querySelector('.pick-clear').addEventListener('click', clear);
    root.querySelector('.pick-locate').addEventListener('click', () => {
        if (!navigator.geolocation) { hint.textContent = 'Your browser cannot share its location.'; return; }
        hint.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(
            pos => place(pos.coords.latitude, pos.coords.longitude, 17),
            () => { hint.textContent = 'Could not get your location. Click the map instead.'; },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    return {
        // Call right after the window becomes visible (a hidden map cannot measure itself).
        // lat/lng = the room's current pin, or empty for none.
        show(lat, lng) {
            ensureMap();
            map.invalidateSize();
            q.value = '';
            const hasPin = lat !== undefined && lat !== null && lat !== '' && lng !== undefined && lng !== null && lng !== '';
            if (hasPin) {
                place(Number(lat), Number(lng), 16);
            } else {
                clear();
                map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            }
        },
    };
}
