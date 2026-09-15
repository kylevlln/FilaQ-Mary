<?php
require_once __DIR__ . '/config/config.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> — <?php echo APP_TAGLINE; ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div></div>

<nav class="nav">
  <a class="brand" href="index.php"><span class="dot"></span><?php echo APP_NAME; ?></a>
  <div class="links">
    <a href="display.php">Live Board</a>
    <?php if ($user): ?>
      <a href="<?php echo strtolower($user['role']) === 'admin' ? 'admin/index.php' : (strtolower($user['role']) === 'staff' ? 'staff/index.php' : 'customer/index.php'); ?>">My Dashboard</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary btn-sm">Sign In</a>
      <a href="register.php" class="btn btn-ghost btn-sm">Get Started</a>
    <?php endif; ?>
  </div>
</nav>

<header class="hero">
  <h1 class="hero-title" aria-label="FilaQ">
    <?php foreach (str_split('FilaQ') as $i => $ch): ?>
      <span class="char" style="animation-delay: <?php echo $i * .14; ?>s"><?php echo e($ch); ?></span>
    <?php endforeach; ?>
  </h1>
  <p class="hero-tagline">Be seen. Be served. Beautifully.</p>

  <div style="display:flex; gap:.8rem; flex-wrap:wrap; justify-content:center; opacity:0; animation: rise .8s 1.1s var(--ease) forwards;">
    <?php if ($user): ?>
      <a class="btn btn-primary btn-lg" href="<?php echo strtolower($user['role']) === 'admin' ? 'admin/index.php' : (strtolower($user['role']) === 'staff' ? 'staff/index.php' : 'customer/index.php'); ?>">Open Dashboard</a>
    <?php else: ?>
      <a class="btn btn-primary btn-lg" href="register.php">Take a Number</a>
      <a class="btn btn-ghost btn-lg" href="login.php">Sign In</a>
    <?php endif; ?>
  </div>

  <div class="queue-preview" aria-hidden="true">
    <div class="ticket">GEN-012</div>
    <div class="ticket">DOC-005</div>
    <div class="ticket pulse-core">GEN-013</div>
    <div class="ticket">PAY-009</div>
  </div>

  <div class="feature-grid">
    <div class="feature-chip" style="animation-delay:1.2s"><span class="ic orange">🎟️</span> Instant queue numbers</div>
    <div class="feature-chip" style="animation-delay:1.3s"><span class="ic cyan">⏱️</span> Live wait estimates</div>
    <div class="feature-chip" style="animation-delay:1.4s"><span class="ic pink">🔔</span> Call · skip · serve</div>
    <div class="feature-chip" style="animation-delay:1.5s"><span class="ic orange">📊</span> Admin monitoring</div>
    <div class="feature-chip" style="animation-delay:1.6s"><span class="ic cyan">🔒</span> Offline &amp; local</div>
    <div class="feature-chip" style="animation-delay:1.7s"><span class="ic pink">✅</span> Staff approvals &amp; logs</div>
  </div>
</header>

<section class="section" id="about">
  <div class="section-inner">
    <p class="section-kicker">What is FilaQ?</p>
    <h2 class="section-title">A calmer way to queue.</h2>
    <p class="lead">FilaQ is a smart queue management system designed for small businesses, school registrars, and office front desks. Visitors take a number, see exactly how long they might wait, and staff call them with a single click — no shouting, no crowding, no confusion.</p>

    <div class="info-cols">
      <div class="info-col"><span class="num">01</span><h3>For waiting</h3><p>Customers and students get a clear ticket with an honest estimated wait time and can track their spot from any phone on the network.</p></div>
      <div class="info-col"><span class="num">02</span><h3>For serving</h3><p>Staff see the full line, call the next person, skip who they must, and mark service complete in one clean flow.</p></div>
      <div class="info-col"><span class="num">03</span><h3>For supervising</h3><p>Admins approve staff accounts, watch activity logs, and review daily records and wait-time trends on a dashboard.</p></div>
    </div>
  </div>
</section>

<section class="section" style="background: linear-gradient(180deg, transparent, var(--blush), transparent);">
  <div class="section-inner" style="text-align:center;">
    <p class="section-kicker">Designed for real front desks</p>
    <h2 class="section-title">Runs completely on your own machine.</h2>
    <p class="lead" style="max-width:640px; margin:0 auto;">FilaQ runs on XAMPP with a relational, normalized database — no internet or third-party service required. Your queue data stays inside your office.</p>
    <div class="row" style="justify-content:center; margin-top:2rem;">
      <?php if (!$user): ?><a href="register.php" class="btn btn-primary btn-lg">Create Your Account</a><?php endif; ?>
      <a href="display.php" class="btn btn-cool btn-lg">Preview the Live Board</a>
    </div>
  </div>
</section>

<footer style="padding:2.5rem 1.5rem; text-align:center; color:var(--ink-soft); font-size:.85rem; border-top:1px solid var(--line);">
  <p style="margin-bottom:.5rem;"><strong class="serif" style="font-size:1.1rem; color:var(--ink);"><?php echo APP_NAME; ?></strong> — <?php echo APP_TAGLINE; ?></p>
  <p style="margin:0;">
    <a href="privacy.php" style="margin:0 .4rem;">Privacy Policy</a>
    <a href="terms.php" style="margin:0 .4rem;">Terms of Service</a>
    <a href="display.php" style="margin:0 .4rem;">Live Board</a>
  </p>
  <p style="margin-top:1rem;">© <?php echo date('Y'); ?> FilaQ · version <?php echo APP_VERSION; ?></p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>