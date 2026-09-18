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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Take a number · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Menu</span>
    <a class="side-link active" href="index.php"><span class="ic"><?php echo icon('ticket'); ?></span> Take a Number</a>
    <a class="side-link" href="track.php"><span class="ic"><?php echo icon('pin'); ?></span> Track Queue</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic"><?php echo icon('monitor'); ?></span> Live Board</a>
    <div class="side-foot">
      Signed in as <strong><?php echo e($user['username']); ?></strong><br>
      <a href="../logout.php">Sign out</a>
    </div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div>
        <h1>Hello, <?php echo e($user['full_name']); ?></h1>
        <p class="sub">Take a number and we'll let you know when it's your turn.</p>
      </div>
      <a class="btn btn-ghost" href="track.php">Track my number →</a>
    </div>

    <?php if ($announcement): ?>
      <div class="alert alert-info"><?php echo e($announcement); ?></div>
    <?php endif; ?>

    <?php if (!$services): ?>
      <div class="card"><p class="empty">No services are available right now. Please check back shortly.</p></div>
    <?php else: ?>
      <div class="section-head"><h2>Choose a service</h2></div>
      <div class="grid-2 stagger" id="services-grid">
        <?php foreach ($services as $s): ?>
          <button class="card service-card" data-id="<?php echo (int) $s['id']; ?>" style="text-align:left; border:none; cursor:pointer; font-family:inherit;">
            <h3 style="margin-bottom:.3rem;"><?php echo e($s['name']); ?></h3>
            <p class="muted" style="font-size:.86rem;"><?php echo e($s['description'] ?: 'General service'); ?></p>
            <p style="margin:0; font-size:.82rem; color:var(--aqua);">
              Est. wait:
              <strong class="wait-min" data-service="<?php echo (int) $s['id']; ?>">…</strong>
            </p>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Ticket result becomes visible after taking a number -->
    <div id="ticket-area" style="display:none; margin-top:2rem;"></div>

    <!-- Live board preview -->
    <div style="margin-top:2.5rem;">
      <div class="section-head"><h2>What's happening now</h2><a class="btn btn-ghost btn-sm" href="../display.php" target="_blank">Open live board</a></div>
      <div class="stats-row" id="live-stats">
        <div class="stat-card"><span class="stat-label">Now serving</span><span class="stat-value" id="ls-now">—</span></div>
        <div class="stat-card glow-cyan"><span class="stat-label">Ahead of you</span><span class="stat-value" id="ls-ahead">—</span></div>
        <div class="stat-card glow-pink"><span class="stat-label">Waiting total</span><span class="stat-value" id="ls-waiting">—</span></div>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=2"></script>
<script>
const svcGrid = document.getElementById('services-grid');

// Take a ticket
document.addEventListener('click', async (e) => {
  const card = e.target.closest('.service-card');
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

// Pretty ticket + tracking info
function renderTicket(t) {
  const zone = document.getElementById('ticket-area');
  const clicked = document.querySelector('.service-card[data-id="' + t.service_id + '"] h3');
  const serviceName = clicked ? clicked.textContent : 'Service';
  const eta = Math.max(1, Math.ceil(Number(t.estimated_wait_sec || 0) / 60));

  zone.style.display = 'block';
  zone.classList.add('fade-in');
  zone.innerHTML = `
    <div class="ticket-paper">
      <div class="tick-head">
        <span class="name">FilaQ</span>
        <span>${escapeHtml(serviceName)}</span>
      </div>
      <div class="tick-id">
        <div class="code">${escapeHtml(t.ticket_code)}</div>
        <div class="svc"><strong>${escapeHtml(t.service_name || (clicked ? clicked.textContent : 'Service'))}</strong></div>
        <div class="svc-note">Your number. Keep this ticket.</div>
      </div>
      <div class="tick-details">
        <span>Tracking code</span><strong style="color:var(--sienna);">${escapeHtml(t.session_code)}</strong>
      </div>
      <div class="tick-eta">
        Estimated wait: <b>~${eta} minute${eta === 1 ? '' : 's'}</b>
      </div>
    </div>
    <p class="center muted" style="margin-top:1rem;">Save your tracking code <strong>${escapeHtml(t.session_code)}</strong>. You can check your exact position on the <a href="track.php">Track Queue</a> page.</p>`;
  zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Live briefing + per-service wait estimates, from a single `live` request.
async function refreshLive() {
  try {
    const d = await api('../api/queue.php?action=live');
    const queues = d.data.queues || [];
    const waiting = queues.reduce((s, q) => s + Number(q.waiting || 0), 0);

    // The live payload already carries the estimated wait per service, so the
    // "Est. wait" labels on the service cards are updated in the same call.
    queues.forEach(q => {
      const el = document.querySelector(`.wait-min[data-service="${q.service_id}"]`);
      if (el) el.textContent = `~${q.est_wait_min} min`;
    });

    const cur = d.data.now ? `${d.data.now.ticket_code || ''}${d.data.now.counter_name ? ' @ ' + d.data.now.counter_name : ''}`.trim() : '—';
    document.getElementById('ls-now').textContent = cur;
    document.getElementById('ls-waiting').textContent = waiting;
    document.getElementById('ls-ahead').textContent = waiting;
  } catch (e) { /* ignore */ }
}

refreshLive();
setInterval(refreshLive, 15000);
</script>
</body>
</html>