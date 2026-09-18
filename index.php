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
<title><?php echo APP_NAME; ?> · Queue management for barangay service windows</title>
<link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body class="landing-page">
<nav class="land-nav">
  <div class="land-nav-inner">
    <a class="brand" href="index.php"><span class="dot"></span><span class="brand-name">FilaQ</span></a>
    <div class="links">
      <a class="btn btn-ghost btn-sm" href="display.php">Live board</a>
      <?php if ($user): ?>
        <a class="btn btn-primary btn-sm" href="<?php echo strtolower($user['role']) === 'admin' ? 'admin/index.php' : (strtolower($user['role']) === 'staff' ? 'staff/index.php' : 'customer/index.php'); ?>">Open dashboard</a>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="customer/index.php">Take a number</a>
        <a class="btn btn-ghost btn-sm" href="login.php">Staff sign in</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="land-hero">
  <div class="land-hero-inner">
    <div class="land-copy">
      <p class="land-kicker">Queue management for government service windows</p>
      <h1 class="land-title">Take a number. Watch your place. Go when it&rsquo;s your turn.</h1>
      <p class="land-lead">FilaQ runs the line at your front desk — visitors pick the service they came for, staff call the next number on a shared screen, and everyone knows exactly where they stand.</p>
      <div class="land-cta">
        <?php if ($user): ?>
          <a class="btn btn-primary btn-lg" href="<?php echo strtolower($user['role']) === 'admin' ? 'admin/index.php' : (strtolower($user['role']) === 'staff' ? 'staff/index.php' : 'customer/index.php'); ?>">Open dashboard</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="customer/index.php">Take a number</a>
          <a class="btn btn-ghost btn-lg" href="login.php">Staff sign in</a>
        <?php endif; ?>
      </div>
      <p class="land-hours"><span class="ic"><?php echo icon('clock', 17); ?></span> Open <?php echo e($openTime); ?> · <?php echo e($closeTime); ?> on service days</p>
    </div>

    <div class="land-demo">
      <p class="land-demo-label">What a ticket looks like</p>
      <div class="demo-ticket demo-live">
        <div class="demo-ticket-top">
          <span class="demo-org">Barangay Hall</span>
          <span class="demo-badge"><span class="demo-dot" aria-hidden="true"></span> being served</span>
        </div>
        <div class="demo-code"><?php echo icon('ticket', 20); ?> COR-014</div>
        <div class="demo-name">Certificate of Residency</div>
        <div class="demo-meta">4 ahead · ~12 min to your turn · Window 1</div>
      </div>
      <div class="demo-ticket">
        <div class="demo-code">BC-007</div>
        <div class="demo-name">Barangay Clearance</div>
        <div class="demo-meta">Next in line · Window 1</div>
      </div>
    </div>
  </div>
</header>

<section class="land-strip" aria-label="At a glance">
  <div class="land-inner land-strip-inner">
    <div class="land-strip-item"><span class="ic"><?php echo icon('ticket', 17); ?></span><span>A number in hand, not a crowd at the window.</span></div>
    <div class="land-strip-item"><span class="ic"><?php echo icon('clock', 17); ?></span><span>Wait times appear from the counters&rsquo; real speed.</span></div>
    <div class="land-strip-item"><span class="ic"><?php echo icon('monitor', 17); ?></span><span>One screen the staff updates, that everyone can see.</span></div>
  </div>
</section>

<section class="land-section" id="how">
  <div class="land-inner">
    <p class="land-kicker">How it works</p>
    <h2 class="land-h2">Three steps, from the door to the desk.</h2>
    <div class="land-steps">
      <div class="land-step">
        <span class="land-num" aria-hidden="true">1</span>
        <h3>Take a number</h3>
        <p>Visitors choose what they came for at the intake screen. FilaQ hands out a ticket such as COR-014 with the full service name on it.</p>
      </div>
      <div class="land-step">
        <span class="land-num" aria-hidden="true">2</span>
        <h3>Watch your place</h3>
        <p>Cards on the board count who is ahead and how long that may take. A tracking code on the ticket lets residents check from anywhere.</p>
      </div>
      <div class="land-step">
        <span class="land-num" aria-hidden="true">3</span>
        <h3>Go when you are called</h3>
        <p>Staff call the next number on the display and mark the service complete with one click. The line moves in order, one at a time.</p>
      </div>
    </div>
  </div>
</section>

<section class="land-section">
  <div class="land-inner trust-band">
    <span class="trust-ic"><?php echo icon('lock'); ?></span>
    <div>
      <h2 class="land-h2">Runs entirely on your own machine.</h2>
      <p>FilaQ works on your office computer and local network with no internet connection and no third-party service. Resident data never leaves the barangay hall it belongs to.</p>
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
      <div class="land-card"><span class="ic"><?php echo icon('clock'); ?></span><h3>Honest wait estimates</h3><p>Based on the real speed of your counters, adjusted as the day goes on.</p></div>
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

<script src="assets/js/main.js?v=3"></script>
</body>
</html>