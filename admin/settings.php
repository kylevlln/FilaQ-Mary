<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_once __DIR__ . '/../includes/icons.php';

$user = require_login();
if ($user['role'] !== 'ADMIN') {
    redirect('../staff/index.php');
}
$initial = strtoupper(substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Counters &amp; Services · FilaQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=4">
</head>
<body>
<div class="mobile-bar">
  <button class="btn btn-ico" id="nav-toggle" type="button" aria-label="Open menu"><?php echo icon('menu', 18); ?></button>
  <a class="brand" href="index.php"><span class="dot"></span>FilaQ · Admin</a>
</div>

<div class="app" id="app">
  <aside class="app-side" id="sidebar">
    <a class="side-brand" href="index.php"><span class="dot"></span>FilaQ<span class="v-tag">Admin</span></a>

    <p class="side-group">Main</p>
    <a class="side-link" href="index.php"><?php echo icon('dashboard'); ?> Dashboard</a>

    <p class="side-group">Admin</p>
    <a class="side-link" href="users.php"><?php echo icon('users'); ?> Users</a>
    <a class="side-link active" href="settings.php"><?php echo icon('settings'); ?> Counters &amp; services</a>
    <a class="side-link" href="logs.php"><?php echo icon('activity'); ?> Activity log</a>

    <p class="side-group">Queue</p>
    <a class="side-link" href="../staff/index.php"><?php echo icon('bell'); ?> Queue desk</a>
    <a class="side-link" href="../display.php" target="_blank"><?php echo icon('monitor'); ?> Live board</a>

    <div class="side-user">
      <span class="avatar"><?php echo e($initial); ?></span>
      <span class="who"><span class="name"><?php echo e($user['full_name']); ?></span><span class="role">Admin</span></span>
      <a class="out" href="../logout.php" aria-label="Sign out"><?php echo icon('logout', 17); ?></a>
    </div>
  </aside>

  <main class="main">
    <div class="main-inner">
      <div class="page-head">
        <div>
          <p class="page-kicker">Administration</p>
          <h1 class="page-title">Counters &amp; services</h1>
          <p class="page-lead">Configure what you offer, where it is served, and how the queue behaves.</p>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">System settings</h2>
            <p class="panel-desc">Organization identity, operating hours, and queue behaviour.</p>
          </div>
        </div>
        <div id="settings-form"><div class="skeleton" style="height:180px;"></div></div>
      </div>

      <div class="grid-2">
        <div class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Counters</h2>
              <p class="panel-desc">Physical windows where staff serve tickets.</p>
            </div>
            <button class="btn btn-primary btn-sm" id="new-counter">+ Add counter</button>
          </div>
          <div id="counter-list"></div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Services</h2>
              <p class="panel-desc">What visitors line up for, each with its ticket prefix.</p>
            </div>
            <button class="btn btn-primary btn-sm" id="new-service">+ Add service</button>
          </div>
          <div id="service-list"></div>
        </div>
      </div>
    </div>
  </main>
</div>

<div id="drawer-root"></div>

<script src="../assets/js/main.js?v=3"></script>
<script>
let countersData = [];
let servicesData = [];

function openDrawer(html) {
  const root = document.getElementById('drawer-root');
  root.innerHTML = `
    <div class="drawer-scrim" id="drawer-scrim">
      <div class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
        <div class="drawer-head">
          <h3 id="drawer-title">Untitled</h3>
          <button class="btn btn-ico btn-ghost btn-sm" type="button" aria-label="Close">${icon('x', 16)}</button>
        </div>
        ${html}
      </div>
    </div>`;
  const close = () => root.innerHTML = '';
  root.querySelector('#drawer-scrim').addEventListener('mousedown', (e) => { if (e.target.id === 'drawer-scrim') close(); });
  root.querySelector('.drawer-head .btn-ico').addEventListener('click', close);
  const first = root.querySelector('.input');
  if (first) setTimeout(() => first.focus(), 60);
  return { rootEl: root, close };
}

async function loadSettings() {
  try {
    const d = await api('../api/admin.php?action=settings');
    const s = d.data;
    document.getElementById('settings-form').innerHTML = `
      <form id="set-form">
        <div class="field-grid">
          <div class="field"><label for="f-org">Organization name</label><input class="input" id="f-org" name="org_name" value="${escapeHtml(s.org_name || '')}"></div>
          <div class="field"><label for="f-prefix">Queue prefix</label><input class="input" id="f-prefix" name="queue_prefix" value="${escapeHtml(s.queue_prefix || 'FQ')}" maxlength="4" placeholder="FQ">
          <span class="input-help">Fallback code used when a service has no prefix of its own.</span></div>
          <div class="field"><label for="f-open">Open time</label><input class="input" id="f-open" name="open_time" type="time" value="${escapeHtml(s.open_time || '08:00')}"></div>
          <div class="field"><label for="f-close">Close time</label><input class="input" id="f-close" name="close_time" type="time" value="${escapeHtml(s.close_time || '17:00')}"></div>
        </div>
        <div class="field"><label for="f-ann">Public announcement</label><input class="input" id="f-ann" name="announcement" value="${escapeHtml(s.announcement || '')}" placeholder="Shown on the live board — optional">
        <span class="input-help">Leave empty to hide it.</span></div>
        <div class="field"><label><input type="checkbox" name="allow_registration" value="1" ${String(s.allow_registration) === '1' ? 'checked' : ''}> Allow new account registration</label></div>
        <div class="row">
          <button class="btn btn-primary" type="submit">Save settings</button>
        </div>
      </form>`;
  } catch (e) { document.getElementById('settings-form').innerHTML = `<div class="alert alert-error" role="alert">${escapeHtml(e.message)}</div>`; }
}
document.addEventListener('submit', async (e) => {
  if (e.target.id !== 'set-form') return;
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = {};
  fd.forEach((v, k) => body[k] = v);
  try { const d = await api('../api/admin.php?action=save-settings', { method: 'POST', body }); toast(d.message, 'success'); }
  catch (err) { toast(err.message, 'error'); }
});

function counterRow(c, i) {
  return `
    <div class="srow">
      <span class="code-pill">#${c.id}</span>
      <div class="main">
        <span class="t">${escapeHtml(c.name)}</span>
        <span class="s">${escapeHtml(c.location || 'no location')} · ${c.staff_count} staff assigned</span>
      </div>
      ${Number(c.is_active) ? '' : '<span class="badge badge--pending">off</span>'}
      <div class="acts">
        <button class="btn btn-ghost btn-sm go-edit-counter" data-id="${c.id}">Edit</button>
        <button class="btn btn-danger btn-sm go-del-counter" data-id="${c.id}" aria-label="Remove counter">${icon('x', 14)}</button>
      </div>
    </div>`;
}
function serviceRow(s, i) {
  return `
    <div class="srow">
      <span class="code-pill">${escapeHtml(s.code_prefix || '—')}</span>
      <div class="main">
        <span class="t">${escapeHtml(s.name)}</span>
        <span class="s">${Math.round(s.avg_service_time_sec / 60)} min avg · ${escapeHtml(s.description || 'no description')}</span>
      </div>
      ${Number(s.is_active) ? '' : '<span class="badge badge--pending">off</span>'}
      <div class="acts">
        <button class="btn btn-ghost btn-sm go-edit-service" data-id="${s.id}">Edit</button>
        <button class="btn btn-danger btn-sm go-del-service" data-id="${s.id}" aria-label="Remove service">${icon('x', 14)}</button>
      </div>
    </div>`;
}

function derivePrefix(name) {
  const s = (name || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  return (s || 'SVC').slice(0, 3);
}

async function loadLists() {
  try {
    const [c, s] = await Promise.all([
      api('../api/admin.php?action=counters'),
      api('../api/admin.php?action=services')
    ]);
    countersData = c.data;
    servicesData = s.data;
    document.getElementById('counter-list').innerHTML = c.data.length
      ? '<div class="rows">' + c.data.map(counterRow).join('') + '</div>'
      : '<div class="empty-state"><p class="empty-title">No counters yet</p><p class="empty-sub">Add a window so staff have a place to serve tickets.</p></div>';
    document.getElementById('service-list').innerHTML = s.data.length
      ? '<div class="rows">' + s.data.map(serviceRow).join('') + '</div>'
      : '<div class="empty-state"><p class="empty-title">No services yet</p><p class="empty-sub">Add the services your office offers so visitors can take a number.</p></div>';
  } catch (e) { toast(e.message, 'error'); }
}

function counterDrawer(c) {
  const isEdit = !!c;
  const h = c ? c : { name: '', location: '', is_active: 1 };
  openDrawer(`
    <form id="counter-form">
      <div class="field"><label for="cf-name">Counter name</label><input class="input" id="cf-name" name="name" value="${escapeHtml(h.name)}" required placeholder="e.g. Window 1"></div>
      <div class="field"><label for="cf-loc">Location (optional)</label><input class="input" id="cf-loc" name="location" value="${escapeHtml(h.location || '')}" placeholder="e.g. Ground floor, intake area"></div>
      <div class="field"><label><input type="checkbox" name="active" value="1" ${Number(h.is_active) ? 'checked' : ''}> Active</label></div>
      <div class="drawer-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-primary" type="submit">${isEdit ? 'Save changes' : 'Add counter'}</button>
      </div>
    </form>`);
  const root = document.getElementById('drawer-root');
  root.querySelector('[data-close]').addEventListener('click', () => {
    root.querySelector('#drawer-scrim') && (root.innerHTML = '');
  });
  root.querySelector('#counter-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const payload = {
      id: isEdit ? c.id : undefined,
      name: root.querySelector('#cf-name').value.trim(),
      location: root.querySelector('#cf-loc').value.trim(),
      is_active: root.querySelector('[name="active"]').checked ? 1 : 0
    };
    createOrUpdateCounter(payload);
    root.innerHTML = '';
  });
}

