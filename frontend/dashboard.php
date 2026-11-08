<?php
require_once 'config/layout.php';
require_login();

$user = current_user();
$stmt = db()->prepare('SELECT * FROM assessments WHERE user_id=? ORDER BY taken_at DESC,id DESC LIMIT 1');
$stmt->execute([$user['id']]);
$assessment = $stmt->fetch();

// Use this user's latest assessment run, never stale recommendations from
// a different assessment. Coverage is stored as a percentage (0–100).
$stmt = db()->prepare('SELECT id FROM recommendation_runs WHERE user_id=? ORDER BY id DESC LIMIT 1');
$stmt->execute([(int)$user['id']]);
$latestRunId = $stmt->fetchColumn();
$recs = [];
if ($latestRunId) {
    $stmt = db()->prepare('SELECT r.*, d.payload AS scoring_payload
        FROM recommendation_details d
        JOIN recommendations r ON r.id=d.recommendation_id
        WHERE d.run_id=? AND r.user_id=?
        ORDER BY COALESCE(r.data_coverage,0) DESC,
                 COALESCE(r.ranking_score,r.match_score,-1) DESC, r.id DESC
        LIMIT 5');
    $stmt->execute([(int)$latestRunId, (int)$user['id']]);
    $recs = $stmt->fetchAll();
} else {
    // Older installations may have recommendations without run records.
    $stmt = db()->prepare('SELECT r.*, NULL AS scoring_payload FROM recommendations r
        WHERE r.user_id=? ORDER BY r.generated_at DESC,r.id DESC LIMIT 5');
    $stmt->execute([(int)$user['id']]);
    $recs = $stmt->fetchAll();
}

$stmt = db()->prepare('SELECT * FROM learner_profiles WHERE user_id=?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch() ?: [];
$scores = $assessment ? (json_decode($assessment['riasec_scores'], true) ?: []) : [];
$riasec = ['R'=>'Realistic','I'=>'Investigative','A'=>'Artistic','S'=>'Social','E'=>'Enterprising','C'=>'Conventional'];
$name = explode(' ', trim($user['full_name']))[0];
page_start('Your dashboard', 'dashboard');
?>
<main id="main" class="wide page-space">
  <div class="dashboard-welcome">
    <div><span class="eyebrow muted-eyebrow">YOUR PERSONAL SPACE</span><h1>Hi, <?= e($name) ?> <span aria-hidden="true">✳</span></h1><p>Every big journey starts with curiosity. Let's see where yours takes you.</p></div>
    <a href="profile.php" class="btn btn-outline">Edit my profile ↗</a>
  </div>

  <?php if (isset($_GET['completed']) && $_GET['completed'] === '1'): ?>
    <div class="alert success" role="status">Your assessment is complete! Explore your career matches below.</div>
  <?php endif; ?>

  <?php if (!$assessment): ?>
    <section class="welcome-banner"><div><span class="eyebrow">YOUR FIRST STEP</span><h2>Let's find what lights you up.</h2><p>Take a short interest assessment and get career ideas tailored to your answers.</p><a href="assessment.php" class="btn btn-light">Start my assessment ↗</a></div><span class="welcome-symbol" aria-hidden="true">✦</span></section>
  <?php else: ?>
    <section class="welcome-banner"><div><span class="eyebrow">YOU'RE MAKING PROGRESS</span><h2>Your journey is taking shape.</h2><p>You have completed your interest assessment. Explore your options and keep learning about what fits you.</p><a href="assessment.php" class="btn btn-light">Retake assessment ↗</a></div><span class="welcome-symbol" aria-hidden="true">◎</span></section>
  <?php endif; ?>

  <div class="dashboard-grid">
    <section class="panel">
      <div class="panel-heading"><div><span class="eyebrow muted-eyebrow">ABOUT YOU</span><h2>Your interest profile</h2></div><span class="panel-symbol">◉</span></div>
      <?php if ($assessment): ?>
        <div class="interest-head"><span class="interest-code"><?= e($assessment['top_interest']) ?></span><div><strong>Holland interest code</strong><small>Top interests: <?php $names=[]; foreach (str_split($assessment['top_interest']) as $d) $names[]=$riasec[$d]??$d; echo e(implode(' · ', $names)); ?></small></div></div>
        <div class="riasec-chart">
          <?php foreach ($riasec as $key=>$label): $percent = min(100,max(0,(float)($scores[$key]??0))); ?>
            <div class="interest-bar"><div class="bar-info"><span><?= e($label) ?></span><strong><?= round($percent) ?>%</strong></div><div class="bar-track"><div class="bar-fill bar-<?= e(strtolower($key)) ?>" style="width:<?= $percent ?>%"></div></div></div>
          <?php endforeach; ?>
        </div>
        <p class="fine-print">These percentages describe interest responses, not your ability or probability of success.</p>
      <?php else: ?>
        <div class="empty-state"><div class="empty-icon">◎</div><h3>Your interests, uncovered</h3><p>Take the assessment to see how you relate to six interest areas.</p><a href="assessment.php" class="text-link">Take the assessment ↗</a></div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><span class="eyebrow muted-eyebrow">WHAT'S NEXT</span><h2>Your career matches</h2></div><span class="panel-symbol">↗</span></div>
      <?php if ($recs): ?>
        <div class="matches-list">
          <?php foreach ($recs as $i=>$rec): ?>
            <?php
              $evidence = json_decode((string)($rec['scoring_payload'] ?: ($rec['score_details'] ?? '')), true);
              $evidence = is_array($evidence) ? $evidence : [];
              $score = $rec['match_score'] ?? null;
              $ranking = $rec['ranking_score'] ?? ($evidence['ranking_score'] ?? null);
              $status = (string)($rec['score_status'] ?? ($evidence['score_status'] ?? 'exploratory'));
              $hasScore = is_numeric($score) && (float)$score >= 0 && (float)$score <= 100;
              $hasRanking = is_numeric($ranking) && (float)$ranking >= 0 && (float)$ranking <= 100;
              $coverage = $rec['data_coverage'] ?? null;
              if (!is_numeric($coverage) && isset($evidence['data_coverage'])) {
                  $coverage = (float)$evidence['data_coverage'] * 100;
              }
              $coverageLabel = is_numeric($coverage) ? round((float)$coverage).'%' : 'unknown';
              if ($hasScore && $status === 'complete') {
                  $scoreLabel = number_format((float)$score, 1).'%';
                  $scoreNote = 'Five-factor alignment · '.$coverageLabel.' evidence';
              } elseif ($hasScore || $hasRanking) {
                  $scoreLabel = number_format((float)($hasScore ? $score : $ranking), 1).'%';
                  $scoreNote = 'Provisional alignment · '.$coverageLabel.' evidence · '.ucfirst($status);
              } else {
                  $scoreLabel = 'Exploratory';
                  $scoreNote = 'Not enough evidence for a reliable percentage';
              }
              $scoreTitle = 'Educational exploration only; admission and employment outcomes are not predicted.';
            ?>
            <a class="match-row" href="career-detail.php?id=<?= (int)$rec['id'] ?>">
              <span class="match-count"><?= sprintf('%02d',$i+1) ?></span>
              <span class="match-title"><strong><?= e($rec['career_title']) ?></strong><small><?= e($scoreNote) ?></small></span>
              <span class="match-percent" title="<?= e($scoreTitle) ?>"><?= e($scoreLabel) ?></span>
              <span class="match-arrow">↗</span>
            </a>
          <?php endforeach; ?>
        </div>
        <p class="fine-print">Scores are preliminary unless all five factors have valid evidence. Evidence coverage is the weighted share of usable information, not an employment probability. Admission is never guaranteed.</p><p class="fine-print"><a href="history.php">View previous assessments and recommendation runs →</a></p>
      <?php else: ?>
        <div class="empty-state"><div class="empty-icon">✧</div><h3>Possibilities await</h3><p>Your career suggestions will appear here after you complete the assessment.</p><a href="assessment.php" class="text-link">Discover matches ↗</a></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="next-grid">
    <a class="next-card" href="profile.php"><span class="next-icon coral">✎</span><span><strong>Update your academic profile</strong><small><?= $profile ? 'Keep your subjects and marks up to date' : 'Add your school subjects to make matches more useful' ?></small></span><span class="next-arrow">↗</span></a>
    <a class="next-card" href="careers.php"><span class="next-icon violet">⌁</span><span><strong>Explore careers</strong><small>Look beyond your top matches and discover new fields</small></span><span class="next-arrow">↗</span></a>
    <a class="next-card" href="chat.php"><span class="next-icon green">✦</span><span><strong>Ask the career assistant</strong><small>Questions about subjects, careers or places to study?</small></span><span class="next-arrow">↗</span></a>
  </div>

  <section class="panel tool-links"><h2>Build your next steps</h2><a class="btn btn-outline" href="directory.php">Programmes & funding</a><a class="btn btn-outline" href="simulate.php">Subject simulator</a><a class="btn btn-outline" href="compare.php">Compare careers</a><a class="btn btn-outline" href="roadmap.php">My career roadmap</a><a class="btn btn-outline" href="history.php">History & changes</a></section>
</main>
<?php page_end(); ?>
