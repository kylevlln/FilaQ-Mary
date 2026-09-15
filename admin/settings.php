<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';

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
<title>Counters &amp; Services — FilaQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div></div>
<div class="dash">
  <aside class="dash-side">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ</a>
    <span class="side-caption">Admin</span>
    <a class="side-link" href="index.php"><span class="ic">📊</span> Dashboard</a>
    <a class="side-link" href="users.php"><span class="ic">👥</span> Users</a>
    <a class="side-link active" href="settings.php"><span class="ic">⚙️</span> Counters &amp; Services</a>
    <a class="side-link" href="logs.php"><span class="ic">🕵️</span> Activity Log</a>
    <span class="side-caption">Queue</span>
    <a class="side-link" href="../staff/index.php"><span class="ic">🔔</span> Queue Desk</a>
    <a class="side-link" href="../display.php" target="_blank"><span class="ic">🖥️</span> Live Board</a>
    <div class="side-foot">Signed in as <strong><?php echo e($user['username']); ?></strong><br><a href="../logout.php">Sign out</a></div>
  </aside>

  <main class="dash-main">
    <div class="dash-top">
      <div><h1>Counters &amp; Services</h1><p class="sub">Configure what you offer and where it is served.</p></div>
    </div>

    <div class="section-head"><h2>System settings</h2></div>
    <div class="card flat"><div id="settings-form"><div class="skeleton" style="height:120px;"></div></div></div>

    <div class="grid-2 mt-3">
      <div class="card">
        <div class="row-between mb-2"><h2 style="margin:0;">Counters</h2>
          <button class="btn btn-primary btn-sm" id="new-counter">+ Add</button></div>
        <div id="counter-list"></div>
      </div>
      <div class="card">
        <div class="row-between mb-2"><h2 style="margin:0;">Services</h2>
          <button class="btn btn-primary btn-sm" id="new-service">+ Add</button></div>
        <div id="service-list"></div>
      </div>
    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
<script>
async function loadSettings() {
  try {
    const d = await api('../api/admin.php?action=settings');
    const s = d.data;
    document.getElementById('settings-form').innerHTML = `
      <form id="set-form" class="grid-2">
        <div class="field"><label>Organization name</label><input class="input" name="org_name" value="${escapeHtml(s.org_name || '')}"></div>
        <div class="field"><label>Queue prefix</label><input class="input" name="queue_prefix" value="${escapeHtml(s.queue_prefix || 'FQ')}" maxlength="4"></div>
        <div class="field"><label>Open time</label><input class="input" name="open_time" type="time" value="${escapeHtml(s.open_time || '08:00')}"></div>
        <div class="field"><label>Close time</label><input class="input" name="close_time" type="time" value="${escapeHtml(s.close_time || '17:00')}"></div>
        <div class="field" style="grid-column:1/-1;"><label>Public announcement</label><input class="input" name="announcement" value="${escapeHtml(s.announcement || '')}"></div>
        <div class="field" style="grid-column:1/-1;">
          <label><input type="checkbox" name="allow_registration" value="1" ${String(s.allow_registration) === '1' ? 'checked' : ''}> Allow new account registration</label>
        </div>
        <button class="btn btn-primary" type="submit">Save settings</button>
      </form>`;
  } catch (e) { document.getElementById('settings-form').innerHTML = `<div class="alert alert-error">${escapeHtml(e.message)}</div>`; }
}
document.getElementById('settings-form')?.addEventListener('submit', () => {});
document.addEventListener('submit', async (e) => {
  if (e.target.id !== 'set-form') return;
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = {};
  fd.forEach((v, k) => body[k] = v);
  try { const d = await api('../api/admin.php?action=save-settings', { method: 'POST', body }); toast(d.message, 'success'); }
  catch (err) { toast(err.message, 'error'); }
});

