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

// ---- Live search: suggestions under the keyword box while typing ("l" → Lourdes, Lower Magsaysay Ave, ...) ----
document.querySelectorAll('[data-suggest]').forEach(input => {
    const form = input.form;
    const list = document.getElementById(input.getAttribute('aria-controls'));
    if (!form || !list) return;
    const ICON = { room: 'bx-home-alt', area: 'bx-map', street: 'bx-map-pin' };
    const KIND = { room: 'Room', area: 'Location', street: 'Street' };
    let items = [], active = -1, timer = null, last = '', ctrl = null;

    const esc = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Bold the typed part: "lo" in "Lourdes" → <mark>Lo</mark>urdes
    const mark = (label, q) => {
        const i = label.toLowerCase().indexOf(q.toLowerCase());
        return i < 0 ? esc(label) : esc(label.slice(0, i)) + '<mark>' + esc(label.slice(i, i + q.length)) + '</mark>' + esc(label.slice(i + q.length));
    };
    const open = yes => { list.hidden = !yes; input.setAttribute('aria-expanded', yes ? 'true' : 'false'); if (!yes) setActive(-1); };
    const setActive = i => {
        active = i;
        [...list.children].forEach((li, n) => li.setAttribute('aria-selected', n === i ? 'true' : 'false'));
        if (i >= 0 && list.children[i]) { input.setAttribute('aria-activedescendant', list.children[i].id); list.children[i].scrollIntoView({ block: 'nearest' }); }
        else input.removeAttribute('aria-activedescendant');
    };
    const render = q => {
        if (!items.length) {
            list.innerHTML = `<li class="s-none" role="option" aria-disabled="true">No rooms, streets or places match “${esc(q)}”</li>`;
        } else {
            list.innerHTML = items.map((s, n) => `<li role="option" id="${list.id}-${n}" data-n="${n}"><i class="bx ${ICON[s.type]}" aria-hidden="true"></i>`
                + `<span class="s-text">${mark(s.label, q)}${s.sub && s.type !== 'area' ? `<span class="s-sub">${esc(s.sub)}</span>` : ''}</span>`
                + `<span class="s-kind">${KIND[s.type]}</span></li>`).join('');
        }
        open(true);
    };
    // Picking a suggestion: a room opens its page; a location picks it in the Location box; a street searches for it.
    const choose = n => {
        const s = items[n];
        if (!s) return;
        open(false);
        if (s.type === 'room' && s.url) { window.location.href = s.url; return; }
        const area = form.querySelector('select[name="area"]');
        if (s.type === 'area' && area && [...area.options].some(o => o.value === s.label)) {
            area.value = s.label;
            input.value = '';
        } else {
            input.value = s.label;
        }
        form.requestSubmit();
    };
    const fetchNow = async () => {
        const q = input.value.trim();
        if (q === last) return;
        last = q;
        if (!q) { items = []; open(false); return; }
        ctrl?.abort();
        ctrl = new AbortController();
        try {
            const res = await fetch(`${input.dataset.suggest}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: ctrl.signal });
            if (!res.ok) return;
            items = await res.json();
            if (input.value.trim() === q && document.activeElement === input) render(q);
        } catch (e) { /* typing again cancelled it, or offline: no suggestions */ }
    };

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(fetchNow, 150); });
    input.addEventListener('focus', () => { if (input.value.trim() && items.length) render(input.value.trim()); });
    input.addEventListener('keydown', e => {
        if (list.hidden || !items.length) { if (e.key === 'Escape') open(false); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % items.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active <= 0 ? items.length - 1 : active - 1); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(active); }
        else if (e.key === 'Escape') { e.preventDefault(); open(false); }
    });
    // mousedown (not click) so the box doesn't lose focus and close first
    list.addEventListener('mousedown', e => { const li = e.target.closest('li[data-n]'); if (li) { e.preventDefault(); choose(Number(li.dataset.n)); } });
    input.addEventListener('blur', () => setTimeout(() => open(false), 120));
    form.addEventListener('submit', () => open(false));
});
