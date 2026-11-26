<?php
/*
 * Game page. play.php?game=1..3&level=1..3
 * Loads the content for the level from the database and starts the game script.
 */
require __DIR__ . '/includes/bootstrap.php';
require_learner();
$me = current_user();

$game = (int) ($_GET['game'] ?? 0);
$level = (int) ($_GET['level'] ?? 0);
$games = games();
if (!isset($games[$game]) || $level < 1 || $level > 3) {
    redirect('dashboard.php');
}
if (!knowledge_check($me['id'], 'pre')) {
    flash('Take the quick knowledge check first. It sets your starting level.', 'info');
    redirect('knowledge-check.php');
}
if (!in_array($level, unlocked_levels($me['id'], $game), true)) {
    flash('That level is still locked. Score ' . PASS_MARK . '% on the level before it to open it.', 'info');
    redirect('dashboard.php');
}

$info = $games[$game];
$cfg = [
    'game' => $game,
    'level' => $level,
    'levelName' => level_names()[$level],
    'levelTopic' => $info['levels'][$level],
    'gameName' => $info['name'],
    'csrf' => csrf_token(),
    'submitUrl' => url('api/submit.php'),
    'dashboardUrl' => url('dashboard.php'),
    'reportUrl' => url('report.php'),
    'playUrl' => url('play.php'),
    'passMark' => PASS_MARK,
];

if ($game === 1) {
    $pool = q_all("SELECT * FROM items WHERE status = 'active' AND ident_level = ? ORDER BY id", [$level]);
    $all = q_all("SELECT * FROM items WHERE status = 'active' AND ident_level > 0 ORDER BY id");
    $cfg['items'] = array_map('item_for_game', $pool);
    $cfg['allItems'] = array_map('item_for_game', $all);
} elseif ($game === 2) {
    $pool = q_all("SELECT * FROM items WHERE status = 'active' AND class_level = ? AND device_class <> 'none' ORDER BY id", [$level]);
    $cfg['items'] = array_map('item_for_game', $pool);
} else {
    $rows = q_all("SELECT id, title, symptom, fault_component, fault_type, hint, explanation FROM scenarios WHERE status = 'active' AND level = ?", [$level]);
    shuffle($rows);
    $rows = array_slice($rows, 0, 2);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }
    unset($r);
    $cfg['scenarios'] = $rows;
    $parts = [];
    foreach (q_all('SELECT * FROM items') as $r) {
        $parts[$r['slug']] = item_for_game($r);
    }
    $cfg['parts'] = $parts;
    $cfg['components'] = g3_components();
    $localThree = __DIR__ . '/assets/vendor/three.module.min.js';
    $threeUrl = is_file($localThree) ? url('assets/vendor/three.module.min.js') : THREE_CDN;
}

$pageTitle = $info['name'] . ' · ' . level_names()[$level];
$bodyClass = 'page-play game-' . $game;
require __DIR__ . '/includes/header.php';
?>
<div class="play-head" style="--game: <?= h($info['color']) ?>">
  <a class="back" href="dashboard.php">← My games</a>
  <div>
    <p class="eyebrow">Game <?= $game ?> · <?= h(level_names()[$level]) ?></p>
    <h1><?= h($info['name']) ?>: <?= h($info['levels'][$level]) ?></h1>
  </div>
</div>

<?php if ($game === 3): ?>
  <div id="game" class="g3" style="--game: <?= h($info['color']) ?>">
    <div class="g3-main">
      <div class="g3-stage" id="g3-stage">
        <div class="g3-loading" id="g3-loading"><?= mascot_svg(72) ?><p>Getting the 3D computer ready…</p></div>
        <div class="g3-views" id="g3-views" role="toolbar" aria-label="Camera views"></div>
        <div class="g3-tooltip" id="g3-tooltip" hidden></div>
        <div class="g3-help">Drag to look around · Scroll to zoom · Click to choose</div>
      </div>
      <section class="g3-dock" id="g3-dock" aria-label="Drag and drop parts"></section>
    </div>
    <aside class="g3-side"><div class="g3-panel" id="g3-panel" aria-live="polite"></div></aside>
  </div>
  <div id="g3-fallback" class="card fallback" hidden>
    <h2>The 3D game cannot start on this device</h2>
    <p id="g3-fallback-msg">This browser or computer cannot show 3D graphics (WebGL).</p>
    <p>You can still play Game 1 and Game 2, which use simple drag-and-drop.</p>
    <a class="btn" href="dashboard.php">Back to my games</a>
  </div>
  <script>window.BITSY = <?= js_json($cfg) ?>;</script>
  <script src="assets/js/icons.js"></script>
  <script src="assets/js/common.js"></script>
  <script>
    (function () {
      var c = document.createElement('canvas');
      var ok = !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
      if (!ok) {
        document.getElementById('game').hidden = true;
        document.getElementById('g3-fallback').hidden = false;
        window.BITSY.noWebGL = true;
      }
      setTimeout(function () {
        if (!window.BITSY.noWebGL && !window.__bitsy3dStarted) {
          document.getElementById('game').hidden = true;
          document.getElementById('g3-fallback').hidden = false;
          document.getElementById('g3-fallback-msg').textContent =
            'The 3D library (Three.js) did not load. Check the internet connection, or copy three.module.min.js into assets/vendor/ (see README).';
        }
      }, 12000);
    })();
  </script>
  <script type="importmap"><?= js_json(['imports' => ['three' => $threeUrl]]) ?></script>
  <script type="module" src="assets/js/game-build3d.js"></script>
<?php else: ?>
  <div id="game" class="g<?= $game ?>" style="--game: <?= h($info['color']) ?>"></div>
  <script>window.BITSY = <?= js_json($cfg) ?>;</script>
  <script src="assets/js/icons.js"></script>
  <script src="assets/js/common.js"></script>
  <script src="assets/js/<?= $game === 1 ? 'game-identification.js' : 'game-classification.js' ?>"></script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
