<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'ADMIN') {
    redirect('../staff/index.php');
}
$counters = fetch_all('SELECT * FROM counters WHERE is_active = 1 ORDER BY name');
$initial = strtoupper(substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=5">
</head>
<body>
<div class="mobile-bar">
  <button class="btn btn-ico" id="nav-toggle" type="button" aria-label="Open menu"><?php echo icon('menu', 18); ?></button>
  <a class="brand" href="index.php"><span class="dot"></span>FilaQ · Admin</a>
</div>

<div class="app" id="app">
  <aside class="app-side" id="sidebar">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ<span class="v-tag">Admin</span></a>

    <p class="side-group">Main</p>
    <a class="side-link" href="index.php"><?php echo icon('dashboard'); ?> Dashboard</a>

    <p class="side-group">Admin</p>
    <a class="side-link active" href="users.php"><?php echo icon('users'); ?> Users</a>
    <a class="side-link" href="settings.php"><?php echo icon('settings'); ?> Counters &amp; services</a>
    <a class="side-link" href="logs.php"><?php echo icon('activity'); ?> Activity log</a>

    <p class="side-group">Queue</p>
    <a class="side-link" href="../staff/index.php"><?php echo icon('bell'); ?> Queue desk</a>
    <a class="side-link" href="../display.php" target="_blank"><?php echo icon('monitor'); ?> Live board</a>

    <div class="side-user">
      <span class="avatar"><?php echo e($initial); ?></span>
      <span class="who"><span class="name"><?php echo e($user['full_name']); ?></span><span class="role">Admin</span></span>
      <a class="out" href="../logout.php" aria-label="Sign out"><?php echo icon('logout', 17); ?></a>
    </div>
  </aside>

  <main class="main">
    <div class="main-inner">
      <div class="page-head">
        <div>
          <p class="page-kicker">Administration</p>
          <h1 class="page-title">User management</h1>
          <p class="page-lead">Approve staff accounts, assign counters, and manage access.</p>
        </div>
      </div>

      <div id="users-wrap"></div>
    </div>
  </main>
</div>

<div id="drawer-root"></div>

<script src="../assets/js/main.js?v=4"></script>
<script>
const counters = <?php echo json_encode($counters); ?>;

function counterName(id) {
  const c = counters.find(c => c.id === id);
  return c ? c.name : '—';
}

function lastSeen(iso) {
  return iso ? new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : 'never signed in';
}

async function load() {
  const w = document.getElementById('users-wrap');
  w.innerHTML = '<div class="panel"><div class="skeleton" style="height:280px; border-radius:var(--r-md);"></div></div>';
  try {
    const d = await api('../api/admin.php?action=users');
    const rows = d.data.map(u => `
      <tr>
        <td><strong>${escapeHtml(u.full_name)}</strong><span class="cell-sub">@${escapeHtml(u.username)}</span></td>
        <td>${escapeHtml(u.email)}<span class="cell-sub">${u.phone ? escapeHtml(u.phone) : 'no phone'}</span></td>
        <td><span class="badge badge--${u.role.toLowerCase()}">${u.role}</span></td>
        <td><span class="badge badge--${u.status.toLowerCase()} ${u.status === 'PENDING' ? 'badge-pulse' : ''}">${u.status}</span></td>
        <td>${escapeHtml(counterName(u.counter_id))}<span class="cell-sub">${escapeHtml(lastSeen(u.last_login))}</span></td>
        <td>
          <div class="row" style="gap:.4rem; flex-wrap:wrap;">
            ${u.role === 'STAFF' && u.status !== 'ACTIVE' ? `<button class="btn btn-success btn-sm go-approve" data-id="${u.id}">Approve</button>` : ''}
            ${u.role === 'CUSTOMER' && u.status !== 'ACTIVE' ? `<button class="btn btn-success btn-sm go-approve" data-id="${u.id}">Activate</button>` : ''}
            ${u.role !== 'ADMIN' && u.status === 'ACTIVE' ? `<button class="btn btn-warning btn-sm go-suspend" data-id="${u.id}">Suspend</button>` : ''}
            ${u.role !== 'ADMIN' && u.status === 'SUSPENDED' ? `<button class="btn btn-cool btn-sm go-reactivate" data-id="${u.id}">Reactivate</button>` : ''}
            ${u.role !== 'ADMIN' ? `<button class="btn btn-danger btn-sm go-delete" data-id="${u.id}" data-name="${escapeHtml(u.username)}">Delete</button>` : '<span class="muted" style="font-size:var(--fs-xs);">you</span>'}
          </div>
        </td>
      </tr>`).join('');

    w.innerHTML = `
      <div class="panel">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">All accounts</h2>
            <p class="panel-desc">${d.data.length} total · pending staff appear in amber</p>
          </div>
          <button class="btn btn-primary btn-sm go-add-user" type="button">${icon('users', 16)} Add user</button>
        </div>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>Status</th><th>Counter / last login</th><th>Actions</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>`;
  } catch (e) {
    w.innerHTML = `<div class="alert alert-error" role="alert">${escapeHtml(e.message)}</div>`;
  }
}

const drawerRoot = document.getElementById('drawer-root');

function openAddUser() {
  const counterOpts = counters.length
    ? `<div class="field" id="nu-counter-field" hidden>
         <label for="nu-counter">Counter assignment</label>
         <select class="input" id="nu-counter" name="counter_id">
           <option value="">— none —</option>
           ${counters.map(c => `<option value="${c.id}">${escapeHtml(c.name)}${c.location ? ' · ' + escapeHtml(c.location) : ''}</option>`).join('')}
         </select>
         <small>Assigned counters appear to their staff on the queue desk.</small>
       </div>`
    : '';

  drawerRoot.innerHTML = `
    <div class="drawer-scrim" id="drawer-scrim">
      <div class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
        <div class="drawer-head">
          <h3 id="drawer-title">Add a new account</h3>
          <button class="btn btn-ico btn-ghost btn-sm" type="button" aria-label="Close">${icon('x', 16)}</button>
        </div>
        <form id="add-user-form" novalidate data-pw-rules>
          <div class="field">
            <label for="nu-name">Full name</label>
            <input class="input" type="text" id="nu-name" name="full_name" required maxlength="120" autocomplete="off">
            <span class="input-error" role="alert"></span>
          </div>
          <div class="field-grid">
            <div class="field">
              <label for="nu-user">Username</label>
              <input class="input" type="text" id="nu-user" name="username" required maxlength="30" autocomplete="off">
              <small>3-30 letters, numbers, dots or underscores.</small>
              <span class="input-error" role="alert"></span>
            </div>
            <div class="field">
              <label for="nu-phone">Phone <span class="opt">(optional)</span></label>
              <input class="input" type="text" id="nu-phone" name="phone" maxlength="30" autocomplete="off">
              <span class="input-error" role="alert"></span>
            </div>
          </div>
          <div class="field">
            <label for="nu-email">Email</label>
            <input class="input" type="email" id="nu-email" name="email" required maxlength="160" autocomplete="off">
            <span class="input-error" role="alert"></span>
          </div>
          <div class="field">
            <label for="nu-role">Role</label>
            <select class="input" id="nu-role" name="role">
              <option value="CUSTOMER">Customer / Student</option>
              <option value="STAFF">Staff / Personnel</option>
            </select>
            <small>Accounts created here are active immediately. Passwords are stored hashed.</small>
          </div>
          ${counterOpts}
          <div class="field-grid">
            <div class="field">
              <label for="nu-pass">Password</label>
              <div class="pw-wrap">
                <input class="input" type="password" id="nu-pass" name="password" required autocomplete="new-password">
                <button class="pw-toggle" type="button" data-pw-for="nu-pass" aria-label="Show password" aria-pressed="false" aria-controls="nu-pass">${icon('eye')}</button>
              </div>
              <ul class="pw-rules" data-pw-list>
                <li data-pw="len">8+ characters</li>
                <li data-pw="upper">Uppercase letter</li>
                <li data-pw="lower">Lowercase letter</li>
                <li data-pw="digit">A number</li>
                <li data-pw="special">A special character</li>
                <li data-pw="match">Passwords match</li>
              </ul>
              <span class="input-error" role="alert"></span>
            </div>
            <div class="field">
              <label for="nu-pass2">Confirm password</label>
              <div class="pw-wrap">
                <input class="input" type="password" id="nu-pass2" name="password2" required autocomplete="new-password">
                <button class="pw-toggle" type="button" data-pw-for="nu-pass2" aria-label="Show password" aria-pressed="false" aria-controls="nu-pass2">${icon('eye')}</button>
              </div>
              <span class="input-error" role="alert"></span>
            </div>
          </div>
          <div class="drawer-foot">
            <button class="btn btn-ghost" type="button" data-close>Cancel</button>
            <button class="btn btn-primary" type="submit">Create account</button>
          </div>
        </form>
      </div>
    </div>`;

  const close = () => { drawerRoot.innerHTML = ''; };
  drawerRoot.querySelector('#drawer-scrim').addEventListener('mousedown', (e) => { if (e.target.id === 'drawer-scrim') close(); });
  drawerRoot.querySelector('.drawer-head .btn-ico').addEventListener('click', close);
  drawerRoot.querySelector('[data-close]').addEventListener('click', close);

  const roleSel = drawerRoot.querySelector('#nu-role');
  const counterField = drawerRoot.querySelector('#nu-counter-field');
  if (roleSel && counterField) {
    const sync = () => { counterField.hidden = roleSel.value !== 'STAFF'; };
    roleSel.addEventListener('change', sync);
    sync();
  }

  bindPasswordRules(drawerRoot.querySelector('#add-user-form'));

  const form = drawerRoot.querySelector('#add-user-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = form.querySelector('[type="submit"]');
    const errs = validateForm(form);
    if (!applyFormErrors(form, errs)) {
      toast('Please fix the highlighted fields.', 'warn');
      return;
    }
    const body = {
      full_name: form.full_name.value.trim(),
      username: form.username.value.trim(),
      email: form.email.value.trim(),
      phone: form.phone.value.trim(),
      role: form.role.value,
      counter_id: form.counter_id ? Number(form.counter_id.value) || null : null,
      password: form.password.value,
      password2: form.password2.value,
    };
    submitBtn.setAttribute('data-loading', '');
    try {
      const d = await api('../api/admin.php?action=create-user', { method: 'POST', body });
      toast(d.message, 'success');
      close();
      load();
    } catch (err) {
      submitBtn.removeAttribute('data-loading');
      toast(err.message, 'error');
    }
  });

  setTimeout(() => form.full_name.focus(), 60);
}

