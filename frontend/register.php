<?php
require_once 'config/layout.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $name = trim(post_text('full_name',480));
    $email = strtolower(trim(post_text('email',120)));
    $pass = post_text('password',72);
    $grade = trim(post_text('grade',20));
    $school = trim(post_text('school',600));
    if (rate_limited('register', 6, 600)) $error = 'Too many attempts. Please try again later.';
    elseif (mb_strlen($name) < 2 || mb_strlen($name) > 120) $error = 'Enter a name between 2 and 120 characters.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $error = 'Enter a valid email address.';
    elseif (password_error($pass)) $error = password_error($pass);
    elseif (!in_array($grade, ['', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'], true)) $error = 'Select a valid grade.';
    elseif (mb_strlen($school) > 150) $error = 'School name is too long.';
    elseif (!isset($_POST['privacy_ack'])) $error = 'Please confirm that you have read the privacy information.';
    else {
        try {
            db()->beginTransaction();
            $stmt = db()->prepare("INSERT INTO users (full_name,email,password_hash,role,grade,school) VALUES (?,?,?,'learner',?,?)");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $grade ?: null, $school ?: null]);
            $newId=(int)db()->lastInsertId();
            db()->prepare('INSERT INTO user_security(user_id) VALUES(?)')->execute([$newId]);
            db()->prepare("INSERT INTO consent_records(user_id,purpose,policy_version,granted_at) VALUES(?,'service_privacy','2026-10-27',UTC_TIMESTAMP())")->execute([$newId]);
            audit_event('registered','account',$newId,[],$newId);
            db()->commit();
            if(mail_ready()) send_account_token(['id'=>$newId,'email'=>$email],'verify');
            if(env_value('REQUIRE_EMAIL_VERIFICATION')==='1') { header('Location: recovery.php?registered=1'); exit; }
            start_authenticated_session(['id'=>$newId]);
            header('Location: dashboard.php'); exit;
        } catch (PDOException $ex) {
            if(db()->inTransaction()) db()->rollBack();
            if ($ex->getCode() === '23000') $error = 'Unable to create this account. If you already registered, use sign in or password recovery.';
            else { error_log('Career Compass registration database failure.'); $error = 'Could not create the account right now. Please try later.'; }
        }
    }
}
page_start('Create an account');
?>
<main id="main" class="auth-section wide"><div class="auth-intro"><span class="eyebrow muted-eyebrow">LET'S GET TO KNOW YOU</span><h1>New beginnings<br><em>start here.</em></h1><p>Create your account to save your interest profile and explore personalised career recommendations.</p><div class="auth-illustration"><span>✳</span><strong>Your journey,<br>your way.</strong></div></div><section class="auth-card"><a href="index.php" class="back-link">← Back to home</a><h2>Create your account</h2><p class="muted">Already have an account? <a href="login.php">Sign in</a></p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e(t($error)) ?></div><?php endif; ?>
<form method="post" class="form-grid" autocomplete="on"><?= csrf_field() ?>
<label><?= e(t('Full name')) ?> <span>*</span><input name="full_name" autocomplete="name" maxlength="120" required value="<?= e(post_text('full_name',480)) ?>" placeholder="Your full name"></label>
<label><?= e(t('Email address')) ?> <span>*</span><input type="email" name="email" autocomplete="email" maxlength="120" required value="<?= e(post_text('email',120)) ?>" placeholder="you@example.com"></label>
<label><?= e(t('Password')) ?> <span>*</span><input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required placeholder="At least 12 characters"><small>Use a long password you do not use elsewhere (12–72 bytes).</small></label>
<div class="form-row"><label>School grade <select name="grade"><option value="">Select grade (optional)</option><?php foreach (['Grade 9','Grade 10','Grade 11','Grade 12'] as $grade): ?><option value="<?= e($grade) ?>" <?= ($_POST['grade'] ?? '') === $grade ? 'selected' : '' ?>><?= e($grade) ?></option><?php endforeach; ?></select></label><label>School name<input name="school" maxlength="150" value="<?= e(post_text('school',600)) ?>" placeholder="Optional"></label></div>
<label class="check-label"><input type="checkbox" name="privacy_ack" value="1" required <?= isset($_POST['privacy_ack']) ? 'checked' : '' ?>><span>I have read the <a href="privacy.php" target="_blank" rel="noopener">privacy information</a>. If I am under the required age, I will follow my school or guardian's guidance before using the service.</span></label>
<button class="btn btn-primary btn-full" type="submit"><?= e(t('Create account')) ?> ↗</button></form><p class="form-note">This service provides educational guidance, not admission decisions or guarantees.</p></section></main>
<?php page_end(); ?>

