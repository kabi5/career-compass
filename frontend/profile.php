<?php
require_once 'config/layout.php';
require_login();
$user = current_user();
$saved = false;
$error = '';

function parse_subject_results(string $text): array {
    $results = [];
    $seen = [];
    $lines = preg_split('/\r\n|\n|\r/', trim($text));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (count($results) >= 40 || mb_strlen($line) > 150) {
            throw new InvalidArgumentException('Please enter no more than 40 subjects (150 characters per line).');
        }
        if (!preg_match('/^([^:]{1,100}):\s*(.+)$/u', $line, $matches)) {
            throw new InvalidArgumentException('Enter each result on a separate line as Subject: Grade (for example Mathematics: B).');
        }
        $subject = trim($matches[1]);
        $grade = strtoupper(trim($matches[2]));
        if ($subject === '' || !preg_match('/^(A\*|[A-G]|CREDIT|PASS|FAIL|\d{1,3}(?:\.\d{1,2})?)$/', $grade)) {
            throw new InvalidArgumentException('Use a valid LGCSE/COSC grade or percentage from 0 to 100.');
        }
        if (is_numeric($grade) && (float)$grade > 100) {
            throw new InvalidArgumentException('Percentage grades cannot exceed 100.');
        }
        $key = mb_strtolower(preg_replace('/\s+/', ' ', $subject));
        if (isset($seen[$key])) throw new InvalidArgumentException('Please list each subject only once.');
        $seen[$key] = true;
        $results[] = ['subject' => $subject, 'grade' => $grade];
    }
    return $results;
}

$stmt = db()->prepare('SELECT * FROM learner_profiles WHERE user_id=?');
$stmt->execute([$user['id']]);
$p = $stmt->fetch() ?: [];
$previousResults = json_decode($p['subject_results'] ?? '[]', true);
$resultsText = is_array($previousResults)
    ? implode("\n", array_map(static fn($r) => ($r['subject'] ?? '') . ': ' . ($r['grade'] ?? ''), $previousResults))
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $subjects = trim((string)($_POST['subjects'] ?? ''));
    $aspirations = trim((string)($_POST['aspirations'] ?? ''));
    $qualification = (string)($_POST['qualification_type'] ?? '');
    $yearRaw = trim((string)($_POST['qualification_year'] ?? ''));
    $resultsText = trim((string)($_POST['subject_results_text'] ?? ''));
    $marks = [];
    if (!in_array($qualification, ['', 'LGCSE', 'COSC', 'OTHER'], true)) {
        $error = 'Please choose a valid qualification type.';
    }
    $year = null;
    if ($yearRaw !== '') {
        if (!ctype_digit($yearRaw) || (int)$yearRaw < 2000 || (int)$yearRaw > 2100) {
            $error = 'Please provide a valid qualification year.';
        } else $year = (int)$yearRaw;
    }
    foreach (['maths_mark', 'science_mark', 'english_mark'] as $key) {
        $raw = trim((string)($_POST[$key] ?? ''));
        if ($raw !== '' && (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100)) {
            $error = 'Every percentage mark must be between 0 and 100.';
            break;
        }
        $marks[$key] = $raw === '' ? null : (float)$raw;
    }
    if (mb_strlen($subjects) > 1000 || mb_strlen($aspirations) > 1000 || mb_strlen($resultsText) > 6000) {
        $error = 'Please shorten your subjects or aspirations.';
    }
    if (!$error) {
        try {
            $results = parse_subject_results($resultsText);
            $statement = db()->prepare('INSERT INTO learner_profiles
                (user_id,subjects,maths_mark,science_mark,english_mark,aspirations,qualification_type,qualification_year,subject_results)
                VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
                subjects=VALUES(subjects),maths_mark=VALUES(maths_mark),science_mark=VALUES(science_mark),
                english_mark=VALUES(english_mark),aspirations=VALUES(aspirations),
                qualification_type=VALUES(qualification_type),qualification_year=VALUES(qualification_year),
                subject_results=VALUES(subject_results)');
            $pdo = db();
            $pdo->beginTransaction();
            $statement->execute([$user['id'], $subjects, $marks['maths_mark'], $marks['science_mark'],
                $marks['english_mark'], $aspirations, $qualification ?: null, $year,
                json_encode($results, JSON_THROW_ON_ERROR)]);
            // Preserve an academic-profile snapshot without changing any older assessments.
            $snapshot = [
                'subjects' => array_values(array_filter(array_map('trim', explode(',', $subjects)))),
                'qualification_type' => $qualification,
                'qualification_year' => $year,
                'subject_results' => $results,
                'marks' => ['maths' => $marks['maths_mark'],
                    'science' => $marks['science_mark'], 'english' => $marks['english_mark']],
                'aspirations' => $aspirations,
            ];
            $pdo->prepare('INSERT INTO profile_versions (user_id,payload) VALUES (?,?)')
                ->execute([(int)$user['id'], json_encode($snapshot, JSON_THROW_ON_ERROR)]);
            $pdo->commit();
            $saved = true;
            $stmt->execute([$user['id']]);
            $p = $stmt->fetch() ?: [];
        } catch (InvalidArgumentException $exception) {
            if (db()->inTransaction()) db()->rollBack();
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('Could not save academic profile: ' . $exception->getMessage());
            $error = 'Your academic profile could not be saved. Please try again.';
        }
    }
}

