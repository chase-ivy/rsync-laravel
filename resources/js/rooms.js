// RSYNC rooms page (was the <script> at the bottom of rooms.php): filter the cards while typing.
document.getElementById('q').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.card').forEach(c => {
        c.style.display = (c.dataset.n + c.dataset.s + c.dataset.p).includes(q) ? '' : 'none';
    });
});