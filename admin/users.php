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
<link rel="stylesheet" href="../assets/css/style.css?v=4">
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

<script src="../assets/js/main.js?v=3"></script>
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

document.getElementById('users-wrap').addEventListener('click', async (e) => {
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