<?php
require_once 'config/layout.php';
require_admin();
$counts=[];
foreach (['users','assessments','recommendations','assessment_questions'] as $table) {
    // Values are a server-controlled allowlist, not user input.
    $counts[$table]=(int)db()->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
}
page_start('Administration');
?>
<main id="main" class="wide page-space"><div class="page-intro"><span class="eyebrow muted-eyebrow">SYSTEM ADMINISTRATION</span><h1>System overview</h1><p>Restricted information about this deployment. Learner profiles and assessment answers are private.</p></div><div class="three-grid"><?php foreach ($counts as $name=>$count): ?><div class="panel stat-card"><span class="eyebrow muted-eyebrow"><?= e(str_replace('_',' ',$name)) ?></span><strong><?= number_format($count) ?></strong></div><?php endforeach; ?></div><div class="panel"><h2>Content management</h2><p>The current career catalogue and local programme reference entries are version-controlled files in <code>ai_engine/data/</code>. Updating information requires source review and a new deployment; this prevents unreviewed information being published. Confirm programme information with its institution before marking it verified.</p><p>Public registration always creates a learner account. Granting an administrator role requires a controlled database operation by the deployment owner; no user can grant themselves elevated access.</p></div></main>
<?php page_end(); ?>
