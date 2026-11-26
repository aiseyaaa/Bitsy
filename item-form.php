<?php
/*
 * Admin: add or edit a part, device or cable.
 */
define('ROOT', '../');
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $id ? q_row('SELECT * FROM items WHERE id = ?', [$id]) : null;
if ($id && !$item) {
    flash('That item was not found.', 'error');
    redirect('admin/items.php');
}
$f = $item ?: [
    'name' => '', 'category' => 'peripheral', 'device_class' => 'none', 'ident_level' => 1, 'class_level' => 0,
    'description' => '', 'icon' => 'chip', 'image_path' => null, 'port_name' => '', 'port_icon' => '', 'status' => 'active',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $f['name'] = trim($_POST['name'] ?? '');
    $f['category'] = $_POST['category'] ?? '';
    $f['device_class'] = $_POST['device_class'] ?? 'none';
    $f['ident_level'] = (int) ($_POST['ident_level'] ?? 0);
    $f['class_level'] = (int) ($_POST['class_level'] ?? 0);
    $f['description'] = trim($_POST['description'] ?? '');
    $f['icon'] = $_POST['icon'] ?? 'chip';
    $f['port_name'] = trim($_POST['port_name'] ?? '');
    $f['port_icon'] = $_POST['port_icon'] ?? '';

    if ($f['name'] === '' || mb_strlen($f['name']) > 100) $errors['name'] = 'Type a name (up to 100 letters).';
    if (!isset(category_names()[$f['category']])) $errors['category'] = 'Choose a type.';
    if (!isset(class_names()[$f['device_class']])) $errors['device_class'] = 'Choose a device type.';
    if ($f['ident_level'] < 0 || $f['ident_level'] > 3) $errors['ident_level'] = 'Choose a level.';
    if ($f['class_level'] < 0 || $f['class_level'] > 3) $errors['class_level'] = 'Choose a level.';
    if ($f['class_level'] > 0 && $f['device_class'] === 'none') $errors['class_level'] = 'To use it in Game 2, set the device type to Input, Output or Storage.';
    if ($f['description'] === '') $errors['description'] = 'Explain in one or two sentences what it does.';
    if (!in_array($f['icon'], icon_keys(), true)) $f['icon'] = 'chip';
    if ($f['port_icon'] !== '' && !in_array($f['port_icon'], port_icon_keys(), true)) $f['port_icon'] = '';
    if ($f['category'] !== 'cable') {
        $f['port_name'] = '';
        $f['port_icon'] = '';
    }

    // Optional picture upload (PNG, JPG, GIF or WEBP).
    $newImage = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = $_FILES['image'];
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        $allowed = ['png' => IMAGETYPE_PNG, 'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP];
        if ($up['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = 'The upload did not work. Try a smaller picture.';
        } elseif ($up['size'] > MAX_UPLOAD_BYTES) {
            $errors['image'] = 'The picture is too big (2 MB at most).';
        } elseif (!isset($allowed[$ext])) {
            $errors['image'] = 'Use a PNG, JPG, GIF or WEBP picture.';
        } else {
            $info = @getimagesize($up['tmp_name']);
            if (!$info || $info[2] !== $allowed[$ext]) {
                $errors['image'] = 'That file is not a valid picture.';
            } else {
                $dir = __DIR__ . '/../uploads/items';
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $fileName = bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                if (move_uploaded_file($up['tmp_name'], $dir . '/' . $fileName)) {
                    $newImage = 'uploads/items/' . $fileName;
                } else {
                    $errors['image'] = 'Could not save the picture. Check that the uploads folder can be written to.';
                }
            }
        }
    }

    if (!$errors) {
        $image = $f['image_path'];
        if (!empty($_POST['remove_image'])) {
            $image = null;
        }
        if ($newImage) {
            $image = $newImage;
        }
        $data = [$f['name'], $f['category'], $f['device_class'], $f['ident_level'], $f['class_level'], $f['description'],
                 $f['icon'], $image, $f['port_name'] !== '' ? $f['port_name'] : null, $f['port_icon'] !== '' ? $f['port_icon'] : null];
        if ($item) {
            q('UPDATE items SET name = ?, category = ?, device_class = ?, ident_level = ?, class_level = ?, description = ?, icon = ?,
               image_path = ?, port_name = ?, port_icon = ?, updated_at = ? WHERE id = ?', array_merge($data, [now(), $id]));
            flash('Saved "' . $f['name'] . '".');
        } else {
            $slug = slugify($f['name']);
            $base = $slug;
            $n = 2;
            while (q_val('SELECT id FROM items WHERE slug = ?', [$slug])) {
                $slug = $base . '-' . $n++;
            }
            q('INSERT INTO items (slug, name, category, device_class, ident_level, class_level, description, icon, image_path, port_name, port_icon, status, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge([$slug], $data, ['active', now(), now()]));
            flash('Added "' . $f['name'] . '".');
        }
        redirect('admin/items.php');
    }
}

$levelOpts = [0 => 'Not used'] + level_names();
$pageTitle = $item ? 'Edit ' . $item['name'] : 'Add a part or device';
$activeNav = 'items';
$bodyClass = 'page-admin';
require __DIR__ . '/../includes/header.php';
$err = function ($k) use ($errors) {
    return isset($errors[$k]) ? '<em class="err">' . h($errors[$k]) . '</em>' : '';
};
?>
<a class="back" href="items.php">← All parts and devices</a>
<h1><?= $item ? 'Edit: ' . h($item['name']) : 'Add a part, device or cable' ?></h1>
<?php if ($errors): ?><div class="flash flash-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card form-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="form-main">
    <label class="field"><span>Name</span>
      <input name="name" value="<?= h($f['name']) ?>" maxlength="100" required><?= $err('name') ?></label>
    <div class="field-row">
      <label class="field"><span>Type</span>
        <select name="category" id="category">
          <?php foreach (category_names() as $k => $v): ?><option value="<?= h($k) ?>" <?= $f['category'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
        </select><?= $err('category') ?></label>
      <label class="field"><span>Device type (for Game 2)</span>
        <select name="device_class">
          <?php foreach (class_names() as $k => $v): ?><option value="<?= h($k) ?>" <?= $f['device_class'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
        </select><?= $err('device_class') ?></label>
    </div>
    <div class="field-row">
      <label class="field"><span>Level in Game 1: Identification</span>
        <select name="ident_level">
          <?php foreach ($levelOpts as $k => $v): ?><option value="<?= $k ?>" <?= (int) $f['ident_level'] === $k ? 'selected' : '' ?>><?= h($v) ?><?= $k ? ' · ' . h(games()[1]['levels'][$k]) : '' ?></option><?php endforeach; ?>
        </select><?= $err('ident_level') ?></label>
      <label class="field"><span>Level in Game 2: Classification</span>
        <select name="class_level">
          <?php foreach ($levelOpts as $k => $v): ?><option value="<?= $k ?>" <?= (int) $f['class_level'] === $k ? 'selected' : '' ?>><?= h($v) ?><?= $k ? ' · ' . h(games()[2]['levels'][$k]) : '' ?></option><?php endforeach; ?>
        </select><?= $err('class_level') ?></label>
    </div>
    <label class="field"><span>What it does (shown to learners after they answer)</span>
      <textarea name="description" rows="3" maxlength="500" required><?= h($f['description']) ?></textarea><?= $err('description') ?></label>
    <fieldset class="field cable-only" id="cable-fields">
      <legend>For cables: the port it plugs into (used in the matching round)</legend>
      <div class="field-row">
        <label class="field"><span>Port name</span><input name="port_name" value="<?= h($f['port_name']) ?>" maxlength="100" placeholder="e.g. HDMI Port"></label>
        <label class="field"><span>Port picture</span>
          <select name="port_icon" id="port_icon">
            <option value="">None (leave out of the matching round)</option>
            <?php foreach (port_icon_keys() as $k): ?><option value="<?= h($k) ?>" <?= $f['port_icon'] === $k ? 'selected' : '' ?>><?= h(ucwords(str_replace(['port-', '-'], ['', ' '], $k))) ?></option><?php endforeach; ?>
          </select></label>
      </div>
      <div class="icon-preview small-preview" id="port-preview"></div>
    </fieldset>
  </div>
  <aside class="form-side">
    <label class="field"><span>Built-in picture</span>
      <select name="icon" id="icon">
        <?php foreach (icon_keys() as $k): ?><option value="<?= h($k) ?>" <?= $f['icon'] === $k ? 'selected' : '' ?>><?= h(ucwords(str_replace('-', ' ', $k))) ?></option><?php endforeach; ?>
      </select></label>
    <div class="icon-preview" id="icon-preview"></div>
    <label class="field"><span>Or upload a picture (PNG, JPG, GIF, WEBP, up to 2 MB)</span>
      <input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp"><?= $err('image') ?></label>
    <?php if ($f['image_path']): ?>
      <div class="current-img"><img src="<?= h(url($f['image_path'])) ?>" alt="Current picture">
        <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove this picture and use the built-in one</label></div>
    <?php endif; ?>
    <p class="muted small">An uploaded picture is shown instead of the built-in drawing. Write its source and license in your project documentation.</p>
  </aside>
  <div class="form-actions">
    <button class="btn btn-lg" type="submit">Save</button>
    <a class="btn btn-lg btn-ghost" href="items.php">Cancel</a>
  </div>
</form>
<script src="../assets/js/icons.js"></script>
<script>
  (function () {
    var icon = document.getElementById('icon'), prev = document.getElementById('icon-preview');
    var port = document.getElementById('port_icon'), pprev = document.getElementById('port-preview');
    var cat = document.getElementById('category'), cable = document.getElementById('cable-fields');
    function upd() {
      prev.innerHTML = BitsyIcons.svg(icon.value);
      pprev.innerHTML = port.value ? BitsyIcons.svg(port.value) : '';
      cable.hidden = cat.value !== 'cable';
    }
    icon.addEventListener('change', upd); port.addEventListener('change', upd); cat.addEventListener('change', upd);
    upd();
  })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
