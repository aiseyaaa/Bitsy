<?php
/*
 * Admin home: anonymous statistics. No learner names are shown here.
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$learners = (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'learner'");
$plays = (int) q_val('SELECT COUNT(*) FROM scores');
$activeItems = (int) q_val("SELECT COUNT(*) FROM items WHERE status = 'active'");
$activeScen = (int) q_val("SELECT COUNT(*) FROM scenarios WHERE status = 'active'");

$byLevel = [];
foreach (q_all('SELECT game, level, COUNT(*) AS plays, AVG(score) AS avg_score FROM scores GROUP BY game, level') as $r) {
    $byLevel[(int) $r['game']][(int) $r['level']] = $r;
}

$missedItems = q_all(
    "SELECT i.name, i.category, i.status, COUNT(*) AS tries,
            SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) AS misses
     FROM attempts a JOIN items i ON i.id = a.ref_id
     WHERE a.ref_type = 'item'
     GROUP BY i.id, i.name, i.category, i.status
     HAVING SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) > 0
     ORDER BY SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) * 1.0 / COUNT(*) DESC,
              SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) DESC
     LIMIT 10");

$missedScen = q_all(
    "SELECT s.title, s.level, COUNT(*) AS tries,
            SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) AS misses
     FROM attempts a JOIN scenarios s ON s.id = a.ref_id
     WHERE a.ref_type = 'scenario'
     GROUP BY s.id, s.title, s.level
     ORDER BY SUM(CASE WHEN a.is_correct = 0 THEN 1 ELSE 0 END) * 1.0 / COUNT(*) DESC
     LIMIT 8");

// Pre-test vs post-test, for learners who took both (latest post-test per learner).
$prePost = q_row(
    "SELECT COUNT(*) AS n, AVG(pre.total_score) AS pre_avg, AVG(post.total_score) AS post_avg
     FROM knowledge_checks pre
     JOIN knowledge_checks post ON post.user_id = pre.user_id AND post.kind = 'post'
       AND post.id = (SELECT MAX(k.id) FROM knowledge_checks k WHERE k.user_id = pre.user_id AND k.kind = 'post')
     WHERE pre.kind = 'pre'");
$preOnly = q_row("SELECT COUNT(*) AS n, AVG(total_score) AS avg FROM knowledge_checks WHERE kind = 'pre'");
$classGain = ($prePost && (int) $prePost['n'] > 0) ? normalized_gain((float) $prePost['post_avg'], (float) $prePost['pre_avg']) : null;

$sus = q_row('SELECT COUNT(*) AS n, AVG(sus_score) AS avg FROM sus_responses');
$susComments = q_all("SELECT comments, created_at FROM sus_responses WHERE comments IS NOT NULL AND comments <> '' ORDER BY id DESC LIMIT 5");

$pageTitle = 'Statistics';
$activeNav = 'admin';
$bodyClass = 'page-admin';
require __DIR__ . '/../includes/header.php';
?>
<section class="admin-head">
  <div>
    <p class="eyebrow">Content manager</p>
    <h1>Statistics</h1>
    <p class="muted">All numbers are anonymous. Right and wrong answers are stored without names.</p>
  </div>
  <div class="row-actions">
    <a class="btn" href="item-form.php">Add a part or device</a>
    <a class="btn btn-ghost" href="scenario-form.php">Add a scenario</a>
  </div>
</section>

<section class="stat-tiles">
  <div class="tile"><span>Learners</span><b><?= $learners ?></b></div>
  <div class="tile"><span>Levels played</span><b><?= $plays ?></b></div>
  <div class="tile"><span>Active parts, devices, cables</span><b><?= $activeItems ?></b></div>
  <div class="tile"><span>Active scenarios</span><b><?= $activeScen ?></b></div>
</section>

<div class="admin-grid">
  <section class="card">
    <h2>Most-missed items</h2>
    <?php if (!$missedItems): ?>
      <p class="muted">No misses yet. This fills in as learners play.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Item</th><th>Type</th><th class="num">Tries</th><th class="num">Missed</th><th>Miss rate</th></tr></thead>
        <tbody>
        <?php foreach ($missedItems as $r): $rate = round($r['misses'] / max(1, $r['tries']) * 100); ?>
          <tr>
            <td><?= h($r['name']) ?><?= $r['status'] === 'archived' ? ' <span class="tag">archived</span>' : '' ?></td>
            <td><?= h(category_names()[$r['category']] ?? $r['category']) ?></td>
            <td class="num"><?= (int) $r['tries'] ?></td>
            <td class="num"><?= (int) $r['misses'] ?></td>
            <td><div class="meter"><i style="width: <?= $rate ?>%"></i></div> <?= $rate ?>%</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Troubleshooting scenarios</h2>
    <p class="muted small">"Missed" means the learner did not find the fault on the first try.</p>
    <?php if (!$missedScen): ?>
      <p class="muted">No scenario attempts yet.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Scenario</th><th>Level</th><th class="num">Tries</th><th>Missed</th></tr></thead>
        <tbody>
        <?php foreach ($missedScen as $r): $rate = round($r['misses'] / max(1, $r['tries']) * 100); ?>
          <tr>
            <td><?= h($r['title']) ?></td>
            <td><?= h(level_names()[(int) $r['level']]) ?></td>
            <td class="num"><?= (int) $r['tries'] ?></td>
            <td><div class="meter"><i style="width: <?= $rate ?>%"></i></div> <?= $rate ?>%</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Average score per level</h2>
    <table class="table">
      <thead><tr><th>Game</th><?php foreach (level_names() as $ln): ?><th><?= h($ln) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (games() as $g => $info): ?>
        <tr>
          <th><?= h($info['name']) ?></th>
          <?php foreach ([1, 2, 3] as $lv): $r = $byLevel[$g][$lv] ?? null; ?>
            <td><?= $r ? h(fmt_num($r['avg_score'])) . '% <span class="muted small">(' . (int) $r['plays'] . ' plays)</span>' : '<span class="muted">–</span>' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Evaluation</h2>
    <h3>Pre-test and post-test</h3>
    <?php if ($prePost && (int) $prePost['n'] > 0): ?>
      <p><?= (int) $prePost['n'] ?> learner<?= (int) $prePost['n'] === 1 ? '' : 's' ?> took both checks.
         Average <b><?= h(fmt_num($prePost['pre_avg'])) ?>%</b> before, <b><?= h(fmt_num($prePost['post_avg'])) ?>%</b> after.
         Class normalized gain: <b><?= h(gain_text($classGain, (float) $prePost['pre_avg'])) ?></b>.</p>
    <?php else: ?>
      <p class="muted"><?= (int) ($preOnly['n'] ?? 0) ?> pre-tests so far<?= !empty($preOnly['n']) ? ' (average ' . h(fmt_num($preOnly['avg'])) . '%)' : '' ?>. No post-tests yet.</p>
    <?php endif; ?>
    <h3>System Usability Scale (SUS)</h3>
    <?php if ($sus && (int) $sus['n'] > 0): ?>
      <p><?= (int) $sus['n'] ?> response<?= (int) $sus['n'] === 1 ? '' : 's' ?>. Average SUS score <b><?= h(fmt_num($sus['avg'])) ?></b> out of 100 (<?= h(sus_grade((float) $sus['avg'])) ?>; about 68 is average).</p>
      <?php if ($susComments): ?>
        <ul class="comments">
          <?php foreach ($susComments as $c): ?><li>“<?= h($c['comments']) ?>”</li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php else: ?>
      <p class="muted">No survey answers yet.</p>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
