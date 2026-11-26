<?php
/*
 * Knowledge check. kind=pre is the starting check (sets the starting level and
 * the baseline for each game). kind=post is the same questions at the end.
 */
require __DIR__ . '/includes/bootstrap.php';
require_learner();
$me = current_user();

$kind = ($_GET['kind'] ?? $_POST['kind'] ?? 'pre') === 'post' ? 'post' : 'pre';
$pre = knowledge_check($me['id'], 'pre');

if ($kind === 'pre' && $pre && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['done'])) {
    flash('You already took the starting knowledge check.', 'info');
    redirect('dashboard.php');
}
if ($kind === 'post' && !$pre) {
    redirect('knowledge-check.php');
}

$questions = q_all('SELECT * FROM kc_questions ORDER BY game, sort_order, id');
$games = games();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($kind === 'pre' && $pre) {
        redirect('dashboard.php');
    }
    $right = [1 => 0, 2 => 0, 3 => 0];
    $count = [1 => 0, 2 => 0, 3 => 0];
    foreach ($questions as $qn) {
        $g = (int) $qn['game'];
        $count[$g]++;
        $ans = $_POST['q' . $qn['id']] ?? '';
        if ($ans === $qn['answer']) {
            $right[$g]++;
        }
    }
    $pct = [];
    foreach ([1, 2, 3] as $g) {
        $pct[$g] = $count[$g] ? (int) round($right[$g] / $count[$g] * 100) : 0;
    }
    $totalQ = array_sum($count);
    $total = $totalQ ? (int) round(array_sum($right) / $totalQ * 100) : 0;
    q('INSERT INTO knowledge_checks (user_id, kind, g1_score, g2_score, g3_score, total_score, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$me['id'], $kind, $pct[1], $pct[2], $pct[3], $total, now()]);
    redirect('knowledge-check.php?kind=' . $kind . '&done=1');
}

if (isset($_GET['done'])) {
    $result = knowledge_check($me['id'], $kind);
    if (!$result) {
        redirect('dashboard.php');
    }
}

$pageTitle = $kind === 'pre' ? 'Knowledge check' : 'Final check (post-test)';
$bodyClass = 'page-kc';
require __DIR__ . '/includes/header.php';
?>

<?php if ($result): ?>
  <section class="card kc-result">
    <div class="kc-result-head">
      <?= mascot_svg(88, 'wow') ?>
      <div>
        <h1><?= $kind === 'pre' ? 'Thanks! Your starting levels are ready.' : 'Final check done!' ?></h1>
        <p class="lead">You got <b><?= (int) $result['total_score'] ?>%</b> overall.
          <?= $kind === 'pre' ? 'This is your baseline. Bitsy compares your game scores with it to show how much you improve.' : 'Here is how it compares with your first check.' ?></p>
      </div>
    </div>
    <div class="kc-levels">
      <?php foreach ($games as $g => $info):
          $score = (int) $result['g' . $g . '_score'];
          $start = start_level_from_score($score);
      ?>
        <div class="kc-level" style="--game: <?= h($info['color']) ?>">
          <div class="kc-level-name"><?= $g ?>. <?= h($info['name']) ?></div>
          <?php if ($kind === 'pre'): ?>
            <div class="kc-level-score"><?= $score ?>%</div>
            <div>Start at <b><?= h(level_names()[$start]) ?></b><?= $start > 1 ? ' (easier levels stay open too)' : '' ?></div>
          <?php else:
              $preScore = (int) $pre['g' . $g . '_score'];
              $gain = normalized_gain($score, $preScore);
          ?>
            <div class="kc-level-score"><?= $preScore ?>% → <?= $score ?>%</div>
            <div>Improvement: <b><?= h(gain_text($gain, $preScore)) ?></b></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($kind === 'post'):
        $g = normalized_gain((int) $result['total_score'], (int) $pre['total_score']); ?>
      <p class="kc-total">Overall: <?= (int) $pre['total_score'] ?>% → <?= (int) $result['total_score'] ?>%, an improvement of <b><?= h(gain_text($g, (int) $pre['total_score'])) ?></b>.</p>
      <div class="row-actions">
        <a class="btn btn-lg" href="survey.php">Rate Bitsy (2 minutes)</a>
        <a class="btn btn-lg btn-ghost" href="report.php">See my full report</a>
      </div>
    <?php else: ?>
      <div class="row-actions"><a class="btn btn-lg" href="dashboard.php">Go to my games</a></div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="kc-intro">
    <h1><?= $kind === 'pre' ? 'Quick knowledge check' : 'Final check' ?></h1>
    <p class="lead"><?= $kind === 'pre'
        ? count($questions) . ' questions, about 5 minutes. It is fine to guess. Your answers set where you start in each game.'
        : 'The same ' . count($questions) . ' questions as your first check. Let\'s see how much you learned!' ?></p>
  </section>

  <form method="post" class="kc-form" id="kc-form">
    <?= csrf_field() ?>
    <input type="hidden" name="kind" value="<?= h($kind) ?>">
    <?php
    $n = 0;
    $current = 0;
    foreach ($questions as $qn):
        $g = (int) $qn['game'];
        if ($g !== $current):
            if ($current) echo '</fieldset>';
            $current = $g; ?>
      <fieldset class="kc-part" style="--game: <?= h($games[$g]['color']) ?>">
        <legend>Part <?= $g ?>: <?= h($games[$g]['name']) ?></legend>
    <?php endif; $n++; ?>
      <div class="kc-q card">
        <div class="kc-q-head">
          <span class="kc-num"><?= $n ?></span>
          <p class="kc-prompt"><?= h($qn['prompt']) ?></p>
        </div>
        <?php if ($qn['icon']): ?><div class="kc-icon" data-icon="<?= h($qn['icon']) ?>"></div><?php endif; ?>
        <div class="kc-choices">
          <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
            <label class="choice">
              <input type="radio" name="q<?= (int) $qn['id'] ?>" value="<?= $letter ?>" required>
              <span><?= h($qn['choice_' . $letter]) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; if ($current) echo '</fieldset>'; ?>
    <div class="kc-submit">
      <p id="kc-count" class="muted" aria-live="polite"></p>
      <button class="btn btn-lg" type="submit">Finish and see my levels</button>
    </div>
  </form>
  <script src="assets/js/icons.js"></script>
  <script>
    document.querySelectorAll('[data-icon]').forEach(function (el) { el.innerHTML = BitsyIcons.svg(el.dataset.icon); });
    (function () {
      var form = document.getElementById('kc-form');
      var total = form.querySelectorAll('.kc-q').length;
      var out = document.getElementById('kc-count');
      function update() {
        var done = new Set(Array.from(form.querySelectorAll('input[type=radio]:checked')).map(function (i) { return i.name; })).size;
        out.textContent = done + ' of ' + total + ' answered';
      }
      form.addEventListener('change', update);
      update();
    })();
  </script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
