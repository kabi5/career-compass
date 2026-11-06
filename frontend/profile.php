<?php
require_once 'config/layout.php';require_once 'config/guidance.php';require_login();
$user=current_user();$p=profile_payload((int)$user['id']);$error='';$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['csrf']??null);
 try{
 $p=['subjects'=>string_list(input_text('subjects')),'aspirations'=>input_text('aspirations'),'skills'=>string_list(input_text('skills',500)),'preferences'=>string_list(input_text('preferences',500)),'guidance_needs'=>string_list(input_text('guidance_needs',500)),'subject_grades'=>parse_grades(input_text('subject_grades',2500)),'grading_system'=>'LGCSE','marks'=>[]];
 foreach(['maths','science','english'] as $key){$raw=input_text($key.'_mark',10);if($raw!==''&&(!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>100))throw new InvalidArgumentException('Every percentage mark must be between 0 and 100.');$p['marks'][$key]=$raw===''?null:(float)$raw;}
 $p['subjects']=array_values(array_unique(array_merge($p['subjects'],array_keys($p['subject_grades']))));if(count($p['subjects'])>40)throw new InvalidArgumentException('Enter no more than 40 subjects.');
 foreach(['subjects','skills','preferences','guidance_needs'] as $field){if(count($p[$field])>40)throw new InvalidArgumentException('Enter no more than 40 entries for '.$field.'.');foreach($p[$field] as $entry)if(mb_strlen($entry)>100)throw new InvalidArgumentException('Each subject, skill, preference or guidance need must be 100 characters or fewer.');}
 db()->beginTransaction();
 db()->prepare('INSERT INTO learner_profiles(user_id,subjects,maths_mark,science_mark,english_mark,aspirations) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE subjects=VALUES(subjects),maths_mark=VALUES(maths_mark),science_mark=VALUES(science_mark),english_mark=VALUES(english_mark),aspirations=VALUES(aspirations)')->execute([$user['id'],implode(', ',$p['subjects']),$p['marks']['maths'],$p['marks']['science'],$p['marks']['english'],$p['aspirations']]);
 db()->prepare('INSERT INTO profile_versions(user_id,payload) VALUES(?,?)')->execute([$user['id'],json_encode($p,JSON_THROW_ON_ERROR)]);db()->commit();$saved=true;
 }catch(InvalidArgumentException $ex){$error=$ex->getMessage();}catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();error_log('Profile save failed: '.$ex->getMessage());$error='Unable to save your profile. Please try again.';}
}
page_start(t('Your academic profile'),'profile');
?>
<main id="main" class="wide page-space"><div class="page-intro"><span class="eyebrow muted-eyebrow">BUILD YOUR STORY</span><h1><?= e(t('Your academic profile')) ?></h1><p>Share only what you choose. Missing marks remain unknown. Your guidance needs help you and your counsellor plan support.</p></div>
<?php if($error):?><div class="alert error" role="alert"><?= e(t('Check form')) ?> <?= e($error) ?></div><?php endif;?>
<?php if($saved):?><div class="alert success" role="status"><?= e(t('Profile saved')) ?></div><?php endif;?>
<div class="two-column"><section class="panel"><form method="post" class="form-grid"><?= csrf_field() ?>
<label><?= e(t('Subjects')) ?><input name="subjects" maxlength="1000" value="<?= e(implode(', ',$p['subjects']??[])) ?>"><small>Separate subjects with commas.</small></label>
<label>LGCSE subjects and grades<textarea name="subject_grades" rows="7" maxlength="2500" placeholder="Mathematics = B&#10;English = C&#10;Physical Science = B"><?= e(grades_text($p['subject_grades']??[])) ?></textarea><small>One subject = grade per line. Use A*, A–G or U. These grades are used for programme requirements; no automatic conversion from percentages is made.</small></label>
<fieldset><legend>Optional current class marks (%)</legend><div class="form-row"><?php foreach(['maths'=>'Mathematics','science'=>'Science','english'=>'English'] as $key=>$label):?><label><?= e($label) ?><input type="number" name="<?= $key ?>_mark" min="0" max="100" step="0.1" value="<?= e(isset($p['marks'][$key])?(string)$p['marks'][$key]:'') ?>"></label><?php endforeach;?></div></fieldset>
<?php foreach(['skills'=>'Skills','preferences'=>'Preferences','guidance_needs'=>'Guidance needs'] as $key=>$label):?><label><?= e(t($label)) ?><input name="<?= $key ?>" maxlength="500" value="<?= e(implode(', ',$p[$key]??[])) ?>"><small><?= $key==='guidance_needs'?'Examples: choosing subjects, funding, applications, workplace experience.':'Separate entries with commas. Describe activities or working environments you enjoy.' ?></small></label><?php endforeach;?>
<label><?= e(t('Aspirations')) ?><textarea name="aspirations" rows="4" maxlength="1000"><?= e($p['aspirations']??'') ?></textarea></label><button class="btn btn-primary"><?= e(t('Save my profile')) ?></button></form></section>
<aside class="side-stack"><div class="panel highlight-panel"><h2>Refresh your career ideas</h2><p>Your latest interest assessment can be reused after you update subjects, marks, skills or ambitions. Save changes first.</p><form method="post" action="recalculate.php"><?= csrf_field() ?><button class="btn btn-primary"><?= e(t('Recalculate')) ?></button></form><p><a href="history.php">Review previous assessments and matches ↗</a></p></div><div class="panel"><h2>Try a scenario</h2><p>Explore different grades without changing this profile.</p><a href="simulate.php" class="btn btn-outline">Open what-if simulator</a></div><div class="panel"><h3>Your privacy</h3><p>Do not include identification numbers, health information or home addresses.</p><a href="account.php">Manage sharing and consent ↗</a></div></aside></div></main>
<?php page_end(); ?>
