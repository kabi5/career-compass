<?php
require_once 'config/layout.php';
require_once 'config/guidance.php';
require_login();
$questions = db()->query('SELECT id,question,riasec_dimension FROM assessment_questions ORDER BY id')->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    if (rate_limited('assessment', 12, 600)) $error = 'Please wait a little before submitting another assessment.';
    elseif (count($questions) === 0) $error = 'Questions have not been installed. Ask the administrator to run the database setup.';
    else {
        $responses = [];
        foreach ($questions as $q) {
            $answer = $_POST['q' . $q['id']] ?? null;
            if (!is_string($answer) || !in_array($answer, ['1','2','3','4','5'], true)) {
                $error = 'Please answer every question before you submit.';
                break;
            }
            $responses[] = ['dimension' => $q['riasec_dimension'], 'answer' => (int)$answer];
        }
        if (!$error) {
            $profile = profile_payload((int)$_SESSION['user_id']);
            $payload = array_merge($profile,['responses'=>$responses,'top_n'=>5]);
            $data = api_request('/recommend', $payload, 45);
            if (!$data || !isset($data['riasec_scores'], $data['top_code'], $data['recommendations']) || !is_array($data['recommendations'])) {
                $error = 'Your results could not be generated right now. Please try again when the recommendation service is available.';
            } else {
                try {
                    $pdo = db(); $pdo->beginTransaction();
                    $ins = $pdo->prepare('INSERT INTO assessments (user_id,riasec_scores,top_interest) VALUES (?,?,?)');
                    $ins->execute([$_SESSION['user_id'], json_encode($data['riasec_scores']), $data['top_code']]);
                    $assessmentId=(int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO assessment_responses(assessment_id,instrument_version,language_code,responses) VALUES(?,?,?,?)')->execute([$assessmentId,'cc-exploratory-24-v1',language(),json_encode($responses)]);
                    save_run((int)$_SESSION['user_id'],$assessmentId,$profile,$data);
                    $pdo->commit();
                    header('Location: dashboard.php?completed=1'); exit;
                } catch (Throwable $ex) {
                    if (db()->inTransaction()) db()->rollBack();
                    error_log('Could not save assessment: ' . $ex->getMessage());
                    $error = 'Something went wrong while saving your results. Please try again.';
                }
            }
        }
    }
}
page_start('Career interest assessment', 'assessment');
?>
<main id="main" class="wide page-space"><div class="page-intro"><span class="eyebrow muted-eyebrow">DISCOVER WHAT MAKES YOU, YOU</span><h1><?= e(t('Your career interest assessment')) ?></h1><p><?= e(t('Assessment instructions')) ?></p><p class="fine-print">Allow about 5–8 minutes. Choose “Unsure” if you cannot judge an activity. This locally written activity is not psychometrically validated. All answers and previous results are retained until you delete your account. <a href="history.php">View history</a>.</p></div>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$questions): ?><div class="panel empty-state"><h2>Questions are not set up yet</h2><p>Ask the site administrator to initialise the assessment questions using the included database setup.</p></div><?php else: ?>
<div class="assessment-layout"><aside class="assessment-aside"><div class="panel sticky-panel"><span class="eyebrow muted-eyebrow">YOUR PROGRESS</span><h2>Getting to know you ✳</h2><div class="progress-track"><div class="progress-fill" data-progress-bar></div></div><p class="small muted" aria-live="polite" data-progress-text>0 of <?= count($questions) ?> answered</p><div class="tip-box"><strong>How to answer</strong><p>Choose from 1 (strongly disagree) to 5 (strongly agree). Pick what really interests you.</p></div><p class="fine-print">This is an exploratory interest activity, not a psychological diagnosis or formal aptitude test.</p></div></aside>
<form action="assessment.php" method="post" class="questions-panel" data-assessment-form><?= csrf_field() ?><div class="question-key"><span>1 = Strongly disagree</span><span>5 = Strongly agree</span></div>
<?php $answerLabels=[1=>'Strongly disagree',2=>'Disagree',3=>'Unsure',4=>'Agree',5=>'Strongly agree']; foreach ($questions as $index => $q): ?><fieldset class="question-card"><legend><span class="q-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span><?= e(question_text($q['question'])) ?></legend><div class="likert"><?php for ($i=1; $i<=5; $i++): ?><label class="likert-opt"><input type="radio" name="q<?= (int)$q['id'] ?>" value="<?= $i ?>" aria-label="<?= e(t($answerLabels[$i])) ?>" required <?= ($_POST['q'.$q['id']] ?? '') === (string)$i ? 'checked' : '' ?>><span><?= $i ?></span><small><?= e(t($answerLabels[$i])) ?></small></label><?php endfor; ?></div></fieldset><?php endforeach; ?>
<div class="assessment-submit"><p class="small muted">Your results may change as you explore new interests. That's completely normal.</p><button type="submit" class="btn btn-primary btn-lg"><?= e(t('See my career matches')) ?> ↗</button></div></form></div>
<?php endif; ?></main>
<?php page_end(); ?>

