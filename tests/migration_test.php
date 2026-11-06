<?php
// Creates a uniquely named disposable database, never selects the real application database.
if(PHP_SAPI!=='cli'||getenv('ALLOW_DISPOSABLE_DB_TESTS')!=='1'){fwrite(STDERR,"Set ALLOW_DISPOSABLE_DB_TESTS=1 with test-server DB_HOST/DB_PORT/DB_USER.\n");exit(2);}
$testName='migration_test_'.bin2hex(random_bytes(6));$root=dirname(__DIR__);
$server=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$checks=0;function migration_check(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function run_migration(string $root):void{$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/migrate.php');passthru($command,$status);if($status!==0)throw new RuntimeException('Migration process failed');}
try{
 $server->exec('CREATE DATABASE `'.$testName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$server->exec('USE `'.$testName.'`');putenv('DB_NAME='.$testName);
 $sql=file_get_contents($root.'/tests/fixtures/legacy_schema.sql');$sql=preg_replace('/^(CREATE DATABASE|USE)\b[^;]*;/mi','',$sql);$server->exec($sql);
 $server->prepare('INSERT INTO users(full_name,email,password_hash) VALUES(?,?,?)')->execute(['MIGRATION TEST fixture','migration@example.invalid',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$uid=(int)$server->lastInsertId();
 $server->prepare('INSERT INTO recommendations(user_id,career_title,match_score) VALUES(?,?,?)')->execute([$uid,'Synthetic legacy career',75]);$rid=(int)$server->lastInsertId();
 run_migration($root);
 migration_check((int)$server->query('SELECT COUNT(*) FROM users WHERE id='.$uid)->fetchColumn()===1,'Legacy account preserved');
 migration_check((int)$server->query('SELECT COUNT(*) FROM recommendations WHERE id='.$rid)->fetchColumn()===1,'Legacy recommendation preserved');
 migration_check((int)$server->query('SELECT COUNT(*) FROM assessment_questions')->fetchColumn()===24,'Questions seeded exactly once');
 migration_check((int)$server->query('SELECT COUNT(*) FROM institutions')->fetchColumn()===14,'Official institutions imported');
 $server->exec("UPDATE institutions SET name='Operator edited fixture' WHERE id='nul'");
 run_migration($root);
 migration_check($server->query("SELECT name FROM institutions WHERE id='nul'")->fetchColumn()==='Operator edited fixture','Rerun preserves operator changes');
 migration_check((int)$server->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()===3,'All tracked migrations applied once');
 migration_check((int)$server->query('SELECT COUNT(*) FROM recommendations')->fetchColumn()===1,'Rerun does not duplicate recommendations');
 $server->prepare('INSERT INTO recommendations(user_id,career_title,match_score,education_level) VALUES(?,?,?,?)')->execute([$uid,'Synthetic long education',60,str_repeat('Long education description ',30)]);
 migration_check(strlen($server->query('SELECT education_level FROM recommendations ORDER BY id DESC LIMIT 1')->fetchColumn())>150,'Long reference descriptions are supported');
 try{$server->exec("INSERT INTO saved_items(user_id,item_type,item_id,title) VALUES(4294967295,'career','test','test')");throw new RuntimeException('Missing FK enforcement');}catch(PDOException $e){migration_check($e->getCode()==='23000','Foreign key rejects orphan item');}
 echo 'PASS: '.$checks." migration preservation/idempotency/constraint checks.\n";
}finally{
 // Generated name checked immediately before cleanup; this cannot name a user database.
 if(preg_match('/^migration_test_[a-f0-9]{12}$/D',$testName))$server->exec('DROP DATABASE IF EXISTS `'.$testName.'`');
}
