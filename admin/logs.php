<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'ADMIN') {
    redirect('../staff/index.php');
}
$initial = strtoupper(substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log · FilaQ</title>
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
    <a class="side-link" href="users.php"><?php echo icon('users'); ?> Users</a>
    <a class="side-link" href="settings.php"><?php echo icon('settings'); ?> Counters &amp; services</a>
    <a class="side-link active" href="logs.php"><?php echo icon('activity'); ?> Activity log</a>

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
          <h1 class="page-title">Activity log</h1>
          <p class="page-lead">An audit trail of every important action across the system.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-ghost" id="refresh-log"><?php echo icon('activity', 17); ?> Refresh</button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">Recent activity</h2>
            <p class="panel-desc">Showing the latest 150 events.</p>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody id="log-body">
              <tr><td colspan="5"><div class="skeleton" style="height:24px;"></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=3"></script>
<script>
const ACTION_COLORS = {
  LOGIN: 'called', LOGOUT: 'called', REGISTER: 'customer',
  TICKET_ISSUED: 'waiting', TICKET_CALLED: 'called', TICKET_SERVING: 'serving',
  TICKET_COMPLETED: 'completed', TICKET_SKIPPED: 'skipped',
  USER_APPROVED: 'green', SETTINGS_UPDATED: 'warning'
};

async function load() {
  const tbody = document.getElementById('log-body');
  try {
    const d = await api('../api/admin.php?action=logs&limit=150');
    tbody.innerHTML = d.data.length ? d.data.map(l => `
      <tr>
        <td class="muted" style="white-space:nowrap; font-size:var(--fs-xs);">${new Date(l.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'medium' })}</td>
        <td><strong>${escapeHtml(l.username)}</strong></td>
        <td><span class="badge badge--${ACTION_COLORS[l.action] || 'cancelled'}">${escapeHtml(l.action)}</span></td>
        <td class="muted">${escapeHtml(l.details || '')}</td>
        <td class="muted" style="font-size:var(--fs-xs); font-variant-numeric:tabular-nums;">${escapeHtml(l.ip_address || '—')}</td>
      </tr>`).join('')
      : '<tr><td colspan="5"><div class="empty-state"><p class="empty-title">No activity recorded yet</p><p class="empty-sub">Actions such as sign-ins, ticket calls, and approvals will appear here.</p></div></td></tr>';
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5"><div class="alert alert-error" role="alert">${escapeHtml(e.message)}</div></td></tr>`;
  }
}

document.getElementById('refresh-log').addEventListener('click', load);
document.getElementById('nav-toggle').addEventListener('click', () => document.getElementById('app').classList.toggle('side-open'));
document.getElementById('sidebar').addEventListener('click', (e) => {
  if (e.target.closest('.side-link')) document.getElementById('app').classList.remove('side-open');
});

load();
setInterval(load, 20000);
</script>
</body>
</html>