<?php
require_once 'config/layout.php';
require_login();
$uid=(int)$_SESSION['user_id'];
$stmt=db()->prepare('SELECT id FROM recommendation_runs WHERE user_id=? ORDER BY id DESC LIMIT 1');$stmt->execute([$uid]);$runId=$stmt->fetchColumn();
$items=[];
if($runId) {
    $stmt=db()->prepare('SELECT r.* FROM recommendation_details d JOIN recommendations r ON r.id=d.recommendation_id
       WHERE d.run_id=? AND r.user_id=? ORDER BY COALESCE(r.data_coverage,0) DESC,
          COALESCE(r.ranking_score,r.match_score,-1) DESC');
    $stmt->execute([$runId,$uid]);$items=$stmt->fetchAll();
}
$selected=array_filter($items,static fn($r)=>in_array((int)$r['id'],[
    filter_input(INPUT_GET,'a',FILTER_VALIDATE_INT) ?: 0,filter_input(INPUT_GET,'b',FILTER_VALIDATE_INT) ?: 0],true));
$selected=array_values($selected);
$factorNames=['interest'=>'Interest alignment','academic'=>'Academic compatibility',
'subject_readiness'=>'Subject readiness','pathway'=>'Education pathways','local_opportunity'=>'Labour evidence'];
page_start('Compare career matches','careers');
?>
<main id="main" class="wide page-space"><a href="dashboard.php" class="back-link">← Dashboard</a>
<div class="page-intro"><span class="eyebrow muted-eyebrow">YOUR OPTIONS</span><h1>Compare career matches</h1><p>See how two of your current recommendations differ by evidence and educational readiness.</p></div>
<?php if(count($items)<2): ?><div class="panel">Take an assessment to generate at least two career suggestions.</div><?php else: ?>
<section class="panel"><form method="get" class="form-grid"><div class="form-row">
<?php foreach(['a'=>'First career','b'=>'Second career'] as $key=>$label): ?>
<label><?= e($label) ?><select name="<?= e($key) ?>" required><option value="">Select a career</option>
<?php foreach($items as $item): ?><option value="<?= (int)$item['id'] ?>" <?= (string)($_GET[$key]??'')===(string)$item['id']?'selected':'' ?>><?= e($item['career_title']) ?></option><?php endforeach; ?>
</select></label><?php endforeach; ?>
</div><button type="submit" class="btn btn-primary">Compare these careers</button></form></section>
<?php if(count($selected)===2): ?><div class="two-column" style="margin-top:20px">
<?php foreach($selected as $r): $data=json_decode($r['score_details']??'',true)?:[];$components=$data['components']??[]; ?>
<section class="panel"><h2><?= e($r['career_title']) ?></h2>
<p><strong><?= is_numeric($r['match_score'])?number_format((float)$r['match_score'],1).'%' : (is_numeric($r['ranking_score'])?number_format((float)$r['ranking_score'],1).'%':'Exploratory') ?></strong> · <?= e(ucfirst($r['score_status']??'exploratory')) ?></p>
<p><strong><?= is_numeric($r['data_coverage'])?round((float)$r['data_coverage']).'%':'Unknown' ?></strong> evidence coverage</p>
<?php foreach($factorNames as $key=>$label): ?><div class="bar-info"><span><?= e($label) ?></span><strong><?= is_numeric($components[$key]??null)?number_format((float)$components[$key],1).'%':'Unknown' ?></strong></div><?php endforeach; ?>
<p class="fine-print">Eligibility: <?= e(str_replace('_',' ',$r['eligibility_status']??'unknown')) ?></p><a class="text-link" href="career-detail.php?id=<?= (int)$r['id'] ?>">Open full explanation ↗</a></section>
<?php endforeach; ?></div><?php elseif(!empty($_GET['a']) || !empty($_GET['b'])): ?><div class="alert error">Choose two different careers from your latest assessment.</div><?php endif; ?>
<?php endif; ?></main><?php page_end(); ?>
