<?php
require_once 'config/layout.php';
require_login();
$user = current_user();
$uid = (int)$user['id'];
$runId = filter_input(INPUT_GET, 'run', FILTER_VALIDATE_INT) ?: 0;
$stmt = db()->prepare('SELECT rr.id,rr.created_at,rr.method_version,a.top_interest,
                              (SELECT COUNT(*) FROM recommendation_details d WHERE d.run_id=rr.id) AS total
                       FROM recommendation_runs rr JOIN assessments a ON a.id=rr.assessment_id
                       WHERE rr.user_id=? ORDER BY rr.id DESC LIMIT 40');
$stmt->execute([$uid]);
$runs = $stmt->fetchAll();
$selected = null;
foreach ($runs as $run) {
    if ((int)$run['id'] === $runId) { $selected = $run; break; }
}
$items = [];
if ($selected) {
    $stmt = db()->prepare('SELECT r.id,r.career_title,r.match_score,r.ranking_score,
                          r.data_coverage,r.score_status,r.score_version
                          FROM recommendation_details d JOIN recommendations r ON r.id=d.recommendation_id
                          WHERE d.run_id=? AND r.user_id=?
                          ORDER BY COALESCE(r.data_coverage,0) DESC,
                            COALESCE(r.ranking_score,r.match_score,-1) DESC,r.id DESC');
    $stmt->execute([$runId,$uid]);
    $items = $stmt->fetchAll();
}
page_start('Assessment history','dashboard');
?>
<main id="main" class="wide page-space">
  <a href="dashboard.php" class="back-link">← Dashboard</a>
  <div class="page-intro"><span class="eyebrow muted-eyebrow">YOUR PROGRESS</span>
    <h1>Assessment history</h1><p>Each assessment is stored separately. Your earlier career suggestions remain available for comparison.</p></div>
  <div class="two-column">
    <section class="panel"><h2>Saved assessment runs</h2>
      <?php if (!$runs): ?><p>No recorded runs yet. <a href="assessment.php">Take your assessment</a>.</p>
      <?php else: ?>
        <div class="history-list">
        <?php foreach ($runs as $run): ?>
          <a class="match-row" href="history.php?run=<?= (int)$run['id'] ?>">
            <span class="match-title"><strong><?= e($run['created_at']) ?></strong>
            <small>RIASEC <?= e($run['top_interest']) ?> · <?= (int)$run['total'] ?> careers · <?= e($run['method_version']) ?></small></span>
            <span aria-hidden="true">↗</span>
          </a>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="panel"><h2><?= $selected ? 'Recommendations from selected run' : 'Choose an assessment' ?></h2>
      <?php if ($selected && !$items): ?><p>No careers were generated in this run.</p><?php endif; ?>
      <?php foreach ($items as $item): ?>
        <?php
        $value = is_numeric($item['match_score']) ? $item['match_score'] : $item['ranking_score'];
        $score = is_numeric($value) ? number_format((float)$value,1).'%' : 'Exploratory';
        ?>
        <a class="match-row" href="career-detail.php?id=<?= (int)$item['id'] ?>">
          <span class="match-title"><strong><?= e($item['career_title']) ?></strong>
          <small><?= e(ucfirst((string)$item['score_status'])) ?> · <?= is_numeric($item['data_coverage']) ? round((float)$item['data_coverage']).'%' : 'Unknown' ?> evidence</small></span>
          <span class="match-percent"><?= e($score) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$selected): ?><p>Choose a run on the left to see the recorded career matches.</p><?php endif; ?>
    </section>
  </div>
</main>
<?php page_end(); ?>
