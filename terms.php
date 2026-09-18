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
<title>Terms of Service · <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=3">
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
    <h1 class="section-title">Terms of Service</h1>
    <p class="muted">Last updated: <?php echo date('F j, Y'); ?></p>

    <div class="card flat" style="line-height:1.8;">
      <h2>1. Agreement</h2>
      <p>By creating an account with, or using, FilaQ (operated by <strong><?php echo e($orgName); ?></strong>), you agree to these terms.</p>

      <h2>2. Using FilaQ</h2>
      <ul>
        <li>You may use FilaQ as a <strong>customer or student</strong> to take queue numbers and track your place in line.</li>
        <li>You may use FilaQ as <strong>staff</strong> only after an administrator approves your account.</li>
        <li>You may use FilaQ as an <strong>administrator</strong> only if the organization has granted you that role.</li>
      </ul>

      <h2>3. Acceptable use</h2>
      <p>You agree not to:</p>
      <ul>
        <li>Attempt to access another user's account or data.</li>
        <li>Manipulate, skip, or complete tickets you are not authorized to manage.</li>
        <li>Take a queue number maliciously or in a way that disrupts service.</li>
        <li>Introduce abusive, offensive, or misleading information.</li>
        <li>Attempt to break, overload, or bypass the system's security.</li>
      </ul>

      <h2>4. Staff responsibility</h2>
      <p>Staff are responsible for using the queue tools correctly and honestly. Calling, skipping, and completing tickets are recorded in an activity log that administrators can review.</p>

      <h2>5. Service availability</h2>
      <p>FilaQ is provided as-is and depends on the organization's local server. Service may be interrupted for maintenance or due to technical failure. Estimated wait times are estimates only and are not guaranteed.</p>

      <h2>6. Account suspension</h2>
      <p>Administrators may suspend, deactivate, or delete accounts that violate these terms or disrupt operations. Suspended accounts cannot sign in.</p>

      <h2>7. Privacy</h2>
      <p>Use of FilaQ is also governed by our <a href="privacy.php">Privacy Policy</a>, which is part of these terms.</p>

      <h2>8. Changes</h2>
      <p>These terms may be updated from time to time. Continued use after an update means you accept the new terms.</p>

      <h2>9. Contact</h2>
      <p>Questions about these terms should be directed to the FilaQ administrator at <strong><?php echo e($orgName); ?></strong>.</p>
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