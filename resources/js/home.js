// RSYNC home page (was the <script> at the bottom of index.php).
import { submitApplication } from './apply.js';

const page = document.body.dataset;

// ---- Live search box ----
const si = document.getElementById('si'), sr = document.getElementById('sr'), sc = document.getElementById('sc');
let t;
si.addEventListener('input', function () {
    sc.style.display = this.value ? 'block' : 'none';
    clearTimeout(t);
    if (!this.value.trim()) { sr.classList.remove('show'); sr.innerHTML = ''; return; }
    t = setTimeout(() => {
        sr.innerHTML = '<div class="sr-msg"><i class="bx bx-loader-alt bx-spin"></i> Searching…</div>';
        sr.classList.add('show');
        fetch(page.searchUrl + '?search=' + encodeURIComponent(si.value), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(data => {
                if (!data.length) { sr.innerHTML = '<div class="sr-msg">No rooms found.</div>'; return; }
                sr.innerHTML = data.map(r => `
                <a class="sr-item" href="${page.roomUrl}/${r.room_id}">
                    ${r.cover_image ? `<img class="sr-img" src="${page.imagesUrl}/${e(r.cover_image)}">` : `<div class="sr-img"></div>`}
                    <div><div class="sr-name">${e(r.room_name)}</div><div class="sr-price">₱${Number(r.price).toLocaleString()} / month · ${e(r.current_occupants)}/${e(r.max_occupants)} occupied</div></div>
                    <span class="sr-badge ${String(r.status).toLowerCase()}">${e(r.status)}</span>
                </a>`).join('');
            }).catch(() => { sr.innerHTML = '<div class="sr-msg">Error. Try again.</div>'; });
    }, 280);
});
sc.addEventListener('click', () => { si.value = ''; sc.style.display = 'none'; sr.classList.remove('show'); sr.innerHTML = ''; si.focus(); });
document.addEventListener('click', ev => { if (!si.contains(ev.target) && !sr.contains(ev.target)) sr.classList.remove('show'); });
si.addEventListener('focus', () => { if (si.value.trim() && sr.innerHTML) sr.classList.add('show'); });
document.addEventListener('keydown', ev => { if (ev.key === 'Escape') sr.classList.remove('show'); });
function e(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

// ---- Buttons used with onclick="..." in the page (so they must be on window) ----
window.selectPlan = function (plan) {
    window.location.href = page.registerUrl + '?role=landlord&plan=' + encodeURIComponent(plan);
};

window.applyForRoom = function (roomId, roomName) {
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
    submitApplication(roomId, roomName);
};

// Prices exactly as in the old script (note: monthly Professional is 299 here but 399 in the page).
const mPrices = { s: 0, p: 399, e: 699 };
const aPrices = { s: 0, p: 319, e: 559 };
window.setBilling = function (mode) {
    document.getElementById('btn-m').classList.toggle('active', mode === 'monthly');
    document.getElementById('btn-a').classList.toggle('active', mode === 'annual');
    const d = mode === 'annual' ? aPrices : mPrices;
    document.getElementById('p-s').textContent = d.s === 0 ? '0' : d.s.toLocaleString();
    document.getElementById('p-p').textContent = d.p.toLocaleString();
    document.getElementById('p-e').textContent = d.e.toLocaleString();
    document.getElementById('o-p').innerHTML = mode === 'annual' ? '₱' + mPrices.p.toLocaleString() + '/mo billed monthly' : '&nbsp;';
    document.getElementById('o-e').innerHTML = mode === 'annual' ? '₱' + mPrices.e.toLocaleString() + '/mo billed monthly' : '&nbsp;';
};