function serviceDrawer(s) {
  const isEdit = !!s;
  const h = s ? s : { name: '', code_prefix: '', description: '', avg_service_time_sec: 300, is_active: 1 };
  openDrawer(`
    <form id="service-form">
      <div class="field"><label for="sf-name">Service name</label><input class="input" id="sf-name" name="name" value="${escapeHtml(h.name)}" required placeholder="e.g. Certificate of Residency"></div>
      <div class="field"><label for="sf-prefix">Ticket code prefix</label><input class="input" id="sf-prefix" name="code_prefix" value="${escapeHtml(h.code_prefix || '')}" maxlength="4" style="text-transform:uppercase;" placeholder="${escapeHtml(derivePrefix(h.name))}">
      <span class="input-help">2-4 letters or numbers, e.g. COR. Tickets will read like COR-001.</span></div>
      <div class="field"><label for="sf-min">Average minutes per customer</label><input class="input" id="sf-min" name="mins" type="number" min="1" value="${Math.max(1, Math.round(Number(h.avg_service_time_sec || 300) / 60))}">
      <span class="input-help">Used to estimate wait times for this service.</span></div>
      <div class="field"><label for="sf-desc">Description (optional)</label><textarea class="input" id="sf-desc" name="description">${escapeHtml(h.description || '')}</textarea></div>
      <div class="field"><label><input type="checkbox" name="active" value="1" ${Number(h.is_active) ? 'checked' : ''}> Active</label></div>
      <div class="drawer-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-primary" type="submit">${isEdit ? 'Save changes' : 'Add service'}</button>
      </div>
    </form>`);
  const root = document.getElementById('drawer-root');
  root.querySelector('[data-close]').addEventListener('click', () => { root.innerHTML = ''; });
  root.querySelector('#service-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const rawPrefix = root.querySelector('#sf-prefix').value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
    const mins = Math.max(1, Number(root.querySelector('#sf-min').value) || 5);
    const payload = {
      id: isEdit ? s.id : undefined,
      name: root.querySelector('#sf-name').value.trim(),
      code_prefix: rawPrefix || derivePrefix(root.querySelector('#sf-name').value.trim()),
      description: root.querySelector('#sf-desc').value.trim(),
      avg_service_time_sec: mins * 60,
      is_active: root.querySelector('[name="active"]').checked ? 1 : 0
    };
    createOrUpdateService(payload);
    root.innerHTML = '';
  });
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
  if (e.target.closest('#new-counter')) return counterDrawer(null);
  if (e.target.closest('#new-service')) return serviceDrawer(null);

  const ec = e.target.closest('.go-edit-counter');
  if (ec) { const c = countersData.find(x => x.id === Number(ec.dataset.id)); return counterDrawer(c); }

  const dc = e.target.closest('.go-del-counter');
  if (dc) {
    if (confirm('Remove this counter?')) api('../api/admin.php?action=delete-counter', { method: 'POST', body: { id: dc.dataset.id } }).then(d => { toast(d.message, 'success'); loadLists(); }).catch(e => toast(e.message, 'error'));
    return;
  }

  const es = e.target.closest('.go-edit-service');
  if (es) { const s = servicesData.find(x => x.id === Number(es.dataset.id)); return serviceDrawer(s); }

  const ds = e.target.closest('.go-del-service');
  if (ds) {
    if (confirm('Remove this service? Its tickets will keep their history.')) api('../api/admin.php?action=delete-service', { method: 'POST', body: { id: ds.dataset.id } }).then(d => { toast(d.message, 'success'); loadLists(); }).catch(e => toast(e.message, 'error'));
  }
});

document.getElementById('nav-toggle').addEventListener('click', () => document.getElementById('app').classList.toggle('side-open'));
document.getElementById('sidebar').addEventListener('click', (e) => {
  if (e.target.closest('.side-link')) document.getElementById('app').classList.remove('side-open');
});

loadSettings();
loadLists();
</script>
</body>
</html>