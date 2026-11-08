<?php
require_once 'config/layout.php';
require_login();

$user = current_user();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = db()->prepare('SELECT * FROM recommendations WHERE id=? AND user_id=?');
$stmt->execute([$id, (int)$user['id']]);
$rec = $stmt->fetch();

if (!$rec) {
    http_response_code(404);
    page_start('Career not found', 'careers');
    echo '<main id="main" class="wide page-space"><div class="panel"><h1>Career match not found</h1><p>The recommendation may have been replaced by a new assessment.</p><a class="btn btn-primary" href="dashboard.php">Return to dashboard</a></div></main>';
    page_end();
    exit;
}

// Read the scoring evidence for THIS recommendation and its actual owner.
// The JSON is authoritative for preliminary alignment and data coverage.
$detailStmt = db()->prepare(
    'SELECT d.payload
       FROM recommendation_details d
       JOIN recommendation_runs rr ON rr.id=d.run_id
      WHERE d.recommendation_id=? AND rr.user_id=?
      ORDER BY d.run_id DESC
      LIMIT 1'
);
$detailStmt->execute([$id, (int)$user['id']]);
$rawEvidence = $detailStmt->fetchColumn();
$evidence = is_string($rawEvidence) ? json_decode($rawEvidence, true) : [];
$evidence = is_array($evidence) ? $evidence : [];

$fullScore = $rec['match_score'] ?? null;
$hasFullScore = is_numeric($fullScore) && (float)$fullScore >= 0 && (float)$fullScore <= 100;
$ranking = $evidence['ranking_score'] ?? null;
$hasRanking = is_numeric($ranking) && (float)$ranking >= 0 && (float)$ranking <= 100;
$coverage = $evidence['data_coverage'] ?? null;
$hasCoverage = is_numeric($coverage) && (float)$coverage >= 0 && (float)$coverage <= 1;
$coverageLabel = $hasCoverage ? round((float)$coverage * 100).'%' : 'Unavailable';

if ($hasFullScore) {
    $scoreLabel = number_format((float)$fullScore, 1).'%';
    $scoreKind = 'approximate overall match';
} elseif ($hasRanking) {
    $scoreLabel = number_format((float)$ranking, 1).'%';
    $scoreKind = 'preliminary alignment · exploratory';
} else {
    $scoreLabel = 'Exploratory';
    $scoreKind = 'not enough evidence for a percentage';
}

$factorLabels = [
    'interest' => 'RIASEC interest alignment',
    'academic' => 'Academic compatibility',
    'subject_readiness' => 'Subject prerequisites',
    'pathway' => 'Lesotho education pathways',
    'local_opportunity' => 'Lesotho labour opportunities',
];
$factorWeights = [
    'interest' => 40,
    'academic' => 25,
    'subject_readiness' => 15,
    'pathway' => 10,
    'local_opportunity' => 10,
];
$components = isset($evidence['components']) && is_array($evidence['components'])
    ? $evidence['components'] : [];
$missing = isset($evidence['missing_factors']) && is_array($evidence['missing_factors'])
    ? $evidence['missing_factors'] : [];
$missingLabels = [];
foreach ($missing as $factor) {
    if (is_string($factor)) {
        $missingLabels[] = $factorLabels[$factor] ?? $factor;
    }
}

$progs = json_decode((string)($rec['local_programmes'] ?? '[]'), true);
$progs = is_array($progs) ? $progs : [];

