/* ============================================================
   FilaQ — shared front-end helpers
   ============================================================ */
(function () {
  const root = document.querySelector('#toast-wrap') ||
    (() => { const d = document.createElement('div'); d.id = 'toast-wrap'; document.body.appendChild(d); return d; })();

  const ICONS = { success: '✓', error: '✕', info: 'i', warn: '!' };

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
    const pass = form.querySelector('[name="password"]');
    const pass2 = form.querySelector('[name="password2"]');
    if (pass && pass2 && pass.value && pass.value !== pass2.value) {
      errors[pass2.name] = 'Passwords do not match.';
    }
    return errors;
  };

  window.applyFormErrors = function (form, errors) {
    form.querySelectorAll('.input').forEach((el) => {
      const key = el.name || el.id;
      const isErr = !!errors[key];
      el.classList.toggle('is-error', isErr);
      const help = el.parentElement?.querySelector('.input-error');
      if (help) { help.textContent = errors[key] || ''; help.style.display = isErr ? 'block' : 'none'; }
    });
    return Object.keys(errors).length === 0;
  };

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