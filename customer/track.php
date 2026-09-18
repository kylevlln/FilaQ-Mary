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
<link rel="stylesheet" href="../assets/css/style.css?v=4">
</head>
<body class="standalone">
<div class="standalone-head no-print">
  <a class="brand" href="index.php"><span class="dot"></span>FilaQ</a>
  <div class="links">
    <a class="btn btn-ghost btn-sm" href="index.php">Take a number</a>
    <a class="btn btn-ghost btn-sm" href="../display.php" target="_blank">Live board</a>
    <a class="btn btn-ghost btn-sm" href="../logout.php">Sign out</a>
  </div>
</div>

<main class="cust-main">
  <div class="track-wrap">
    <div class="page-head">
      <div>
        <p class="page-kicker">Track my queue</p>
        <h1 class="page-title">Where am I in line?</h1>
        <p class="page-lead">Enter the tracking code printed on your ticket.</p>
      </div>
    </div>

    <div class="panel" style="background:var(--surface-2);">
      <form id="track-form" class="row" style="align-items:flex-start;">
        <div class="field flex-1" style="margin-bottom:0;">
          <label for="code">Tracking code</label>
          <input class="input" type="text" id="code" name="code" placeholder="e.g. 4F6A2B81" maxlength="12" style="text-transform:uppercase;" required>
          <span class="input-error"></span>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:1.55rem;">Check</button>
      </form>
    </div>

    <div id="result"></div>
  </div>
</main>

<script src="../assets/js/main.js?v=3"></script>
<script>
const STAGES = { WAITING: 0, CALLED: 1, SERVING: 2, COMPLETED: 3, SKIPPED: 3, CANCELLED: 0 };

const form = document.getElementById('track-form');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const code = document.getElementById('code').value.trim().toUpperCase();
  if (!code) { toast('Please enter your tracking code.', 'warn'); return; }
  const zone = document.getElementById('result');
  zone.innerHTML = '<div class="skeleton" style="height:220px; border-radius:var(--r-lg);"></div>';
  try {
    const d = await api('../api/queue.php?action=track&code=' + encodeURIComponent(code));
    render(d.data);
  } catch (err) {
    zone.innerHTML = `<div class="alert alert-warn" role="alert">${escapeHtml(err.message)}</div>`;
  }
});

function stateMeta(t) {
  const sub = Math.max(0, t.remaining_wait_min);
  const pos = Math.max(1, t.position + 1);
  switch (t.status) {
    case 'WAITING':
      return { title: `You are #${pos} in line`, text: `Approximately ~${sub} min to your turn`, icon: 'clock', tone: 'warn' };
    case 'CALLED':
      return { title: 'Your number was called', text: 'Please proceed to the counter now.', icon: 'bell', tone: 'teal' };
    case 'SERVING':
      return { title: 'Now being served', text: t.service_name ? t.service_name : 'The counter has your number.', icon: 'check', tone: 'acc' };
    case 'COMPLETED':
      return { title: 'Served — thank you', text: 'Your transaction at this counter is complete.', icon: 'check', tone: 'ok' };
    case 'SKIPPED':
      return { title: 'This number was skipped', text: 'Please take a new number at the intake desk.', icon: 'alert', tone: 'bad' };
    default:
      return { title: t.status, text: '', icon: 'clock', tone: 'warn' };
  }
}

function render(t) {
  const stage = STAGES[t.status] ?? 0;
  const isSkipped = t.status === 'SKIPPED';
  const labels = ['Waiting', 'Called', 'Serving', 'Done'];
  const done = (i) => (i < stage) || (isSkipped && i < 3);
  const steps = labels.map((lbl, i) => {
    const cls = isSkipped && (i === 3) ? 'skip' : done(i) ? 'done' : (i === stage ? 'current' : '');
    const circle = isSkipped && i === 3
      ? `<span class="circle">${icon('x', 16)}</span>`
      : (i === stage && !isSkipped)
        ? `<span class="circle">${i === 3 ? icon('check', 16) : ''}<span class="serif">${i + 1}</span></span>`
        : `<span class="circle"><span class="serif">${i + 1}</span></span>`;
    return `<div class="track-step ${cls}">${circle}<span class="lbl">${lbl}</span></div>`;
  });

  const sm = stateMeta(t);
  const badgeTone = t.status === 'SKIPPED' ? 'skipped' : t.status.toLowerCase();
  const zone = document.getElementById('result');
  zone.innerHTML = `
    <div class="track-panel">
      <p class="kicker center" style="margin-bottom:.6rem;">Your ticket</p>
      <div class="track-code">${escapeHtml(t.ticket_code)}</div>
      <div class="track-svc">${escapeHtml(t.service_name || 'Service')}</div>
      <div class="track-state">
        <span class="state" style="color:${t.status === 'SKIPPED' ? 'var(--rose)' : 'var(--acc)'}">
          ${icon(sm.icon, 20)} ${escapeHtml(sm.title)}
        </span>
        <div class="help">${escapeHtml(sm.text)}</div>
      </div>

      <div class="track-facts">
        <span class="track-fact"><span class="n">${escapeHtml(t.ticket_code.split('-')[1] || '—')}</span><span class="l">Your number</span></span>
        ${(t.status === 'WAITING' || t.status === 'CALLED') ? `<span class="track-fact"><span class="n">${Math.max(1, Number(t.position) + 1)}</span><span class="l">In line</span></span>` : ''}
        ${(t.status === 'WAITING') ? `<span class="track-fact"><span class="n">~${Math.max(0, t.remaining_wait_min)}</span><span class="l">Min to turn</span></span>` : ''}
        <span class="track-fact"><span class="n">${escapeHtml(t.counter_name || '—')}</span><span class="l">Window</span></span>
      </div>

      <div class="track-steps">${steps.join('')}</div>

      <div class="divider" style="margin:1.2rem 0;"></div>
      <p class="muted" style="font-size:var(--fs-sm); margin:0;">
        Issued ${new Date(t.issued_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} ·
        check again to see if it is your turn
      </p>
      <div class="row" style="justify-content:center; margin-top:1.2rem; flex-wrap:wrap;">
        <a class="btn btn-primary btn-sm" href="index.php">Take a new number</a>
        <a class="btn btn-ghost btn-sm" href="../display.php" target="_blank">Watch the live board</a>
      </div>
    </div>`;
  zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
</body>
</html>