<?php
function seed_directory(PDO $pdo,string $root): void {
 $dir=$root.'/ai_engine/data/directory';
 foreach(['institutions','programmes','scholarships'] as $table){$path=$dir.'/'.$table.'.json';if(!is_file($path))continue;$items=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 foreach($items as $p){$cols=['id'];$values=[$p['id']];if($table==='institutions'){$cols[]='name';$values[]=$p['name'];}else{if($table==='programmes'){$cols[]='institution_id';$values[]=$p['institution_id']??null;}$cols[]='title';$values[]=$p['title']??$p['programme'];if($table==='scholarships'){$cols[]='provider';$values[]=$p['provider'];}}
 $cols=array_merge($cols,['payload','verification_status','source_url','last_verified']);$values=array_merge($values,[json_encode($p,JSON_THROW_ON_ERROR),$p['verification_status']??'unverified',$p['source_url']??null,$p['last_verified']??null]);
 $q=$pdo->prepare('INSERT IGNORE INTO '.$table.'('.implode(',',$cols).') VALUES('.implode(',',array_fill(0,count($cols),'?')).')');$q->execute($values);
 if($table==='programmes'&&!empty($p['admission_rules'])){$q=$pdo->prepare('INSERT IGNORE INTO admission_requirements(programme_id,grading_system,rules_json) VALUES(?,?,?)');$q->execute([$p['id'],$p['admission_rules']['grading_system']??'LGCSE',json_encode($p['admission_rules'])]);}
 }}
 $path=$root.'/ai_engine/data/programmes/programmes.json';if(is_file($path))foreach(json_decode(file_get_contents($path),true) as $p){$p['verification_status']='unverified';$p['verified']=false;$p['source_document']=$p['source']??'Legacy sample';$pdo->prepare('INSERT IGNORE INTO programmes(id,title,payload,verification_status) VALUES(?,?,?,?)')->execute([$p['id'],$p['programme'],json_encode($p),'unverified']);}
}
