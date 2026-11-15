// RSYNC home page (was the <script> at the bottom of index.php): the landlord plans.
import './browse.js';   // filters, room cards and the map (shared with /rooms)

const page = document.body.dataset;

// ---- Buttons used with onclick="..." in the page (so they must be on window) ----
window.selectPlan = function (plan) {
    window.location.href = page.registerUrl + '?role=landlord&plan=' + encodeURIComponent(plan);
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