document.getElementById('users-wrap').addEventListener('click', async (e) => {
  if (e.target.closest('.go-add-user')) {
    openAddUser();
    return;
  }
  const btn = e.target.closest('button[data-id]');
  if (!btn) return;
  const id = btn.dataset.id;

  if (btn.classList.contains('go-approve')) {
    const row = btn.closest('tr');
    const role = (row.querySelector('.badge').textContent.trim().toUpperCase() === 'STAFF') ? 'STAFF' : 'CUSTOMER';
    const payload = { id: id, role: role };
    if (role === 'STAFF') {
      const pick = prompt('Assign this staff member to which counter?\n' + counters.map(c => c.id + ': ' + c.name).join('\n'));
      if (pick === null) return;
      if (counters.some(c => c.id === Number(pick))) payload.counter_id = Number(pick);
      else { toast('Invalid counter number. Nothing was changed.', 'warn'); return; }
    }
    try {
      const d = await api('../api/admin.php?action=approve-user', { method: 'POST', body: payload });
      toast(d.message, 'success');
      load();
    } catch (err) { toast(err.message, 'error'); }
    return;
  }

  if (btn.classList.contains('go-suspend')) {
    if (!confirm('Suspend this account? They will be unable to sign in.')) return;
    try { const d = await api('../api/admin.php?action=suspend-user', { method: 'POST', body: { id } }); toast(d.message, 'success'); load(); }
    catch (err) { toast(err.message, 'error'); }
    return;
  }
  if (btn.classList.contains('go-reactivate')) {
    try { const d = await api('../api/admin.php?action=reactivate-user', { method: 'POST', body: { id } }); toast(d.message, 'success'); load(); }
    catch (err) { toast(err.message, 'error'); }
    return;
  }
  if (btn.classList.contains('go-delete')) {
    if (!confirm(`Delete @${btn.dataset.name} permanently? This cannot be undone.`)) return;
    try { const d = await api('../api/admin.php?action=delete-user', { method: 'POST', body: { id } }); toast(d.message, 'success'); load(); }
    catch (err) { toast(err.message, 'error'); }
  }
});

document.getElementById('nav-toggle').addEventListener('click', () => document.getElementById('app').classList.toggle('side-open'));
document.getElementById('sidebar').addEventListener('click', (e) => {
  if (e.target.closest('.side-link')) document.getElementById('app').classList.remove('side-open');
});

load();
setInterval(load, 15000);
</script>
</body>
</html>