<?php
// Trusted local operator only; never place scripts/ inside the public document root.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$root=dirname(__DIR__);require is_file($root.'/frontend/config/db.php')?$root.'/frontend/config/db.php':$root.'/html/config/db.php';
$opts=getopt('',['email:','role:','apply']);$email=strtolower(trim($opts['email']??''));$role=$opts['role']??'';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['learner','teacher','admin'],true)){fwrite(STDERR,"Usage: php scripts/security_role.php --email=existing@example.org --role=admin [--apply]\n");exit(2);}
$pdo=db();$pdo->beginTransaction();
try {
 $adminIds=$pdo->query("SELECT id FROM users WHERE role='admin' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
 $q=$pdo->prepare('SELECT id,role FROM users WHERE email=? FOR UPDATE');$q->execute([$email]);$user=$q->fetch();
 if(!$user)throw new RuntimeException('Register the account first; this tool does not create passwords.');
 if($user['role']==='admin'&&$role!=='admin'&&count($adminIds)<2)throw new RuntimeException('The last administrator cannot be demoted.');
 if(!array_key_exists('apply',$opts)){$pdo->rollBack();echo 'Dry run: existing account role '.$user['role'].' would become '.$role.". Add --apply to perform the change.\n";exit;}
 $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$user['id']]);
 $pdo->prepare('INSERT INTO user_security(user_id,auth_version) VALUES(?,2) ON DUPLICATE KEY UPDATE auth_version=auth_version+1')->execute([$user['id']]);
 $pdo->prepare('UPDATE teacher_grants SET revoked_at=UTC_TIMESTAMP() WHERE (learner_id=? OR teacher_id=?) AND revoked_at IS NULL')->execute([$user['id'],$user['id']]);
 $pdo->prepare("UPDATE consent_records SET revoked_at=UTC_TIMESTAMP() WHERE revoked_at IS NULL AND ((user_id=? AND purpose LIKE 'teacher_access:%') OR purpose=?)")->execute([$user['id'],'teacher_access:'.$user['id']]);
 audit_event('operator_role_changed','account',$user['id'],['from_role'=>$user['role'],'to_role'=>$role]);$pdo->commit();
 echo "Role updated. Existing sessions and guidance grants were revoked. School assignment remains an administrator responsibility.\n";
}catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$ex->getMessage()."\n");exit(1);}
