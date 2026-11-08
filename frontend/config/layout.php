<?php
require_once __DIR__ . '/db.php';
function page_start(string $title, string $section = ''): void {
    $user = current_user();
    $name = $user ? explode(' ', trim($user['full_name']))[0] : '';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#103f3c">
<meta name="description" content="Explore career interests and educational pathways with Career Compass, built for learners in Lesotho.">
<title><?= e($title) ?> · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css?v=2">
<script defer src="assets/js/app.js?v=2"></script>
</head>
<body class="<?= $user ? 'app-body' : 'public-body' ?>">
<a href="#main" class="skip-link">Skip to content</a>
<header class="site-header">
  <div class="header-inner">
    <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="brand" aria-label="Career Compass home">
      <span class="brand-mark" aria-hidden="true">✦</span><span>career<span class="brand-accent">compass</span><small>Find your direction.</small></span>
    </a>
    <nav class="nav-links" aria-label="Main navigation">
      <?php if ($user): ?>
        <a class="<?= $section === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
        <a class="<?= $section === 'assessment' ? 'active' : '' ?>" href="assessment.php">Assessment</a>
        <a class="<?= $section === 'careers' ? 'active' : '' ?>" href="careers.php">Explore careers</a>
        <a href="directory.php">Study &amp; funding</a>
        <a class="<?= $section === 'chat' ? 'active' : '' ?>" href="chat.php">AI assistant</a>
        <a class="<?= $section === 'profile' ? 'active' : '' ?>" href="profile.php">My profile</a>
        <?php if ($user['role'] === 'admin'): ?><a href="admin.php">Admin</a><?php endif; ?>
        <form action="logout.php" method="post" class="nav-logout"><?= csrf_field() ?><button class="nav-signout" type="submit">Sign out</button></form>
      <?php else: ?>
        <a href="index.php#how-it-works">How it works</a>
        <a href="index.php#features">Features</a>
        <a href="login.php">Sign in</a>
        <a class="nav-cta" href="register.php">Get started <span aria-hidden="true">↗</span></a>
      <?php endif; ?>
    </nav>
    <button class="mobile-menu" type="button" aria-label="Toggle navigation" aria-expanded="false" data-menu-toggle>☰</button>
  </div>
</header>
<?php
}
function page_end(): void { ?>
<footer class="site-footer"><div class="footer-inner"><span>✦ Career Compass · Built for learners in Lesotho</span><span>Guidance to explore, not a final career decision. <a href="privacy.php">Privacy</a></span></div></footer>
</body></html>
<?php }
