// RSYNC rooms page (was the <script> at the bottom of rooms.php): filter the cards while typing.
// Matches the room name, the status or the price; shows a message when nothing matches.
const q = document.getElementById('q');
const cards = document.querySelectorAll('.card');
const grid = document.querySelector('.grid');

const none = document.createElement('div');
none.className = 'empty';
none.style.display = 'none';
grid.appendChild(none);

q.addEventListener('input', function () {
    const text = this.value.trim().toLowerCase();
    let shown = 0;
    cards.forEach(c => {
        const hit = text === '' || [c.dataset.n, c.dataset.s, c.dataset.p].some(v => (v || '').includes(text));
        c.style.display = hit ? '' : 'none';
        if (hit) shown++;
    });
    none.textContent = 'No rooms match "' + this.value.trim() + '".';
    none.style.display = cards.length && shown === 0 ? '' : 'none';
});