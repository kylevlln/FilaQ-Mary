<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'STAFF' && $user['role'] !== 'ADMIN') {
    redirect('../customer/index.php');
}

$counters = fetch_all('SELECT * FROM counters WHERE is_active = 1 ORDER BY name');
$defaultCounter = $user['counter_id'] ?? ($counters[0]['id'] ?? 0);
$roleCode = $user['role'] === 'ADMIN' ? 'Admin' : 'Staff';
$initial = strtoupper(substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Queue Desk · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=4">
</head>
<body>
<div class="mobile-bar">
  <button class="btn btn-ico" id="nav-toggle" type="button" aria-label="Open menu"><?php echo icon('menu', 18); ?></button>
  <a class="brand" href="index.php"><span class="dot"></span>FilaQ · Queue desk</a>
</div>

<div class="app" id="app">
  <aside class="app-side" id="sidebar">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ<span class="v-tag"><?php echo $roleCode; ?></span></a>

    <p class="side-group">Menu</p>
    <a class="side-link active" href="index.php"><?php echo icon('bell'); ?> Queue desk</a>
    <a class="side-link" href="../display.php" target="_blank"><?php echo icon('monitor'); ?> Live board</a>

    <?php if ($user['role'] === 'ADMIN'): ?>
      <p class="side-group">Admin</p>
      <a class="side-link" href="../admin/index.php"><?php echo icon('dashboard'); ?> Dashboard</a>
      <a class="side-link" href="../admin/users.php"><?php echo icon('users'); ?> Users</a>
      <a class="side-link" href="../admin/settings.php"><?php echo icon('settings'); ?> Counters &amp; services</a>
      <a class="side-link" href="../admin/logs.php"><?php echo icon('activity'); ?> Activity log</a>
    <?php endif; ?>

    <div class="side-user">
      <span class="avatar"><?php echo e($initial); ?></span>
      <span class="who"><span class="name"><?php echo e($user['full_name']); ?></span><span class="role"><?php echo $roleCode; ?></span></span>
      <a class="out" href="../logout.php" aria-label="Sign out"><?php echo icon('logout', 17); ?></a>
    </div>
  </aside>

  <main class="main">
    <div class="main-inner">
      <div class="page-head">
        <div>
          <p class="page-kicker">Queue desk</p>
          <h1 class="page-title">Serve the next number</h1>
          <p class="page-lead">Manage the waiting line and serve the next customer.</p>
          <div class="shortcuts-hint" style="margin-top:.6rem;">Shortcuts <span class="kbd">N</span> next <span class="kbd">S</span> skip <span class="kbd">C</span> complete/start</div>
        </div>
        <div class="page-actions">
          <label class="muted" for="counter" style="font-size:var(--fs-sm);">Counter</label>
          <select class="input" id="counter" style="width:auto;">
            <?php foreach ($counters as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === (int) $defaultCounter ? ' selected' : ''; ?>><?php echo e($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="desk-now" aria-live="polite"></div>

      <div class="metrics">
        <div class="metric"><span class="metric-label">Issued today</span><span class="metric-value" id="st-issued">—</span></div>
        <div class="metric"><span class="metric-label">Now serving</span><span class="metric-value" id="st-serving">—</span></div>
        <div class="metric"><span class="metric-label">Completed</span><span class="metric-value" id="st-completed">—</span></div>
        <div class="metric"><span class="metric-label">Avg wait</span><span class="metric-value" id="st-avg">—</span><span class="metric-sub">minutes, today</span></div>
      </div>

      <div class="panel-head">
        <h2 class="panel-title">Waiting line</h2>
        <span class="badge badge--waiting" id="queue-count">0 waiting</span>
      </div>
      <div id="queue-list"><div class="skeleton" style="height:120px; border-radius:var(--r-md);"></div></div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=3"></script>
<script>
let lastCalled = null;
let lastQueueHash = '';

function queueHash(rows) {
  return rows.map(t => t.id + ':' + t.status + ':' + (t.position || 0)).join('|');
}

function minsAgo(iso) {
  const diff = (Date.now() - new Date(iso).getTime()) / 60000;
  if (!isFinite(diff) || diff < 1) return 'just now';
  return Math.round(diff) + ' min';
}

function renderNow(rows) {
  const zone = document.getElementById('desk-now');
  const serving = rows.find(t => t.status === 'SERVING');
  const called = rows.find(t => t.status === 'CALLED');
  const current = serving || called;
  const first = rows.find(t => t.status === 'WAITING');

  if (current) {
    const act = serving ? 'complete' : 'start';
    const actTxt = serving ? 'Complete service' : 'Start service';
    zone.innerHTML = `
      <div class="desk-now">
        <div>
          <p class="label">${serving ? 'Now serving' : 'Called — please proceed'}</p>
          <div class="code">${escapeHtml(current.ticket_code)}</div>
        </div>
        <div class="meta">
          <div class="svc">${escapeHtml(current.service_name || 'Service')}</div>
          <div class="cnt">${escapeHtml(current.counter_name || '')} · ${escapeHtml(current.customer_name || '')}</div>
        </div>
        <div class="desk-actions">
          <button class="btn btn-primary btn-lg act-now-${act}" data-id="${current.id}">${actTxt}</button>
          <button class="btn btn-ghost act-now-skip" data-id="${current.id}">Skip</button>
        </div>
      </div>`;
    return;
  }

  zone.innerHTML = `
    <div class="desk-now">
      <div>
        <p class="label">Now serving</p>
        <div class="code" style="color:var(--ink-3);">—</div>
      </div>
      <div class="meta">
        <div class="svc">No one is being served right now</div>
        <div class="cnt">Call the next number to get the line moving.</div>
      </div>
      <div class="desk-actions">
        <button class="btn btn-primary btn-lg act-now-next" data-id="${first ? first.id : ''}" ${first ? '' : 'disabled'}>Call next number</button>
      </div>
    </div>`;
}

async function loadQueue() {
  try {
    const d = await api('../api/staff.php?action=queue');

    const s = d.stats;
    document.getElementById('st-issued').textContent = s.issued;
    document.getElementById('st-serving').textContent = s.serving;
    document.getElementById('st-completed').textContent = s.completed;
    document.getElementById('st-avg').textContent = s.avg_wait;

    const called = d.data.find(t => t.status === 'CALLED' || t.status === 'SERVING');
    const nowHash = queueHash(d.data);
    if (called && nowHash !== lastQueueHash && lastQueueHash !== '') {
      if (lastCalled !== called.ticket_code + called.status) {
        playDing();
        toast(`Now serving: ${called.ticket_code} · ${called.service_name || ''}`, 'info');
      }
    }
    lastCalled = called ? called.ticket_code + called.status : null;

    renderNow(d.data);

    if (nowHash === lastQueueHash) return;
    lastQueueHash = nowHash;

    const list = document.getElementById('queue-list');
    document.getElementById('queue-count').textContent = d.data.length + ' waiting';
    if (!d.data.length) {
      list.innerHTML = '<div class="panel empty-state" style="box-shadow:none;"><span class="empty-title">The line is empty</span><p class="empty-sub">You will see the next waiting ticket here as soon as someone takes a number.</p></div>';
      return;
    }

    list.innerHTML = d.data.map((t, i) => {
      const isCurrent = t.status === 'SERVING' || t.status === 'CALLED';
      const posTxt = isCurrent ? (t.status === 'SERVING' ? 'serving now' : 'called — please proceed') : `pos ${t.position + 1}`;
      return `
        <div class="qitem ${isCurrent ? 'serving' : ''}" data-id="${t.id}" data-status="${t.status}">
          <div class="code">${escapeHtml(t.ticket_code)}</div>
          <div class="who">
            <div class="svc">${escapeHtml(t.service_name)}${t.customer_name ? ' · ' + escapeHtml(t.customer_name) : ''}</div>
            <div class="wait"><span class="badge badge--${t.status.toLowerCase()}">${t.status}</span> ${posTxt} · waited ${escapeHtml(minsAgo(t.issued_at))}</div>
          </div>
          <div class="actions">
            ${t.status === 'WAITING' ? `
              <button class="btn btn-primary btn-sm act-call" data-id="${t.id}">Call</button>
              <button class="btn btn-ghost btn-sm act-skip" data-id="${t.id}">Skip</button>` : ''}
            ${t.status === 'CALLED' ? `<button class="btn btn-cool btn-sm act-start" data-id="${t.id}">Start</button>` : ''}
            ${t.status === 'SERVING' ? `<button class="btn btn-success btn-sm act-complete" data-id="${t.id}">Complete</button>` : ''}
          </div>
        </div>`;
    }).join('');
  } catch (e) {
    document.getElementById('queue-list').innerHTML = `<div class="alert alert-error" role="alert">${escapeHtml(e.message)}</div>`;
    toast(e.message, 'error');
  }
}

async function act(ticketId, action) {
  if (!ticketId) return;
  try {
    const d = await api('../api/queue.php?action=' + action, {
      method: 'POST',
      body: { ticket_id: ticketId, counter_id: document.getElementById('counter').value }
    });
    toast(d.message, 'success');
    loadQueue();
  } catch (e) {
    toast(e.message, 'error');
    loadQueue();
  }
}

document.addEventListener('click', (e) => {
  const nowNext = e.target.closest('.act-now-next');
  if (nowNext) return act(nowNext.dataset.id, 'call');

  const nowStart = e.target.closest('.act-now-start');
  if (nowStart) return act(nowStart.dataset.id, 'start');
  const nowComplete = e.target.closest('.act-now-complete');
  if (nowComplete) return act(nowComplete.dataset.id, 'complete');
  const nowSkip = e.target.closest('.act-now-skip');
  if (nowSkip) {
    if (!confirm('Skip this ticket?')) return;
    return act(nowSkip.dataset.id, 'skip');
  }

  const item = e.target.closest('.qitem');
  if (!item) return;
  const id = item.dataset.id;
  if (e.target.closest('.act-call')) return act(id, 'call');
  if (e.target.closest('.act-start')) return act(id, 'start');
  if (e.target.closest('.act-complete')) return act(id, 'complete');
  if (e.target.closest('.act-skip')) {
    if (!confirm('Skip this ticket?')) return;
    return act(id, 'skip');
  }
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
  if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
  const next = document.querySelector('.act-now-next') || document.querySelector('.act-call');
  const start = document.querySelector('.act-now-start') || document.querySelector('.act-start');
  const complete = document.querySelector('.act-now-complete') || document.querySelector('.act-complete');
  const skip = document.querySelector('.act-now-skip') || document.querySelector('.act-skip');
  if (e.key === 'n' || e.key === 'N') next && next.click();
  if (e.key === 'c' || e.key === 'C') (start || complete) && (start || complete).click();
  if (e.key === 's' || e.key === 'S') skip && skip.click();
});

const counterSel = document.getElementById('counter');
counterSel.addEventListener('change', () => {
  toast('Counter set to ' + counterSel.selectedOptions[0].textContent, 'info');
});
const actualCounter = <?php echo json_encode((int) ($user['counter_id'] ?? 0)); ?>;
if (actualCounter > 0 && counterSel.value !== String(actualCounter)) counterSel.value = String(actualCounter);

// Mobile drawer
const app = document.getElementById('app');
document.getElementById('nav-toggle').addEventListener('click', () => app.classList.toggle('side-open'));
document.getElementById('sidebar').addEventListener('click', (e) => {
  if (e.target.closest('.side-link')) app.classList.remove('side-open');
});

loadQueue();
setInterval(loadQueue, 5000);
</script>
</body>
</html>