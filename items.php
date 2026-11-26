<?php
/*
 * Admin: list of parts, devices and cables. Archive or restore them.
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['archive', 'restore'], true)) {
        q('UPDATE items SET status = ?, updated_at = ? WHERE id = ?', [$action === 'archive' ? 'archived' : 'active', now(), $id]);
        flash($action === 'archive' ? 'Archived. It no longer appears in the games.' : 'Restored. It appears in the games again.');
    }
    redirect('admin/items.php?' . http_build_query(['cat' => $_POST['cat'] ?? '', 'status' => $_POST['status_filter'] ?? '']));
}

$cat = $_GET['cat'] ?? '';
$status = $_GET['status'] ?? '';
$where = [];
$params = [];
if (isset(category_names()[$cat])) {
    $where[] = 'category = ?';
    $params[] = $cat;
}
if (in_array($status, ['active', 'archived'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}
$items = q_all('SELECT * FROM items' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY category, ident_level, name', $params);
$levelNames = level_names();

$pageTitle = 'Parts & devices';
$activeNav = 'items';
$bodyClass = 'page-admin';
require __DIR__ . '/../includes/header.php';
?>
<section class="admin-head">
  <div>
    <h1>Parts, devices and cables</h1>
    <p class="muted">These appear in Game 1 (Identification) and Game 2 (Classification). Parts of the 3D computer use the matching names and pictures.</p>
  </div>
  <a class="btn" href="item-form.php">Add new</a>
</section>

<form class="filters" method="get">
  <label>Type
    <select name="cat" onchange="this.form.submit()">
      <option value="">All</option>
      <?php foreach (category_names() as $k => $v): ?><option value="<?= h($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Status
    <select name="status" onchange="this.form.submit()">
      <option value="">All</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>
  </label>
  <noscript><button class="btn btn-sm">Filter</button></noscript>
  <span class="muted"><?= count($items) ?> shown</span>
</form>

<div class="card table-wrap">
  <table class="table items-table">
    <thead><tr><th></th><th>Name</th><th>Type</th><th>Device type</th><th>Game 1 level</th><th>Game 2 level</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr class="<?= $it['status'] === 'archived' ? 'is-archived' : '' ?>">
        <td class="thumb">
          <?php if ($it['image_path']): ?><img src="<?= h(url($it['image_path'])) ?>" alt=""><?php else: ?><span data-icon="<?= h($it['icon'] ?: 'chip') ?>"></span><?php endif; ?>
        </td>
        <td><b><?= h($it['name']) ?></b><?php if ($it['port_name']): ?><br><span class="muted small">Plugs into: <?= h($it['port_name']) ?></span><?php endif; ?></td>
        <td><?= h(category_names()[$it['category']]) ?></td>
        <td><?= $it['device_class'] === 'none' ? '<span class="muted">–</span>' : h(ucfirst($it['device_class'])) ?></td>
        <td><?= (int) $it['ident_level'] ? h($levelNames[(int) $it['ident_level']]) : '<span class="muted">Not used</span>' ?></td>
        <td><?= (int) $it['class_level'] ? h($levelNames[(int) $it['class_level']]) : '<span class="muted">Not used</span>' ?></td>
        <td><span class="tag tag-<?= h($it['status']) ?>"><?= h(ucfirst($it['status'])) ?></span></td>
        <td class="actions">
          <a class="btn btn-sm btn-ghost" href="item-form.php?id=<?= (int) $it['id'] ?>">Edit</a>
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
            <input type="hidden" name="cat" value="<?= h($cat) ?>">
            <input type="hidden" name="status_filter" value="<?= h($status) ?>">
            <?php if ($it['status'] === 'active'): ?>
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
</div>
<script src="../assets/js/icons.js"></script>
<script>document.querySelectorAll('[data-icon]').forEach(function (el) { el.innerHTML = BitsyIcons.svg(el.dataset.icon); });</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
