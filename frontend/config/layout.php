<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/icons.php';
function page_start(string $title, string $section = ''): void {
    $user = current_user();
    $name = $user ? explode(' ', trim($user['full_name']))[0] : '';
    ?>
<!doctype html>
<html lang="<?= e(language()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#103f3c">
<link rel="icon" href="assets/icons/compass.svg" type="image/svg+xml">
<meta name="description" content="Explore career interests and educational pathways with Career Compass, built for learners in Lesotho.">
<title><?= e($title) ?> · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css?v=3">
<link rel="manifest" href="manifest.webmanifest">
<script defer src="assets/js/app.js?v=3"></script>
</head>
<body class="<?= $user ? 'app-body' : 'public-body' ?>">
<a href="#main" class="skip-link"><?= e(t('Skip to content')) ?></a>
<header class="site-header">
  <div class="header-inner">
    <a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" class="brand" aria-label="Career Compass home">
      <span class="brand-mark" aria-hidden="true">✦</span><span>career<span class="brand-accent">compass</span><small>Find your direction.</small></span>
    </a>
    <nav class="nav-links" aria-label="Main navigation">
      <?php if ($user): ?>
        <a class="<?= $section === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><?= e(t('Dashboard')) ?></a>
        <a class="<?= $section === 'assessment' ? 'active' : '' ?>" href="assessment.php"><?= e(t('Assessment')) ?></a>
        <a class="<?= $section === 'careers' ? 'active' : '' ?>" href="careers.php"><?= e(t('Explore careers')) ?></a>
        <a href="directory.php"><?= e(t('Study & funding')) ?></a>
        <a href="progress.php"><?= e(t('My progress')) ?></a>
        <a class="<?= $section === 'chat' ? 'active' : '' ?>" href="chat.php"><?= e(t('AI assistant')) ?></a>
        <a class="<?= $section === 'profile' ? 'active' : '' ?>" href="profile.php"><?= e(t('My profile')) ?></a>
        <a href="account.php"><?= e(t('Account')) ?></a>
        <?php if (in_array($user['role'],['teacher','admin'],true)): ?><a href="teacher.php">Educator</a><?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?><a href="admin.php">Admin</a><?php endif; ?>
        <form action="logout.php" method="post" class="nav-logout"><?= csrf_field() ?><button class="nav-signout" type="submit"><?= e(t('Sign out')) ?></button></form>
      <?php else: ?>
        <a href="index.php#how-it-works">How it works</a>
        <a href="index.php#features">Features</a>
        <a href="directory.php"><?= e(t('Study & funding')) ?></a>
        <a href="login.php"><?= e(t('Sign in')) ?></a>
        <a class="nav-cta" href="register.php"><?= e(t('Get started')) ?> <span aria-hidden="true">↗</span></a>
      <?php endif; ?>
    </nav>
    <a class="language-switch" href="<?= e(language_url()) ?>" lang="<?= language()==='en'?'st':'en' ?>"><?= language()==='en'?'Sesotho':'English' ?></a>
    <button class="mobile-menu" type="button" aria-label="Toggle navigation" aria-expanded="false" data-menu-toggle>☰</button>
  </div>
</header>
<?php if(language()==='st'): ?><div class="translation-note" role="note">Sesotho ke moralo o hlokang tlhahlobo. Draft translations require linguistic review; untranslated text appears in English.</div><?php endif; ?>
<?php
}
function page_end(): void { ?>
<footer class="site-footer"><div class="footer-inner"><span>✦ Career Compass · Built for learners in Lesotho</span><span>Guidance to explore, not a final career decision. <a href="privacy.php">Privacy</a></span></div></footer>
</body></html>
<?php }
