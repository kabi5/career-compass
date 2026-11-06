<?php
require_once 'config/layout.php';
header('Referrer-Policy: no-referrer');
// Remove secrets from the address bar before rendering links or submitting forms.
if(isset($_GET['token'])) {
    $_SESSION['reset_token']=is_string($_GET['token'])?$_GET['token']:'';
    header('Location: recovery.php'); exit;
}
$error=''; $message=''; $reset=isset($_SESSION['reset_token']);
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf']??null);
    if(rate_limited('account-recovery',10,900)) $error='Too many requests. Please try again in 15 minutes.';
    elseif($reset) {
        $password=(string)($_POST['password']??'');
        if(password_error($password))$error=password_error($password);
        elseif(!hash_equals($password,(string)($_POST['confirm_password']??'')))$error='The passwords do not match.';
        elseif(consume_account_token((string)$_SESSION['reset_token'],'reset',$password)) {
            unset($_SESSION['reset_token'],$_SESSION['user_id'],$_SESSION['auth_version']); $reset=false;
            $message='Your password has been changed and previous sessions have been signed out. You can sign in now.';
        } else {$error='This link is invalid, already used or expired. Request a new link.';unset($_SESSION['reset_token']);$reset=false;}
    } else {
        $email=strtolower(trim((string)($_POST['email']??'')));
        $purpose=($_POST['purpose']??'reset')==='verify'?'verify':'reset';
        $message='If an eligible account exists and email delivery is configured, a link will be sent. Check your inbox and spam folder.';
        if(filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($email)<=120 && !security_rate_limit('recovery-email:'.$email,3,3600) && mail_ready()) {
            $q=db()->prepare('SELECT id,email FROM users WHERE email=?');$q->execute([$email]);$u=$q->fetch();
            if($u)send_account_token($u,$purpose);
        }
    }
}
page_start('Account recovery');
?>
<main id="main" class="wide page-space"><section class="panel narrow-panel"><h1><?= $reset?'Choose a new password':'Account recovery' ?></h1>
<?php if(!mail_ready()): ?><div class="alert" role="status">Email delivery is not configured on this deployment. Contact the school or deployment operator for supervised account support. No recovery link will be displayed or written to a public log.</div><?php endif; ?>
<?php if(isset($_GET['registered'])): ?><p>Registration requires email verification on this deployment. Follow the link sent to your address, or request it below.</p><?php endif; ?>
<?php if($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if($message): ?><div class="alert success" role="status"><?= e($message) ?></div><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?>
<?php if($reset): ?><label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72" required></label><button class="btn btn-primary">Change password</button>
<?php else: ?><label>Email address<input type="email" name="email" autocomplete="email" maxlength="120" required></label><label>Request<select name="purpose"><option value="reset">Password reset</option><option value="verify">Email verification</option></select></label><button class="btn btn-primary">Request email link</button><?php endif; ?>
</form><p><a href="login.php">Back to sign in</a></p></section></main>
<?php page_end(); ?>
