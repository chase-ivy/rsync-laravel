// RSYNC maps: shared helpers for the room lists (home, /rooms), the room page and the landlord's pin picker.
// Leaflet itself is loaded from a CDN in the page <head> (partials/leaflet.blade.php), so it is the global `L`.
// Map tiles come from OpenStreetMap.
import '../css/maps.css';

const L = window.L;

// Where maps open when there is nothing to show yet: Baguio City.
const DEFAULT_CENTER = [16.4023, 120.5960];
const DEFAULT_ZOOM = 13;
// Room lists open a little closer, on the city centre (Session Road / Burnham Park).
const BAGUIO_CENTER = [16.4110, 120.5970];
const BAGUIO_ZOOM = 14;

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

// ── Room lists (home page and /rooms): one price pin per building, linked to the room cards ──
// rooms: [{ id, name, price, price_label, rent, area, image, lat, lng }] (RoomFilters::mapPins)
// opts: { roomUrl, canApply, cover }  →  returns { highlight(id, on), show(id), refresh() } or null.
// cover = an element drawn over the map's left side (the home page search card): pins are kept to its right.
const peso = n => '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: 0 });

export function initBrowseMap(el, rooms, opts) {
    if (!el) return null;
    if (!L) { unavailable(el); return null; }

    // Baguio City: the whole city fits at zoom 14 around Burnham Park / Session Road.
    const map = baseMap(el, { zoomControl: false }).setView(BAGUIO_CENTER, BAGUIO_ZOOM);
    L.control.zoom({ position: opts.cover ? 'topright' : 'topleft' }).addTo(map);

    // How many pixels of the map's left side the cover hides right now (0 when it sits above or below the map, as on phones).
    const coverLeft = () => {
        if (!opts.cover) return 0;
        const c = opts.cover.getBoundingClientRect(), m = el.getBoundingClientRect();
        const overlaps = c.bottom > m.top && c.top < m.bottom && c.left < m.right && c.right > m.left;
        return overlaps ? Math.max(0, Math.round(c.right - m.left)) : 0;
    };

    // Rooms in the same building share one pin; its popup lists all of them.
    const groups = new Map();
    rooms.forEach(r => {
        const key = Number(r.lat).toFixed(6) + ',' + Number(r.lng).toFixed(6);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(r);
    });

    const byRoom = new Map();   // room id → marker
    const points = [];
    groups.forEach(list => {
        const ll = [Number(list[0].lat), Number(list[0].lng)];
        points.push(ll);
        const cheapest = list.reduce((a, b) => (Number(b.price) < Number(a.price) ? b : a));
        const label = peso(cheapest.price) + (list.length > 1 ? ` <small>+${list.length - 1}</small>` : '');
        const icon = L.divIcon({ className: 'price-pin', html: `<span>${label}</span>`, iconSize: null, iconAnchor: [0, 0] });
        const names = list.map(r => r.name).join(', ');
        const marker = L.marker(ll, { icon, title: names, alt: names, riseOnHover: true }).addTo(map);
        marker.bindPopup(popupHtml(list, opts), { maxWidth: 300, minWidth: 240, autoPanPadding: [20, 20], className: 'room-pop' });
        list.forEach(r => byRoom.set(String(r.id), marker));
    });

    // Show every pin (or Baguio when there are none).
    const fit = () => {
        if (points.length === 1) {
            map.setView(points[0], 16);
            map.panBy([-coverLeft() / 2, 0], { animate: false });   // into the middle of the part you can see
        } else if (points.length > 1) {
            map.fitBounds(points, { paddingTopLeft: [48 + coverLeft(), 48], paddingBottomRight: [48, 48], maxZoom: 16 });
        }
    };
    // A map made while hidden (phone List view) has no size yet: fit it the first time it is shown.
    let fitLater = el.clientHeight === 0;
    if (!fitLater) fit();

    const setActive = (marker, on) => {
        const node = marker.getElement();
        if (node) node.classList.toggle('is-active', on);
        marker.setZIndexOffset(on ? 1000 : 0);
    };

    return {
        // A card is hovered or focused: light up its pin.
        highlight(id, on) {
            const m = byRoom.get(String(id));
            if (m) setActive(m, on);
        },
        // "Show on map": go to the pin and open its popup.
        show(id) {
            const m = byRoom.get(String(id));
            if (!m) return;
            map.setView(m.getLatLng(), Math.max(map.getZoom(), 16), { animate: false });
            map.panBy([-coverLeft() / 2, 0], { animate: false });
            m.openPopup();
        },
        // The map was hidden (phone List view) or resized: let it measure itself again.
        refresh(refit = false) {
            map.invalidateSize();
            if (refit) fitLater = true;
            if (fitLater && el.clientHeight > 0) { fitLater = false; fit(); }
        },
    };
}

function popupHtml(list, { roomUrl, canApply }) {
    return '<div class="map-pop-list">' + list.map(r => {
        const url = `${esc(roomUrl)}/${encodeURIComponent(r.id)}`;
        return `
        <div class="map-pop">
            ${r.image ? `<a href="${url}" tabindex="-1"><img src="${esc(r.image)}" alt=""></a>` : '<div class="map-pop-noimg"></div>'}
            <div class="map-pop-body">
                <a class="map-pop-name" href="${url}">${esc(r.name)}</a>
                ${r.area ? `<div class="map-pop-meta">${esc(r.area)}</div>` : ''}
                <div class="map-pop-price">${esc(r.price_label)}</div>
                <div class="map-pop-actions">
                    <a class="map-pop-btn" href="${url}">View</a>
                    ${canApply ? `<button type="button" class="map-pop-btn amber" data-apply="${esc(r.id)}" data-name="${esc(r.name)}">Apply</button>` : ''}
                </div>
            </div>
        </div>`;
    }).join('') + '</div>';
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
