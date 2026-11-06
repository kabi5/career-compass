<?php
require_once 'config/layout.php';require_once 'config/guidance.php';require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Use the recalculate button on your profile.');}
verify_csrf($_POST['csrf']??null);$uid=(int)current_user()['id'];$a=latest_assessment($uid);$error='';
if(!$a)$error='Complete your first assessment before recalculating.';
elseif(rate_limited('recalculate',12,600))$error='Please wait before recalculating again.';
else{$p=profile_payload($uid);$data=api_request('/recommend',array_merge($p,['riasec_scores'=>json_array($a['riasec_scores']),'top_n'=>5]));if(!$data||!isset($data['recommendations']))$error='The recommendation service is unavailable. Your existing results are safe.';else{try{db()->beginTransaction();save_run($uid,(int)$a['id'],$p,$data);db()->commit();header('Location: dashboard.php?refreshed=1');exit;}catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();error_log($ex->getMessage());$error='Unable to save new recommendations. Please try again.';}}}
page_start('Recalculate');echo '<main id="main" class="wide page-space"><div class="panel"><h1>Recalculate recommendations</h1><p role="alert">'.e($error).'</p><a href="profile.php">Return to profile</a> · <a href="assessment.php">Take assessment</a></div></main>';page_end();
