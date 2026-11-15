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

// ---------- Messages under each field (register and new-password forms) ----------
// A field turns red with a short message when it is wrong; the server checks everything again.
const PW_RULES = {
    length: v => v.length >= 8,
    upper: v => /[A-Z]/.test(v),
    lower: v => /[a-z]/.test(v),
    number: v => /[0-9]/.test(v),
};

function emailProblem(v) {
    if (v === '') return 'Please enter your email.';
    if (!v.includes('@')) return 'Your email is missing the "@" sign, like name@gmail.com.';
    if (/\s/.test(v)) return 'Your email can\'t contain spaces.';
    const [user, domain = ''] = v.split('@');
    if (user === '') return 'Add your name before the "@", like name@gmail.com.';
    if (v.split('@').length > 2) return 'Your email can only have one "@" sign.';
    if (!/^[^.]+(\.[^.]+)*\.[a-z]{2,}$/i.test(domain)) return 'Check the part after the "@", like @gmail.com.';
    return '';
}

function passwordProblem(v) {
    if (v === '') return 'Please enter a password.';
    const missing = [];
    if (!PW_RULES.length(v)) missing.push('at least 8 characters');
    if (!PW_RULES.upper(v)) missing.push('an uppercase letter');
    if (!PW_RULES.lower(v)) missing.push('a lowercase letter');
    if (!PW_RULES.number(v)) missing.push('a number');
    return missing.length ? 'Your password needs ' + missing.join(', ') + '.' : '';
}

document.querySelectorAll('form[data-validate]').forEach(form => {
    const get = name => form.querySelector(`[name="${name}"]`);
    const email = get('email');
    const pw = get('password');
    const confirm = get('password_confirmation');
    const role = form.querySelector('select[name="role"]');
    const plan = form.querySelector('select[name="plan"]');
    const rules = form.querySelector('[data-pw-rules]');
    const touched = new Set();

    // Shows (or clears) the message under one field. Returns true when the field is fine.
    function mark(input, problem) {
        if (!input) return true;
        const field = input.closest('.field');
        const msg = field.querySelector(`[data-msg-for="${input.name}"]`);
        if (msg) msg.textContent = problem;
        field.classList.toggle('invalid', problem !== '');
        field.classList.toggle('valid', problem === '' && input.value !== '');
        input.setAttribute('aria-invalid', problem !== '' ? 'true' : 'false');
        return problem === '';
    }

    function updateRules() {
        if (!rules || !pw) return;
        rules.querySelectorAll('li').forEach(li => {
            const met = PW_RULES[li.dataset.rule](pw.value);
            li.classList.toggle('met', met);
            li.classList.toggle('miss', !met && touched.has('password'));
            li.querySelector('i').className = met ? 'bx bx-check-circle' : (touched.has('password') ? 'bx bx-x-circle' : 'bx bx-circle');
        });
    }

    const checks = {
        email: () => mark(email, emailProblem(email.value.trim())),
        password: () => mark(pw, passwordProblem(pw.value)),
        password_confirmation: () => mark(confirm, confirm.value === '' ? 'Please type your password again.'
            : (confirm.value !== pw.value ? 'The passwords do not match.' : '')),
        role: () => mark(role, role && role.value === '' ? 'Please choose a role.' : ''),
        plan: () => mark(plan, plan && !plan.disabled && plan.value === '' ? 'Please choose a plan.' : ''),
    };
    const inputs = { email, password: pw, password_confirmation: confirm, role, plan };

    Object.entries(inputs).forEach(([name, input]) => {
        if (!input) return;
        // Check when leaving a field, then live while fixing it (not while typing the first time).
        input.addEventListener('blur', () => {
            if (input.value !== '' || touched.has(name)) { touched.add(name); checks[name](); updateRules(); }
        });
        input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', () => {
            if (name === 'password') updateRules();
            if (touched.has(name)) checks[name]();
            if (name === 'password' && touched.has('password_confirmation')) checks.password_confirmation();
        });
    });
    updateRules();

    form.addEventListener('submit', e => {
        let firstBad = null;
        Object.entries(inputs).forEach(([name, input]) => {
            if (!input || input.closest('[hidden]')) return;
            touched.add(name);
            if (!checks[name]() && !firstBad) firstBad = input;
        });
        updateRules();
        if (firstBad) {
            e.preventDefault();
            firstBad.focus();
        }
    });
});
