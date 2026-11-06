<?php
// Shared authentication, privacy and authorisation primitives. No token is logged.
function post_text(string $key, int $max=1000): string {
    $value=$_POST[$key]??'';
    return is_string($value)&&strlen($value)<=$max ? $value : '';
}
function security_rate_limit(string $key, int $limit, int $window): bool {
    $hash = hash_hmac('sha256', $key, env_value('RATE_LIMIT_SECRET', env_value('INTERNAL_API_TOKEN', 'local-development-only')));
    $now = time(); $cutoff = $now - max(1, $window);
    $q = db()->prepare('INSERT INTO rate_limits(bucket_hash,window_start,attempts) VALUES(?,?,1) ON DUPLICATE KEY UPDATE attempts=IF(window_start<=?,1,attempts+1),window_start=IF(window_start<=?,VALUES(window_start),window_start)');
    $q->execute([$hash,$now,$cutoff,$cutoff]);
    $q = db()->prepare('SELECT attempts FROM rate_limits WHERE bucket_hash=?'); $q->execute([$hash]);
    return (int)$q->fetchColumn() > $limit;
}
function audit_event(string $event, string $type = 'system', $target = null, array $details = [], ?int $actor = null): void {
    // Details are server-generated metadata only: never passwords, notes, marks or tokens.
    $stmt = db()->prepare('INSERT INTO audit_logs(actor_id,event,target_type,target_id,details) VALUES(?,?,?,?,?)');
    $stmt->execute([$actor ?? ($_SESSION['user_id'] ?? null),substr($event,0,80),substr($type,0,40),$target === null ? null : substr((string)$target,0,80),json_encode($details, JSON_THROW_ON_ERROR)]);
}
function password_error(string $password): ?string {
    if (strlen($password) < 12 || strlen($password) > 72) return 'Choose a password between 12 and 72 bytes (usually characters).';
    return null;
}
function start_authenticated_session(array $user): void {
    db()->prepare('INSERT IGNORE INTO user_security(user_id) VALUES(?)')->execute([(int)$user['id']]);
    $q=db()->prepare('SELECT auth_version FROM user_security WHERE user_id=?'); $q->execute([(int)$user['id']]);
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['auth_version']=(int)$q->fetchColumn();
    $_SESSION['signed_in_at']=$_SESSION['last_active']=time();
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}
function mail_ready(): bool {
    $url=env_value('APP_URL');
    return env_value('MAIL_ENABLED') === '1' && filter_var(env_value('MAIL_FROM'), FILTER_VALIDATE_EMAIL)
        && filter_var($url,FILTER_VALIDATE_URL) && in_array(parse_url($url,PHP_URL_SCHEME), ['http','https'], true)
        && (env_value('APP_ENV') !== 'production' || parse_url($url,PHP_URL_SCHEME) === 'https')
        && !preg_match('/[\r\n]/',env_value('MAIL_FROM'));
}
function send_account_token(array $user, string $purpose): bool {
    if (!mail_ready() || !in_array($purpose,['verify','reset'],true)) return false;
    $token=bin2hex(random_bytes(32));
    $minutes=$purpose === 'reset' ? 30 : 1440;
    $pdo=db(); $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE account_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose=? AND used_at IS NULL')->execute([$user['id'],$purpose]);
        $pdo->prepare('INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))')->execute([$user['id'],$purpose,hash('sha256',$token),$minutes]);
        $tokenId=$pdo->lastInsertId(); $pdo->commit();
    } catch(Throwable $ex) { if($pdo->inTransaction())$pdo->rollBack(); throw $ex; }
    $page=$purpose==='reset'?'recovery.php':'verify.php';
    $link=rtrim(env_value('APP_URL'),'/').'/'.$page.'?token='.$token;
    $subject=$purpose==='reset'?'Reset your Career Compass password':'Verify your Career Compass email';
    $body="Use this one-time link to continue:\n\n".$link."\n\nThis link expires in ".$minutes." minutes. If you did not request it, ignore this email.\nCareer Compass";
    $sent=mail($user['email'],$subject,$body,['From'=>env_value('MAIL_FROM'),'Content-Type'=>'text/plain; charset=UTF-8']);
    if(!$sent) {
        $pdo->prepare('UPDATE account_tokens SET used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$tokenId]);
        error_log('Career Compass: account email transport failed. Check configured mail transport.');
    }
    audit_event($sent?'account_email_requested':'account_email_failed','account',$user['id'],['purpose'=>$purpose]);
    return $sent;
}
function consume_account_token(string $token, string $purpose, ?string $newPassword=null): bool {
    if (!preg_match('/^[a-f0-9]{64}$/D',$token) || !in_array($purpose,['verify','reset'],true)) return false;
    if($purpose==='reset' && ($newPassword===null || password_error($newPassword)!==null)) return false;
    $pdo=db(); $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT id,user_id FROM account_tokens WHERE token_hash=? AND purpose=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE');
        $q->execute([hash('sha256',$token),$purpose]); $record=$q->fetch();
        if(!$record){$pdo->rollBack();return false;}
        $uid=(int)$record['user_id'];
        $pdo->prepare('INSERT IGNORE INTO user_security(user_id) VALUES(?)')->execute([$uid]);
        if($purpose==='reset') {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),$uid]);
            $pdo->prepare('UPDATE user_security SET auth_version=auth_version+1 WHERE user_id=?')->execute([$uid]);
        } else $pdo->prepare('UPDATE user_security SET email_verified_at=UTC_TIMESTAMP() WHERE user_id=?')->execute([$uid]);
        $pdo->prepare('UPDATE account_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND purpose=? AND used_at IS NULL')->execute([$uid,$purpose]);
        audit_event($purpose==='reset'?'password_reset':'email_verified','account',$uid,[],$uid);
        $pdo->commit(); return true;
    } catch(Throwable $ex) {if($pdo->inTransaction())$pdo->rollBack();throw $ex;}
}
function teacher_can_view(int $teacher, int $learner): bool {
    $q=db()->prepare("SELECT 1 FROM teacher_grants g JOIN users t ON t.id=g.teacher_id JOIN users l ON l.id=g.learner_id JOIN user_security ts ON ts.user_id=t.id JOIN user_security ls ON ls.user_id=l.id WHERE t.id=? AND l.id=? AND t.role IN ('teacher','admin') AND l.role='learner' AND ts.school_id IS NOT NULL AND ts.school_id=ls.school_id AND g.revoked_at IS NULL");
    $q->execute([$teacher,$learner]); return (bool)$q->fetchColumn();
}
function safe_csv_cell($value): string {
    $value=(string)$value;
    return preg_match('/^[\s]*[=+@\-\t\r]/u',$value) ? "'".$value : $value;
}
function export_csv(string $filename, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9_.-]/','',$filename).'"');
    $out=fopen('php://output','w');
    foreach($rows as $row) fputcsv($out,array_map('safe_csv_cell',$row));
    fclose($out); exit;
}
function official_url_valid($url): bool {
    if(!is_string($url) || !filter_var($url,FILTER_VALIDATE_URL))return false;
    $parts=parse_url($url);
    return ($parts['scheme']??'')==='https' && !isset($parts['user']) && !isset($parts['pass']) && !preg_match('/[\r\n]/',$url);
}
