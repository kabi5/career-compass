<?php
require_once __DIR__.'/db.php';
function json_array(?string $value): array { $v=json_decode($value??'[]',true); return is_array($v)?$v:[]; }
function input_text(string $key,int $max=1000): string {
 $v=$_POST[$key]??''; if(!is_string($v)||mb_strlen($v)>$max)throw new InvalidArgumentException('Please check the length and format of '.$key.'.');return trim($v);
}
function string_list(string $text): array { return array_values(array_unique(array_filter(array_map('trim',explode(',',$text))))); }
function parse_grades(string $text): array {
 $out=[];foreach(preg_split('/\R/u',trim($text)) as $line){if(!trim($line))continue;$parts=explode('=',$line);
 if(count($parts)!==2)throw new InvalidArgumentException('Use one subject = grade per line, for example Mathematics = B.');
 [$subject,$grade]=array_map('trim',$parts);$grade=strtoupper($grade);
 if(!$subject||mb_strlen($subject)>80||!in_array($grade,['A*','A','B','C','D','E','F','G','U',''],true))throw new InvalidArgumentException('Use LGCSE grades A*, A–G or U. Leave an unknown grade blank.');
 $key=mb_strtolower(preg_replace('/\s+/u',' ',$subject));$aliases=['maths'=>'mathematics','math'=>'mathematics','physical sciences'=>'physical science','english language'=>'english'];$key=$aliases[$key]??$key;
 if(isset($out[$key]))throw new InvalidArgumentException('Enter each subject only once, including alternative subject names.');$out[$key]=['name'=>$subject,'grade'=>$grade];if(count($out)>20)throw new InvalidArgumentException('Enter at most 20 subjects.');
 }$grades=[];foreach($out as $v)$grades[$v['name']]=$v['grade'];return $grades;
}
function grades_text(array $grades): string {$rows=[];foreach($grades as $s=>$g)$rows[]=$s.' = '.$g;return implode("\n",$rows);}
function profile_payload(int $uid): array {
 $s=db()->prepare('SELECT payload FROM profile_versions WHERE user_id=? ORDER BY id DESC LIMIT 1');$s->execute([$uid]);$row=$s->fetch();if($row)return json_array($row['payload']);
 $s=db()->prepare('SELECT * FROM learner_profiles WHERE user_id=?');$s->execute([$uid]);$p=$s->fetch()?:[];
 return ['subjects'=>string_list($p['subjects']??''),'marks'=>array_map(static fn($v)=>$v===null?null:(float)$v,['maths'=>$p['maths_mark']??null,'science'=>$p['science_mark']??null,'english'=>$p['english_mark']??null]),'subject_grades'=>[],'aspirations'=>$p['aspirations']??'','skills'=>[],'preferences'=>[],'guidance_needs'=>[]];
}
function latest_assessment(int $uid): ?array {$s=db()->prepare('SELECT * FROM assessments WHERE user_id=? ORDER BY id DESC LIMIT 1');$s->execute([$uid]);return $s->fetch()?:null;}
function save_run(int $uid,int $assessmentId,array $profile,array $data): int {
 $p=db()->prepare('INSERT INTO profile_versions(user_id,payload) VALUES(?,?)');$p->execute([$uid,json_encode($profile,JSON_THROW_ON_ERROR)]);$version=(int)db()->lastInsertId();
 $p=db()->prepare('INSERT INTO recommendation_runs(user_id,assessment_id,profile_version_id,method_version,payload) VALUES(?,?,?,?,?)');$p->execute([$uid,$assessmentId,$version,$data['method_version']??'weighted-v2',json_encode($data,JSON_THROW_ON_ERROR)]);$run=(int)db()->lastInsertId();
 $rec=db()->prepare('INSERT INTO recommendations(user_id,career_title,match_score,explanation,recommended_subjects,pathway,holland_code,education_level,local_programmes) VALUES(?,?,?,?,?,?,?,?,?)');$detail=db()->prepare('INSERT INTO recommendation_details(recommendation_id,run_id,payload) VALUES(?,?,?)');
 foreach($data['recommendations'] as $r){$rec->execute([$uid,$r['title'],$r['match_score'],$r['explanation'],$r['recommended_subjects'],$r['pathway'],$r['holland_code']??null,$r['education_level']??null,json_encode($r['local_programmes']??[])]);$detail->execute([(int)db()->lastInsertId(),$run,json_encode($r,JSON_THROW_ON_ERROR)]);}return $run;
}
function catalogue(string $type): array {
 if(!in_array($type,['institutions','programmes','scholarships','career_catalogue'],true))throw new InvalidArgumentException('Unknown directory');
 $rows=db()->query('SELECT * FROM '.$type.' ORDER BY '.($type==='institutions'?'name':'title'))->fetchAll();$out=[];foreach($rows as $r){$p=json_array($r['payload']);$p['id']=$r['id'];$p['verification_status']=$r['verification_status'];$p['source_url']=$r['source_url'];$p['last_verified']=$r['last_verified'];$p['verified']=$r['verification_status']==='verified';$out[]=$p;}return $out;
}
function safe_url(?string $url): string {return $url&&filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['https','http'],true)?$url:'';}
function source_link(array $p): string {$url=safe_url($p['source_url']??$p['official_url']??'');$official=safe_url($p['official_url']??'');$html=$url?'<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">Official source ↗</a>':'Source not yet available';if($official&&$official!==$url)$html.=' · <a href="'.e($official).'" target="_blank" rel="noopener noreferrer">Institution/provider website ↗</a>';return $html;}
function saved_button(string $type,string $id,string $title): string {return '<form method="post" action="progress.php">'.csrf_field().'<input type="hidden" name="action" value="save"><input type="hidden" name="type" value="'.e($type).'"><input type="hidden" name="id" value="'.e($id).'"><input type="hidden" name="title" value="'.e($title).'"><button class="btn btn-outline" type="submit">Save to my progress</button></form>';}
function funding_status(array $p,?string $today=null): string {$today=$today??date('Y-m-d');if(($p['application_status']??'')==='closed')return 'closed';if(!empty($p['closes_on'])&&$p['closes_on']<$today)return 'closed';if(!empty($p['opens_on'])&&$p['opens_on']>$today)return 'upcoming';if(($p['verification_status']??'')==='verified'&&!empty($p['opens_on'])&&!empty($p['closes_on']))return 'open';return 'dates_unconfirmed';}
function reference_careers(): array {static $rows=null;if($rows===null){$path=dirname(__DIR__,2).'/ai_engine/data/processed/careers.json';$rows=is_file($path)?json_array(file_get_contents($path)):[];}return $rows;}
function career_options(): void {echo '<datalist id="career-titles">';foreach(reference_careers() as $c)echo '<option value="'.e($c['title']).'"></option>';echo '</datalist>';}
function career_supplement(string $title): array {$q=db()->prepare("SELECT payload FROM career_catalogue WHERE title=? AND verification_status='verified' LIMIT 1");$q->execute([$title]);return json_array($q->fetchColumn()?:'[]');}
function render_eligibility(array $r): void { ?>
<article class="panel"><span class="badge"><?= e(str_replace('_',' ',$r['status']??'undetermined')) ?></span><h3><?= e($r['programme']??$r['id']??'Programme') ?></h3>
<?php foreach(['met'=>'Requirements met','not_met'=>'Requirements not met','missing_subjects'=>'Missing subjects','unknown'=>'Cannot yet determine','improvements'=>'Next steps'] as $key=>$label): if(!empty($r[$key])): ?><h4><?= e($label) ?></h4><ul><?php foreach($r[$key] as $line): ?><li><?= e(is_string($line)?$line:json_encode($line)) ?></li><?php endforeach; ?></ul><?php endif; endforeach; ?><?= source_link($r) ?><p class="fine-print">This checks documented requirements only. Admission is decided by the institution.</p></article>
<?php }
