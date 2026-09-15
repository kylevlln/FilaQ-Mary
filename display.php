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
<title>Live Board — <?php echo e($orgName); ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  html, body { min-height: 100%; }
  .display-stage { min-height: 100vh; display: flex; flex-direction: column; padding: 1.4rem 2rem; }
  .display-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
  .display-head .brand { font-family: var(--font-serif); font-size: 1.7rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
  .display-head .brand .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--grad-warm); }
  .display-clock { font-family: var(--font-serif); font-size: 1.9rem; color: var(--ink); }
  .display-now { text-align: center; padding: 2.6rem 1rem 1.8rem; margin-top: .5rem; }
  .display-now .big { font-size: clamp(6rem, 19vw, 13rem); }
  .display-title { font-family: var(--font-serif); font-size: clamp(1.6rem, 4.5vw, 2.8rem); color: var(--ink); margin-top: .2rem; font-weight: 600; }
  .display-ann { font-size: 1.15rem; color: var(--aqua); letter-spacing: .05em; margin-top: .9rem; font-weight: 500; }
  .display-queues { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.2rem; width: min(1240px, 98vw); margin: .7rem auto 0; }
  .qcard { text-align: center; padding: 1.5rem 1.2rem; background: var(--paper); border: 1px solid var(--line); border-radius: var(--r-md); box-shadow: var(--shadow-sm); }
  .qcard .qname { font-size: .9rem; text-transform: uppercase; letter-spacing: .14em; color: var(--ink-soft); font-weight: 600; }
  .qcard .qcurrent { font-family: var(--font-serif); font-size: 3.2rem; font-weight: 700; margin: .25rem 0; }
  .qcard .qwait { font-size: .95rem; color: var(--ink-soft); }
  .qcard.now { background: linear-gradient(135deg, #fff7f0, #fbe9dc); border-color: var(--caramel); }
  .upcoming-strip { width: min(1240px, 98vw); margin: 1.6rem auto 0; }
  .upcoming-strip h3 { font-size: 1.1rem; color: var(--ink-soft); letter-spacing: .12em; text-transform: uppercase; }
  .upcoming-strip .tickets { display: flex; flex-wrap: wrap; gap: .8rem; }
  .u-tick { font-family: var(--font-serif); font-weight: 700; font-size: 1.5rem; padding: .9rem 1.4rem; background: var(--paper); border: 1px solid var(--line); border-radius: var(--r-sm); box-shadow: var(--shadow-sm); }
  .u-tick .sv { display:block; font-family: var(--font-sans); font-size:.78rem; font-weight:400; color: var(--ink-soft); letter-spacing: .06em; }
  @media (max-width: 700px) { .display-stage { padding: 1rem .8rem; } .display-now .big { font-size: 5rem; } }
</style>
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div></div>
<div class="display-stage">
  <div class="display-head">
    <div class="brand"><span class="dot"></span><?php echo e($orgName); ?> — FilaQ</div>
    <div class="display-clock" id="clock">--:--</div>
  </div>

  <div class="display-now">
    <div class="label">Now serving</div>
    <div class="big" id="now-code">---</div>
    <div class="display-title" id="now-where">Please wait…</div>
    <div class="display-ann"><?php echo e($announcement ?: ''); ?></div>
  </div>

  <div class="display-queues" id="queues">
    <div class="skeleton" style="height:110px; border-radius:var(--r-md);"></div>
    <div class="skeleton" style="height:110px; border-radius:var(--r-md);"></div>
    <div class="skeleton" style="height:110px; border-radius:var(--r-md);"></div>
  </div>

  <div class="upcoming-strip">
    <h3>Upcoming</h3>
    <div class="tickets" id="upcoming"></div>
  </div>
</div>

<script src="assets/js/main.js"></script>
<script>
const tickCache = {};

function pad(n) { return String(n).padStart(2, '0'); }
function tickClock() {
  const d = new Date();
  document.getElementById('clock').textContent = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

let lastBoardHash = '';
function boardHash(d) {
  const now = d.now;
  return (now && now.ticket_code || '—') + '|' + (now && now.counter_name || '') + '|' + (d.upcoming || []).map(u => u.ticket_code).join() + '|' + (d.queues || []).map(q => q.current_code || '').join();
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
    if (now && now.ticket_code) {
      nowEl.textContent = now.ticket_code;
      document.getElementById('now-where').textContent =
        (now.counter_name ? now.counter_name : 'Counter') + (now.status === 'SERVING' ? ' — serving now' : ' — please proceed');
    } else {
      nowEl.textContent = '—';
      document.getElementById('now-where').textContent = 'No active call';
    }

    if (changed) {
      document.getElementById('queues').innerHTML = (d.queues || []).map(q => {
        const active = q.current_code;
        return `
        <div class="qcard ${active ? 'now' : ''}">
          <div class="qname">${escapeHtml(q.service_name)}</div>
          <div class="qcurrent">${escapeHtml(active || '· · ·')}</div>
          <div class="qwait">${q.waiting} waiting · ~${q.est_wait_min} min</div>
        </div>`;
      }).join('') || '<div class="empty">No services configured.</div>';

      document.getElementById('upcoming').innerHTML = (d.upcoming || []).map(t => `
      <div class="u-tick" style="animation: rise .4s var(--ease) both;">
        ${escapeHtml(t.ticket_code)}
        <span class="sv">${escapeHtml(t.service_name)}</span>
      </div>`).join('');
    }
  } catch (e) {
    document.getElementById('now-where').textContent = 'Offline — check server & database';
  }
}

tickClock();
setInterval(tickClock, 1000);
refresh();
setInterval(refresh, 5000);
</script>
</body>
</html>