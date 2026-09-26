<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/queue.php';
require_once __DIR__ . '/includes/icons.php';

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
    if (mb_strlen($old['full_name']) > 120) $errors['full_name'] = 'Full name is too long (max 120 characters).';
    if ($old['username'] === '') $errors['username'] = 'Choose a username.';
    if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $old['username'])) $errors['username'] = 'Username: 3-30 letters, numbers, dots, or underscores.';
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if (mb_strlen($old['email']) > 160) $errors['email'] = 'Email is too long.';
    if (mb_strlen($old['phone'] ?? '') > 30) $errors['phone'] = 'Phone number is too long (max 30 characters).';

    // Password policy (shared with the admin add-user flow).
    $pwError = validate_password_strength($password, $password2);
    if ($pwError !== null) {
        if (str_contains($pwError, 'match') || $pwError === 'Passwords do not match.') {
            $errors['password2'] = $pwError;
        } else {
            $errors['password'] = $pwError;
        }
    }

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
        } catch (PDOException $e) {
            // A race can still trip the UNIQUE keys even after the checks above.
            if ($e->getCode() == 23000) {
                $msg = (string) $e->getMessage();
                if (stripos($msg, 'username') !== false) {
                    $errors['username'] = 'That username is already taken.';
                    $error = 'Please fix the errors below.';
                } elseif (stripos($msg, 'email') !== false) {
                    $errors['email'] = 'That email is already registered.';
                    $error = 'Please fix the errors below.';
                } else {
                    $error = 'That account already exists. Try signing in instead.';
                }
            } else {
                error_log('[FilaQ] register error: ' . $e->getMessage());
                $error = 'Something went wrong creating your account. Please try again.';
            }
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
<link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body>
<div class="auth-shell register-body">
  <div class="auth-card">
    <div class="auth-brand">
      <span class="dot" aria-hidden="true"></span>
      <h1>Create your account</h1>
      <p>Join the queue. It takes less than a minute.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?php echo icon('alert', 18); ?><span><?php echo e($error); ?></span></div>
    <?php endif; ?>

    <form method="post" action="register.php" novalidate id="reg-form" data-pw-rules>
      <p class="fieldset-title">Account</p>
      <div class="field<?php echo isset($errors['full_name']) ? ' has-error' : ''; ?>">
        <label for="full_name">Full name</label>
        <input class="input<?php echo isset($errors['full_name']) ? ' is-error' : ''; ?>" type="text" id="full_name" name="full_name" value="<?php echo e($old['full_name']); ?>" required autocomplete="name">
        <span class="input-error" role="alert"><?php echo e($errors['full_name'] ?? ''); ?></span>
      </div>
      <div class="field-grid">
        <div class="field<?php echo isset($errors['username']) ? ' has-error' : ''; ?>">
          <label for="username">Username</label>
          <input class="input<?php echo isset($errors['username']) ? ' is-error' : ''; ?>" type="text" id="username" name="username" value="<?php echo e($old['username']); ?>" required autocomplete="username">
          <span class="input-error" role="alert"><?php echo e($errors['username'] ?? ''); ?></span>
        </div>
        <div class="field<?php echo isset($errors['phone']) ? ' has-error' : ''; ?>">
          <label for="phone">Phone <span class="opt">(optional)</span></label>
          <input class="input<?php echo isset($errors['phone']) ? ' is-error' : ''; ?>" type="text" id="phone" name="phone" value="<?php echo e($old['phone']); ?>">
          <span class="input-error" role="alert"><?php echo e($errors['phone'] ?? ''); ?></span>
        </div>
      </div>

      <p class="fieldset-title">Contact</p>
      <div class="field<?php echo isset($errors['email']) ? ' has-error' : ''; ?>">
        <label for="email">Email</label>
        <input class="input<?php echo isset($errors['email']) ? ' is-error' : ''; ?>" type="email" id="email" name="email" value="<?php echo e($old['email']); ?>" required autocomplete="email">
        <span class="input-error" role="alert"><?php echo e($errors['email'] ?? ''); ?></span>
      </div>

      <p class="fieldset-title">Security</p>
      <div class="field-grid">
        <div class="field<?php echo isset($errors['password']) ? ' has-error' : ''; ?>">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input class="input<?php echo isset($errors['password']) ? ' is-error' : ''; ?>" type="password" id="password" name="password" required autocomplete="new-password">
            <button class="pw-toggle" type="button" data-pw-for="password" aria-label="Show password" aria-pressed="false" aria-controls="password"><?php echo icon('eye'); ?></button>
          </div>
          <ul class="pw-rules" data-pw-list>
            <li data-pw="len">8+ characters</li>
            <li data-pw="upper">Uppercase letter</li>
            <li data-pw="lower">Lowercase letter</li>
            <li data-pw="digit">A number</li>
            <li data-pw="special">A special character</li>
            <li data-pw="match">Passwords match</li>
          </ul>
          <span class="input-error" role="alert"><?php echo e($errors['password'] ?? ''); ?></span>
        </div>
        <div class="field<?php echo isset($errors['password2']) ? ' has-error' : ''; ?>">
          <label for="password2">Confirm password</label>
          <div class="pw-wrap">
            <input class="input<?php echo isset($errors['password2']) ? ' is-error' : ''; ?>" type="password" id="password2" name="password2" required autocomplete="new-password">
            <button class="pw-toggle" type="button" data-pw-for="password2" aria-label="Show password" aria-pressed="false" aria-controls="password2"><?php echo icon('eye'); ?></button>
          </div>
          <span class="input-error" role="alert"><?php echo e($errors['password2'] ?? ''); ?></span>
        </div>
      </div>

      <div class="field">
        <label for="role">I am a…</label>
        <select class="input" id="role" name="role">
          <option value="CUSTOMER"<?php echo $old['role']==='CUSTOMER'?' selected':''; ?>>Customer / Student</option>
          <option value="STAFF"<?php echo $old['role']==='STAFF'?' selected':''; ?>>Staff / Personnel</option>
        </select>
        <small>Staff accounts require administrator approval before they become active.</small>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button>
    </form>

    <div class="auth-switch">
      Already have an account? <a href="login.php">Sign in</a>
      <small>By registering you agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>.</small>
    </div>
  </div>
</div>
<script src="assets/js/main.js?v=4"></script>
<script>
document.getElementById('reg-form').addEventListener('submit', function (e) {
  const errs = validateForm(this);
  if (!applyFormErrors(this, errs)) e.preventDefault();
  else this.querySelector('[type="submit"]').setAttribute('data-loading', '');
});
bindPasswordRules(document.getElementById('reg-form'));
</script>
</body>
</html>