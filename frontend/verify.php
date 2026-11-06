<?php
require_once 'config/layout.php';
header('Referrer-Policy: no-referrer');
if(isset($_GET['token'])) { $_SESSION['verify_token']=is_string($_GET['token'])?$_GET['token']:'';header('Location: verify.php');exit; }
$message='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf']??null);
    if(rate_limited('verify',10,900)) $message='Too many attempts. Please try again in 15 minutes.';
    else {
        $ok=consume_account_token((string)($_SESSION['verify_token']??''),'verify');
        unset($_SESSION['verify_token']);
        $message=$ok?'Email verified. You can now sign in.':'This verification link is invalid, already used or expired. Request a new link.';
    }
}
page_start('Verify email'); ?>
<main id="main" class="wide page-space"><section class="panel narrow-panel"><h1>Verify email</h1>
<?php if($message): ?><div class="alert" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if(isset($_SESSION['verify_token'])): ?><p>Confirm that this is your email verification request.</p><form method="post"><?= csrf_field() ?><button class="btn btn-primary">Verify my email</button></form><?php endif; ?>
<p><a href="login.php">Sign in</a> · <a href="recovery.php">Request a new link</a></p></section></main>
<?php page_end(); ?>
