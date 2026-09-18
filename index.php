<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/queue.php';
require_once __DIR__ . '/includes/icons.php';
$user = current_user();
$openTime = setting('open_time', '08:00');
$closeTime = setting('close_time', '17:00');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> · Queue Management for Barangay Services</title>
<link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body class="landing-page">
<nav class="land-nav">
  <div class="land-nav-inner">
    <a class="brand" href="index.php"><span class="dot"></span><span class="brand-name">FilaQ</span></a>
    <div class="links">
      <a class="live-link" href="display.php">Live board</a>
      <?php if ($user): ?>
        <a class="btn btn-primary btn-sm" href="<?php echo strtolower($user['role']) === 'admin' ? 'admin/index.php' : (strtolower($user['role']) === 'staff' ? 'staff/index.php' : 'customer/index.php'); ?>">Open dashboard</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="login.php">Staff sign in</a>
        <a class="btn btn-primary btn-sm" href="register.php">Take a number</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="land-hero">
  <div class="land-hero-inner">
    <div class="land-copy">
      <p class="land-kicker">Queue management for government service windows</p>
      <h1 class="land-title">Give every visitor a number, show them their wait, and call people to the counter in order.</h1>
      <p class="land-lead">FilaQ is the queue system that runs at your front desk. Visitors take a ticket for the service they came for, staff call the next number on a display, and everyone knows where they stand.</p>
      <div class="land-cta">
        <?php if ($user): ?>
          <a class="btn btn-primary btn-lg" href="<?php echo strtolower($user['role']) === 'admin' ? 'admin/index.php' : (strtolower($user['role']) === 'staff' ? 'staff/index.php' : 'customer/index.php'); ?>">Open dashboard</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="register.php">Take a number</a>
          <a class="btn btn-ghost btn-lg" href="login.php">Staff sign in</a>
        <?php endif; ?>
      </div>
      <p class="land-hours"><span class="ic"><?php echo icon('clock', 18); ?></span> Open <?php echo e($openTime); ?> · <?php echo e($closeTime); ?> on service days</p>
    </div>

    <div class="land-demo">
      <p class="land-demo-label">What every ticket looks like</p>
      <div class="demo-ticket demo-live">
        <div class="demo-ticket-top">
          <span class="demo-org">Barangay Hall</span>
          <span class="demo-badge">being served</span>
        </div>
        <div class="demo-code"><?php echo icon('ticket', 22); ?> COR-014</div>
        <div class="demo-name">Certificate of Residency</div>
        <div class="demo-meta">Position 4 · ~12 min to your turn · Window 1</div>
      </div>
      <div class="demo-ticket demo-next">
        <div class="demo-code">BC-007</div>
        <div class="demo-name">Barangay Clearance</div>
        <div class="demo-meta">Next in line · Window 1</div>
      </div>
    </div>
  </div>
</header>

<section class="land-strip" aria-label="At a glance">
  <div class="land-inner land-strip-inner">
    <div class="land-strip-item"><span class="ic"><?php echo icon('ticket'); ?></span><span>A number in hand instead of a crowd at the window.</span></div>
    <div class="land-strip-item"><span class="ic"><?php echo icon('clock'); ?></span><span>Set the counter speed, and wait times appear by themselves.</span></div>
    <div class="land-strip-item"><span class="ic"><?php echo icon('monitor'); ?></span><span>One screen the staff updates, that everyone can see.</span></div>
  </div>
</section>

<section class="land-section" id="how">
  <div class="land-inner">
    <p class="land-kicker">How it works</p>
    <h2 class="land-h2">Three steps, from the door to the desk.</h2>
    <div class="land-steps">
      <div class="land-step">
        <span class="land-num">1</span>
        <h3>Take a number</h3>
        <p>At the intake desk, visitors choose what they came for. FilaQ hands them a printed ticket such as <strong>COR-014</strong> for a Certificate of Residency, with the full service name on it.</p>
      </div>
      <div class="land-step">
        <span class="land-num">2</span>
        <h3>Watch your place</h3>
        <p>Cards on the board count how many people are ahead and how long that may take, so waiting is never a mystery. Residents can also check their spot by tracking code.</p>
      </div>
      <div class="land-step">
        <span class="land-num">3</span>
        <h3>Go when it's your turn</h3>
        <p>Staff call <strong>COR-014</strong> to the window and mark service complete with one click. The display moves on to the next number, one at a time, in order.</p>
      </div>
    </div>
  </div>
</section>

<section class="land-section land-trust">
  <div class="land-inner trust-band">
    <span class="trust-ic"><?php echo icon('lock'); ?></span>
    <div>
      <h2 class="land-h2">Runs entirely on your own machine.</h2>
      <p>FilaQ works on your office computer and local network with no internet connection and no third-party service. Resident data never leaves the barangay hall or clinic it belongs to.</p>
      <a class="link" href="privacy.php">Read the privacy practices</a>
    </div>
  </div>
</section>

<section class="land-section">
  <div class="land-inner">
    <p class="land-kicker">What you get</p>
    <h2 class="land-h2">Small details that make a frontline desk calmer.</h2>
    <div class="land-grid">
      <div class="land-card"><span class="ic"><?php echo icon('check'); ?></span><h3>Plain-language names</h3><p>Codes are short for staff, but every ticket also carries the full service name.</p></div>
      <div class="land-card"><span class="ic"><?php echo icon('clock'); ?></span><h3>Honest wait estimates</h3><p>Based on the real speed of your counters and adjusted as the day goes on.</p></div>
      <div class="land-card"><span class="ic"><?php echo icon('bell'); ?></span><h3>One-click calling</h3><p>Call, skip, and complete a service with a single click or a keyboard key.</p></div>
      <div class="land-card"><span class="ic"><?php echo icon('activity'); ?></span><h3>A record for officials</h3><p>Daily counts and wait-time trends for supervisors, in plain tables and charts.</p></div>
    </div>
  </div>
</section>

<footer class="land-footer">
  <div class="land-inner">
    <p><strong class="serif footer-name"><?php echo APP_NAME; ?></strong> · <?php echo APP_TAGLINE; ?></p>
    <p class="footer-links"><a href="privacy.php">Privacy</a> · <a href="terms.php">Terms</a> · <a href="display.php">Live board</a></p>
    <p class="footer-copy">© <?php echo date('Y'); ?> FilaQ · version <?php echo APP_VERSION; ?></p>
  </div>
</footer>

<script src="assets/js/main.js?v=2"></script>
</body>
</html>