<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/queue.php';

if (current_user() !== null) {
    redirect('index.php');
}

// Registration may be disabled via admin setting
$allowReg = setting('allow_registration', '1');
if ($allowReg !== '1' && $allowReg !== 1) {
    set_flash('New account registration is currently closed.', 'warn');
    redirect('login.php');
}

$error = null;
$old = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'role' => 'CUSTOMER'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['username']  = trim($_POST['username'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['role']      = in_array($_POST['role'] ?? '', ['CUSTOMER', 'STAFF']) ? $_POST['role'] : 'CUSTOMER';

    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validation
    $errors = [];
    if ($old['full_name'] === '') $errors['full_name'] = 'Enter your full name.';
    if ($old['username'] === '') $errors['username'] = 'Choose a username.';
    if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $old['username'])) $errors['username'] = 'Username: 3-30 letters, numbers, dots, or underscores.';
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if (mb_strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors['password2'] = 'Passwords do not match.';

    // Unique checks
    if (empty($errors)) {
        if (fetch_one('SELECT id FROM users WHERE username = ?', [$old['username']])) $errors['username'] = 'That username is already taken.';
        if (fetch_one('SELECT id FROM users WHERE email = ?', [$old['email']])) $errors['email'] = 'That email is already registered.';
    }

    if (!empty($errors)) {
        $error = 'Please fix the errors below.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $status = $old['role'] === 'STAFF' ? 'PENDING' : 'ACTIVE';
            exec_write(
                'INSERT INTO users (full_name, username, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$old['full_name'], $old['username'], $old['email'], $old['phone'] ?: null, $hash, $old['role'], $status]
            );
            log_activity('REGISTER', "New {$old['role']} account: {$old['username']}");
            if ($old['role'] === 'STAFF') {
                set_flash('Account created! An administrator will review your request shortly.', 'info');
            } else {
                set_flash('Account created! You can now sign in.', 'success');
            }
            redirect('login.php');
        } catch (Throwable $t) {
            error_log('[FilaQ] register error: ' . $t->getMessage());
            $error = 'Something went wrong creating your account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create your account · <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body>
<div class="blobs" aria-hidden="true"><div class="blob a"></div></div>
<div class="auth-wrap">
  <div class="auth-card" style="max-height:95vh; overflow-y:auto;">
    <div class="brand">
      <a href="index.php" style="text-decoration:none; color:inherit;"><h1><?php echo APP_NAME; ?></h1></a>
      <p>Join the queue. It takes less than a minute.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="post" action="register.php" novalidate id="reg-form">
      <div class="field">
        <label for="full_name">Full Name</label>
        <input class="input<?php echo isset($errors['full_name']) ? ' is-error' : ''; ?>" type="text" id="full_name" name="full_name" value="<?php echo e($old['full_name']); ?>" required autofocus>
        <span class="input-error" style="<?php echo isset($errors['full_name']) ? '' : 'display:none;'; ?>"><?php echo e($errors['full_name'] ?? ''); ?></span>
      </div>
      <div class="field">
        <label for="username">Username</label>
        <input class="input<?php echo isset($errors['username']) ? ' is-error' : ''; ?>" type="text" id="username" name="username" value="<?php echo e($old['username']); ?>" required>
        <span class="input-error" style="<?php echo isset($errors['username']) ? '' : 'display:none;'; ?>"><?php echo e($errors['username'] ?? ''); ?></span>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input class="input<?php echo isset($errors['email']) ? ' is-error' : ''; ?>" type="email" id="email" name="email" value="<?php echo e($old['email']); ?>" required>
        <span class="input-error" style="<?php echo isset($errors['email']) ? '' : 'display:none;'; ?>"><?php echo e($errors['email'] ?? ''); ?></span>
      </div>
      <div class="field">
        <label for="phone">Phone <span style="font-weight:300;color:var(--ink-soft);">(optional)</span></label>
        <input class="input" type="text" id="phone" name="phone" value="<?php echo e($old['phone']); ?>">
      </div>
      <div class="field">
        <label for="role">I am a…</label>
        <select class="input" id="role" name="role">
          <option value="CUSTOMER"<?php echo $old['role']==='CUSTOMER'?' selected':''; ?>>Customer / Student</option>
          <option value="STAFF"<?php echo $old['role']==='STAFF'?' selected':''; ?>>Staff / Personnel</option>
        </select>
        <small>Staff accounts require administrator approval before they become active.</small>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input class="input<?php echo isset($errors['password']) ? ' is-error' : ''; ?>" type="password" id="password" name="password" required>
        <span class="input-error" style="<?php echo isset($errors['password']) ? '' : 'display:none;'; ?>"><?php echo e($errors['password'] ?? ''); ?></span>
      </div>
      <div class="field">
        <label for="password2">Confirm Password</label>
        <input class="input<?php echo isset($errors['password2']) ? ' is-error' : ''; ?>" type="password" id="password2" name="password2" required>
        <span class="input-error" style="<?php echo isset($errors['password2']) ? '' : 'display:none;'; ?>"><?php echo e($errors['password2'] ?? ''); ?></span>
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Create Account</button>
    </form>

    <div style="text-align:center; margin-top:1.3rem; font-size:.9rem;">
      Already have an account? <a href="login.php"><strong>Sign in</strong></a>
    </div>
    <div style="text-align:center; margin-top:.4rem; font-size:.78rem; color:var(--ink-soft);">
      By registering you agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>.
    </div>
  </div>
</div>
<script src="assets/js/main.js?v=2"></script>
<script>
document.getElementById('reg-form').addEventListener('submit', function (e) {
  const errs = validateForm(this);
  // Additional password check
  const p = this.querySelector('[name="password"]');
  const p2 = this.querySelector('[name="password2"]');
  if (p && p2 && p.value && p.value !== p2.value) errs[p2.name] = 'Passwords do not match.';
  if (!applyFormErrors(this, errs)) e.preventDefault();
});
</script>
</body>
</html>