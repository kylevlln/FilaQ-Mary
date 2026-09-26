/* ============================================================
   FilaQ — shared front-end helpers
   ============================================================ */
(function () {
  const root = document.querySelector('#toast-wrap') ||
    (() => { const d = document.createElement('div'); d.id = 'toast-wrap'; document.body.appendChild(d); return d; })();

  // Inline SVG icon set (Lucide/Feather-style, single stroke). Mirrors
  // icon() in includes/icons.php so client-rendered markup stays offline-safe.
  const SVGICONS = {
    dashboard: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    settings: '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="2" y1="14" x2="6" y2="14"/><line x1="10" y1="8" x2="14" y2="8"/><line x1="18" y1="16" x2="22" y2="16"/>',
    activity: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
    bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    monitor: '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
    ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
    pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    chart: '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
    check: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    alert: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'eye-off': '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    menu: '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>'
  };

  window.icon = function (name, size = 20) {
    const body = SVGICONS[name] || '';
    return `<svg class="svg-icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${body}</svg>`;
  };

  window.toast = function (message, type = 'info', ms = 4200) {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="t-dot"></span><span>${escapeHtml(message)}</span>`;
    root.appendChild(el);
    setTimeout(() => {
      el.classList.add('leaving');
      setTimeout(() => el.remove(), 320);
    }, ms);
    return el;
  };

  window.escapeHtml = function (v) {
    return String(v ?? '')
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;').replaceAll("'", '&#39;');
  };

  // Fetch wrapper with JSON + friendly error handling
  window.api = async function (url, options = {}) {
    const opts = Object.assign({
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
    }, options);
    if (opts.body && typeof opts.body !== 'string') opts.body = JSON.stringify(opts.body);

    const res = await fetch(url, opts).catch(() => { throw new Error('Network problem — is the server running?'); });
    let data = {};
    try { data = await res.json(); } catch (e) { data = {}; }
    if (!res.ok) throw new Error(data.message || `Request failed (${res.status})`);
    return data;
  };

  // Form validation helper: returns error map {name: message}
  window.validateForm = function (form) {
    const errors = {};
    form.querySelectorAll('[required]').forEach((el) => {
      if (!el.value.trim()) {
        errors[el.name || el.id] = 'This field is required.';
      }
    });
    const email = form.querySelector('[type="email"]');
    if (email && email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      errors[email.name] = 'Enter a valid email address.';
    }
    if (form.hasAttribute('data-pw-rules')) {
      const pass = form.querySelector('[name="password"]');
      const pass2 = form.querySelector('[name="password2"]');
      if (pass && pass.value && !pass2.value) {
        errors[pass2.name] = 'Please repeat the password.';
      }
      if (pass && pass.value) {
        const reason = window.weakPassword(pass.value);
        if (reason) errors[pass.name] = reason;
        else if (pass2 && pass.value !== pass2.value) errors[pass2.name] = 'Passwords do not match.';
      }
    } else {
      const pass = form.querySelector('[name="password"]');
      const pass2 = form.querySelector('[name="password2"]');
      if (pass && pass2 && pass.value && pass.value !== pass2.value) {
        errors[pass2.name] = 'Passwords do not match.';
      }
    }
    return errors;
  };

  // Returns a message when a password is too weak, else null. Mirrors the
  // server-side policy in validate_password_strength() (includes/queue.php).
  window.weakPassword = function (value) {
    if (!value) return 'Choose a password.';
    if (value.length < 8) return 'Password must be at least 8 characters.';
    if (!/[a-z]/.test(value)) return 'Add at least one lowercase letter.';
    if (!/[A-Z]/.test(value)) return 'Add at least one uppercase letter.';
    if (!/\d/.test(value)) return 'Add at least one number.';
    if (!/[^A-Za-z0-9]/.test(value)) return 'Add at least one special character (e.g. ! @ # $).';
    return null;
  };

  window.applyFormErrors = function (form, errors) {
    form.querySelectorAll('.input').forEach((el) => {
      const key = el.name || el.id;
      const isErr = !!errors[key];
      el.classList.toggle('is-error', isErr);
      el.closest('.field')?.classList.toggle('has-error', isErr);
      const help = el.parentElement?.querySelector('.input-error');
      if (help) { help.textContent = errors[key] || ''; help.style.display = isErr ? 'block' : 'none'; }
    });
    return Object.keys(errors).length === 0;
  };

  // Live password-requirements checklist: a form marked data-pw-rules whose
  // password field has a sibling `ul.pw-rules[data-pw-list]` of li[data-pw].
  window.bindPasswordRules = function (scope) {
    const root = scope || document;
    root.querySelectorAll('[data-pw-rules]').forEach((form) => {
      const pass = form.querySelector('[name="password"]');
      const pass2 = form.querySelector('[name="password2"]');
      const list = form.querySelector('[data-pw-list]');
      if (!pass || !list) return;
      const refresh = () => {
        const v = pass.value;
        const flags = {
          len: v.length >= 8,
          upper: /[A-Z]/.test(v),
          lower: /[a-z]/.test(v),
          digit: /\d/.test(v),
          special: /[^A-Za-z0-9]/.test(v),
          match: pass2 ? (v !== '' && v === pass2.value) : v.length >= 8,
        };
        list.querySelectorAll('li[data-pw]').forEach((li) => {
          li.classList.toggle('ok', !!flags[li.dataset.pw]);
        });
        // Keep the confirm field in sync: match can't be true while it's empty.
        if (pass2 && pass2.value) list.querySelector('li[data-pw="match"]')?.classList.toggle('ok', v !== '' && v === pass2.value);
        else list.querySelector('li[data-pw="match"]')?.classList.remove('ok');
      };
      pass.addEventListener('input', refresh);
      if (pass2) pass2.addEventListener('input', refresh);
      refresh();
    });
  };

  // Show/hide password toggles — any [data-pw-for] button toggles the named input.
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-pw-for]');
    if (!btn) return;
    const input = document.getElementById(btn.dataset.pwFor);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    const icon = btn.querySelector('.svg-icon');
    if (icon) btn.innerHTML = window.icon(show ? 'eye-off' : 'eye', 18);
    input.focus();
    try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) { /* not text-like — ignore */ }
  });

  window.highlightFlash = function () {
    const a = document.querySelector('.alert');
    if (a) setTimeout(() => a.animate([{ transform: 'translateX(-8px)' }, { transform: 'translateX(8px)' }, { transform: 'translateX(0)' }], { duration: 500, iterations: 3 }), 400);
  };

  // Simple Web-Audio "ding" when a ticket is called (graceful on failure).
  let audioCtx = null;
  window.playDing = function () {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      const stop = audioCtx.currentTime;
      const o = audioCtx.createOscillator();
      const g = audioCtx.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(880, stop);
      o.frequency.exponentialRampToValueAtTime(1174, stop + 0.18);
      g.gain.setValueAtTime(0.25, stop);
      g.gain.exponentialRampToValueAtTime(0.001, stop + 0.5);
      o.connect(g).connect(audioCtx.destination);
      o.start(stop); o.stop(stop + 0.55);
    } catch (e) { /* audio unavailable — ignore */ }
  };

  document.addEventListener('DOMContentLoaded', highlightFlash);
})();