page_start('My academic profile', 'profile');
?>
<main id="main" class="wide page-space">
  <div class="page-intro"><span class="eyebrow muted-eyebrow">BUILD YOUR STORY</span><h1>Your academic profile</h1><p>Add your school results to explore careers and possible Lesotho study routes.</p></div>
  <div class="two-column"><section class="panel">
    <div class="panel-heading"><h2>My subjects & goals</h2></div>
    <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($saved): ?><div class="alert success" role="status">Saved. Retake the assessment to update your recommendations.</div><?php endif; ?>
    <form method="post" class="form-grid"><?= csrf_field() ?>
      <div class="form-row">
        <label>Qualification system
          <select name="qualification_type">
            <?php foreach ([''=>'Not selected','LGCSE'=>'LGCSE','COSC'=>'COSC','OTHER'=>'Other / not sure'] as $key=>$label): ?>
              <option value="<?= e($key) ?>" <?= ($qualification ?? ($p['qualification_type'] ?? '')) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Qualification year (optional)<input type="number" name="qualification_year" min="2000" max="2100" value="<?= e((string)($yearRaw ?? ($p['qualification_year'] ?? ''))) ?>" placeholder="e.g. 2026"></label>
      </div>
      <label>Subjects you are taking<input name="subjects" maxlength="1000" placeholder="Mathematics, English, Physical Science" value="<?= e($subjects ?? ($p['subjects'] ?? '')) ?>"><small>Separate subjects with commas.</small></label>
      <label>School subject results<textarea name="subject_results_text" rows="7" maxlength="6000" placeholder="Mathematics: B&#10;English: C&#10;Physical Science: B"><?= e($resultsText) ?></textarea><small>One result per line: Subject: Grade. LGCSE: A*, A–G. COSC: Credit / Pass / Fail, or record a numeric percentage where available. Results are used for guidance, not official admission decisions.</small></label>
      <p class="fine-print">If you only know percentage marks, you may also enter these below. Structured results take priority for the same subject.</p>
      <div class="form-row"><label>Mathematics (%)<input type="number" name="maths_mark" min="0" max="100" step="0.1" value="<?= e((string)($marks['maths_mark'] ?? ($p['maths_mark'] ?? ''))) ?>"></label><label>Science (%)<input type="number" name="science_mark" min="0" max="100" step="0.1" value="<?= e((string)($marks['science_mark'] ?? ($p['science_mark'] ?? ''))) ?>"></label></div>
      <label>English (%)<input type="number" name="english_mark" min="0" max="100" step="0.1" value="<?= e((string)($marks['english_mark'] ?? ($p['english_mark'] ?? ''))) ?>"></label>
      <label>Career ideas or ambitions<textarea name="aspirations" rows="4" maxlength="1000" placeholder="What would you like to learn or do someday?"><?= e($aspirations ?? ($p['aspirations'] ?? '')) ?></textarea></label>
      <button type="submit" class="btn btn-primary">Save my profile</button>
    </form>
  </section><aside class="side-stack"><div class="panel highlight-panel"><h2>Why your results help</h2><p>We compare your interests with career profiles and use your grades to suggest areas of preparation. Admission requirements must be checked with the institution.</p><a class="text-link" href="assessment.php">Go to assessment ↗</a></div><div class="panel"><h3>Your privacy matters</h3><p class="muted">Do not include identity numbers, addresses or private contact details in your aspirations.</p><a class="text-link" href="privacy.php">Read about privacy ↗</a></div></aside></div>
</main>
<?php page_end(); ?>
