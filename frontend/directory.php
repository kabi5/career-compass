<?php
require_once 'config/layout.php';
require_login();
$tab = ($_GET['tab'] ?? 'programmes') === 'funding' ? 'funding' : 'programmes';
$search = mb_substr(trim((string)($_GET['q'] ?? '')),0,80);
$base = rtrim(env_value('AI_API', 'http://127.0.0.1:5001'), '/');
$path = $tab === 'programmes' ? '/programmes' : '/scholarships';
$items = []; $offline = false;
if (function_exists('curl_init')) {
    $ch = curl_init($base.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>3,
        CURLOPT_TIMEOUT=>12, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_HTTPHEADER=>['X-Internal-Token: '.env_value('INTERNAL_API_TOKEN')]]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $response = $code === 200 && is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($response)) $offline=true;
    else $items = $response[$tab === 'funding' ? 'scholarships' : 'programmes'] ?? [];
} else $offline=true;
if (!is_array($items)) $items=[];
if ($search !== '') {
    $items = array_values(array_filter($items, static function($item) use($search) {
        return is_array($item) && mb_stripos(json_encode(array_intersect_key($item,array_flip(['programme','university','level','title','provider']))),$search)!==false;
    }));
}
page_start('Programmes and funding','careers');
?>
<main id="main" class="wide page-space">
  <a href="dashboard.php" class="back-link">← Your dashboard</a>
  <div class="page-intro"><span class="eyebrow muted-eyebrow">LESOTHO STUDY ROUTES</span><h1>Programmes &amp; funding</h1>
    <p>Explore possible study routes and financial support. Information must be confirmed directly with its provider before applying.</p></div>
  <div class="tool-nav"><a class="btn <?= $tab==='programmes'?'btn-primary':'btn-outline' ?>" href="directory.php?tab=programmes">Programmes</a>
    <a class="btn <?= $tab==='funding'?'btn-primary':'btn-outline' ?>" href="directory.php?tab=funding">Funding &amp; bursaries</a></div>
  <section class="panel" style="margin-top:20px">
    <form method="get" class="career-search"><input type="hidden" name="tab" value="<?= e($tab) ?>">
      <label class="visually-hidden" for="search-dir">Search listings</label>
      <input id="search-dir" type="search" name="q" value="<?= e($search) ?>" maxlength="80" placeholder="Search programme, institution or provider">
      <button class="btn btn-primary">Search</button></form>
  </section>
  <?php if ($offline): ?><div class="alert error">The directory service is offline. Start Flask and retry.</div>
  <?php elseif (!$items): ?><div class="panel" style="margin-top:22px"><h2>No matching listings</h2><p>Try a broader search or select another tab.</p></div>
  <?php else: ?><div class="careers-grid" style="margin-top:20px">
    <?php foreach ($items as $item): if(!is_array($item)) continue;
      $name = $tab==='programmes' ? ($item['programme'] ?? '') : ($item['title'] ?? '');
      $owner = $tab==='programmes' ? ($item['university'] ?? '') : ($item['provider'] ?? '');
      $url = $item['source_url'] ?? '';
      $safe = filter_var($url, FILTER_VALIDATE_URL) && parse_url($url,PHP_URL_SCHEME)==='https';
      $verified = !empty($item['verified']) && $tab==='programmes'; ?>
      <article class="career-card"><span class="eyebrow muted-eyebrow"><?= $verified?'SOURCE-REVIEWED':'CHECK WITH PROVIDER' ?></span>
      <h2><?= e((string)$name) ?></h2><p><strong><?= e((string)$owner) ?></strong>
      <?php if($tab==='programmes' && !empty($item['level'])): ?> · <?= e($item['level']) ?><?php endif; ?></p>
      <?php if($tab==='funding'): ?><p class="small muted"><?= e((string)($item['status_note'] ?? 'Intake and eligibility unconfirmed')) ?></p><?php else: ?>
      <p class="small muted"><?= $verified?'Reviewed source reference; verify accreditation and current intake.':'Unverified sample listing. Do not treat as admission evidence.' ?></p><?php endif; ?>
      <?php if($safe): ?><a class="text-link" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">View source ↗</a><?php endif; ?></article>
    <?php endforeach; ?>
  </div><?php endif; ?>
  <p class="fine-print" style="margin-top:20px">A listed pathway does not guarantee admission, availability or funding. Only explicitly verified, current rules contribute to eligibility scoring.</p>
</main>
<?php page_end(); ?>
