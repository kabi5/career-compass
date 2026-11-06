<?php
// Run ONLY against a disposable migrated database whose name begins security_test_.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(!preg_match('/^security_test_[a-z0-9_]+$/D',getenv('DB_NAME')?:'')){fwrite(STDERR,"Set DB_NAME to a disposable migrated security_test_* database.\n");exit(2);}
require dirname(__DIR__).'/frontend/config/db.php';require dirname(__DIR__).'/frontend/config/admin_helpers.php';
$passed=0;$ids=[];$schoolIds=[];$tokenHashes=[];
function check(bool $condition,string $label):void{global $passed;if(!$condition)throw new RuntimeException('FAILED: '.$label);$passed++;}
function rejected(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException $ex){check(true,$label);return;}check(false,$label);}
$pdo=db();$tag=bin2hex(random_bytes(6));$password='A secure test password 309!';
try {
 check(current_user()===null,'anonymous account absent');
 foreach(['A','B'] as $suffix){$pdo->prepare('INSERT INTO schools(name,code) VALUES(?,?)')->execute(['TEST school '.$suffix,'TEST_'.$tag.'_'.$suffix]);$schoolIds[]=(int)$pdo->lastInsertId();}
 foreach(['learner','teacher','teacher','admin'] as $index=>$role){$pdo->prepare('INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,?)')->execute(['Security fixture',$tag.$index.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),$role]);$ids[]=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO user_security(user_id,school_id) VALUES(?,?)')->execute([$ids[$index],$index===2?$schoolIds[1]:$schoolIds[0]]);}
 [$learner,$teacher,$unrelated,$admin]=$ids;
 check(!teacher_can_view($teacher,$learner),'consent absent denies access');
 $pdo->prepare('INSERT INTO teacher_grants(learner_id,teacher_id,granted_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$learner,$teacher]);
 check(teacher_can_view($teacher,$learner),'same school with consent allowed');
 $pdo->prepare('INSERT INTO teacher_grants(learner_id,teacher_id,granted_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$learner,$unrelated]);
 check(!teacher_can_view($unrelated,$learner),'unrelated school denied despite grant');
 $pdo->prepare('UPDATE teacher_grants SET revoked_at=UTC_TIMESTAMP() WHERE teacher_id=?')->execute([$teacher]);
 check(!teacher_can_view($teacher,$learner),'revocation immediate');
 $pdo->prepare('UPDATE teacher_grants SET revoked_at=NULL WHERE teacher_id=?')->execute([$teacher]);
 $pdo->prepare("UPDATE users SET role='learner' WHERE id=?")->execute([$teacher]);
 check(!teacher_can_view($teacher,$learner),'role demotion immediate');
 $pdo->prepare("UPDATE users SET role='teacher' WHERE id=?")->execute([$teacher]);
 start_authenticated_session(['id'=>$teacher]);check(current_user()['role']==='teacher','authenticated session established');
 $pdo->prepare("UPDATE users SET role='learner' WHERE id=?")->execute([$teacher]);check(current_user()['role']==='learner','role reloaded per request');
 $pdo->prepare('UPDATE user_security SET auth_version=auth_version+1 WHERE user_id=?')->execute([$teacher]);check(current_user()===null,'security version invalidates old session');
 start_authenticated_session(['id'=>$learner]);$_SESSION['last_active']=time()-1801;check(current_user()===null,'idle timeout');
 start_authenticated_session(['id'=>$learner]);$_SESSION['signed_in_at']=time()-28801;check(current_user()===null,'absolute timeout');
 $key='security-test-'.$tag;check(!security_rate_limit($key,2,600),'first attempt permitted');check(!security_rate_limit($key,2,600),'second attempt permitted');$_SESSION=[];check(security_rate_limit($key,2,600),'limiter survives session rotation');
 check(password_error('short')!==null,'short passwords rejected');check(password_error(str_repeat('x',73))!==null,'bcrypt truncation prevented');check(password_error($password)===null,'valid password accepted');
 check(!consume_account_token('predictable','reset',$password),'malformed recovery token rejected');
 $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$tokenHashes[]=$hash;
 $pdo->prepare("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,'reset',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))")->execute([$learner,$hash]);
 $new='Another secure test password 309!';check(consume_account_token($token,'reset',$new),'valid token consumed');check(!consume_account_token($token,'reset',$new),'single use enforced');
 $q=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$learner]);check(password_verify($new,$q->fetchColumn()),'reset password hashed and usable');
 $expired=bin2hex(random_bytes(32));$pdo->prepare("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,'reset',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))")->execute([$learner,hash('sha256',$expired)]);check(!consume_account_token($expired,'reset',$new),'expired token rejected');
 $verify=bin2hex(random_bytes(32));$pdo->prepare("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,'verify',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))")->execute([$learner,hash('sha256',$verify)]);check(!consume_account_token($verify,'reset',$new),'token purpose bound');check(consume_account_token($verify,'verify'),'email verification consumed');
 $q=$pdo->prepare('SELECT email_verified_at FROM user_security WHERE user_id=?');$q->execute([$learner]);check((bool)$q->fetchColumn(),'verification timestamp stored');
 $q=$pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE details LIKE ?');$q->execute(['%'.$token.'%']);check((int)$q->fetchColumn()===0,'raw tokens excluded from audits');
 check(safe_csv_cell('=HYPERLINK("bad")')[0]==="'",'CSV formulas neutralised');check(safe_csv_cell('  +123')[0]==="'",'CSV whitespace formulas neutralised');
 check(!official_url_valid('javascript:alert(1)'),'script URL rejected');check(!official_url_valid('https://user:secret@example.org/'),'credential URL rejected');
 $payload=['id'=>'test-institution','name'=>'TEST only','source_url'=>'https://example.invalid/source','source_document'=>'TEST fixture','last_verified'=>gmdate('Y-m-d'),'verification_scope'=>'TEST source scope only.'];
 check(validate_content_payload('institutions',$payload)['verified']===true,'valid draft metadata accepted');
 rejected(fn()=>validate_content_payload('institutions',array_merge($payload,['source_url'=>'javascript:alert(1)'])),'unsafe source rejected');
 rejected(fn()=>validate_content_payload('institutions',array_merge($payload,['last_verified'=>'2099-02-30'])),'invalid verification date rejected');
 rejected(fn()=>validate_content_payload('users',$payload),'arbitrary publication target rejected');
 $_POST=['email'=>['bad']];check(post_text('email')==='','array form value rejected');
 check(strlen(csrf_token())===64,'random CSRF token length');
 echo 'PASS: '.$passed." security/auth/access-control checks.\n";
}finally {
 $_SESSION=[];
 foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
 foreach($schoolIds as $id)$pdo->prepare('DELETE FROM schools WHERE id=?')->execute([$id]);
 // Fixture audit entries remain de-identified in this disposable database.
}
