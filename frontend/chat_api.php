<?php
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!current_user()) { http_response_code(401); echo json_encode(['reply'=>'Please sign in again.']); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['reply'=>'Method not allowed.']); exit; }
$token=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$token || !hash_equals(csrf_token(),$token)) { http_response_code(419); echo json_encode(['reply'=>'Refresh this page and try again.']); exit; }
if (rate_limited('chat',30,600)) { http_response_code(429); echo json_encode(['reply'=>'You have reached the temporary message limit. Please come back in a few minutes.']); exit; }
$raw=file_get_contents('php://input');
$data=json_decode($raw?:'',true);
$message=$data['message'] ?? null;
if (!is_string($message) || trim($message)==='' || mb_strlen($message)>500) { http_response_code(400); echo json_encode(['reply'=>'Enter a question of 1–500 characters.']); exit; }
$uid=(int)$_SESSION['user_id'];
$stmt=db()->prepare('SELECT top_interest FROM assessments WHERE user_id=? ORDER BY taken_at DESC,id DESC LIMIT 1');$stmt->execute([$uid]);$a=$stmt->fetch();
$stmt=db()->prepare('SELECT subjects FROM learner_profiles WHERE user_id=?');$stmt->execute([$uid]);$p=$stmt->fetch();
$payload=['message'=>trim($message),'context'=>['riasec_code'=>$a['top_interest']??'','subjects'=>array_values(array_filter(array_map('trim',explode(',',$p['subjects']??''))))]];
$result=api_request('/chat',$payload,30);
if (!$result || !isset($result['reply'])) { http_response_code(502); echo json_encode(['reply'=>'The assistant is unavailable right now. Please try again shortly.']); exit; }
echo json_encode(['reply'=>(string)$result['reply'],'sources'=>$result['sources']??[]],JSON_UNESCAPED_UNICODE);
