// RSYNC room page (was the <script> at the bottom of room_detail.php): photo gallery + Apply.
import { submitApplication } from './apply.js';
import { initRoomMap } from './maps.js';

const allImages = JSON.parse(document.body.dataset.images || '[]');
let lbIndex = 0;

window.switchImg = function (thumb, src, idx) {
    document.getElementById('mainImgEl').src = src;
    document.querySelectorAll('.gallery-thumbs img').forEach(i => i.classList.remove('active'));
    thumb.classList.add('active');
    lbIndex = idx;
};

window.openLightbox = function (idx) {
    lbIndex = idx;
    document.getElementById('lbImg').src = allImages[lbIndex];
    document.getElementById('lightbox').classList.add('show');
};

window.closeLightbox = function () { document.getElementById('lightbox').classList.remove('show'); };

window.lbNav = function (dir) {
    lbIndex = (lbIndex + dir + allImages.length) % allImages.length;
    document.getElementById('lbImg').src = allImages[lbIndex];
};

document.getElementById('lightbox').addEventListener('click', function (e) {
    if (e.target === this) window.closeLightbox();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') window.closeLightbox();
    if (e.key === 'ArrowLeft') window.lbNav(-1);
    if (e.key === 'ArrowRight') window.lbNav(1);
});

window.applyForRoom = submitApplication;
// ---- Map with this room's pin (only on rooms that have one) ----
const roomMapEl = document.getElementById('room-map');
if (roomMapEl) initRoomMap(roomMapEl, Number(roomMapEl.dataset.lat), Number(roomMapEl.dataset.lng), roomMapEl.dataset.name);
