// RSYNC room lists (home page and /rooms): filters, Apply buttons, the map next to the cards
// and the List / Map switch on phones. The page works without this script; it only makes it nicer.
import { submitApplication } from './apply.js';
import { initBrowseMap } from './maps.js';

// ---- Filters: a plain GET form ----
document.querySelectorAll('[data-filters]').forEach(form => {
    // Picking a location or room type searches right away (no need to find the button).
    form.querySelectorAll('[data-autosubmit]').forEach(sel => sel.addEventListener('change', () => form.requestSubmit()));
    // Leave empty boxes out of the address, so links stay short: /rooms?area=Lourdes instead of ?name=&area=Lourdes&min=...
    form.addEventListener('submit', () => {
        form.querySelectorAll('input, select').forEach(f => { if (f.name && f.value.trim() === '') f.disabled = true; });
        setTimeout(() => form.querySelectorAll(':disabled').forEach(f => { f.disabled = false; }), 0);   // back button
    });
});

const browse = document.querySelector('[data-browse]');
if (browse) {
    const page = browse.dataset;

    // ---- Apply (cards and map popups) ----
    browse.addEventListener('click', ev => {
        const btn = ev.target.closest('[data-apply]');
        if (!btn) return;
        if (page.loggedIn !== '1') {
            if (confirm('You need to log in to apply for a room.\n\nWould you like to log in or register?')) {
                window.location.href = page.loginUrl;
            }
            return;
        }
        if (page.tenant !== '1') {
            alert('Only tenants can apply for rooms.');
            return;
        }
        submitApplication(btn.dataset.apply, btn.dataset.name);
    });

    // ---- Map ----
    const mapEl = document.getElementById('rooms-map');
    const map = mapEl ? initBrowseMap(mapEl, JSON.parse(mapEl.dataset.rooms || '[]'), {
        roomUrl: page.roomUrl,
        canApply: page.canApply === '1',
        cover: document.querySelector('[data-map-cover]'),   // home page search card
    }) : null;

    if (map) {
        browse.classList.add('map-ready');   // the List / Map switch is only shown when the map works

        // Hovering or tabbing to a card lights up its pin.
        browse.querySelectorAll('.card').forEach(card => {
            const id = card.dataset.roomId;
            card.addEventListener('mouseenter', () => map.highlight(id, true));
            card.addEventListener('mouseleave', () => map.highlight(id, false));
            card.addEventListener('focusin', () => map.highlight(id, true));
            card.addEventListener('focusout', () => map.highlight(id, false));
        });

        const setView = view => {
            browse.dataset.view = view;
            browse.querySelectorAll('[data-view-btn]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.viewBtn === view)));
            map.refresh(view === 'map');   // the map may have changed size: show all pins again
        };
        browse.querySelectorAll('[data-view-btn]').forEach(b => b.addEventListener('click', () => {
            setView(b.dataset.viewBtn);
            browse.querySelector('.browse-head').scrollIntoView({ block: 'start' });
        }));

        // "Show on map" on a card.
        browse.addEventListener('click', ev => {
            const link = ev.target.closest('[data-show-on-map]');
            if (!link) return;
            ev.preventDefault();
            const toggle = browse.querySelector('.view-toggle');
            const stacked = toggle && getComputedStyle(toggle).display !== 'none';   // phone / tablet: one view at a time
            if (stacked) setView('map');
            if (stacked) browse.querySelector('.browse-head').scrollIntoView({ block: 'start' });
            else mapEl.scrollIntoView({ block: 'nearest' });
            map.show(link.dataset.showOnMap);
        });

        // The map column changes size with the window.
        let t;
        window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => map.refresh(), 150); });
    }
}
