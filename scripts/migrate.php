<?php
// Run ONLY from PHP CLI: php scripts/migrate.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$root = dirname(__DIR__);
$bootstrap = is_file($root . '/frontend/config/db.php')
    ? $root . '/frontend/config/db.php' : $root . '/html/config/db.php';
require $bootstrap;
$pdo=db();
// Create any missing history/roadmap tables on older deployments. Existing
// tables and their data are left untouched.
$extensions = file_get_contents($root . '/database/history_extensions.sql');
$statements = array_filter(array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $extensions))));
foreach ($statements as $statement) {
    if (stripos($statement, 'CREATE TABLE') === 0) $pdo->exec($statement);
}
echo "History and roadmap tables checked.\n";
// Extend learner profiles without removing existing subjects or marks.
$profileCols=$pdo->query('SHOW COLUMNS FROM learner_profiles')->fetchAll();
$profileExisting=array_column($profileCols,'Field');
foreach ([
    'qualification_type'=>'VARCHAR(20) NULL',
    'qualification_year'=>'SMALLINT UNSIGNED NULL',
    'subject_results'=>'LONGTEXT NULL',
] as $column=>$type) {
    if (!in_array($column,$profileExisting,true)) {
        $pdo->exec("ALTER TABLE learner_profiles ADD COLUMN $column $type");
        echo "Added learner profile column $column\n";
    }
}
// Upgrade existing deployments that have the original recommendations table.
$cols=$pdo->query('SHOW COLUMNS FROM recommendations')->fetchAll();
$existing=array_column($cols,'Field');
foreach ([
    'holland_code'=>'VARCHAR(10) NULL',
    'education_level'=>'VARCHAR(150) NULL',
    'local_programmes'=>'LONGTEXT NULL',
    'ranking_score'=>'DECIMAL(5,2) NULL',
    'data_coverage'=>'DECIMAL(5,2) NULL DEFAULT NULL',
    'score_status'=>"VARCHAR(30) NULL",
    'score_version'=>"VARCHAR(40) NULL",
    'score_details'=>'LONGTEXT NULL',
    'eligibility_status'=>"VARCHAR(50) NULL",
] as $column=>$type) {
    if (!in_array($column,$existing,true)) {
        $pdo->exec("ALTER TABLE recommendations ADD COLUMN $column $type");
        echo "Added column $column\n";
    }
}
// The exploratory engine must be allowed to store no numeric score.
$matchColumn=array_values(array_filter($cols,static fn($c) => $c['Field']==='match_score'));
if ($matchColumn && strtoupper($matchColumn[0]['Null']) !== 'YES') {
    $pdo->exec('ALTER TABLE recommendations MODIFY COLUMN match_score DECIMAL(5,2) NULL');
    echo "Allowed unknown match scores\n";
}
// The seed statements in schema.sql are safe to run after the table exists.
$sql=file_get_contents(dirname(__DIR__).'/database/schema.sql');
if (!preg_match('/INSERT IGNORE INTO assessment_questions.*?;/s',$sql,$m)) throw new RuntimeException('No question seed found');
$pdo->exec($m[0]);
echo "Database migration and question seeding complete.\n";
