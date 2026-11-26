<?php
require __DIR__ . '/includes/bootstrap.php';
require_learner();
$me = current_user();
$p = progress_summary($me['id']);
$myBadges = user_badges($me['id']);
$levelNames = level_names();
$history = q_all('SELECT game, level, score, created_at FROM scores WHERE user_id = ? ORDER BY id DESC LIMIT 15', [$me['id']]);
$sus = q_row('SELECT sus_score FROM sus_responses WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$me['id']]);

$pageTitle = $p['all_played'] ? 'Final report' : 'My progress';
$activeNav = 'report';
$bodyClass = 'page-report';
require __DIR__ . '/includes/header.php';
?>
<section class="report-head">
  <div>
    <p class="eyebrow"><?= $p['all_played'] ? 'Final report · all games played' : 'Progress report so far' ?></p>
    <h1><?= h($me['full_name']) ?></h1>
    <p class="muted">Printed <?= h(date('F j, Y')) ?> · <?= $p['levels_played'] ?> of 9 levels played · <?= $p['levels_passed'] ?> passed</p>
  </div>
  <button class="btn btn-ghost no-print" type="button" onclick="window.print()">Print or save as PDF</button>
</section>

<?php if (!$p['pre']): ?>
  <div class="card"><p>Take the <a href="knowledge-check.php">knowledge check</a> first, then play a level to see your progress here.</p></div>
<?php else: ?>

<section class="card overall">
  <div class="overall-big">
    <span class="overall-label">Overall improvement</span>
    <span class="overall-num"><?= h(gain_text($p['overall_gain'], $p['overall_baseline'])) ?></span>
  </div>
  <p class="formula">Improvement = (current score − starting score) ÷ (100 − starting score). It shows how much of the room to grow you have covered. <?= $p['overall_baseline'] !== null ? 'Starting score ' . h($p['overall_baseline']) . '%, current score ' . h($p['overall_current']) . '%.' : '' ?></p>
</section>

<section class="report-games">
  <?php foreach (games() as $g => $info): $gp = $p['games'][$g]; ?>
    <article class="card report-game" style="--game: <?= h($info['color']) ?>">
      <h2><?= $g ?>. <?= h($info['name']) ?></h2>
      <div class="bars" role="img" aria-label="Starting score <?= (int) $gp['baseline'] ?> percent, current score <?= $gp['current'] === null ? 'not played' : h($gp['current']) . ' percent' ?>">
        <div class="bar-row"><span>Start</span><div class="bar"><i style="width: <?= (int) $gp['baseline'] ?>%" class="bar-base"></i></div><b><?= (int) $gp['baseline'] ?>%</b></div>
        <div class="bar-row"><span>Now</span><div class="bar"><i style="width: <?= (float) ($gp['current'] ?? 0) ?>%" class="bar-now"></i></div><b><?= $gp['current'] === null ? '–' : h($gp['current']) . '%' ?></b></div>
      </div>
      <p class="gain-line">Improvement: <b><?= h(gain_text($gp['gain'], $gp['baseline'])) ?></b></p>
      <table class="mini">
        <thead><tr><th>Level</th><th>Best score</th></tr></thead>
        <tbody>
          <?php foreach ([1, 2, 3] as $lv): $b = $gp['best'][$lv] ?? null; ?>
            <tr>
              <td><?= h($levelNames[$lv]) ?> <span class="muted">· <?= h($info['levels'][$lv]) ?></span></td>
              <td><?= $b === null ? '<span class="muted">Not played</span>' : stars_html($b) . ' ' . $b . '%' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </article>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Knowledge check: before and after</h2>
  <?php if ($p['post']): $kg = normalized_gain((int) $p['post']['total_score'], (int) $p['pre']['total_score']); ?>
    <table class="table">
      <thead><tr><th></th><?php foreach (games() as $info): ?><th><?= h($info['name']) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
      <tbody>
        <tr><th>First check (pre-test)</th><?php foreach ([1, 2, 3] as $g): ?><td><?= (int) $p['pre']['g' . $g . '_score'] ?>%</td><?php endforeach; ?><td><?= (int) $p['pre']['total_score'] ?>%</td></tr>
        <tr><th>Final check (post-test)</th><?php foreach ([1, 2, 3] as $g): ?><td><?= (int) $p['post']['g' . $g . '_score'] ?>%</td><?php endforeach; ?><td><?= (int) $p['post']['total_score'] ?>%</td></tr>
        <tr><th>Improvement</th><?php foreach ([1, 2, 3] as $g): ?><td><?= h(gain_text(normalized_gain((int) $p['post']['g' . $g . '_score'], (int) $p['pre']['g' . $g . '_score']), (int) $p['pre']['g' . $g . '_score'])) ?></td><?php endforeach; ?><td><b><?= h(gain_text($kg, (int) $p['pre']['total_score'])) ?></b></td></tr>
      </tbody>
    </table>
  <?php else: ?>
    <p>First check: <b><?= (int) $p['pre']['total_score'] ?>%</b>.
      <?= $p['every_game_started'] ? '<a href="knowledge-check.php?kind=post">Take the final check</a> to compare.' : 'Play at least one level of every game, then take the final check to compare.' ?></p>
  <?php endif; ?>
  <?php if ($sus): ?><p class="muted">Your usability rating (SUS): <?= h(fmt_num($sus['sus_score'])) ?> out of 100.</p><?php endif; ?>
</section>

<section class="card">
  <h2>Badges</h2>
  <ul class="badge-grid">
    <?php foreach (badges() as $key => $b): $earned = isset($myBadges[$key]); ?>
      <li class="badge<?= $earned ? '' : ' is-locked' ?>">
        <?= badge_svg($key, 56, $earned) ?>
        <b><?= h($b['name']) ?></b>
        <span><?= $earned ? 'Earned ' . h(date('M j', strtotime($myBadges[$key]))) : h($b['desc']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($history): ?>
<section class="card">
  <h2>Recent plays</h2>
  <table class="table">
    <thead><tr><th>When</th><th>Game</th><th>Level</th><th>Score</th></tr></thead>
    <tbody>
      <?php foreach ($history as $hrow): ?>
        <tr>
          <td><?= h(date('M j, g:i A', strtotime($hrow['created_at']))) ?></td>
          <td><?= h(games()[(int) $hrow['game']]['name']) ?></td>
          <td><?= h($levelNames[(int) $hrow['level']]) ?></td>
          <td><?= (int) $hrow['score'] ?>%</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
