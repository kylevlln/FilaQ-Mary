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
<title>Admin Dashboard · FilaQ</title>
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
    <a class="side-link active" href="index.php"><?php echo icon('dashboard'); ?> Dashboard</a>

    <p class="side-group">Admin</p>
    <a class="side-link" href="users.php"><?php echo icon('users'); ?> Users</a>
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
          <h1 class="page-title">Today at a glance</h1>
          <p class="page-lead">Monitor the queue, approve staff, and review performance.</p>
        </div>
        <div class="page-actions">
          <a class="btn btn-primary" href="users.php">Review pending approvals</a>
        </div>
      </div>

      <div class="metrics">
        <div class="metric"><span class="metric-label">Issued today</span><span class="metric-value" id="a-issued">—</span></div>
        <div class="metric"><span class="metric-label">Served today</span><span class="metric-value" id="a-completed">—</span></div>
        <div class="metric"><span class="metric-label">Still waiting</span><span class="metric-value" id="a-waiting">—</span></div>
        <div class="metric"><span class="metric-label">Skipped today</span><span class="metric-value" id="a-skipped">—</span></div>
        <div class="metric"><span class="metric-label">Registered users</span><span class="metric-value" id="a-users">—</span></div>
      </div>

      <div id="pending-banner" style="display:none;" class="mb-2"></div>

      <div class="grid-2">
        <div class="card">
          <div class="card-head"><h2>Queue by service</h2></div>
          <div id="by-service"><div class="skeleton" style="height:120px;"></div></div>
        </div>
        <div class="card">
          <div class="card-head"><h2>Last 2 weeks</h2></div>
          <canvas id="chart" height="180" style="width:100%;"></canvas>
          <p class="muted" style="font-size:var(--fs-xs); margin-top:1rem;">Rollups are generated as tickets complete.</p>
          <div class="legend">
            <span><span class="sw acc"></span>Issued</span>
            <span><span class="sw teal"></span>Served</span>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=3"></script>
<script>
async function load() {
  try {
    const d = await api('../api/admin.php?action=stats');
    const t = d.data.today;
    document.getElementById('a-issued').textContent = t.issued;
    document.getElementById('a-completed').textContent = t.completed;
    document.getElementById('a-waiting').textContent = t.waiting;
    document.getElementById('a-skipped').textContent = t.skipped;
    document.getElementById('a-users').textContent = t.users;

    const pb = document.getElementById('pending-banner');
    if (t.pending > 0) {
      pb.style.display = 'block';
      pb.innerHTML = `<div class="alert alert-warn">${icon('alert', 18)}<span>${t.pending} staff account${t.pending === 1 ? '' : 's'} await${t.pending === 1 ? 's' : ''} your approval. <a href="users.php">Review now</a></span></div>`;
    } else {
      pb.style.display = 'none';
      pb.innerHTML = '';
    }

    const bs = document.getElementById('by-service');
    if (d.data.by_service.length) {
      const max = Math.max(...d.data.by_service.map(s => s.cnt), 1);
      bs.innerHTML = d.data.by_service.map(s => `
        <div class="bar-row">
          <div class="bar-name"><strong>${escapeHtml(s.name)}</strong><span class="cnt">${s.cnt}</span></div>
          <div class="bar-track"><div class="bar-fill" style="width:${Math.round(100 * s.cnt / max)}%;"></div></div>
        </div>`).join('');
    } else {
      bs.innerHTML = '<div class="empty-state"><p class="empty-title">No tickets yet today</p><p class="empty-sub">Ticket counts for each service will appear here as numbers are issued.</p></div>';
    }

    drawChart(d.data.week);
  } catch (e) {
    toast(e.message, 'error');
  }
}

function drawChart(week) {
  const el = document.getElementById('chart');
  if (!week || !week.length) return;
  const ctx = el.getContext('2d');
  const W = el.clientWidth || 300, H = 180;
  ctx.clearRect(0, 0, W, H);
  const pad = 8;
  const issued = week.map(d => Number(d.issued_count));
  const served = week.map(d => Number(d.served_count));
  const max = Math.max(...issued, ...served, 1);
  const bw = (W - pad * 2) / week.length;
  week.forEach((d, i) => {
    const x = pad + i * bw;
    const hi = Math.max(2, (Number(d.issued_count) / max) * (H - pad * 3));
    const hs = Math.max(2, (Number(d.served_count) / max) * (H - pad * 3));
    ctx.fillStyle = 'rgba(164,82,43,.8)';
    ctx.fillRect(x + bw * .15, H - pad * 2 - hi, bw * .3, hi);
    ctx.fillStyle = 'rgba(47,106,106,.8)';
    ctx.fillRect(x + bw * .55, H - pad * 2 - hs, bw * .3, hs);
    ctx.fillStyle = '#75706a';
    ctx.font = '9px Poppins, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(d.stat_date.slice(5), x + bw / 2, H - pad * 0.6);
  });
}

document.getElementById('nav-toggle').addEventListener('click', () => document.getElementById('app').classList.toggle('side-open'));
document.getElementById('sidebar').addEventListener('click', (e) => {
  if (e.target.closest('.side-link')) document.getElementById('app').classList.remove('side-open');
});

load();
setInterval(load, 20000);
</script>
</body>
</html>