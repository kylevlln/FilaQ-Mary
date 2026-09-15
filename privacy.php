<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/queue.php';
$orgName = setting('org_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy — <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div></div>
<nav class="nav">
  <a class="brand" href="index.php"><span class="dot"></span><?php echo APP_NAME; ?></a>
  <div class="links"><a href="index.php">Home</a><a href="display.php">Live Board</a></div>
</nav>

<main class="section">
  <div class="section-inner">
    <p class="section-kicker">Legal</p>
    <h1 class="section-title">Privacy Policy</h1>
    <p class="muted">Last updated: <?php echo date('F j, Y'); ?></p>

    <div class="card flat" style="line-height:1.8;">
      <h2>1. What FilaQ is</h2>
      <p>FilaQ is a queue management system operated by <strong><?php echo e($orgName); ?></strong>. It helps people wait for services in an organized way and helps staff serve them efficiently.</p>

      <h2>2. Information we collect</h2>
      <p>FilaQ collects the minimum information needed to operate a queue:</p>
      <ul>
        <li><strong>Account data:</strong> full name, username, email, and (optionally) phone number when you create an account.</li>
        <li><strong>Queue data:</strong> your issued ticket number, the service you requested, and timestamps such as when your ticket was issued, called, and completed.</li>
        <li><strong>Activity data:</strong> a record of actions such as signing in, taking a number, or calling a number. This is used purely for administration and monitoring.</li>
        <li><strong>Technical data:</strong> your IP address when performing monitored actions.</li>
      </ul>

      <h2>3. How we use your information</h2>
      <p>We use this information only to provide, secure, and improve the queue service — including issuing tickets, calculating estimated wait times, tracking queue status, monitoring activity, and keeping administrative records. We do not sell or rent your information to anyone.</p>

      <h2>4. Local &amp; offline operation</h2>
      <p>FilaQ is designed to run locally on the organization's own computer (for example, on XAMPP). This means your data generally stays on the organization's premises and is not transmitted to third-party servers.</p>

      <h2>5. Data retention</h2>
      <p>Queue tickets and activity logs are kept for as long as they are useful for administration and reporting. If you would like your personal account data removed, contact the administrator.</p>

      <h2>6. Your choices</h2>
      <p>You may choose to use FilaQ without creating an account — a guest can simply take a number and use the tracking code printed on their ticket. You may also request that your account be deleted.</p>

      <h2>7. Security</h2>
      <p>Passwords are stored as secure hashes, prepared statements are used for all database queries, and staff accounts must be approved by an administrator. No system is completely secure, but we take reasonable measures appropriate to a local, non-public service.</p>

      <h2>8. Changes to this policy</h2>
      <p>If we change this policy, the "last updated" date above will be revised. Continued use of FilaQ after changes means you accept the updated policy.</p>

      <h2>9. Contact</h2>
      <p>Questions about this policy should be directed to the FilaQ administrator at <strong><?php echo e($orgName); ?></strong>.</p>
    </div>
  </div>
</main>

<footer style="padding:2rem 1.5rem; text-align:center; color:var(--ink-soft); font-size:.85rem; border-top:1px solid var(--line);">
  <a href="privacy.php" style="margin:0 .4rem;">Privacy Policy</a>
  <a href="terms.php" style="margin:0 .4rem;">Terms of Service</a>
  <a href="index.php" style="margin:0 .4rem;">Back to Home</a>
</footer>
</body>
</html>