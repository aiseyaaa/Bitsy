<?php
/*
 * Admin: change the content manager's password.
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
$me = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $new2 = (string) ($_POST['new2'] ?? '');
    $hash = q_val('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    if (!password_verify($cur, $hash)) $errors['current'] = 'That is not your current password.';
    if (strlen($new) < 8) $errors['new'] = 'Use at least 8 characters.';
    elseif ($new !== $new2) $errors['new2'] = 'The two new passwords are not the same.';
    if (!$errors) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        flash('Password changed.');
        redirect('admin/account.php');
    }
}

$usingDefault = password_verify('admin123', (string) q_val('SELECT password_hash FROM users WHERE id = ?', [$me['id']]));
$pageTitle = 'Account';
$activeNav = 'account';
$bodyClass = 'page-admin';
require __DIR__ . '/../includes/header.php';
$err = function ($k) use ($errors) {
    return isset($errors[$k]) ? '<em class="err">' . h($errors[$k]) . '</em>' : '';
};
?>
<h1>Account</h1>
<?php if ($usingDefault): ?><div class="flash flash-info">You are still using the default password. Please change it.</div><?php endif; ?>
<form method="post" class="card form-narrow">
  <?= csrf_field() ?>
  <p>Logged in as <b><?= h($me['username']) ?></b> (content manager).</p>
  <label class="field"><span>Current password</span><input type="password" name="current" autocomplete="current-password" required><?= $err('current') ?></label>
  <label class="field"><span>New password</span><input type="password" name="new" autocomplete="new-password" minlength="8" required><?= $err('new') ?></label>
  <label class="field"><span>New password again</span><input type="password" name="new2" autocomplete="new-password" minlength="8" required><?= $err('new2') ?></label>
  <button class="btn btn-lg" type="submit">Change password</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
