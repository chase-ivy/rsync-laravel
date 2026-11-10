// Admin Tenants page: picking a contract fills in its current start and end dates.
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('c-contract');
    if (!select) return;

    select.addEventListener('change', () => {
        const opt = select.selectedOptions[0];
        if (!opt || !opt.value) return;
        document.getElementById('c-start').value = opt.dataset.start;
        document.getElementById('c-end').value = opt.dataset.end;
    });
});