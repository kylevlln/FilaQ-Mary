<?php
require_once __DIR__ . '/config/config.php';

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
<title>Sign In — <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div><div class="blob b"></div><div class="blob c"></div></div>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="brand">
      <a href="index.php" style="text-decoration:none; color:inherit;"><h1 class="grad-text"><?php echo APP_NAME; ?></h1></a>
      <p>Welcome back — your number awaits.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="post" action="login.php" novalidate id="login-form">
      <div class="field">
        <label for="username">Username or Email</label>
        <input class="input" type="text" id="username" name="username" value="<?php echo e($username); ?>" required autofocus>
        <span class="input-error"></span>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required>
        <span class="input-error"></span>
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Sign In</button>
    </form>

    <div style="text-align:center; margin-top:1.3rem; font-size:.9rem;">
      New to FilaQ? <a href="register.php"><strong>Create an account</strong></a><br>
      <span style="font-size:.82rem; color:var(--ink-soft);">Staff accounts require administrator approval.</span>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
<script>
document.getElementById('login-form').addEventListener('submit', function (e) {
  const errors = validateForm(this);
  if (!applyFormErrors(this, errors)) e.preventDefault();
});
</script>
</body>
</html>