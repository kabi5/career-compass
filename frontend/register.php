<?php
require_once 'config/layout.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $grade = trim($_POST['grade'] ?? '');
    $school = trim($_POST['school'] ?? '');
    if (rate_limited('register', 6, 600)) $error = 'Too many attempts. Please try again later.';
    elseif (mb_strlen($name) < 2 || mb_strlen($name) > 120) $error = 'Enter a name between 2 and 120 characters.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $error = 'Enter a valid email address.';
    elseif (strlen($pass) < 12) $error = 'Choose a password of at least 12 characters.';
    elseif (!in_array($grade, ['', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'], true)) $error = 'Select a valid grade.';
    elseif (mb_strlen($school) > 150) $error = 'School name is too long.';
    elseif (!isset($_POST['privacy_ack'])) $error = 'Please confirm that you have read the privacy information.';
    else {
        try {
            $stmt = db()->prepare("INSERT INTO users (full_name,email,password_hash,role,grade,school) VALUES (?,?,?,'learner',?,?)");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $grade ?: null, $school ?: null]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            header('Location: dashboard.php'); exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') $error = 'That email is already registered. Try signing in.';
            else { error_log('Registration failed: ' . $ex->getMessage()); $error = 'Could not create the account right now. Please try later.'; }
        }
    }
}
page_start('Create an account');
?>
<main id="main" class="auth-section wide"><div class="auth-intro"><span class="eyebrow muted-eyebrow">LET'S GET TO KNOW YOU</span><h1>New beginnings<br><em>start here.</em></h1><p>Create your account to save your interest profile and explore personalised career recommendations.</p><div class="auth-illustration"><span>✳</span><strong>Your journey,<br>your way.</strong></div></div><section class="auth-card"><a href="index.php" class="back-link">← Back to home</a><h2>Create your account</h2><p class="muted">Already have an account? <a href="login.php">Sign in</a></p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-grid" autocomplete="on"><?= csrf_field() ?>
<label>Full name <span>*</span><input name="full_name" autocomplete="name" maxlength="120" required value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Your full name"></label>
<label>Email address <span>*</span><input type="email" name="email" autocomplete="email" maxlength="120" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com"></label>
<label>Password <span>*</span><input type="password" name="password" autocomplete="new-password" minlength="12" required placeholder="At least 12 characters"><small>Use a long password you do not use elsewhere.</small></label>
<div class="form-row"><label>School grade <select name="grade"><option value="">Select grade (optional)</option><?php foreach (['Grade 9','Grade 10','Grade 11','Grade 12'] as $grade): ?><option value="<?= e($grade) ?>" <?= ($_POST['grade'] ?? '') === $grade ? 'selected' : '' ?>><?= e($grade) ?></option><?php endforeach; ?></select></label><label>School name<input name="school" maxlength="150" value="<?= e($_POST['school'] ?? '') ?>" placeholder="Optional"></label></div>
<label class="check-label"><input type="checkbox" name="privacy_ack" value="1" required <?= isset($_POST['privacy_ack']) ? 'checked' : '' ?>><span>I have read the <a href="privacy.php" target="_blank" rel="noopener">privacy information</a>. If I am under the required age, I will follow my school or guardian's guidance before using the service.</span></label>
<button class="btn btn-primary btn-full" type="submit">Create account ↗</button></form><p class="form-note">This service provides educational guidance, not admission decisions or guarantees.</p></section></main>
<?php page_end(); ?>
