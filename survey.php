<?php
/*
 * System Usability Scale (SUS) questionnaire (Brooke, 1996).
 */
require __DIR__ . '/includes/bootstrap.php';
require_learner();
$me = current_user();
$statements = sus_statements();
$error = '';
$done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ans = [];
    for ($i = 1; $i <= 10; $i++) {
        $v = (int) ($_POST['q' . $i] ?? 0);
        if ($v < 1 || $v > 5) {
            $error = 'Please answer all 10 statements.';
            break;
        }
        $ans[$i] = $v;
    }
    if (!$error) {
        $score = sus_score($ans);
        $comments = trim((string) ($_POST['comments'] ?? ''));
        q('INSERT INTO sus_responses (user_id, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, sus_score, comments, created_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$me['id'], $ans[1], $ans[2], $ans[3], $ans[4], $ans[5], $ans[6], $ans[7], $ans[8], $ans[9], $ans[10],
             $score, $comments !== '' ? mb_substr($comments, 0, 1000) : null, now()]);
        $done = $score;
    }
}

$pageTitle = 'Rate Bitsy';
require __DIR__ . '/includes/header.php';
?>
<?php if ($done !== null): ?>
  <section class="card kc-result">
    <div class="kc-result-head">
      <?= mascot_svg(88) ?>
      <div>
        <h1>Thank you!</h1>
        <p class="lead">Your answers help the Bitsy team make the app easier to use.</p>
      </div>
    </div>
    <div class="row-actions">
      <a class="btn btn-lg" href="report.php">See my report</a>
      <a class="btn btn-lg btn-ghost" href="dashboard.php">Back to my games</a>
    </div>
  </section>
<?php else: ?>
  <section class="kc-intro">
    <h1>How easy is Bitsy to use?</h1>
    <p class="lead">For each statement, choose how much you agree. There are no right or wrong answers.</p>
  </section>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="sus-form card">
    <?= csrf_field() ?>
    <div class="sus-scale-head" aria-hidden="true">
      <span></span><span>Strongly disagree</span><span></span><span></span><span></span><span>Strongly agree</span>
    </div>
    <?php foreach ($statements as $i => $text): ?>
      <fieldset class="sus-row">
        <legend><span class="kc-num"><?= $i ?></span> <?= h($text) ?></legend>
        <div class="sus-options">
          <?php for ($v = 1; $v <= 5; $v++): ?>
            <label class="sus-opt">
              <input type="radio" name="q<?= $i ?>" value="<?= $v ?>" required <?= (int) ($_POST['q' . $i] ?? 0) === $v ? 'checked' : '' ?>>
              <span><?= $v ?></span>
            </label>
          <?php endfor; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
    <label class="field">
      <span>Anything else you want to tell us? (optional)</span>
      <textarea name="comments" rows="3" maxlength="1000"><?= h($_POST['comments'] ?? '') ?></textarea>
    </label>
    <button class="btn btn-lg" type="submit">Send my answers</button>
    <p class="muted small">1 = strongly disagree, 5 = strongly agree. Questionnaire: System Usability Scale (Brooke, 1996).</p>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
