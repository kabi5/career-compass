<?php
require_once 'config/layout.php';
require_login();
$uid=(int)$_SESSION['user_id'];
$error=''; $saved=false;
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf']??null);
    if(rate_limited('roadmap',20,600)) $error='Too many changes. Please try again shortly.';
    elseif(($_POST['action']??'')==='create') {
        $recId=filter_input(INPUT_POST,'recommendation_id',FILTER_VALIDATE_INT) ?: 0;
        $stmt=db()->prepare('SELECT r.id,r.career_title FROM recommendations r WHERE r.id=? AND r.user_id=?');
        $stmt->execute([$recId,$uid]);$rec=$stmt->fetch();
        if(!$rec) $error='Choose a recommendation from your account.';
        else {
            try {
                $pdo=db();$pdo->beginTransaction();
                $roadmap=['career'=>$rec['career_title'],'source_recommendation_id'=>(int)$rec['id'],
                    'note'=>'Exploration plan only; verify all admission and funding information.'];
                $pdo->prepare('INSERT INTO career_plans (user_id,career_title,goal,roadmap) VALUES (?,?,?,?)')
                    ->execute([$uid,$rec['career_title'],'Research a possible pathway to '.$rec['career_title'],json_encode($roadmap,JSON_THROW_ON_ERROR)]);
                $planId=(int)$pdo->lastInsertId();
                $stmt=$pdo->prepare('INSERT INTO roadmap_milestones (plan_id,title) VALUES (?,?)');
                foreach(['Discuss this career with a teacher or adviser',
                    'Confirm current programme admission criteria',
                    'Complete a career-related activity or interview',
                    'Review available funding and closing dates'] as $title) $stmt->execute([$planId,$title]);
                $pdo->commit();$saved=true;
            } catch(Throwable $ex) {
                if(db()->inTransaction()) db()->rollBack();
                error_log('Roadmap creation failed: '.$ex->getMessage());$error='Could not save your career plan.';
            }
        }
    } elseif(($_POST['action']??'')==='toggle') {
        $id=filter_input(INPUT_POST,'milestone_id',FILTER_VALIDATE_INT)?:0;
        $stmt=db()->prepare('UPDATE roadmap_milestones m JOIN career_plans p ON p.id=m.plan_id
             SET m.completed_at=IF(m.completed_at IS NULL,NOW(),NULL)
             WHERE m.id=? AND p.user_id=?');
        $stmt->execute([$id,$uid]);
        $saved=$stmt->rowCount()>0;
        if(!$saved) $error='Could not find that milestone in your plan.';
    }
}
$stmt=db()->prepare('SELECT id,career_title,goal,created_at FROM career_plans WHERE user_id=? ORDER BY id DESC LIMIT 20');
$stmt->execute([$uid]);$plans=$stmt->fetchAll();
$stmt=db()->prepare('SELECT r.id,r.career_title FROM recommendations r
    JOIN recommendation_details d ON d.recommendation_id=r.id
    WHERE r.user_id=? AND d.run_id=(SELECT MAX(id) FROM recommendation_runs WHERE user_id=?)
    ORDER BY COALESCE(r.data_coverage,0) DESC,COALESCE(r.ranking_score,r.match_score,-1) DESC');
$stmt->execute([$uid,$uid]);$recs=$stmt->fetchAll();
page_start('My career roadmap','dashboard');
?>
<main id="main" class="wide page-space"><a href="dashboard.php" class="back-link">← Dashboard</a>
<div class="page-intro"><span class="eyebrow muted-eyebrow">MAKE A PLAN</span><h1>My career roadmap</h1><p>Create achievable exploration steps and track your progress over time.</p></div>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if($saved): ?><div class="alert success">Your roadmap has been updated.</div><?php endif; ?>
<section class="panel"><h2>Create a plan from a recommendation</h2>
<?php if(!$recs): ?><p>Complete your assessment first to choose a recommended career.</p><?php else: ?>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<label>Career <select name="recommendation_id" required><option value="">Choose your career</option>
<?php foreach($recs as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (string)($_GET['career_id']??'')===(string)$r['id']?'selected':'' ?>><?= e($r['career_title']) ?></option><?php endforeach; ?>
</select></label><button class="btn btn-primary">Create four-step roadmap</button></form><?php endif; ?>
</section>
<?php foreach($plans as $plan): ?>
<section class="panel" style="margin-top:22px"><span class="eyebrow muted-eyebrow">CAREER PLAN</span>
<h2><?= e($plan['career_title']) ?></h2><p><?= e($plan['goal']) ?></p>
<?php $stmt=db()->prepare('SELECT id,title,due_date,completed_at FROM roadmap_milestones WHERE plan_id=? ORDER BY id');$stmt->execute([(int)$plan['id']]);$milestones=$stmt->fetchAll(); ?>
<?php foreach($milestones as $m): ?><form method="post" class="match-row" style="align-items:center">
<?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="milestone_id" value="<?= (int)$m['id'] ?>">
<span class="match-title"><strong><?= e($m['title']) ?></strong><small><?= $m['completed_at']?'Completed':'Not yet completed' ?></small></span>
<button class="btn btn-outline" type="submit"><?= $m['completed_at']?'Mark incomplete':'Mark completed' ?></button></form><?php endforeach; ?>
<p class="fine-print">This is a personal learning plan, not an admission or funding application.</p></section>
<?php endforeach; ?>
</main><?php page_end(); ?>
