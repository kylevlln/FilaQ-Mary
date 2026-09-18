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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Queue Desk · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Menu</span>
    <a class="side-link active" href="index.php"><span class="ic"><?php echo icon('bell'); ?></span> Queue Desk</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic"><?php echo icon('monitor'); ?></span> Live Board</a>
    <?php if ($user['role'] === 'ADMIN'): ?>
      <span class="side-caption">Admin</span>
      <a class="side-link" href="../admin/index.php"><span class="ic"><?php echo icon('dashboard'); ?></span> Dashboard</a>
      <a class="side-link" href="../admin/users.php"><span class="ic"><?php echo icon('users'); ?></span> Users</a>
    <?php endif; ?>
    <div class="side-foot">
      Signed in as <strong><?php echo e($user['username']); ?></strong><br>
      <a href="../logout.php">Sign out</a>
    </div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div>
        <h1>Queue Desk</h1>
        <p class="sub">Call the next number, skip when needed, and complete service. Keyboard shortcuts: <strong>N</strong> next · <strong>S</strong> skip · <strong>C</strong> complete/start.</p>
      </div>
      <div class="row">
        <label class="muted" for="counter" style="font-size:.82rem;">Counter</label>
        <select class="input" id="counter" style="width:auto;">
          <?php foreach ($counters as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === (int) $defaultCounter ? ' selected' : ''; ?>><?php echo e($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="stats-row stagger" id="stats">
      <div class="stat-card glow-orange"><span class="stat-label">Issued today</span><span class="stat-value" id="st-issued">—</span></div>
      <div class="stat-card glow-cyan"><span class="stat-label">Serving now</span><span class="stat-value" id="st-serving">—</span></div>
      <div class="stat-card glow-pink"><span class="stat-label">Completed</span><span class="stat-value" id="st-completed">—</span></div>
      <div class="stat-card"><span class="stat-label">Avg wait (min)</span><span class="stat-value" id="st-avg">—</span></div>
    </div>

    <div class="section-head"><h2>Waiting line</h2><span class="badge badge--waiting" id="queue-count">0 waiting</span></div>
    <div id="queue-list"><div class="skeleton" style="height:80px; border-radius:var(--r-md);"></div></div>
  </main>
</div>

<script src="../assets/js/main.js?v=2"></script>
<script>
let lastCalled = null;
let lastQueueHash = '';

function queueHash(rows) {
  return rows.map(t => t.id + ':' + t.status + ':' + (t.position || 0)).join('|');
}

async function loadQueue() {
  try {
    const d = await api('../api/staff.php?action=queue');

    // Stats arrive with the queue in one request — no separate poll.
    const s = d.stats;
    document.getElementById('st-issued').textContent = s.issued;
    document.getElementById('st-serving').textContent = s.serving;
    document.getElementById('st-completed').textContent = s.completed;
    document.getElementById('st-avg').textContent = s.avg_wait;

    // Detect newly-called ticket for the ding
    const called = d.data.find(t => t.status === 'CALLED' || t.status === 'SERVING');
    const nowHash = queueHash(d.data);
    if (called && nowHash !== lastQueueHash && lastQueueHash !== '') {
      if (lastCalled !== called.ticket_code + called.status) {
        playDing();
        toast(`Now serving: ${called.ticket_code} · ${escapeHtml(called.service_name || '')}`, 'info');
      }
    }
    lastCalled = called ? called.ticket_code + called.status : null;

    // Skip re-rendering the list if nothing changed (less layout churn)
    if (nowHash === lastQueueHash) return;
    lastQueueHash = nowHash;

    // Render list
    const list = document.getElementById('queue-list');
    document.getElementById('queue-count').textContent = d.data.length + ' waiting';
    if (!d.data.length) {
      list.innerHTML = '<div class="empty">No one is in line right now.</div>';
      return;
    }

    list.innerHTML = d.data.map((t, i) => {
      const isCurrent = t.status === 'SERVING' || t.status === 'CALLED';
      return `
        <div class="queue-card ${isCurrent ? 'confirm' : ''}" data-id="${t.id}" data-status="${t.status}" style="${isCurrent ? 'border-color:var(--caramel); background:#fffaf4;' : ''}">
          <div class="ticket-no">${escapeHtml(t.ticket_code)}</div>
          <div class="meta">
            <div><strong>${escapeHtml(t.service_name)}</strong> ${escapeHtml(t.customer_name ? ' · ' + t.customer_name : '')}</div>
            <div><span class="badge badge--${t.status.toLowerCase()}">${t.status}</span> <span class="muted">pos ${t.position + 1}</span></div>
          </div>
          <div class="actions">
            ${t.status === 'WAITING' ? `
              <button class="btn btn-primary btn-sm act-call">Call</button>
              <button class="btn btn-ghost btn-sm act-skip">Skip</button>` : ''}
            ${t.status === 'CALLED' ? `<button class="btn btn-cool btn-sm act-start">Start service</button>` : ''}
            ${t.status === 'SERVING' ? `<button class="btn btn-success btn-sm act-complete">Complete</button>` : ''}
          </div>
        </div>`;
    }).join('');
  } catch (e) {
    document.getElementById('queue-list').innerHTML = `<div class="alert alert-error">${escapeHtml(e.message)}</div>`;
    toast(e.message, 'error');
  }
}

async function act(ticketId, action) {
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

document.getElementById('queue-list').addEventListener('click', (e) => {
  const card = e.target.closest('.queue-card');
  if (!card) return;
  const id = card.dataset.id;
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
  const first = document.querySelector('.queue-card .act-call');
  const start = document.querySelector('.queue-card .act-start');
  const complete = document.querySelector('.queue-card .act-complete');
  const skip = document.querySelector('.queue-card .act-skip');
  if (e.key === 'n' || e.key === 'N') first && first.click();
  if (e.key === 'c' || e.key === 'C') (start || complete) && (start || complete).click();
  if (e.key === 's' || e.key === 'S') skip && skip.click();
});

const counterSel = document.getElementById('counter');
counterSel.addEventListener('change', () => {
  toast('Counter set to ' + counterSel.selectedOptions[0].textContent, 'info');
});
// keep staff counter assignment in sync with admin if it changed
const actualCounter = <?php echo json_encode((int) ($user['counter_id'] ?? 0)); ?>;
if (actualCounter > 0 && counterSel.value !== String(actualCounter)) counterSel.value = String(actualCounter);

loadQueue();
setInterval(loadQueue, 8000);
</script>
</body>
</html>