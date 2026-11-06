<?php
// Requires a local PHP HTTP server pointing at the same disposable security_test_* DB.
if(PHP_SAPI!=='cli'||!preg_match('/^security_test_[a-z0-9_]+$/D',getenv('DB_NAME')?:'')){fwrite(STDERR,"Use a disposable security_test_* database.\n");exit(2);}
require dirname(__DIR__).'/frontend/config/db.php';
$base=getenv('SECURITY_TEST_URL')?:'';
if(!preg_match('~^http://127\.0\.0\.1:\d+/?$~D',$base)){fwrite(STDERR,"Set SECURITY_TEST_URL=http://127.0.0.1:8093 for the disposable test server.\n");exit(2);}
$base=rtrim($base,'/');$jars=[];$ids=[];$schoolIds=[];$checks=0;$tag='http'.bin2hex(random_bytes(4));$password='Test fixture secure password 309!';
function assert_http(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function browser_request(string $client,string $path,?array $data=null):array {
 global $jars,$base;if(!isset($jars[$client]))$jars[$client]=tempnam(sys_get_temp_dir(),'cc-cookie-');
 $ch=curl_init($base.'/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$jars[$client],CURLOPT_COOKIEJAR=>$jars[$client],CURLOPT_TIMEOUT=>15]);
 if($data!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
 $raw=curl_exec($ch);if($raw===false)throw new RuntimeException('HTTP fixture server unavailable: '.curl_error($ch));
 $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$size=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);return ['status'=>$status,'headers'=>substr($raw,0,$size),'body'=>substr($raw,$size)];
}
function browser_csrf(string $client,string $page):string{$r=browser_request($client,$page);if(!preg_match('/name="csrf" value="([a-f0-9]{64})"/',$r['body'],$m))throw new RuntimeException('No CSRF field: '.$page.' ('.$r['status'].')');return $m[1];}
function browser_login(string $client,string $email,string $password):void{$csrf=browser_csrf($client,'login.php');$r=browser_request($client,'login.php',['csrf'=>$csrf,'email'=>$email,'password'=>$password]);assert_http($r['status']===302,'successful login '.$client);}
$pdo=db();
try {
 foreach(['A','B'] as $s){$pdo->prepare('INSERT INTO schools(name,code) VALUES(?,?)')->execute(['HTTP TEST school','HT_'.$tag.'_'.$s]);$schoolIds[]=(int)$pdo->lastInsertId();}
 foreach(['learner','teacher','admin','admin','learner'] as $i=>$role){$pdo->prepare('INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,?)')->execute(['HTTP TEST fixture',$tag.$i.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),$role]);$ids[]=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO user_security(user_id,school_id) VALUES(?,?)')->execute([$ids[$i],$i===4?$schoolIds[1]:$schoolIds[0]]);}
 [$learner,$teacher,$admin,$reviewer,$other]=$ids;
 assert_http(browser_request('guest','admin.php')['status']===403,'anonymous admin access denied');
 assert_http(browser_request('guest','teacher.php')['status']===403,'anonymous educator access denied');
 assert_http(browser_request('guest','register.php',['full_name'=>'TEST'])['status']===419,'missing CSRF rejected');
 $public=browser_request('guest','login.php');assert_http(str_contains($public['headers'],'no-store'),'no-store header');assert_http(str_contains($public['headers'],'Content-Security-Policy:'),'CSP present');
 browser_login('learner',$tag.'0@example.invalid',$password);browser_login('teacher',$tag.'1@example.invalid',$password);browser_login('admin',$tag.'2@example.invalid',$password);browser_login('reviewer',$tag.'3@example.invalid',$password);
 assert_http(browser_request('learner','admin.php')['status']===403,'learner admin access denied');assert_http(browser_request('learner','teacher.php')['status']===403,'learner educator access denied');
 assert_http(browser_request('teacher','teacher.php?learner='.$learner)['status']===403,'educator cannot view without consent');
 $csrf=browser_csrf('learner','account.php');$r=browser_request('learner','account.php',['csrf'=>$csrf,'action'=>'grant','teacher_id'=>$teacher,'understand_consent'=>'1']);assert_http(str_contains($r['body'],'Sharing is enabled'),'learner grant saved');
 assert_http(browser_request('teacher','teacher.php?learner='.$learner)['status']===200,'consented learner view works');
 assert_http(browser_request('teacher','teacher.php?learner='.$other)['status']===403,'other-school access denied');
 $csrf=browser_csrf('teacher','teacher.php?learner='.$learner);$r=browser_request('teacher','teacher.php',['csrf'=>$csrf,'action'=>'note','learner_id'=>$learner,'note'=>'TEST: Discussed subject choices.','next_action'=>'Read the prospectus.','follow_up_on'=>'2027-01-15']);assert_http(str_contains($r['body'],'Guidance note saved.'),'guidance note persisted');
 $csrf=browser_csrf('learner','account.php');$r=browser_request('learner','account.php',['csrf'=>$csrf,'action'=>'revoke','teacher_id'=>$teacher]);assert_http(str_contains($r['body'],'access was revoked'),'consent revoked');assert_http(browser_request('teacher','teacher.php?learner='.$learner)['status']===403,'revoked access denied');
 $csrf=browser_csrf('learner','account.php');$export=browser_request('learner','account.php',['csrf'=>$csrf,'action'=>'export']);$data=json_decode($export['body'],true);assert_http($export['status']===200&&isset($data['assessments'],$data['counselling_notes']),'personal JSON export works');assert_http(!str_contains($export['body'],'password_hash')&&!str_contains($export['body'],'token_hash'),'export omits security secrets');
 $payload=['id'=>$tag,'name'=>'SYNTHETIC HTTP TEST institution','official_url'=>'https://example.invalid/','source_url'=>'https://example.invalid/test','source_document'=>'TEST ONLY fixture','last_verified'=>gmdate('Y-m-d'),'publication_year'=>null,'verification_scope'=>'Synthetic test fixture only; no real institution claim.'];
 $csrf=browser_csrf('admin','admin.php');$r=browser_request('admin','admin.php',['csrf'=>$csrf,'action'=>'submit','type'=>'institutions','payload'=>json_encode($payload)]);assert_http(str_contains($r['body'],'Draft submitted.'),'draft staged');
 $q=$pdo->prepare('SELECT id FROM content_submissions WHERE record_id=?');$q->execute([$tag]);$draft=(int)$q->fetchColumn();
 $csrf=browser_csrf('admin','admin.php');$r=browser_request('admin','admin.php',['csrf'=>$csrf,'action'=>'review','submission_id'=>$draft,'decision'=>'publish','review_note'=>'TEST: checked the synthetic fixture.','source_checked'=>'1','admin_password'=>$password]);assert_http(str_contains($r['body'],'different administrator'),'self-review blocked');
 $csrf=browser_csrf('reviewer','admin.php');$r=browser_request('reviewer','admin.php',['csrf'=>$csrf,'action'=>'review','submission_id'=>$draft,'decision'=>'publish','review_note'=>'TEST: checked the synthetic fixture.','source_checked'=>'1','admin_password'=>$password]);assert_http(str_contains($r['body'],'Verified content published.'),'second reviewer publishes');
 $q=$pdo->prepare('SELECT verification_status FROM institutions WHERE id=?');$q->execute([$tag]);assert_http($q->fetchColumn()==='verified','published source stored');
 $csrf=browser_csrf('guest','recovery.php');$unknown=browser_request('guest','recovery.php',['csrf'=>$csrf,'email'=>$tag.'-missing@example.invalid']);$known=browser_request('guest','recovery.php',['csrf'=>$csrf,'email'=>$tag.'0@example.invalid']);assert_http(str_contains($unknown['body'],'If an eligible account exists')&&str_contains($known['body'],'If an eligible account exists'),'recovery existence responses generic');
 $csrf=browser_csrf('teacher','teacher.php');$r=browser_request('teacher','teacher.php?export=csv');assert_http($r['status']===200&&str_contains($r['body'],'Suppressed (fewer than 5)'),'small school export suppressed');
 echo 'PASS: '.$checks." HTTP authentication/CSRF/consent/publication checks.\n";
} finally {
 foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
 foreach($schoolIds as $id)$pdo->prepare('DELETE FROM schools WHERE id=?')->execute([$id]);
 $pdo->prepare('DELETE FROM content_submissions WHERE record_id=?')->execute([$tag]);$pdo->prepare('DELETE FROM institutions WHERE id=?')->execute([$tag]);
 foreach($jars as $jar)if(is_file($jar))unlink($jar);
}
