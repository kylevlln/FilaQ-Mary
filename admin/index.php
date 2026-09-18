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
<title>Admin Dashboard · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Admin</span>
    <a class="side-link active" href="index.php"><span class="ic"><?php echo icon('dashboard'); ?></span> Dashboard</a>
    <a class="side-link" href="users.php"><span class="ic"><?php echo icon('users'); ?></span> Users</a>
    <a class="side-link" href="settings.php"><span class="ic"><?php echo icon('settings'); ?></span> Counters &amp; Services</a>
    <a class="side-link" href="logs.php"><span class="ic"><?php echo icon('activity'); ?></span> Activity Log</a>
    <span class="side-caption">Queue</span>
    <a class="side-link" href="../staff/index.php"><span class="ic"><?php echo icon('bell'); ?></span> Queue Desk</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic"><?php echo icon('monitor'); ?></span> Live Board</a>
    <div class="side-foot">
      Signed in as <strong><?php echo e($user['username']); ?></strong><br>
      <a href="../logout.php">Sign out</a>
    </div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div><h1>Administration Overview</h1><p class="sub">Monitor the queue, approve staff, and review performance.</p></div>
      <a class="btn btn-primary" href="users.php">Review pending approvals</a>
    </div>

    <div class="stats-row stagger">
      <div class="stat-card glow-orange"><span class="stat-label">Issued today</span><span class="stat-value" id="a-issued">—</span></div>
      <div class="stat-card glow-cyan"><span class="stat-label">Served today</span><span class="stat-value" id="a-completed">—</span></div>
      <div class="stat-card glow-pink"><span class="stat-label">Still waiting</span><span class="stat-value" id="a-waiting">—</span></div>
      <div class="stat-card"><span class="stat-label">Skipped today</span><span class="stat-value" id="a-skipped">—</span></div>
      <div class="stat-card"><span class="stat-label">Registered users</span><span class="stat-value" id="a-users">—</span></div>
    </div>

    <div id="pending-banner" style="display:none;" class="mt-2"></div>

    <div class="grid-2 mt-3">
      <div class="card">
        <h2>Queue by service</h2>
        <div id="by-service"><p class="muted">Loading…</p></div>
      </div>
      <div class="card">
        <h2>Last 2 weeks</h2>
        <canvas id="chart" height="180" style="width:100%;"></canvas>
        <p class="muted" style="font-size:.78rem; margin-top:1rem;">Issued (orange) and served (cyan) per day. Rollups are generated as tickets complete.</p>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=2"></script>
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

    // pending banner
    const pb = document.getElementById('pending-banner');
    if (t.pending > 0) {
      pb.style.display = 'block';
      pb.innerHTML = `<div class="alert alert-warn">${icon('alert')} ${t.pending} staff account${t.pending === 1 ? '' : 's'} await${t.pending === 1 ? 's' : ''} your approval. <a href="users.php">Review now</a></div>`;
    }

    // by service table
    const bs = document.getElementById('by-service');
    if (d.data.by_service.length) {
      const max = Math.max(...d.data.by_service.map(s => s.cnt), 1);
      bs.innerHTML = d.data.by_service.map(s => `
        <div class="row-between" style="margin-bottom:.6rem;">
          <span style="flex:1; display:flex; align-items:center; gap:.6rem;">
            <span style="font-size:.88rem;">${escapeHtml(s.name)}</span>
            <span class="bar" style="display:block; height:8px; border-radius:6px; background:var(--grad-warm); width:${Math.round(100 * s.cnt / max)}%;"></span>
          </span>
          <strong>${s.cnt}</strong>
        </div>`).join('');
    } else {
      bs.innerHTML = '<p class="muted">No tickets yet today.</p>';
    }

    drawChart(d.data.week);
  } catch (e) {
    toast(e.message, 'error');
  }
}

function drawChart(week) {
  const el = document.getElementById('chart');
  if (!week || !week.length) { el; return; }
  const ctx = el.getContext('2d');
  const W = el.clientWidth || 300, H = 180;
  ctx.clearRect(0, 0, W, H);
  const pad = 8;
  const issued = week.map(d => Number(d.issued_count));
  const served = week.map(d => Number(d.served_count));
  const max = Math.max(...issued, ...served, 1);
  const bw = (W - pad * 2) / week.length;
  function bar(v, color) {
    const h = Math.max(2, (v / max) * (H - pad * 3));
    ctx.fillStyle = color;
    return h;
  }
  week.forEach((d, i) => {
    const x = pad + i * bw;
    const hi = bar(d.issued_count, 'rgba(232,168,124,.85)');
    const hs = bar(d.served_count, 'rgba(156,201,201,.85)');
    ctx.fillRect(x + bw * .15, H - pad * 2 - hi, bw * .3, hi);
    ctx.fillRect(x + bw * .55, H - pad * 2 - hs, bw * .3, hs);
    ctx.fillStyle = '#7a6f68';
    ctx.font = '9px Poppins, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(d.stat_date.slice(5), x + bw / 2, H - pad * 0.6);
  });
}

load();
setInterval(load, 20000);
</script>
</body>
</html>