<?php
// Additive migration. MySQL DDL auto-commits; take a backup first. Safe to rerun.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$root=dirname(__DIR__);require is_file($root.'/frontend/config/db.php')?$root.'/frontend/config/db.php':$root.'/html/config/db.php';
$pdo=db();
if((int)$pdo->query("SELECT GET_LOCK('career_compass_migrate',30)")->fetchColumn()!==1)throw new RuntimeException('Migration already running.');
try {
 $sql=file_get_contents($root.'/database/schema.sql');
 $sql=preg_replace('/^(CREATE DATABASE|USE)\b[^;]*;/mi','',$sql);
 $pdo->exec($sql);
 $cols=array_column($pdo->query('SHOW COLUMNS FROM recommendations')->fetchAll(),'Field');
 foreach(['holland_code'=>'VARCHAR(10) NULL','education_level'=>'VARCHAR(150) NULL','local_programmes'=>'LONGTEXT NULL'] as $column=>$type)if(!in_array($column,$cols,true))$pdo->exec("ALTER TABLE recommendations ADD COLUMN $column $type");
 $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
 foreach(glob($root.'/database/migrations/*.sql') as $file){$version=basename($file);$hash=hash_file('sha256',$file);$q=$pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?');$q->execute([$version]);$old=$q->fetchColumn();if($old){if($old!==$hash)throw new RuntimeException('Applied migration changed: '.$version);continue;}$pdo->exec(file_get_contents($file));$pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES(?,?)')->execute([$version,$hash]);echo "Applied $version\n";}
 require __DIR__.'/seed_directory.php';seed_directory($pdo,$root);
 echo "Migration complete. Existing accounts and histories preserved.\n";
} finally {$pdo->query("SELECT RELEASE_LOCK('career_compass_migrate')");}
