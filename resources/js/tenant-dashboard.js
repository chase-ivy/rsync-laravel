// RSYNC tenant dashboard: switch between sections, and the slide-in menu on phones/tablets.
// Without JavaScript every section simply shows one after another.
document.documentElement.classList.add('js');

const panels = [...document.querySelectorAll('[data-panel]')];
const links = [...document.querySelectorAll('[data-panel-link]')];
const tabs = [...document.querySelectorAll('.tabs [data-panel-link]')];
const side = document.getElementById('td-menu');
const scrim = document.querySelector('.scrim');
const menuBtn = document.querySelector('[data-menu-open]');
const KEY = 'rsync-tenant-return';   // section to come back to after a form reloads the page

function show(name, focus) {
    if (!panels.some(p => p.dataset.panel === name)) name = 'overview';
    panels.forEach(p => p.classList.toggle('is-active', p.dataset.panel === name));
    document.body.dataset.section = name;
    tabs.forEach(a => {
        if (a.dataset.panelLink === name) a.setAttribute('aria-current', 'page');
        else a.removeAttribute('aria-current');
    });
    if (focus) document.getElementById(name)?.focus({ preventScroll: true });
}

links.forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    const name = a.dataset.panelLink;
    history.replaceState(null, '', '#' + name);
    show(name, true);
    setMenu(false);
    window.scrollTo({ top: 0 });
}));

// Sections can take focus after switching, for keyboard and screen-reader users.
panels.forEach(p => p.setAttribute('tabindex', '-1'));

// After a form is sent the page reloads (without a #hash): come back to the same section, once.
document.querySelectorAll('[data-panel] form').forEach(f => f.addEventListener('submit', () => {
    try { sessionStorage.setItem(KEY, f.closest('[data-panel]').dataset.panel); } catch (e) { /* private mode */ }
}));
let back = null;
try { back = sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) { /* private mode */ }

show(location.hash.slice(1) || back || 'overview', false);

// Slide-in menu (phones and tablets). On laptops the menu is always on screen.
const isOpen = () => side?.classList.contains('is-open');
function setMenu(open, returnFocus) {
    if (!side || !menuBtn) return;
    side.classList.toggle('is-open', open);
    scrim?.classList.toggle('is-open', open);
    document.documentElement.classList.toggle('menu-open', open);
    menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (typeof syncToggle === 'function') syncToggle();
    if (open) (side.querySelector('[aria-current="page"]') || side.querySelector('a'))?.focus();
    else if (returnFocus) menuBtn.focus();
}
menuBtn?.addEventListener('click', () => setMenu(!isOpen()));
document.querySelectorAll('[data-menu-close]').forEach(el => el.addEventListener('click', () => setMenu(false, true)));
document.addEventListener('keydown', e => {
    if (!isOpen()) return;
    if (e.key === 'Escape') { setMenu(false, true); return; }
    // Keep Tab inside the open menu.
    if (e.key === 'Tab') {
        const items = [...side.querySelectorAll('a, button')].filter(el => el.offsetParent !== null);
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
});
// The arrow button: on laptops it folds the menu into icons (and back); on phones/tablets it slides the menu away.
const toggle = document.querySelector('[data-side-toggle]');
const wide = matchMedia('(min-width: 901px)');
const root = document.documentElement;
function syncToggle() {
    if (!toggle) return;
    const folded = wide.matches && root.classList.contains('side-collapsed');
    const label = !wide.matches ? 'Close menu' : folded ? 'Expand menu' : 'Collapse menu';
    toggle.setAttribute('aria-label', label);
    toggle.title = label;
    toggle.setAttribute('aria-expanded', wide.matches ? String(!folded) : String(isOpen()));
}
toggle?.addEventListener('click', () => {
    if (!wide.matches) { setMenu(false, true); return; }
    const folded = root.classList.toggle('side-collapsed');
    try { localStorage.setItem('rsync-side-collapsed', folded ? '1' : '0'); } catch (e) { /* private mode */ }
    syncToggle();
});
syncToggle();

// Close it if the window is widened to laptop size while it is open.
wide.addEventListener('change', e => { if (e.matches) setMenu(false); syncToggle(); });

// Swipe the open menu to the left to close it (phones and tablets).
if (side) {
    let startX = 0, startY = 0, dx = 0, dragging = false, decided = false, startT = 0;
    side.addEventListener('touchstart', e => {
        if (!isOpen() || e.touches.length !== 1) return;
        startX = e.touches[0].clientX; startY = e.touches[0].clientY;
        dx = 0; dragging = true; decided = false; startT = Date.now();
    }, { passive: true });
    side.addEventListener('touchmove', e => {
        if (!dragging) return;
        const x = e.touches[0].clientX - startX, y = e.touches[0].clientY - startY;
        if (!decided) {
            if (Math.abs(x) < 8 && Math.abs(y) < 8) return;
            decided = true;
            if (Math.abs(y) > Math.abs(x)) { dragging = false; return; }   // scrolling the menu, not swiping
            side.classList.add('is-dragging');
        }
        dx = Math.min(0, x);
        side.style.transform = `translateX(${dx}px)`;
        if (scrim) scrim.style.opacity = String(1 + dx / side.offsetWidth);
    }, { passive: true });
    const end = () => {
        if (!dragging) return;
        dragging = false;
        side.classList.remove('is-dragging');
        side.style.transform = '';
        if (scrim) scrim.style.opacity = '';
        const fast = dx < -30 && Date.now() - startT < 250;
        if (dx < -side.offsetWidth * 0.3 || fast) setMenu(false, true);
    };
    side.addEventListener('touchend', end);
    side.addEventListener('touchcancel', end);
}

// Notifications box: show 3 at a time, with Newer / Older buttons.
const noticeList = document.getElementById('notice-list');
const pager = document.querySelector('.notice-pager');
if (noticeList && pager) {
    const items = [...noticeList.children];
    const per = Number(noticeList.dataset.perPage) || 3;
    const pages = Math.ceil(items.length / per);
    const prev = pager.querySelector('[data-notice-prev]');
    const next = pager.querySelector('[data-notice-next]');
    const pos = pager.querySelector('.notice-pos');
    let page = 0;
    const render = () => {
        items.forEach((li, i) => { li.hidden = Math.floor(i / per) !== page; });
        const from = page * per + 1, to = Math.min(items.length, from + per - 1);
        pos.textContent = from === to ? `${from} of ${items.length}` : `${from}–${to} of ${items.length}`;
        prev.disabled = page === 0;
        next.disabled = page === pages - 1;
    };
    prev.addEventListener('click', () => { if (page > 0) { page--; render(); } });
    next.addEventListener('click', () => { if (page < pages - 1) { page++; render(); } });
    pager.hidden = false;
    render();
}
