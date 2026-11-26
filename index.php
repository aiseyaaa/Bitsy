<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect($u['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
}

$pageTitle = 'Learn computer hardware by playing';
$bodyClass = 'page-home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-text">
    <p class="eyebrow">Free · No installation · Works in your browser</p>
    <h1>Learn the parts of a computer by <span class="hl">playing</span>.</h1>
    <p class="lead">Bitsy has three short games. Name the parts, sort the devices, then build and fix a 3D computer, without the risk of breaking a real one.</p>
    <div class="hero-cta">
      <a class="btn btn-lg" href="register.php">Start learning</a>
      <a class="btn btn-lg btn-ghost" href="login.php">I have an account</a>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <div class="hero-bubble">Hi! I'm Bitsy. Let's open up a computer together.</div>
    <?= mascot_svg(220) ?>
  </div>
</section>

<section class="steps-row" aria-label="How Bitsy works">
  <?php foreach (games() as $g => $info): ?>
    <article class="step-card" style="--game: <?= h($info['color']) ?>">
      <div class="step-num"><?= $g ?></div>
      <h2><?= h($info['name']) ?></h2>
      <p class="step-tag"><?= h($info['tagline']) ?></p>
      <p><?= h($info['desc']) ?></p>
    </article>
  <?php endforeach; ?>
</section>

<section class="how">
  <h2>How it works</h2>
  <ol class="how-list">
    <li><b>Make a free account.</b> Just a name, username and password.</li>
    <li><b>Take a quick knowledge check.</b> Twelve questions find the right starting level for you.</li>
    <li><b>Play Easy, Medium and Hard levels.</b> Each game gets you ready for the next one.</li>
    <li><b>See how much you improved.</b> After every level you get a progress report and can earn badges.</li>
  </ol>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
