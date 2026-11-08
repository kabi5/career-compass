<?php
require_once 'config/layout.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (rate_limited('login', 8, 600)) $error = 'Too many login attempts. Wait 10 minutes and try again.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    else {
        $stmt = db()->prepare('SELECT id,password_hash FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            unset($_SESSION['rate_login']);
            header('Location: dashboard.php'); exit;
        }
        $error = 'Incorrect email or password.';
    }
}
page_start('Welcome back');
?>
<main id="main" class="auth-section wide"><div class="auth-intro"><span class="eyebrow muted-eyebrow">GOOD TO HAVE YOU BACK</span><h1>Keep moving<br><em>forward.</em></h1><p>Your next discovery is waiting. Sign in to see your interests, saved recommendations and career pathways.</p><div class="auth-illustration"><span>↗</span><strong>Every step<br>counts.</strong></div></div><section class="auth-card"><a class="back-link" href="index.php">← Back to home</a><h2>Welcome back</h2><p class="muted">New here? <a href="register.php">Create your account</a></p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?><label>Email address<input type="email" name="email" autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="you@example.com"></label><label>Password<input type="password" name="password" autocomplete="current-password" required placeholder="Your password"></label><button type="submit" class="btn btn-primary btn-full">Sign in to my dashboard ↗</button></form><p class="form-note">Need help accessing your account? Contact your school or system administrator.</p></section></main>
<?php page_end(); ?>
