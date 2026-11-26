<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect($u['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = strtolower(trim($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $user = q_row('SELECT * FROM users WHERE username = ?', [$username]);
    if ($user && password_verify($pass, $user['password_hash'])) {
        login_user($user);
        if ($user['role'] === 'admin') {
            redirect('admin/index.php');
        }
        redirect(knowledge_check($user['id'], 'pre') ? 'dashboard.php' : 'knowledge-check.php');
    }
    $error = 'Wrong username or password. Please try again.';
}

$pageTitle = 'Log in';
$bodyClass = 'page-auth';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card card">
  <div class="auth-head"><?= mascot_svg(64) ?><h1>Welcome back</h1></div>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label class="field">
      <span>Username</span>
      <input name="username" value="<?= h($username) ?>" autocomplete="username" autocapitalize="none" required autofocus>
    </label>
    <label class="field">
      <span>Password</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-lg btn-block" type="submit">Log in</button>
  </form>
  <p class="auth-alt">New to Bitsy? <a href="register.php">Make a free account</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
