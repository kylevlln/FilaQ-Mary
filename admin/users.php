<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';

$user = require_login();
if ($user['role'] !== 'ADMIN') {
    redirect('../staff/index.php');
}
$counters = fetch_all('SELECT * FROM counters WHERE is_active = 1 ORDER BY name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — FilaQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Admin</span>
    <a class="side-link" href="index.php"><span class="ic">📊</span> Dashboard</a>
    <a class="side-link active" href="users.php"><span class="ic">👥</span> Users</a>
    <a class="side-link" href="settings.php"><span class="ic">⚙️</span> Counters &amp; Services</a>
    <a class="side-link" href="logs.php"><span class="ic">🕵️</span> Activity Log</a>
    <span class="side-caption">Queue</span>
    <a class="side-link" href="../staff/index.php"><span class="ic">🔔</span> Queue Desk</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic">🖥️</span> Live Board</a>
    <div class="side-foot">Signed in as <strong><?php echo e($user['username']); ?></strong><br><a href="../logout.php">Sign out</a></div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div><h1>User Management</h1><p class="sub">Approve staff accounts, assign counters, and manage access.</p></div>
    </div>

    <div id="users-wrap"></div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
<script>
const counters = <?php echo json_encode($counters); ?>;

function counterName(id) {
  const c = counters.find(c => c.id === id);
  return c ? c.name : '—';
}

async function load() {
  const w = document.getElementById('users-wrap');
  w.innerHTML = '<div class="skeleton" style="height:260px; border-radius:var(--r-md);"></div>';
  try {
    const d = await api('../api/admin.php?action=users');
    const rows = d.data.map(u => `
      <tr>
        <td><strong>${escapeHtml(u.full_name)}</strong><br><span class="muted" style="font-size:.78rem;">@${escapeHtml(u.username)}</span></td>
        <td>${escapeHtml(u.email)}<br><span class="muted" style="font-size:.78rem;">${u.phone ? escapeHtml(u.phone) : 'no phone'}</span></td>
        <td><span class="badge badge--${u.role.toLowerCase()}">${u.role}</span></td>
        <td><span class="badge badge--${u.status.toLowerCase()} ${u.status === 'PENDING' ? 'badge-pulse' : ''}">${u.status}</span></td>
        <td class="muted" style="font-size:.8rem;">${counterName(u.counter_id)}<br>${u.last_login ? 'last: ' + new Date(u.last_login).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : 'never signed in'}</td>
        <td>
          <div class="row" style="gap:.4rem; flex-wrap:wrap;">
            ${u.role === 'STAFF' && u.status !== 'ACTIVE' ? `<button class="btn btn-success btn-sm go-approve" data-id="${u.id}">Approve</button>` : ''}
            ${u.role === 'CUSTOMER' && u.status !== 'ACTIVE' ? `<button class="btn btn-success btn-sm go-approve" data-id="${u.id}">Activate</button>` : ''}
            ${u.role !== 'ADMIN' && u.status === 'ACTIVE' ? `<button class="btn btn-warning btn-sm go-suspend" data-id="${u.id}">Suspend</button>` : ''}
            ${u.role !== 'ADMIN' && u.status === 'SUSPENDED' ? `<button class="btn btn-cool btn-sm go-reactivate" data-id="${u.id}">Reactivate</button>` : ''}
            ${u.role !== 'ADMIN' ? `<button class="btn btn-danger btn-sm go-delete" data-id="${u.id}" data-name="${escapeHtml(u.username)}">Delete</button>` : '<span class="muted" style="font-size:.78rem;">you</span>'}
          </div>
        </td>
      </tr>`).join('');

    w.innerHTML = `
      <div class="card flat">
        <div class="row-between mb-2">
          <h2 style="margin:0;">All accounts</h2>
          <span class="muted">${d.data.length} total</span>
        </div>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Name</th><th>Contact</th><th>Role</th><th>Status</th><th>Counter / last login</th><th>Actions</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>`;
  } catch (e) {
    w.innerHTML = `<div class="alert alert-error">${escapeHtml(e.message)}</div>`;
  }
}

document.getElementById('users-wrap').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-id]');
  if (!btn) return;
  const id = btn.dataset.id;

  if (btn.classList.contains('go-approve')) {
    let role = btn.classList.contains('go-approve') && btn.textContent.includes('Approve') ? 'STAFF' : 'CUSTOMER';
    // determine from the row badge
    const row = btn.closest('tr');
    const roleBadge = row.querySelector('.badge');
    role = roleBadge.textContent.trim().toUpperCase() === 'STAFF' ? 'STAFF' : 'CUSTOMER';
    const payload = { id: id, role: role };
    if (role === 'STAFF') {
      const pick = prompt('Assign this staff member to which counter?\n' + counters.map(c => c.id + ': ' + c.name).join('\n'));
      if (pick === null) return;
      if (counters.some(c => c.id === Number(pick))) payload.counter_id = Number(pick);
      else { toast('Invalid counter number — cancelled.', 'warn'); return; }
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
  }
  if (btn.classList.contains('go-delete')) {
    if (!confirm(`Delete @${btn.dataset.name} permanently? This cannot be undone.`)) return;
    try { const d = await api('../api/admin.php?action=delete-user', { method: 'POST', body: { id } }); toast(d.message, 'success'); load(); }
    catch (err) { toast(err.message, 'error'); }
  }
});

load();
setInterval(load, 15000);
</script>
</body>
</html>