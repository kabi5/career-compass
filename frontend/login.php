<?php
require_once 'config/layout.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $email = strtolower(trim(post_text('email',120)));
    $pass = post_text('password',72);
    $blockedIp=rate_limited('login', 30, 600);
    $blockedAccount=security_rate_limit('login-account:'.$email,8,600);
    if ($blockedIp || $blockedAccount) $error = 'Too many login attempts. Wait 10 minutes and try again.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    else {
        $stmt = db()->prepare('SELECT u.id,u.password_hash,u.role,s.email_verified_at FROM users u LEFT JOIN user_security s ON s.user_id=u.id WHERE u.email=? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $valid=password_verify($pass, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if ($user && $valid) {
            if(env_value('REQUIRE_EMAIL_VERIFICATION')==='1' && !$user['email_verified_at']) {
                $error='Verify your email before signing in. Use the email verification request on the recovery page.';
            } else {
                if(password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT)) db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$user['id']]);
                start_authenticated_session($user);
                audit_event('signed_in','account',$user['id']);
                header('Location: '.($user['role']==='admin'?'admin.php':($user['role']==='teacher'?'teacher.php':'dashboard.php'))); exit;
            }
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
page_start('Welcome back');
?>
<main id="main" class="auth-section wide"><div class="auth-intro"><span class="eyebrow muted-eyebrow">GOOD TO HAVE YOU BACK</span><h1>Keep moving<br><em>forward.</em></h1><p>Your next discovery is waiting. Sign in to see your interests, saved recommendations and career pathways.</p><div class="auth-illustration"><span>↗</span><strong>Every step<br>counts.</strong></div></div><section class="auth-card"><a class="back-link" href="index.php">← Back to home</a><h2>Welcome back</h2><p class="muted">New here? <a href="register.php">Create your account</a></p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e(t($error)) ?></div><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?><label><?= e(t('Email address')) ?><input type="email" name="email" autocomplete="username" value="<?= e(post_text('email',120)) ?>" required placeholder="you@example.com"></label><label><?= e(t('Password')) ?><input type="password" name="password" autocomplete="current-password" maxlength="72" required placeholder="Your password"></label><button type="submit" class="btn btn-primary btn-full">Sign in to my dashboard ↗</button></form><p class="form-note"><a href="recovery.php">Reset your password or request email verification</a></p></section></main>
<?php page_end(); ?>

