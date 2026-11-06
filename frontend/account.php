<?php
require_once 'config/layout.php';
require_login(); $user=current_user(); $uid=(int)$user['id']; $error='';$message='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf($_POST['csrf']??null); $action=(string)($_POST['action']??'');
 try {
  if($action==='verify') {
   if(rate_limited('request-verification',3,3600))throw new InvalidArgumentException('Please wait before requesting another email.');
   $message=send_account_token($user,'verify')?'A verification email was requested. Check your inbox.':'Email delivery is unavailable. Contact the deployment operator.';
  } elseif($action==='grant' || $action==='revoke') {
   if($user['role']!=='learner')throw new InvalidArgumentException('Only a learner can manage their guidance consent.');
   $teacher=filter_var($_POST['teacher_id']??null,FILTER_VALIDATE_INT);
   if(!$teacher)throw new InvalidArgumentException('Select an educator.');
   if($action==='grant') {
    if(empty($_POST['understand_consent']))throw new InvalidArgumentException('Read and confirm the guidance-sharing information first.');
    $q=db()->prepare("SELECT 1 FROM users t JOIN user_security ts ON ts.user_id=t.id JOIN user_security ls ON ls.user_id=? WHERE t.id=? AND t.role='teacher' AND ts.school_id IS NOT NULL AND ts.school_id=ls.school_id");$q->execute([$uid,$teacher]);
    if(!$q->fetchColumn())throw new InvalidArgumentException('This educator is not assigned to your school.');
   }
   db()->beginTransaction();
   if($action==='grant') {
    db()->prepare('INSERT INTO teacher_grants(learner_id,teacher_id,granted_at,revoked_at) VALUES(?,?,UTC_TIMESTAMP(),NULL) ON DUPLICATE KEY UPDATE granted_at=UTC_TIMESTAMP(),revoked_at=NULL')->execute([$uid,$teacher]);
    db()->prepare("INSERT INTO consent_records(user_id,purpose,policy_version,granted_at) VALUES(?,?,'2026-10-27',UTC_TIMESTAMP())")->execute([$uid,'teacher_access:'.$teacher]);
   } else {
    db()->prepare('UPDATE teacher_grants SET revoked_at=UTC_TIMESTAMP() WHERE learner_id=? AND teacher_id=?')->execute([$uid,$teacher]);
    db()->prepare('UPDATE consent_records SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose=? AND revoked_at IS NULL')->execute([$uid,'teacher_access:'.$teacher]);
   }
   audit_event($action==='grant'?'educator_access_granted':'educator_access_revoked','teacher',$teacher);
   db()->commit();$message=$action==='grant'?'Sharing is enabled for the selected educator. You can revoke it at any time.':'Educator access was revoked.';
  } elseif($action==='export') {
   if(rate_limited('account-export',5,3600))throw new InvalidArgumentException('Too many export requests. Please try later.');
   $data=['exported_at'=>gmdate(DATE_ATOM),'account'=>array_intersect_key($user,array_flip(['id','full_name','email','role','grade','school','school_id','email_verified_at']))];
   foreach(['learner_profiles','assessments','recommendations','profile_versions','recommendation_runs','recommendation_feedback','saved_items','career_plans','consent_records'] as $table) {
    $q=db()->prepare('SELECT * FROM '.$table.' WHERE user_id=?');$q->execute([$uid]);$data[$table]=$q->fetchAll();
   }
   foreach(['assessment_responses'=>'SELECT r.* FROM assessment_responses r JOIN assessments a ON a.id=r.assessment_id WHERE a.user_id=?','recommendation_details'=>'SELECT d.* FROM recommendation_details d JOIN recommendations r ON r.id=d.recommendation_id WHERE r.user_id=?','roadmap_milestones'=>'SELECT m.* FROM roadmap_milestones m JOIN career_plans p ON p.id=m.plan_id WHERE p.user_id=?','teacher_grants'=>'SELECT * FROM teacher_grants WHERE learner_id=?','counselling_notes'=>'SELECT * FROM counselling_notes WHERE learner_id=?'] as $key=>$sql) {
    $q=db()->prepare($sql);$q->execute([$uid]);$data[$key]=$q->fetchAll();
   }
   audit_event('personal_data_exported','account',$uid);
   header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="career-compass-my-data.json"');
   echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
  } elseif($action==='delete') {
   if(rate_limited('account-delete',5,900))throw new InvalidArgumentException('Too many attempts. Please try later.');
   $q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$uid]);
   if(($_POST['confirm_delete']??'')!=='DELETE' || !password_verify((string)($_POST['password']??''),(string)$q->fetchColumn()))throw new InvalidArgumentException('Enter your password and type DELETE to confirm.');
   if($user['role']==='admin')throw new InvalidArgumentException('An administrator must first transfer responsibilities and have another administrator remove their role.');
   db()->beginTransaction();
   // Do not retain an account identifier in deletion audit metadata.
   audit_event('account_deleted','account',null,[],null);
   db()->prepare('UPDATE audit_logs SET target_id=NULL WHERE target_type="account" AND target_id=?')->execute([(string)$uid]);
   db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);db()->commit();
   $_SESSION=[];session_regenerate_id(true);header('Location: index.php?account_deleted=1');exit;
  } else throw new InvalidArgumentException('Unknown action.');
 } catch(InvalidArgumentException $ex){if(db()->inTransaction())db()->rollBack();$error=$ex->getMessage();}
 catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();error_log('Career Compass account operation failed.');$error='The change could not be completed. Please try again.';}
}
$q=db()->prepare("SELECT u.id,u.full_name,g.granted_at,g.revoked_at FROM users u JOIN user_security s ON s.user_id=u.id LEFT JOIN teacher_grants g ON g.teacher_id=u.id AND g.learner_id=? WHERE u.role='teacher' AND s.school_id=? ORDER BY u.full_name");$q->execute([$uid,$user['school_id']]);$teachers=$q->fetchAll();
$q=db()->prepare('SELECT g.teacher_id,u.full_name FROM teacher_grants g JOIN users u ON u.id=g.teacher_id WHERE g.learner_id=? AND g.revoked_at IS NULL');$q->execute([$uid]);$activeGrants=$q->fetchAll();
page_start('Account and privacy','account'); ?>
<main id="main" class="wide page-space"><div class="page-intro"><h1>Account & privacy</h1><p>Control who can support your guidance journey and download your information.</p></div>
<?php if($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if($message): ?><div class="alert success" role="status"><?= e($message) ?></div><?php endif; ?>
<div class="two-column"><section class="panel"><h2>Email verification</h2><p><?= e($user['email']) ?> — <?= $user['email_verified_at']?'Verified':'Not verified' ?></p><?php if(!$user['email_verified_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><button class="btn btn-outline">Send verification email</button></form><p class="small"><?= mail_ready()?'Email transport is configured.':'Email delivery is not configured; ask the operator for help.' ?></p><?php endif; ?><p><a href="recovery.php">Change a forgotten password securely</a></p></section>
<section class="panel"><h2>Download your information</h2><p>Includes your profile, assessment history, recommendations, saved items, plans and guidance notes. Store your downloaded file privately.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="export"><button class="btn btn-primary">Download my data (JSON)</button></form></section></div>
<?php if($user['role']==='learner'): ?><section class="panel"><h2>Educator guidance consent</h2><p>Sharing lets the selected educator see your profile, interest results and recommendations and write guidance notes. It is optional and can be revoked immediately. Only educators assigned to your school by an administrator are eligible. Your self-entered school name does not grant access.</p><p>Follow your school's guardian-consent and safeguarding requirements before sharing. Do not put medical, family, or other sensitive details in guidance notes.</p>
<?php if(!$user['school_id']): ?><p class="empty-state">An administrator has not yet confirmed your school. Educator sharing is unavailable.</p><?php elseif(!$teachers): ?><p>No educators have been assigned to your school.</p><?php else: ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="grant"><label>Educator<select name="teacher_id" required><?php foreach($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>"><?= e($teacher['full_name']) ?></option><?php endforeach; ?></select></label><label class="check-label"><input type="checkbox" name="understand_consent" value="1" required><span>I understand what is shared and have followed my school's consent requirements.</span></label><button class="btn btn-primary">Allow guidance access</button></form><?php endif; ?>
<?php foreach($activeGrants as $grant): ?><form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="teacher_id" value="<?= (int)$grant['teacher_id'] ?>"><p>Active permission: <?= e($grant['full_name']) ?></p><button class="btn btn-outline">Revoke this educator's access</button></form><?php endforeach; ?></section><?php endif; ?>
<section class="panel"><h2>Delete my account</h2><p>Deletion permanently removes your active profile, assessments, recommendations, goals, saved items and notes about you. It cannot be undone through this website. Backups expire according to the operator's retention policy; anonymised operational audit entries may remain. An administrator must transfer their role before deleting their account.</p><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><label>Current password<input type="password" name="password" autocomplete="current-password" maxlength="72" required></label><label>Type DELETE to confirm<input name="confirm_delete" pattern="DELETE" autocomplete="off" required></label><button class="btn btn-outline">Permanently delete my account</button></form><p><a href="privacy.php">Read privacy and retention information</a></p></section></main>
<?php page_end(); ?>
