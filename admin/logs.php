<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'ADMIN') {
    redirect('../staff/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Admin</span>
    <a class="side-link" href="index.php"><span class="ic"><?php echo icon('dashboard'); ?></span> Dashboard</a>
    <a class="side-link" href="users.php"><span class="ic"><?php echo icon('users'); ?></span> Users</a>
    <a class="side-link" href="settings.php"><span class="ic"><?php echo icon('settings'); ?></span> Counters &amp; Services</a>
    <a class="side-link active" href="logs.php"><span class="ic"><?php echo icon('activity'); ?></span> Activity Log</a>
    <span class="side-caption">Queue</span>
    <a class="side-link" href="../staff/index.php"><span class="ic"><?php echo icon('bell'); ?></span> Queue Desk</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic"><?php echo icon('monitor'); ?></span> Live Board</a>
    <div class="side-foot">Signed in as <strong><?php echo e($user['username']); ?></strong><br><a href="../logout.php">Sign out</a></div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div><h1>Activity Log</h1><p class="sub">Audit trail of every important action across the system.</p></div>
      <button class="btn btn-ghost" id="refresh-log">Refresh</button>
    </div>

    <div class="card flat">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
          <tbody id="log-body"><tr><td colspan="5"><div class="skeleton" style="height:24px;"></div></td></tr></tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=2"></script>
<script>
const ACTION_COLORS = {
  LOGIN: 'called', LOGOUT: 'called', REGISTER: 'customer',
  TICKET_ISSUED: 'waiting', TICKET_CALLED: 'called', TICKET_SERVING: 'serving',
  TICKET_COMPLETED: 'completed', TICKET_SKIPPED: 'skipped',
  USER_APPROVED: 'success', SETTINGS_UPDATED: 'warning'
};

async function load() {
  const tbody = document.getElementById('log-body');
  try {
    const d = await api('../api/admin.php?action=logs&limit=150');
    tbody.innerHTML = d.data.length ? d.data.map(l => `
      <tr>
        <td class="muted" style="white-space:nowrap; font-size:.82rem;">${new Date(l.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'medium' })}</td>
        <td><strong>${escapeHtml(l.username)}</strong></td>
        <td><span class="badge badge--${ACTION_COLORS[l.action] || 'canceled'}">${escapeHtml(l.action)}</span></td>
        <td class="muted">${escapeHtml(l.details || '')}</td>
        <td class="muted" style="font-size:.8rem;">${escapeHtml(l.ip_address || '—')}</td>
      </tr>`).join('') : '<tr><td colspan="5"><p class="empty">No activity recorded yet.</p></td></tr>';
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5"><div class="alert alert-error">${escapeHtml(e.message)}</div></td></tr>`;
  }
}
document.getElementById('refresh-log').addEventListener('click', load);
load();
setInterval(load, 20000);
</script>
</body>
</html>