page_start($rec['career_title'], 'careers');
?>
<main id="main" class="wide page-space">
  <a href="dashboard.php" class="back-link">← Your matches</a>

  <section class="career-hero">
    <span class="eyebrow">YOUR CAREER MATCH</span>
    <h1><?= e($rec['career_title']) ?></h1>
    <p>Explore why this career was suggested and what it could take to get there.</p>
    <span class="big-score"><?= e($scoreLabel) ?> <small><?= e($scoreKind) ?></small></span>
    <?php if ($hasRanking && !$hasFullScore): ?>
      <p class="fine-print">This percentage compares available factors only. It is not a complete five-factor career match.</p>
    <?php endif; ?>
  </section>

  <section class="panel" aria-labelledby="scoring-evidence-heading">
    <span class="eyebrow muted-eyebrow">HOW THIS RECOMMENDATION WAS CALCULATED</span>
    <h2 id="scoring-evidence-heading">Your scoring evidence</h2>
    <p class="readable-text"><strong>Evidence coverage: <?= e($coverageLabel) ?></strong>. This describes how much of the five-factor model has usable information; it is not your probability of career success.</p>

    <?php foreach ($factorLabels as $key => $label): ?>
      <?php
        $value = $components[$key] ?? null;
        $available = is_numeric($value) && (float)$value >= 0 && (float)$value <= 100;
        $percent = $available ? max(0, min(100, (float)$value)) : 0;
        $valueLabel = $available ? number_format($percent, 1).'%' : 'Evidence unavailable';
      ?>
      <div class="interest-bar">
        <div class="bar-info">
          <span><?= e($label) ?> <small>(<?= (int)$factorWeights[$key] ?>% weight)</small></span>
          <strong><?= e($valueLabel) ?></strong>
        </div>
        <?php if ($available): ?>
          <div class="bar-track"><div class="bar-fill bar-i" style="width:<?= $percent ?>%"></div></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($missingLabels): ?>
      <p class="fine-print"><strong>Not yet supported by enough evidence:</strong> <?= e(implode(', ', $missingLabels)) ?>.</p>
    <?php endif; ?>
    <?php if (($evidence['eligibility_status'] ?? '') === 'insufficient_information'): ?>
      <p class="fine-print">Programme admission eligibility is unknown because programme-specific entry rules have not been sufficiently verified.</p>
    <?php endif; ?>
    <p class="fine-print">Preliminary alignment is calculated from available factors only. No missing factor is treated as zero or automatically awarded points. Scores are guidance, not predictions or admission decisions.</p>
  </section>

  <div class="two-column">
    <div class="side-stack">
      <section class="panel">
        <span class="eyebrow muted-eyebrow">YOUR EXPLANATION</span>
        <h2>Why this may fit you</h2>
        <p class="readable-text"><?= nl2br(e($rec['explanation'] ?? 'No explanation available.')) ?></p>
      </section>
      <section class="panel">
        <span class="eyebrow muted-eyebrow">YOUR LEARNING PATH</span>
        <h2>Useful subjects to explore</h2>
        <p class="readable-text"><?= e($rec['recommended_subjects'] ?? 'Not available') ?></p>
        <p class="fine-print">Suggested subjects are general guidance, not officially verified admission requirements.</p>
      </section>
    </div>

    <div class="side-stack">
      <section class="panel">
        <span class="eyebrow muted-eyebrow">OPTIONS CLOSE TO HOME</span>
        <h2>Possible Lesotho programmes</h2>
        <?php if ($progs): ?>
          <div class="programme-list">
            <?php foreach ($progs as $p): ?>
              <?php if (!is_array($p)) continue; ?>
              <div class="programme-item">
                <strong><?= e($p['programme'] ?? '') ?></strong>
                <span><?= e($p['university'] ?? '') ?> · <?= e($p['level'] ?? '') ?></span>
                <?php if (!empty($p['missing_subjects']) && is_array($p['missing_subjects'])): ?>
                  <small>Subjects not listed in your profile: <?= e(implode(', ', $p['missing_subjects'])) ?></small>
                <?php endif; ?>
                <small class="unverified">Always check the current prospectus and official entry requirements. The listing alone does not guarantee admission.</small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p>No matching local programmes are listed in the current reference data. Check local prospectuses or ask a careers adviser for related qualifications.</p>
        <?php endif; ?>
      </section>
      <section class="panel">
        <h3>Typical education information</h3>
        <p class="muted"><?= e($rec['education_level'] ?? 'Not available') ?></p>
        <p class="fine-print">Typical education estimates come from an international occupational dataset and may differ in Lesotho.</p>
        <a href="chat.php" class="btn btn-outline">Ask a follow-up question ↗</a>
      </section>
    </div>
  </div>
</main>
<?php page_end(); ?>
