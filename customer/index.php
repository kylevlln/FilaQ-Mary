<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'CUSTOMER') {
    redirect($user['role'] === 'ADMIN' ? '../admin/index.php' : '../staff/index.php');
}

$services = fetch_all('SELECT id, name, description, avg_service_time_sec FROM services WHERE is_active = 1 ORDER BY name');
$announcement = setting('announcement', '');
$firstName = trim(explode(' ', $user['full_name'])[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Take a number · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=4">
</head>
<body class="standalone">
<div class="standalone-head no-print">
  <a class="brand" href="index.php"><span class="dot"></span>FilaQ</a>
  <div class="links">
    <span class="muted" style="font-size:var(--fs-sm);">Hi, <?php echo e($firstName); ?></span>
    <a class="btn btn-ghost btn-sm" href="track.php">Track queue</a>
    <a class="btn btn-ghost btn-sm" href="../display.php" target="_blank">Live board</a>
    <a class="btn btn-ghost btn-sm" href="../logout.php">Sign out</a>
  </div>
</div>

<main class="cust-main">
  <div class="cust-inner">
    <div class="page-head">
      <div>
        <p class="page-kicker">Take a number</p>
        <h1 class="page-title">What did you come for?</h1>
        <p class="page-lead">Choose a service and we will hand you a number, tell you the wait, and call you when it is your turn.</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-ghost" href="track.php">Track my number</a>
      </div>
    </div>

    <?php if ($announcement): ?>
      <div class="alert alert-warn" role="note"><?php echo icon('alert', 18); ?><span><?php echo e($announcement); ?></span></div>
    <?php endif; ?>

    <?php if (!$services): ?>
      <div class="panel empty-state">
        <span class="empty-ic"><?php echo icon('alert', 22); ?></span>
        <p class="empty-title">No services available right now</p>
        <p class="empty-sub">The counters are closed for the moment. Please check back shortly.</p>
      </div>
    <?php else: ?>
      <div class="svc-grid" id="services-grid">
        <?php foreach ($services as $s): ?>
          <button class="svc-card" data-id="<?php echo (int) $s['id']; ?>" type="button">
            <span class="svc-ico"><?php echo icon('ticket', 20); ?></span>
            <span class="svc-name"><?php echo e($s['name']); ?></span>
            <span class="svc-note"><?php echo e($s['description'] ?: 'General service'); ?></span>
            <span class="svc-meta">Est. wait: <strong class="wait-min" data-service="<?php echo (int) $s['id']; ?>">…</strong></span>
            <span class="go"><?php echo icon('check', 18); ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div id="ticket-area" style="display:none;" aria-live="polite"></div>

    <section style="margin-top:2.5rem;" aria-label="What is happening now">
      <div class="panel-head">
        <h2 class="panel-title">What&rsquo;s happening now</h2>
        <div class="row">
          <span class="muted" style="font-size:var(--fs-sm);"><strong class="wait-total" id="ls-waiting">…</strong> waiting in line</span>
          <a class="btn btn-ghost btn-sm" href="../display.php" target="_blank">Open live board</a>
        </div>
      </div>
      <div class="now-mini" id="live-stats">
        <div class="now-chip serving"><span class="role" id="ls-now">—</span><span class="who"><span class="at">Now serving</span><br>watch the board for your number</span></div>
        <div class="now-chip next"><span class="role" id="ls-next">—</span><span class="who"><span class="at">Coming up next</span><br>one ticket after the current one</span></div>
      </div>
    </section>
  </div>
</main>

<script src="../assets/js/main.js?v=3"></script>
<script>
const svcGrid = document.getElementById('services-grid');

document.addEventListener('click', async (e) => {
  const card = e.target.closest('.svc-card');
  if (!card) return;
  const id = card.dataset.id;
  card.disabled = true;
  card.dataset.loading = '1';
  try {
    const d = await api('../api/queue.php?action=take-ticket', {
      method: 'POST',
      body: { service_id: id }
    });
    renderTicket(d.data);
    toast(d.message, 'success');
    refreshLive();
  } catch (err) {
    toast(err.message, 'error');
  } finally {
    delete card.dataset.loading;
    card.disabled = false;
  }
});

function renderTicket(t) {
  const zone = document.getElementById('ticket-area');
  const clicked = document.querySelector('.svc-card[data-id="' + t.service_id + '"] .svc-name');
  const serviceName = clicked ? clicked.textContent : 'Service';
  const eta = Math.max(1, Math.ceil(Number(t.estimated_wait_sec || 0) / 60));

  zone.style.display = 'block';
  zone.innerHTML = `
    <div class="ticket" role="status" aria-label="Your ticket">
      <div class="ticket-head">
        <span class="name">FilaQ</span>
        <span class="meta">Your ticket</span>
      </div>
      <div class="ticket-body">
        <div class="ticket-code">${escapeHtml(t.ticket_code)}</div>
        <div class="ticket-svc">${escapeHtml(t.service_name || serviceName)}</div>
        <div class="ticket-note">Keep this number handy — the board will call you next.</div>
      </div>
      <div class="ticket-foot">
        <span>Tracking code</span>
        <strong>${escapeHtml(t.session_code)}</strong>
      </div>
      <div class="ticket-foot">
        <span>Estimated wait</span>
        <strong class="t-eta">~${eta} minute${eta === 1 ? '' : 's'}</strong>
      </div>
      <div class="ticket-actions">
        <a class="btn btn-primary" href="track.php">Track my number</a>
        <button class="btn btn-ghost" type="button" onclick="location.reload()">Take another</button>
      </div>
    </div>
    <p class="center muted" style="margin-top:1rem;">Save <strong>${escapeHtml(t.session_code)}</strong> — it is how you check your exact place on the <a href="track.php">Track Queue</a> page.</p>`;
  zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function refreshLive() {
  try {
    const d = await api('../api/queue.php?action=live');
    const queues = d.data.queues || [];
    const waiting = queues.reduce((s, q) => s + Number(q.waiting || 0), 0);

    queues.forEach(q => {
      const el = document.querySelector(`.wait-min[data-service="${q.service_id}"]`);
      if (el) el.textContent = `~${q.est_wait_min} min`;
    });

    document.getElementById('ls-waiting').textContent = waiting;

    const n = d.data.now, u = (d.data.upcoming || [])[0];
    document.getElementById('ls-now').textContent = n && n.ticket_code ? n.ticket_code : '—';
    document.getElementById('ls-next').textContent = u ? u.ticket_code : '—';
  } catch (e) { /* ignore */ }
}

refreshLive();
setInterval(refreshLive, 15000);
</script>
</body>
</html>