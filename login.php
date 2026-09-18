<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/icons.php';

if (current_user() !== null) {
    redirect('index.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        try {
            $user = fetch_one('SELECT * FROM users WHERE username = ? OR email = ?', [$username, $username]);
            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Incorrect username or password.';
            } elseif ($user['status'] === 'SUSPENDED') {
                $error = 'This account has been suspended. Contact an administrator.';
            } elseif ($user['status'] === 'PENDING') {
                $error = 'This account is awaiting administrator approval. Please check back later.';
            } elseif ($user['status'] === 'INACTIVE') {
                $error = 'This account is inactive. Contact an administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                exec_write('UPDATE users SET last_login = NOW() WHERE id = ?', [(int) $user['id']]);
                log_activity('LOGIN', "Signed in as {$user['username']}", (int) $user['id']);
                set_flash('Welcome back, ' . $user['full_name'] . '.', 'success');
                $dest = $user['role'] === 'ADMIN' ? 'admin/index.php' : ($user['role'] === 'STAFF' ? 'staff/index.php' : 'customer/index.php');
                redirect($dest);
            }
        } catch (Throwable $t) {
            error_log('[FilaQ] login error: ' . $t->getMessage());
            $error = 'Something went wrong while signing in. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-brand">
      <span class="dot" aria-hidden="true"></span>
      <h1><?php echo APP_NAME; ?></h1>
      <p>Welcome back. Your number awaits.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?php echo icon('alert', 18); ?><span><?php echo e($error); ?></span></div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate id="login-form">
      <div class="field">
        <label for="username">Username or email</label>
        <input class="input" type="text" id="username" name="username" value="<?php echo e($username); ?>" required autocomplete="username">
        <span class="input-error" role="alert"></span>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
        <span class="input-error" role="alert"></span>
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
    </form>

    <div class="auth-switch">
      New to FilaQ? <a href="register.php">Create an account</a>
      <small>Staff accounts require administrator approval.</small>
    </div>
  </div>
</div>
<script src="assets/js/main.js?v=3"></script>
<script>
document.getElementById('login-form').addEventListener('submit', function (e) {
  const errors = validateForm(this);
  if (!applyFormErrors(this, errors)) e.preventDefault();
});
</script>
</body>
</html>