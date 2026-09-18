<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'CUSTOMER') {
    redirect($user['role'] === 'ADMIN' ? '../admin/index.php' : '../staff/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track my queue · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Menu</span>
    <a class="side-link" href="index.php"><span class="ic"><?php echo icon('ticket'); ?></span> Take a Number</a>
    <a class="side-link active" href="track.php"><span class="ic"><?php echo icon('pin'); ?></span> Track Queue</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic"><?php echo icon('monitor'); ?></span> Live Board</a>
    <div class="side-foot">
      Signed in as <strong><?php echo e($user['username']); ?></strong><br>
      <a href="../logout.php">Sign out</a>
    </div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div><h1>Track my queue</h1><p class="sub">Enter the tracking code printed on your ticket.</p></div>
    </div>

    <div class="card" style="max-width:520px;">
      <form id="track-form" class="row" style="align-items:flex-start;">
        <div class="field flex-1" style="margin-bottom:0;">
          <label for="code">Tracking code</label>
          <input class="input" type="text" id="code" name="code" placeholder="e.g. 4F6A2B81" maxlength="12" style="text-transform:uppercase;" required>
          <span class="input-error"></span>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:1.6rem;">Check</button>
      </form>
    </div>

    <div id="result" style="margin-top:1.6rem;"></div>
  </main>
</div>

<script src="../assets/js/main.js?v=2"></script>
<script>
const STAGES = {
  WAITING:   0,
  CALLED:    1,
  SERVING:   2,
  COMPLETED: 3,
  SKIPPED:   3,
  CANCELLED: 0
};

const form = document.getElementById('track-form');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const code = document.getElementById('code').value.trim().toUpperCase();
  if (!code) { toast('Please enter your tracking code.', 'warn'); return; }
  const zone = document.getElementById('result');
  zone.innerHTML = '<div class="skeleton" style="height:180px; border-radius:var(--r-md);"></div>';
  try {
    const d = await api('../api/queue.php?action=track&code=' + encodeURIComponent(code));
    render(d.data);
    setTimeout(playDing, 0);
  } catch (err) {
    zone.innerHTML = `<div class="alert alert-warn">${escapeHtml(err.message)}</div>`;
  }
});

function render(t) {
  const stage = STAGES[t.status] ?? 0;
  const isSkipped = t.status === 'SKIPPED';
  const labels = ['Waiting', 'Called', 'Serving', 'Complete'];
  const steps = labels.map((lbl, i) => {
    let cls = 'step';
    if (i < stage || (isSkipped && i < 3)) cls += ' done';
    else if (i === stage && !isSkipped) cls += ' current';
    const circle = isSkipped && i === 3 ? `<span class="circle" style="background:var(--red); color:#fff;">${icon('x', 14)}</span>` : `<span class="circle">${i + 1}</span>`;
    return `${circle}<span class="lbl">${lbl}</span>`;
  });
  const line = '<div class="step-line' + (stage >= 1 ? ' on' : '') + '"></div>';
  const badge = `<span class="badge badge--${t.status.toLowerCase()}">${t.status}</span>`;
  const etaLine = (t.status === 'WAITING')
    ? `<p class="lead" style="margin:.4rem 0;">Approximately <strong class="serif" style="font-size:1.6rem; color:var(--sienna);">${Math.max(0, t.remaining_wait_min)} min</strong> to your turn (position ${Math.max(1, t.position + 1)} in line).</p>`
    : '<p class="muted" style="margin:0;">Please head to the counter when your number is called.</p>';

  const zone = document.getElementById('result');
  zone.classList.remove('fade-in'); void zone.offsetWidth; zone.classList.add('fade-in');
  zone.innerHTML = `
    <div class="card" style="max-width:560px;">
      <div class="row-between">
        <div>
          <span class="muted" style="font-size:.8rem;">Ticket</span>
          <div class="serif" style="font-size:2rem; font-weight:700;">${escapeHtml(t.ticket_code)}</div>
          <div class="muted" style="font-size:.95rem;"><strong style="color:var(--ink);">${escapeHtml(t.service_name)}</strong></div>
        </div>
        ${badge}
      </div>
      <div class="stepper">${steps.join(line)}</div>
      <div class="center" style="text-align:center;">${isSkipped
        ? '<p class="alert alert-warn" style="text-align:left;">This number was skipped. Please take a new number at the desk.</p>'
        : etaLine}
      </div>
      <div class="divider"></div>
      <p class="muted center" style="font-size:.85rem;">Issued ${new Date(t.issued_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
    </div>
    <div class="row" style="justify-content:center; margin-top:1rem; flex-wrap:wrap;">
      <a class="btn btn-ghost btn-sm" href="index.php">Take a new number</a>
      <a class="btn btn-cool btn-sm" href="../display.php" target="_blank">Live board</a>
    </div>`;
}
</script>
</body>
</html>