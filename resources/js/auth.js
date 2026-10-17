// RSYNC sign-in pages.
// The old pages call togglePw() from onclick="...", so it must be on window
// (Vite keeps everything else private inside the bundle).
window.togglePw = function () {
    const p = document.getElementById('pw');
    p.type = p.type === 'password' ? 'text' : 'password';
};

// Registration: the Plan choice appears only when the role is Landlord or Sublessor.
const roleSelect = document.querySelector('select[name="role"]');
const planField = document.getElementById('plan-field');
if (roleSelect && planField) {
    const planSelect = document.getElementById('plan');
    roleSelect.addEventListener('change', () => {
        const needsPlan = roleSelect.value === 'landlord' || roleSelect.value === 'sublessor';
        planField.hidden = !needsPlan;
        planSelect.disabled = !needsPlan;   // a hidden plan is not sent with the form
        planSelect.required = needsPlan;
    });
}