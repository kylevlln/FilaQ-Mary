<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/queue.php';
$announcement = setting('announcement', '');
$orgName = setting('org_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Board · <?php echo e($orgName); ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body class="display-body">
<div class="display-page">
  <div class="display-head">
    <div class="org"><?php echo e($orgName); ?> <span class="muted">· FilaQ</span></div>
    <div class="clock" id="clock">--:--</div>
  </div>

  <div class="display-stage">
    <div class="now-serving">
      <p class="now-label">Now serving</p>
      <div class="now-code" id="now-code">—</div>
      <div class="now-where">
        <div class="svc" id="now-service">Waiting for the next call</div>
        <div class="cnt" id="now-where"></div>
      </div>
      <?php if ($announcement): ?>
        <p class="cnt" style="margin-top:1.6rem; color:var(--warn);"><?php echo e($announcement); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="display-sub">
    <p class="sub-label">Coming up next</p>
    <div class="display-upcoming" id="upcoming">
      <div class="skeleton" style="height:86px; border-radius:var(--r-md);"></div>
      <div class="skeleton" style="height:86px; border-radius:var(--r-md);"></div>
      <div class="skeleton" style="height:86px; border-radius:var(--r-md);"></div>
      <div class="skeleton" style="height:86px; border-radius:var(--r-md);"></div>
    </div>
  </div>

  <div class="display-foot">Waiting for a call · updates automatically</div>
</div>

<script src="assets/js/main.js?v=3"></script>
<script>
function pad(n) { return String(n).padStart(2, '0'); }
function tickClock() {
  const d = new Date();
  document.getElementById('clock').textContent = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

let lastBoardHash = '';
function boardHash(d) {
  const now = d.now;
  return (now && now.ticket_code || '—') + '|' +
    (now && now.counter_name || '') + '|' +
    (d.upcoming || []).map(u => u.ticket_code + ':' + (u.service_name || '')).join();
}

async function refresh() {
  try {
    const res = await api('api/queue.php?action=live');
    const d = res.data;
    const newHash = boardHash(d);
    const changed = newHash !== lastBoardHash;
    lastBoardHash = newHash;

    const now = d.now;
    const nowEl = document.getElementById('now-code');
    const svcEl = document.getElementById('now-service');
    const whereEl = document.getElementById('now-where');
    if (now && now.ticket_code) {
      if (changed) {
        nowEl.style.animation = 'none';
        void nowEl.offsetWidth;
        nowEl.style.animation = '';
      }
      nowEl.textContent = now.ticket_code;
      svcEl.textContent = now.service_name || '';
      whereEl.textContent =
        (now.counter_name ? now.counter_name : 'Counter') + (now.status === 'SERVING' ? ' · serving now' : ' · please proceed');
    } else {
      nowEl.textContent = '—';
      svcEl.textContent = 'Waiting for the next call';
      whereEl.textContent = document.querySelector('#upcoming .skeleton') ? '' : '';
    }

    const up = document.getElementById('upcoming');
    up.innerHTML = (d.upcoming || []).map(t => `
      <div class="upcoming-card">
        <div class="n">${escapeHtml(t.ticket_code)}</div>
        <div class="sv">${escapeHtml(t.service_name)}</div>
      </div>`).join('') ||
      '<p class="empty" style="grid-column:1/-1;">No tickets waiting right now.</p>';
  } catch (e) {
    document.getElementById('now-service').textContent = '';
    document.getElementById('now-where').textContent = 'Offline — check the server and database.';
  }
}

tickClock();
setInterval(tickClock, 1000);
refresh();
setInterval(refresh, 5000);
</script>
</body>
</html>