<?php
require_once 'config/layout.php';
require_login();
$questions = db()->query('SELECT id,question,riasec_dimension FROM assessment_questions ORDER BY id')->fetchAll();
$error = '';
$dimensionCounts = array_count_values(array_column($questions, 'riasec_dimension'));
$setup_ok = count($questions) === 24;
foreach (['R','I','A','S','E','C'] as $dimension) {
    if (($dimensionCounts[$dimension] ?? 0) !== 4) $setup_ok = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    if (rate_limited('assessment', 12, 600)) $error = 'Please wait a little before submitting another assessment.';
    elseif (!$setup_ok) $error = 'The 24 questions are not configured correctly. Please ask the administrator to update the database.';
    else {
        $responses = [];
        foreach ($questions as $q) {
            $answer = $_POST['q' . $q['id']] ?? null;
            if (!is_string($answer) || !in_array($answer, ['1','2','3','4','5'], true)) {
                $error = 'Please answer every question before you submit.';
                break;
            }
            $responses[] = ['question_id' => (int)$q['id'], 'dimension' => $q['riasec_dimension'], 'answer' => (int)$answer];
        }
        if (!$error) {
            $stmt = db()->prepare('SELECT * FROM learner_profiles WHERE user_id = ?');
            $stmt->execute([(int)$_SESSION['user_id']]);
            $profile = $stmt->fetch() ?: [];
            $structured = json_decode($profile['subject_results'] ?? '[]', true);
            if (!is_array($structured)) $structured = [];
            $subjectNames = array_values(array_filter(array_map('trim', explode(',', $profile['subjects'] ?? ''))));
            foreach ($structured as $item) {
                if (is_array($item) && isset($item['subject']) && is_string($item['subject'])) $subjectNames[] = $item['subject'];
            }
            $marks = [];
            foreach (['maths'=>'maths_mark','science'=>'science_mark','english'=>'english_mark'] as $key=>$column) {
                $marks[$key] = isset($profile[$column]) && $profile[$column] !== '' ? (float)$profile[$column] : null;
            }
            $payload = [
                'responses' => $responses,
                'marks' => $marks,
                'subjects' => array_values(array_unique($subjectNames)),
                'qualification_type' => $profile['qualification_type'] ?? '',
                'subject_results' => $structured,
                'aspirations' => $profile['aspirations'] ?? '',
                'top_n' => 5,
            ];
            $data = api_request('/recommend', $payload, 45);
            if (!$data || !isset($data['riasec_scores'], $data['top_code'], $data['recommendations']) || !is_array($data['recommendations'])) {
                $error = 'Your results could not be generated right now. Please try again when the recommendation service is available.';
            } else {
                try {
                    $pdo = db();
                    $pdo->beginTransaction();

                    // Append-only history: each assessment creates a new run. Never
                    // delete old recommendations or their dependent details/feedback.
                    $pdo->prepare('INSERT INTO assessments (user_id,riasec_scores,top_interest) VALUES (?,?,?)')
                        ->execute([(int)$_SESSION['user_id'],
                            json_encode($data['riasec_scores'], JSON_THROW_ON_ERROR), $data['top_code']]);
                    $assessmentId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO assessment_responses (assessment_id,instrument_version,language_code,responses) VALUES (?,?,?,?)')
                        ->execute([$assessmentId, 'cc-exploratory-24-v1', 'en',
                            json_encode($responses, JSON_THROW_ON_ERROR)]);

                    $profileSnapshot = [
                        'subjects' => $subjectNames,
                        'marks' => $marks,
                        'qualification_type' => $profile['qualification_type'] ?? '',
                        'qualification_year' => $profile['qualification_year'] ?? null,
                        'subject_results' => $structured,
                        'aspirations' => $profile['aspirations'] ?? '',
                    ];
                    $pdo->prepare('INSERT INTO profile_versions (user_id,payload) VALUES (?,?)')
                        ->execute([(int)$_SESSION['user_id'],
                            json_encode($profileSnapshot, JSON_THROW_ON_ERROR)]);
                    $profileVersionId = (int)$pdo->lastInsertId();

                    $version = (string)($data['score_version'] ?? 'unknown');
                    $runPayload = [
                        'score_version' => $version,
                        'factor_weights' => $data['factor_weights'] ?? [],
                        'methodology' => $data['methodology'] ?? '',
                        'recommendations_count' => count($data['recommendations']),
                    ];
                    $pdo->prepare('INSERT INTO recommendation_runs
                        (user_id,assessment_id,profile_version_id,method_version,payload)
                        VALUES (?,?,?,?,?)')->execute([
                            (int)$_SESSION['user_id'], $assessmentId, $profileVersionId,
                            $version, json_encode($runPayload, JSON_THROW_ON_ERROR)
                        ]);
                    $runId = (int)$pdo->lastInsertId();

                    $rec = $pdo->prepare('INSERT INTO recommendations
                      (user_id,career_title,match_score,ranking_score,data_coverage,score_status,score_version,score_details,eligibility_status,explanation,recommended_subjects,pathway,holland_code,education_level,local_programmes)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $insertDetail = $pdo->prepare('INSERT INTO recommendation_details
                        (recommendation_id,run_id,payload) VALUES (?,?,?)');
                    foreach ($data['recommendations'] as $r) {
                        if (!is_array($r) || empty($r['title']) || !is_string($r['title'])) {
                            throw new UnexpectedValueException('Invalid career recommendation received');
                        }
                        $details = json_encode($r, JSON_THROW_ON_ERROR);
                        $coverage = isset($r['data_coverage']) && is_numeric($r['data_coverage'])
                            ? round(max(0, min(1, (float)$r['data_coverage'])) * 100, 2) : null;
                        $rec->execute([
                            (int)$_SESSION['user_id'], mb_substr($r['title'], 0, 200),
                            $r['match_score'] ?? null, $r['ranking_score'] ?? null,
                            $coverage, $r['score_status'] ?? 'exploratory',
                            $version, $details,
                            $r['eligibility_status'] ?? 'insufficient_information',
                            $r['explanation'] ?? '', $r['recommended_subjects'] ?? '', $r['pathway'] ?? '',
                            $r['holland_code'] ?? null, $r['education_level'] ?? null,
                            json_encode($r['local_programmes'] ?? [], JSON_THROW_ON_ERROR),
                        ]);
                        $insertDetail->execute([(int)$pdo->lastInsertId(), $runId, $details]);
                    }
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
<main id="main" class="wide page-space"><div class="page-intro"><span class="eyebrow muted-eyebrow">DISCOVER WHAT MAKES YOU, YOU</span><h1>Your career interest assessment</h1><p>Think about what you enjoy doing, not just what you're good at today. There are no right or wrong answers.</p></div>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$setup_ok): ?><div class="panel empty-state"><h2>Questions need to be updated</h2><p>Ask the administrator to install all 24 questions (four per interest category) using the included non-destructive migration.</p></div><?php else: ?>
<div class="assessment-layout"><aside class="assessment-aside"><div class="panel sticky-panel"><span class="eyebrow muted-eyebrow">YOUR PROGRESS</span><h2>Getting to know you ✳</h2><div class="progress-track"><div class="progress-fill" data-progress-bar></div></div><p class="small muted" data-progress-text>0 of <?= count($questions) ?> answered</p><div class="tip-box"><strong>How to answer</strong><p>Choose from 1 (strongly disagree) to 5 (strongly agree). Pick what really interests you.</p></div><p class="fine-print">This is an exploratory interest activity, not a psychological diagnosis or formal aptitude test.</p></div></aside>
<form action="assessment.php" method="post" class="questions-panel" data-assessment-form><?= csrf_field() ?><div class="question-key"><span>1 = Strongly disagree</span><span>5 = Strongly agree</span></div>
<?php foreach ($questions as $index => $q): ?><fieldset class="question-card"><legend><span class="q-number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span><?= e($q['question']) ?></legend><div class="likert"><?php for ($i=1; $i<=5; $i++): ?><label class="likert-opt"><input type="radio" name="q<?= (int)$q['id'] ?>" value="<?= $i ?>" required <?= ($_POST['q'.$q['id']] ?? '') === (string)$i ? 'checked' : '' ?>><span><?= $i ?></span></label><?php endfor; ?></div></fieldset><?php endforeach; ?>
<div class="assessment-submit"><p class="small muted">Your results may change as you explore new interests. That's completely normal.</p><button type="submit" class="btn btn-primary btn-lg">See my career matches ↗</button></div></form></div>
<?php endif; ?></main>
<?php page_end(); ?>
