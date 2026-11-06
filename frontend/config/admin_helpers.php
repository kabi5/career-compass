<?php
require_once __DIR__.'/security.php';
function admin_reauthenticate(array $user, string $password): void {
 if(rate_limited('admin-confirmation',8,900))throw new InvalidArgumentException('Too many confirmation attempts. Please wait 15 minutes.');
 $q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$user['id']]);
 if(!password_verify($password,(string)$q->fetchColumn()))throw new InvalidArgumentException('Enter your current administrator password.');
}
function validate_content_payload(string $type, array $p): array {
 if(!in_array($type,['institutions','programmes','scholarships','career_catalogue'],true))throw new InvalidArgumentException('Unknown content type.');
 if(!is_string($p['id']??null)||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$p['id']))throw new InvalidArgumentException('Record ID must use letters, numbers, dashes or underscores (1–64 characters).');
 $titleKey=$type==='institutions'?'name':($type==='programmes'?'programme':'title');
 if(!is_string($p[$titleKey]??null)||trim($p[$titleKey])===''||mb_strlen($p[$titleKey])>($type==='institutions'?180:200))throw new InvalidArgumentException('Provide a valid '.$titleKey.' field.');
 if(!official_url_valid($p['source_url']??null)||strlen($p['source_url'])>500)throw new InvalidArgumentException('An HTTPS official source URL (at most 500 characters) is required.');
 if(!is_string($p['source_document']??null)||!trim($p['source_document'])||mb_strlen($p['source_document'])>500)throw new InvalidArgumentException('Name the exact source document or official webpage.');
 if(!is_string($p['verification_scope']??null)||mb_strlen(trim($p['verification_scope']))<12||mb_strlen($p['verification_scope'])>1500)throw new InvalidArgumentException('Describe what the source verifies and unresolved conditions in verification_scope.');
 $date=$p['last_verified']??'';
 if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||$date>gmdate('Y-m-d'))throw new InvalidArgumentException('Use a valid verification date that is not in the future.');
 if(isset($p['publication_year']) && (!is_int($p['publication_year'])||$p['publication_year']<1900||$p['publication_year']>(int)gmdate('Y')))throw new InvalidArgumentException('Use a publication year or null for an undated source.');
 if(isset($p['official_url'])&&!official_url_valid($p['official_url']))throw new InvalidArgumentException('Official links must use HTTPS.');
 foreach(['description','entry_requirements','eligibility','level','verification_scope','accreditation_status'] as $field)if(isset($p[$field])&&(!is_string($p[$field])||mb_strlen($p[$field])>6000))throw new InvalidArgumentException($field.' must be plain text of at most 6000 characters.');
 foreach(['skills','typical_activities','related_careers','required_subjects','study_modes','fields','documents'] as $field)if(isset($p[$field])&&(!is_array($p[$field])||count($p[$field])>60||array_filter($p[$field],fn($value)=>!is_string($value)||mb_strlen($value)>1000)))throw new InvalidArgumentException($field.' must contain at most 60 short text entries.');
 if(isset($p['duration_years'])&&(!is_numeric($p['duration_years'])||$p['duration_years']<=0||$p['duration_years']>15))throw new InvalidArgumentException('Programme duration must be a positive number up to 15 years or null if unverified.');
 if($type==='programmes') {
  if(!is_string($p['institution_id']??null)||!is_string($p['university']??null)||!is_string($p['level']??null))throw new InvalidArgumentException('Programme requires institution_id, university and level.');
  $q=db()->prepare('SELECT name FROM institutions WHERE id=?');$q->execute([$p['institution_id']]);$institution=$q->fetchColumn();
  if(!$institution)throw new InvalidArgumentException('Publish the institution record before its programme.');$p['university']=$institution;
  foreach(['required_subjects','related_careers','study_modes'] as $key)if(!isset($p[$key])||!is_array($p[$key])||array_filter($p[$key],fn($v)=>!is_string($v)))throw new InvalidArgumentException($key.' must be an array of strings.');
  if(!is_string($p['entry_requirements']??null))throw new InvalidArgumentException('Provide entry_requirements text.');
  $rules=$p['admission_rules']??null;
  if(!is_array($rules)||($rules['grading_system']??null)!=='LGCSE'||!is_bool($rules['complete']??null)||!is_array($rules['rules']??null))throw new InvalidArgumentException('admission_rules needs grading_system LGCSE, complete boolean and rules array. Use complete:false for unresolved requirements.');
  foreach($rules['rules'] as $rule) {
   if(!is_array($rule)||!in_array($rule['type']??'', ['subject','any_subject','count','manual'],true))throw new InvalidArgumentException('Unsupported admission rule type.');
   if($rule['type']==='manual')continue;
   if(!in_array($rule['minimum_grade']??'', ['A*','A','B','C','D','E','F','G'],true))throw new InvalidArgumentException('An admission rule requires a recognised minimum LGCSE grade.');
   if($rule['type']==='subject'&&(!is_string($rule['subject']??null)||!trim($rule['subject'])))throw new InvalidArgumentException('Subject rule needs a subject.');
   if($rule['type']==='any_subject'&&(!is_array($rule['subjects']??null)||!$rule['subjects']||array_filter($rule['subjects'],fn($v)=>!is_string($v))))throw new InvalidArgumentException('Alternative subject rule needs subjects.');
   if(isset($rule['count'])&&(!is_int($rule['count'])||$rule['count']<1||$rule['count']>20))throw new InvalidArgumentException('Rule count must be between 1 and 20.');
  }
 }
 if($type==='scholarships') {
  if(!is_string($p['provider']??null)||!trim($p['provider'])||mb_strlen($p['provider'])>180)throw new InvalidArgumentException('Provide the funding provider.');
  foreach(['fields','documents'] as $key)if(!is_array($p[$key]??null)||array_filter($p[$key],fn($v)=>!is_string($v)))throw new InvalidArgumentException($key.' must be an array of strings.');
  foreach(['opens_on','closes_on'] as $key)if(!empty($p[$key])&&(!is_string($p[$key])||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$p[$key])||!checkdate((int)substr($p[$key],5,2),(int)substr($p[$key],8,2),(int)substr($p[$key],0,4))))throw new InvalidArgumentException('Use valid funding dates or null when unconfirmed.');
  if(!empty($p['opens_on'])&&!empty($p['closes_on'])&&$p['opens_on']>$p['closes_on'])throw new InvalidArgumentException('Opening date must not follow closing date.');
  if(!in_array($p['application_status']??'unknown',['unknown','open','closed','upcoming','ongoing'],true))throw new InvalidArgumentException('Use an allowed application_status.');
  if(($p['application_status']??'unknown')==='open'&&(empty($p['opens_on'])||empty($p['closes_on'])))throw new InvalidArgumentException('Do not mark a funding call open without verified opening and closing dates.');
 }
 $p['verification_status']='verified';$p['verified']=true;return $p;
}
function publish_content(string $type,array $p): void {
 $json=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 $values=[$p['id'],$type==='institutions'?$p['name']:($type==='programmes'?$p['programme']:$p['title']),$json,'verified',$p['source_url'],$p['last_verified']];
 $titleColumn=$type==='institutions'?'name':'title';$columns='id,'.$titleColumn.',payload,verification_status,source_url,last_verified';$marks='?,?,?,?,?,?';$extra='';
 if($type==='programmes') {$columns.=',institution_id';$marks.=',?';$values[]=$p['institution_id'];$extra=',institution_id=VALUES(institution_id)';}
 if($type==='scholarships') {$columns.=',provider';$marks.=',?';$values[]=$p['provider'];$extra=',provider=VALUES(provider)';}
 db()->prepare('INSERT INTO '.$type.'('.$columns.') VALUES('.$marks.') ON DUPLICATE KEY UPDATE '.$titleColumn.'=VALUES('.$titleColumn.'),payload=VALUES(payload),verification_status=VALUES(verification_status),source_url=VALUES(source_url),last_verified=VALUES(last_verified)'.$extra)->execute($values);
 if($type==='programmes')db()->prepare('INSERT INTO admission_requirements(programme_id,grading_system,rules_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE grading_system=VALUES(grading_system),rules_json=VALUES(rules_json)')->execute([$p['id'],$p['admission_rules']['grading_system'],json_encode($p['admission_rules'],JSON_THROW_ON_ERROR)]);
}
