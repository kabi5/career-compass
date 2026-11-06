<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$root=dirname(__DIR__);require is_file($root.'/frontend/config/db.php')?$root.'/frontend/config/db.php':$root.'/html/config/db.php';
$apply=in_array('--apply',$argv,true);
$noteDays=(int)env_value('COUNSELLING_RETENTION_DAYS','365');$auditDays=(int)env_value('AUDIT_RETENTION_DAYS','365');
if($noteDays<30||$noteDays>3650||$auditDays<30||$auditDays>3650){fwrite(STDERR,"Retention days must be between 30 and 3650.\n");exit(2);}
$policies=[
 'account_tokens'=>'expires_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)',
 'rate_limits'=>'window_start < UNIX_TIMESTAMP() - 172800',
 'counselling_notes'=>'created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$noteDays.' DAY)',
 'audit_logs'=>'created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$auditDays.' DAY)',
];
foreach($policies as $table=>$condition){$count=(int)db()->query('SELECT COUNT(*) FROM '.$table.' WHERE '.$condition)->fetchColumn();echo $table.': '.$count.($apply?' to delete':' would be deleted')."\n";}
if(!$apply){echo "Dry run only. Review your published retention policy and backups, then use --apply. Account/profile/assessment history is never purged by this maintenance script.\n";exit;}
db()->beginTransaction();
try{foreach($policies as $table=>$condition)db()->exec('DELETE FROM '.$table.' WHERE '.$condition);audit_event('retention_maintenance','system',null,['notes_days'=>$noteDays,'audit_days'=>$auditDays]);db()->commit();echo "Retention maintenance complete.\n";}
catch(Throwable $ex){db()->rollBack();fwrite(STDERR,"Maintenance failed; no deletion was committed.\n");exit(1);}
