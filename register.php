<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['full_name' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['username'] = strtolower(trim($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Please type your name (up to 100 letters).';
    }
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $old['username'])) {
        $errors['username'] = 'Use 3 to 30 letters, numbers, dots or underscores. No spaces.';
    } elseif (q_val('SELECT id FROM users WHERE username = ?', [$old['username']])) {
        $errors['username'] = 'That username is taken. Try another one.';
    }
    if (strlen($pass) < 6) {
        $errors['password'] = 'Your password needs at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $errors['password2'] = 'The two passwords are not the same.';
    }

    if (!$errors) {
        q('INSERT INTO users (full_name, username, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)',
            [$old['full_name'], $old['username'], password_hash($pass, PASSWORD_DEFAULT), 'learner', now()]);
        $user = q_row('SELECT * FROM users WHERE username = ?', [$old['username']]);
        login_user($user);
        flash('Welcome to Bitsy, ' . first_name($user['full_name']) . '! Start with a quick knowledge check.');
        redirect('knowledge-check.php');
    }
}

$pageTitle = 'Sign up';
$bodyClass = 'page-auth';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card card">
  <div class="auth-head"><?= mascot_svg(64) ?><h1>Create your free account</h1></div>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label class="field">
      <span>Your name</span>
      <input name="full_name" value="<?= h($old['full_name']) ?>" maxlength="100" autocomplete="name" required>
      <?php if (isset($errors['full_name'])): ?><em class="err"><?= h($errors['full_name']) ?></em><?php endif; ?>
    </label>
    <label class="field">
      <span>Username</span>
      <input name="username" value="<?= h($old['username']) ?>" maxlength="30" autocomplete="username" autocapitalize="none" required>
      <?php if (isset($errors['username'])): ?><em class="err"><?= h($errors['username']) ?></em><?php endif; ?>
    </label>
    <label class="field">
      <span>Password</span>
      <input type="password" name="password" autocomplete="new-password" minlength="6" required>
      <?php if (isset($errors['password'])): ?><em class="err"><?= h($errors['password']) ?></em><?php endif; ?>
    </label>
    <label class="field">
      <span>Type the password again</span>
      <input type="password" name="password2" autocomplete="new-password" minlength="6" required>
      <?php if (isset($errors['password2'])): ?><em class="err"><?= h($errors['password2']) ?></em><?php endif; ?>
    </label>
    <button class="btn btn-lg btn-block" type="submit">Sign up</button>
  </form>
  <p class="auth-alt">Already have an account? <a href="login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