function counterRow(c) {
  return `<div class="queue-card flat" style="margin:0;">
    <div class="ticket-no small">#${c.id}</div>
    <div class="meta"><strong>${escapeHtml(c.name)}</strong><br><span class="muted">${escapeHtml(c.location || 'no location')} · ${c.staff_count} staff</span></div>
    <div class="actions">
      <button class="btn btn-ghost btn-sm go-edit-counter" data-id="${c.id}" data-name="${escapeHtml(c.name)}" data-loc="${escapeHtml(c.location || '')}" data-active="${c.is_active}">Edit</button>
      ${Number(c.is_active) ? '' : '<span class="badge badge--pending">off</span>'}
      <button class="btn btn-danger btn-sm go-del-counter" data-id="${c.id}">✕</button>
    </div>
  </div>`;
}
function serviceRow(s) {
  return `<div class="queue-card flat" style="margin:0;">
    <div class="meta">
      <strong>${escapeHtml(s.name)}</strong>
      <br><span class="muted">${Math.round(s.avg_service_time_sec / 60)} min avg · ${escapeHtml(s.description || 'no description')}</span>
    </div>
    <div class="actions">
      <button class="btn btn-ghost btn-sm go-edit-service" data-id="${s.id}" data-name="${escapeHtml(s.name)}" data-desc="${escapeHtml(s.description || '')}" data-time="${s.avg_service_time_sec}" data-active="${s.is_active}">Edit</button>
      <button class="btn btn-danger btn-sm go-del-service" data-id="${s.id}">✕</button>
    </div>
  </div>`;
}

async function loadLists() {
  try {
    const [c, s] = await Promise.all([
      api('../api/admin.php?action=counters'),
      api('../api/admin.php?action=services')
    ]);
    document.getElementById('counter-list').innerHTML = c.data.length
      ? c.data.map(counterRow).join('') : '<p class="empty">No counters yet.</p>';
    document.getElementById('service-list').innerHTML = s.data.length
      ? s.data.map(serviceRow).join('') : '<p class="empty">No services yet.</p>';
  } catch (e) { toast(e.message, 'error'); }
}

async function createOrUpdateCounter(payload) {
  try { const d = await api('../api/admin.php?action=save-counter', { method: 'POST', body: payload }); toast(d.message, 'success'); loadLists(); }
  catch (err) { toast(err.message, 'error'); }
}
async function createOrUpdateService(payload) {
  try { const d = await api('../api/admin.php?action=save-service', { method: 'POST', body: payload }); toast(d.message, 'success'); loadLists(); }
  catch (err) { toast(err.message, 'error'); }
}

document.addEventListener('click', (e) => {
  if (e.target.closest('#new-counter')) {
    const name = prompt('Counter name:');
    if (name && name.trim()) { const loc = prompt('Location (optional):'); createOrUpdateCounter({ name: name.trim(), location: loc || '', is_active: 1 }); }
  }
  if (e.target.closest('#new-service')) {
    const name = prompt('Service name:');
    if (name && name.trim()) {
      const mins = prompt('Average minutes per customer (default 5):', '5');
      createOrUpdateService({ name: name.trim(), description: '', avg_service_time_sec: Math.max(1, Number(mins) || 5) * 60, is_active: 1 });
    }
  }
  const ec = e.target.closest('.go-edit-counter');
  if (ec) {
    const name = prompt('Counter name:', ec.dataset.name);
    if (name && name.trim()) createOrUpdateCounter({ id: ec.dataset.id, name: name.trim(), location: prompt('Location:', ec.dataset.loc), is_active: ec.dataset.active });
    return;
  }
  const dc = e.target.closest('.go-del-counter');
  if (dc) {
    if (confirm('Remove this counter?')) api('../api/admin.php?action=delete-counter', { method: 'POST', body: { id: dc.dataset.id } }).then(d => { toast(d.message, 'success'); loadLists(); }).catch(e => toast(e.message, 'error'));
    return;
  }
  const es = e.target.closest('.go-edit-service');
  if (es) {
    const name = prompt('Service name:', es.dataset.name);
    if (name && name.trim()) {
      const mins = prompt('Average minutes per customer:', Math.round(Number(es.dataset.time) / 60) || 5);
      const desc = prompt('Description (optional):', es.dataset.desc);
      createOrUpdateService({ id: es.dataset.id, name: name.trim(), description: desc || '', avg_service_time_sec: Math.max(1, Number(mins) || 5) * 60, is_active: es.dataset.active });
    }
    return;
  }
  const ds = e.target.closest('.go-del-service');
  if (ds) {
    if (confirm('Remove this service? Its tickets will keep their history.')) api('../api/admin.php?action=delete-service', { method: 'POST', body: { id: ds.dataset.id } }).then(d => { toast(d.message, 'success'); loadLists(); }).catch(e => toast(e.message, 'error'));
  }
});

loadSettings();
loadLists();
</script>
</body>
</html>