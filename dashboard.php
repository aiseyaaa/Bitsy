<?php
require __DIR__ . '/includes/bootstrap.php';
require_learner();
$me = current_user();
$p = progress_summary($me['id']);
$myBadges = user_badges($me['id']);
$sus = q_row('SELECT sus_score FROM sus_responses WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$me['id']]);
$levelNames = level_names();

// Suggest what to do next.
$next = null;
if (!$p['pre']) {
    $tip = 'First, take the quick knowledge check so I know where you should start.';
} else {
    foreach (games() as $g => $info) {
        foreach ([1, 2, 3] as $lv) {
            $played = isset($p['games'][$g]['best'][$lv]);
            if (in_array($lv, $p['games'][$g]['unlocked'], true) && !$played) {
                $next = [$g, $lv];
                break 2;
            }
        }
    }
    if ($next) {
        $tip = 'Up next: ' . games()[$next[0]]['name'] . ', ' . $levelNames[$next[1]] . '. You can do it!';
    } elseif ($p['all_played']) {
        $tip = 'You played every level! Take the final check to see how much you learned.';
    } else {
        $tip = 'Score ' . PASS_MARK . '% or more on a level to open the next one. Try again any time.';
    }
}

$pageTitle = 'My games';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="greet">
  <?= mascot_svg(84) ?>
  <div>
    <h1>Hi, <?= h(first_name($me['full_name'])) ?>!</h1>
    <p class="bubble"><?= h($tip) ?></p>
  </div>
</section>

<?php if (!$p['pre']): ?>
  <section class="card callout">
    <div>
      <h2>Start here: quick knowledge check</h2>
      <p>12 questions, about 5 minutes. Guessing is fine. Your answers open the right levels for you and give Bitsy a starting point to measure your improvement.</p>
    </div>
    <a class="btn btn-lg" href="knowledge-check.php">Take the check</a>
  </section>
<?php endif; ?>

<section class="games-grid" aria-label="Games">
  <?php foreach (games() as $g => $info):
      $gp = $p['games'][$g]; ?>
    <article class="game-card" style="--game: <?= h($info['color']) ?>">
      <header class="game-card-head">
        <span class="game-num"><?= $g ?></span>
        <div>
          <h2><?= h($info['name']) ?></h2>
          <p class="game-tag"><?= h($info['tagline']) ?></p>
        </div>
      </header>
      <p class="game-desc"><?= h($info['desc']) ?></p>
      <ul class="level-list">
        <?php foreach ([1, 2, 3] as $lv):
            $open = in_array($lv, $gp['unlocked'], true);
            $best = $gp['best'][$lv] ?? null; ?>
          <li class="level-row<?= $open ? '' : ' is-locked' ?>">
            <div class="level-info">
              <b><?= h($levelNames[$lv]) ?></b>
              <span><?= h($info['levels'][$lv]) ?></span>
            </div>
            <div class="level-side">
              <?php if ($best !== null): ?>
                <span class="best"><?= stars_html($best) ?> <?= $best ?>%</span>
              <?php endif; ?>
              <?php if ($open): ?>
                <a class="btn btn-sm" href="play.php?game=<?= $g ?>&amp;level=<?= $lv ?>"><?= $best === null ? 'Play' : 'Replay' ?></a>
              <?php else: ?>
                <span class="locked" title="<?= $p['pre'] ? 'Score ' . PASS_MARK . '% on the level before to open this' : 'Take the knowledge check first' ?>"><?= lock_svg() ?> Locked</span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($gp['current'] !== null): ?>
        <footer class="game-card-foot">
          Improvement: <b><?= h(gain_text($gp['gain'], $gp['baseline'])) ?></b>
          <span class="muted">(started at <?= (int) $gp['baseline'] ?>%, now <?= h($gp['current']) ?>%)</span>
        </footer>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

<section class="card">
  <div class="section-head">
    <h2>My badges</h2>
    <span class="muted"><?= count($myBadges) ?> of <?= count(badges()) ?> earned</span>
  </div>
  <ul class="badge-grid">
    <?php foreach (badges() as $key => $b): $earned = isset($myBadges[$key]); ?>
      <li class="badge<?= $earned ? '' : ' is-locked' ?>">
        <?= badge_svg($key, 64, $earned) ?>
        <b><?= h($b['name']) ?></b>
        <span><?= h($b['desc']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($p['every_game_started']): ?>
  <section class="card finish">
    <h2>Finish line</h2>
    <div class="finish-grid">
      <div>
        <h3>Final check</h3>
        <?php if ($p['post']): ?>
          <p>Done: <?= (int) $p['pre']['total_score'] ?>% at the start, <?= (int) $p['post']['total_score'] ?>% now.</p>
          <a class="btn btn-ghost" href="knowledge-check.php?kind=post">Take it again</a>
        <?php else: ?>
          <p>Answer the same 12 questions again to see how much you learned.</p>
          <a class="btn" href="knowledge-check.php?kind=post">Take the final check</a>
        <?php endif; ?>
      </div>
      <div>
        <h3>Rate Bitsy</h3>
        <?php if ($sus): ?>
          <p>Thank you for your feedback!</p>
        <?php else: ?>
          <p>Ten quick statements about how easy Bitsy is to use.</p>
          <a class="btn btn-ghost" href="survey.php">Answer the survey</a>
        <?php endif; ?>
      </div>
      <div>
        <h3>My report</h3>
        <p>Scores, improvement and badges in one page you can print.</p>
        <a class="btn btn-ghost" href="report.php">Open my report</a>
      </div>
    </div>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
