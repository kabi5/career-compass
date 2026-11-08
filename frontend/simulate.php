<?php
require_once 'config/layout.php';
require_login();
$uid=(int)$_SESSION['user_id'];
$stmt=db()->prepare('SELECT riasec_scores FROM assessments WHERE user_id=? ORDER BY id DESC LIMIT 1');
$stmt->execute([$uid]); $prior=$stmt->fetch();
$stmt=db()->prepare('SELECT * FROM learner_profiles WHERE user_id=?');
$stmt->execute([$uid]); $profile=$stmt->fetch() ?: [];
$gradeNames=['Mathematics','Physical Science','English'];
$existing=json_decode($profile['subject_results'] ?? '[]',true);
$lookup=[];
foreach (is_array($existing)?$existing:[] as $item) {
    if(is_array($item) && isset($item['subject'],$item['grade'])) $lookup[mb_strtolower(trim($item['subject']))]=$item['grade'];
}
$grades=['Mathematics'=>'','Physical Science'=>'','English'=>''];
foreach($grades as $name=>$_) $grades[$name]=(string)($lookup[mb_strtolower($name)] ?? ($lookup[mb_strtolower($name.'s')] ?? ''));
$error='';$matches=[];$submitted=false;
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf']??null);
    $submitted=true;
    $gradeList=[];
    foreach($gradeNames as $subject) {
        $value=strtoupper(trim((string)($_POST['grade'][str_replace(' ','_',$subject)] ?? '')));
        if($value!=='' && !preg_match('/^(A\*|[A-G])$/',$value)) $error='Use LGCSE grades A*, A–G, or leave the field blank.';
        $grades[$subject]=$value;
        if($value!=='') $gradeList[]=['subject'=>$subject,'grade'=>$value];
    }
    if(!$prior) $error='Complete your interest assessment before using the subject simulator.';
    if(!$error) {
        $interest=json_decode($prior['riasec_scores'],true);
        $names=array_values(array_filter(array_map('trim',explode(',',(string)($profile['subjects']??'')))));
        $payload=['riasec_scores'=>$interest,'qualification_type'=>'LGCSE',
                  'subject_results'=>$gradeList,'subjects'=>array_values(array_unique(array_merge($names,array_column($gradeList,'subject')))),
                  'marks'=>[], 'top_n'=>5];
        $result=api_request('/recommend',$payload,45);
        if(!$result || !isset($result['recommendations']) || !is_array($result['recommendations'])) $error='The recommendation service is unavailable.';
        else $matches=$result['recommendations'];
    }
}
page_start('Subject simulator','profile');
?>
<main id="main" class="wide page-space"><a href="dashboard.php" class="back-link">← Dashboard</a>
<div class="page-intro"><span class="eyebrow muted-eyebrow">WHAT IF?</span><h1>Subject simulator</h1>
<p>Explore how different LGCSE grades could change your recommended study routes. This does <strong>not</strong> edit your academic profile or overwrite your recommendations.</p></div>
<?php if(!$prior): ?><div class="alert error">Take the assessment first to use the simulator. <a href="assessment.php">Start assessment</a>.</div><?php else: ?>
<section class="panel"><h2>Try different grades</h2><?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-grid"><?= csrf_field() ?>
<?php foreach($gradeNames as $name): ?><label><?= e($name) ?>
<select name="grade[<?= e(str_replace(' ','_',$name)) ?>]">
<option value="">Unknown / not supplied</option>
<?php foreach(['A*','A','B','C','D','E','F','G'] as $g): ?><option value="<?= e($g) ?>" <?= $grades[$name]===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?>
</select></label><?php endforeach; ?>
<button class="btn btn-primary" type="submit">Preview career alignment ↗</button></form></section>
<?php if($submitted && !$error): ?><section class="panel" style="margin-top:24px"><h2>Preview results</h2>
<?php foreach($matches as $m):
    if(!is_array($m))continue;
    $display=$m['match_score']??$m['ranking_score']??null;
    $text=is_numeric($display)?number_format((float)$display,1).'%':'Exploratory'; ?>
    <div class="match-row"><span class="match-title"><strong><?= e($m['title']??'') ?></strong><small><?= e(ucfirst($m['score_status']??'exploratory')) ?> · <?= round((float)($m['data_coverage']??0)*100) ?>% evidence · <?= e(str_replace('_',' ',$m['eligibility_status']??'unknown')) ?></small></span><span class="match-percent"><?= e($text) ?></span></div>
<?php endforeach; ?>
<p class="fine-print">Preview only. Admission still requires direct institutional verification, and this simulation does not guarantee a career outcome.</p></section><?php endif; ?>
<?php endif; ?></main><?php page_end(); ?>
