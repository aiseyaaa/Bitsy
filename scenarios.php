<?php
/*
 * Admin: troubleshooting scenarios for Game 3. Archive or restore them.
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['archive', 'restore'], true)) {
        q('UPDATE scenarios SET status = ?, updated_at = ? WHERE id = ?', [$action === 'archive' ? 'archived' : 'active', now(), $id]);
        flash($action === 'archive' ? 'Scenario archived.' : 'Scenario restored.');
    }
    redirect('admin/scenarios.php');
}

$rows = q_all('SELECT * FROM scenarios ORDER BY level, status, id');
$comp = g3_components();
$types = fault_types();
$countActive = [1 => 0, 2 => 0, 3 => 0];
foreach ($rows as $r) {
    if ($r['status'] === 'active') {
        $countActive[(int) $r['level']]++;
    }
}

$pageTitle = 'Scenarios';
$activeNav = 'scenarios';
$bodyClass = 'page-admin';
require __DIR__ . '/../includes/header.php';
?>
<section class="admin-head">
  <div>
    <h1>Troubleshooting scenarios</h1>
    <p class="muted">Each time a learner plays a Build-a-PC level, Bitsy picks up to two active scenarios of that level at random.</p>
  </div>
  <a class="btn" href="scenario-form.php">Add a scenario</a>
</section>

<?php foreach (level_names() as $lv => $ln): ?>
  <section class="card">
    <div class="section-head">
      <h2><?= h($ln) ?> · <?= h(games()[3]['levels'][$lv]) ?></h2>
      <span class="muted"><?= $countActive[$lv] ?> active</span>
    </div>
    <?php if ($countActive[$lv] === 0): ?><p class="flash flash-info">No active scenarios. Learners will only build at this level.</p><?php endif; ?>
    <table class="table">
      <thead><tr><th>Title</th><th>Faulty part</th><th>Fault</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): if ((int) $r['level'] !== $lv) continue; ?>
        <tr class="<?= $r['status'] === 'archived' ? 'is-archived' : '' ?>">
          <td><b><?= h($r['title']) ?></b><br><span class="muted small"><?= h($r['symptom']) ?></span></td>
          <td><?= h($comp[$r['fault_component']]['name'] ?? $r['fault_component']) ?></td>
          <td><?= h(explode(' (', $types[$r['fault_type']] ?? $r['fault_type'])[0]) ?></td>
          <td><span class="tag tag-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span></td>
          <td class="actions">
            <a class="btn btn-sm btn-ghost" href="scenario-form.php?id=<?= (int) $r['id'] ?>">Edit</a>
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <?php if ($r['status'] === 'active'): ?>
                <button class="btn btn-sm btn-ghost" name="action" value="archive">Archive</button>
              <?php else: ?>
                <button class="btn btn-sm btn-ghost" name="action" value="restore">Restore</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
