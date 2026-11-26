<?php
/*
 * Admin: add or edit a troubleshooting scenario. No code changes are needed:
 * the 3D game reads the faulty part and the kind of fault from the database.
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id ? q_row('SELECT * FROM scenarios WHERE id = ?', [$id]) : null;
if ($id && !$row) {
    flash('That scenario was not found.', 'error');
    redirect('admin/scenarios.php');
}
$f = $row ?: ['title' => '', 'symptom' => '', 'level' => 1, 'fault_component' => 'power-cable', 'fault_type' => 'unplugged', 'hint' => '', 'explanation' => ''];
$comp = g3_components();
$types = fault_types();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['title', 'symptom', 'hint', 'explanation'] as $k) {
        $f[$k] = trim($_POST[$k] ?? '');
    }
    $f['level'] = (int) ($_POST['level'] ?? 0);
    $f['fault_component'] = $_POST['fault_component'] ?? '';
    $f['fault_type'] = $_POST['fault_type'] ?? '';

    if ($f['title'] === '' || mb_strlen($f['title']) > 150) $errors['title'] = 'Type a short title (up to 150 letters).';
    if ($f['symptom'] === '') $errors['symptom'] = 'Describe what the learner sees or hears.';
    if ($f['level'] < 1 || $f['level'] > 3) $errors['level'] = 'Choose a level.';
    if (!isset($comp[$f['fault_component']])) {
        $errors['fault_component'] = 'Choose the faulty part.';
    } elseif (!in_array($f['level'], $comp[$f['fault_component']]['levels'], true)) {
        $errors['fault_component'] = 'This part is not in the 3D computer at this level.';
    }
    if (!isset($types[$f['fault_type']])) {
        $errors['fault_type'] = 'Choose what is wrong with it.';
    } elseif ($f['fault_type'] === 'switched_off' && $f['fault_component'] !== 'psu') {
        $errors['fault_type'] = '"Switched off" only works with the power supply.';
    }
    if ($f['hint'] === '') $errors['hint'] = 'Write a hint.';
    if ($f['explanation'] === '') $errors['explanation'] = 'Explain the fix (shown after the learner finds it).';

    if (!$errors) {
        $data = [$f['title'], $f['symptom'], $f['level'], $f['fault_component'], $f['fault_type'], $f['hint'], $f['explanation']];
        if ($row) {
            q('UPDATE scenarios SET title = ?, symptom = ?, level = ?, fault_component = ?, fault_type = ?, hint = ?, explanation = ?, updated_at = ? WHERE id = ?',
                array_merge($data, [now(), $id]));
            flash('Scenario saved.');
        } else {
            q('INSERT INTO scenarios (title, symptom, level, fault_component, fault_type, hint, explanation, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($data, ['active', now(), now()]));
            flash('Scenario added.');
        }
        redirect('admin/scenarios.php');
    }
}

$pageTitle = $row ? 'Edit scenario' : 'Add a scenario';
$activeNav = 'scenarios';
$bodyClass = 'page-admin';
require __DIR__ . '/../includes/header.php';
$err = function ($k) use ($errors) {
    return isset($errors[$k]) ? '<em class="err">' . h($errors[$k]) . '</em>' : '';
};
?>
<a class="back" href="scenarios.php">← All scenarios</a>
<h1><?= $row ? 'Edit scenario' : 'Add a troubleshooting scenario' ?></h1>
<?php if ($errors): ?><div class="flash flash-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" class="card form-narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <label class="field"><span>Title</span><input name="title" value="<?= h($f['title']) ?>" maxlength="150" required placeholder="e.g. The computer will not turn on"><?= $err('title') ?></label>
  <label class="field"><span>What happens (the symptom the learner reads)</span>
    <textarea name="symptom" rows="2" required><?= h($f['symptom']) ?></textarea><?= $err('symptom') ?></label>
  <div class="field-row three">
    <label class="field"><span>Level</span>
      <select name="level" id="level">
        <?php foreach (level_names() as $k => $v): ?><option value="<?= $k ?>" <?= (int) $f['level'] === $k ? 'selected' : '' ?>><?= h($v) ?> · <?= h(games()[3]['levels'][$k]) ?></option><?php endforeach; ?>
      </select><?= $err('level') ?></label>
    <label class="field"><span>Faulty part</span>
      <select name="fault_component" id="fault_component">
        <?php foreach ($comp as $k => $c): ?>
          <option value="<?= h($k) ?>" data-levels="<?= h(implode(',', $c['levels'])) ?>" <?= $f['fault_component'] === $k ? 'selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select><?= $err('fault_component') ?></label>
    <label class="field"><span>What is wrong</span>
      <select name="fault_type" id="fault_type">
        <?php foreach ($types as $k => $v): ?><option value="<?= h($k) ?>" <?= $f['fault_type'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
      </select><?= $err('fault_type') ?></label>
  </div>
  <label class="field"><span>Hint</span><textarea name="hint" rows="2" required><?= h($f['hint']) ?></textarea><?= $err('hint') ?></label>
  <label class="field"><span>Explanation of the fix (shown after it is found)</span><textarea name="explanation" rows="3" required><?= h($f['explanation']) ?></textarea><?= $err('explanation') ?></label>
  <p class="muted small">The monitor shows a matching screen automatically. For example, a loose RAM stick gives a black screen with beeps, a loose drive gives "No bootable device", and power faults leave the computer dead.</p>
  <div class="form-actions">
    <button class="btn btn-lg" type="submit">Save</button>
    <a class="btn btn-lg btn-ghost" href="scenarios.php">Cancel</a>
  </div>
</form>
<script>
  (function () {
    var level = document.getElementById('level'), part = document.getElementById('fault_component');
    function upd() {
      var lv = level.value;
      Array.prototype.forEach.call(part.options, function (o) {
        var ok = o.dataset.levels.split(',').indexOf(lv) !== -1;
        o.disabled = !ok; o.hidden = !ok;
      });
      if (part.selectedOptions[0] && part.selectedOptions[0].disabled) {
        var first = Array.prototype.find.call(part.options, function (o) { return !o.disabled; });
        if (first) part.value = first.value;
      }
    }
    level.addEventListener('change', upd);
    upd();
  })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
