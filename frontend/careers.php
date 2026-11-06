<?php
require_once 'config/layout.php';
require_once 'config/guidance.php';
require_login();
$search=trim((string)($_GET['q']??''));
$search=mb_substr($search,0,80);
$page=max(1,min(100,(int)($_GET['page']??1)));
// Career directory is provided by the bundled occupational knowledge base.
$api=rtrim(env_value('AI_API','http://127.0.0.1:5001'),'/');
$items=[];$total=0;$offline=false;
if (function_exists('curl_init')) {
    $url=$api.'/careers?'.http_build_query(['q'=>$search,'page'=>$page,'per_page'=>12]);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['X-Internal-Token: '.env_value('INTERNAL_API_TOKEN')],CURLOPT_FOLLOWLOCATION=>false]);
    $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $data=($code===200 && is_string($raw))?json_decode($raw,true):null;
    if (is_array($data)) { $items=$data['items']??[];$total=(int)($data['total']??0); } else $offline=true;
} else $offline=true;
page_start('Explore careers','careers');
?>
<main id="main" class="wide page-space"><div class="page-intro"><span class="eyebrow muted-eyebrow">THE WORLD IS FULL OF OPTIONS</span><h1>Explore different career paths <span aria-hidden="true">↗</span></h1><p>Browse a wide range of occupations. Start with something that interests you and follow your curiosity.</p></div><section class="panel"><form action="careers.php" method="get" class="career-search"><label for="career-q" class="visually-hidden">Search careers</label><input id="career-q" type="search" name="q" maxlength="80" value="<?= e($search) ?>" placeholder="Try 'engineer', 'nurse', 'designer'..."><button class="btn btn-primary" type="submit">Search ↗</button></form><p class="fine-print">Occupational titles and interest codes are reference data and may not represent current vacancies or qualifications in Lesotho.</p></section>
<?php if ($offline): ?><div class="alert error">The career directory is unavailable. Start the Python recommendation service and refresh.</div><?php elseif (!$items): ?><div class="panel empty-state"><h2>No careers found</h2><p>Try a more general search term.</p><a href="careers.php" class="text-link">See all careers ↗</a></div><?php else: ?><div class="career-count"><?= number_format($total) ?> careers<?= $search!=='' ? ' matching “'.e($search).'”':'' ?></div><div class="careers-grid"><?php foreach ($items as $item): ?><article class="career-card"><span class="career-card-icon">✧</span><span class="eyebrow muted-eyebrow"><?= e($item['riasec_code']?:'CAREER') ?> INTEREST CODE</span><h2><?= e($item['title']) ?></h2><p>Learn more about this occupation by asking the career assistant, or take the assessment to find your personal match.</p><a class="text-link" href="compare.php?career=<?= e(urlencode($item['title'])) ?>">Explore and compare ↗</a><?= saved_button('career',$item['title'],$item['title']) ?></article><?php endforeach; ?></div><div class="pagination"><?php if ($page>1): ?><a href="?<?= e(http_build_query(['q'=>$search,'page'=>$page-1])) ?>" class="btn btn-outline">← Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= max(1,(int)ceil($total/12)) ?></span><?php if ($page*12<$total): ?><a href="?<?= e(http_build_query(['q'=>$search,'page'=>$page+1])) ?>" class="btn btn-outline">Next →</a><?php endif; ?></div><?php endif; ?><?php $supplements=array_filter(catalogue('career_catalogue'),static fn($c)=>($c['verification_status']??'')==='verified'&&($search===''||mb_stripos($c['title'],$search)!==false));if($supplements):?><section class="panel"><h2>Reviewed career references</h2><div class="careers-grid"><?php foreach($supplements as $c):?><article><h3><?= e($c['title']) ?></h3><p><?= e($c['description']??'') ?></p><a href="career-reference.php?id=<?= e(urlencode($c['id'])) ?>">Read source-reviewed information ↗</a></article><?php endforeach;?></div></section><?php endif;?></main>
<?php page_end(); ?>


