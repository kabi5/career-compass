<?php
require_once 'config/layout.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
page_start('Find a future that fits you');
?>
<main id="main">
<section class="landing-hero">
  <div class="wide hero-grid">
    <div class="hero-copy">
      <span class="eyebrow"><span class="eyebrow-dot"></span> YOUR FUTURE STARTS HERE</span>
      <h1>Big dreams.<br><em>Clear direction.</em></h1>
      <p class="hero-lede">Your future isn't one-size-fits-all. Discover careers that connect with your interests, school subjects and goals — with guidance made for learners in Lesotho.</p>
      <div class="hero-buttons"><a href="register.php" class="btn btn-primary btn-lg">Discover my path <span aria-hidden="true">↗</span></a><a href="#how-it-works" class="btn btn-outline btn-lg">See how it works ↓</a></div>
      <div class="hero-proof"><span class="proof-icon">✓</span><span>Personalised exploration</span><span class="proof-sep">·</span><span>Free to get started</span></div>
    </div>
    <div class="hero-art" aria-label="Illustration of different pathways connecting with career interests" role="img">
      <div class="orb orb-one"></div><div class="orb orb-two"></div>
      <div class="art-stars">✳</div>
      <div class="art-main-card">
        <span class="art-card-label">YOUR POSSIBILITIES</span>
        <span class="art-card-title">You can be more<br>than one thing.</span>
        <div class="art-career"><span class="art-career-emoji">💻</span><span>Technology & innovation</span><span>↗</span></div>
        <div class="art-career"><span class="art-career-emoji">⚕</span><span>Health & wellbeing</span><span>↗</span></div>
        <div class="art-career"><span class="art-career-emoji">✎</span><span>Design & creativity</span><span>↗</span></div>
      </div>
      <div class="floating-note note-left"><span class="note-icon">✦</span><span><strong>Explore your interests</strong><small>Real interests, real choices</small></span></div>
      <div class="floating-note note-right"><span class="note-icon note-coral">◎</span><span><strong>Find your fit</strong><small>Personalised matches</small></span></div>
      <span class="art-squiggle">〰</span>
    </div>
  </div>
</section>
<section class="intro-strip"><div class="wide strip-grid"><span><strong>01</strong> Understand yourself</span><span><strong>02</strong> Explore possibilities</span><span><strong>03</strong> Make a plan</span></div></section>
<section id="how-it-works" class="section wide">
  <div class="section-heading"><span class="eyebrow muted-eyebrow">A SIMPLE WAY FORWARD</span><h2>Find your direction in <em>three steps.</em></h2><p>You don't need to know exactly what you want to be. This is a place to start.</p></div>
  <div class="three-grid">
    <article class="feature-card"><div class="feature-icon mint">✎</div><span class="step">STEP 01</span><h3>Tell us about you</h3><p>Share your interests, favourite subjects and the things you enjoy doing.</p></article>
    <article class="feature-card"><div class="feature-icon peach">◎</div><span class="step">STEP 02</span><h3>Discover career matches</h3><p>Explore ranked career ideas and understand why each could suit you.</p></article>
    <article class="feature-card"><div class="feature-icon lavender">↗</div><span class="step">STEP 03</span><h3>See your next steps</h3><p>Learn which subjects may help and explore possible education pathways.</p></article>
  </div>
</section>
<section class="section section-soft" id="features"><div class="wide split-feature"><div><span class="eyebrow muted-eyebrow">MORE THAN A CAREER QUIZ</span><h2>Start exploring.<br><em>Keep growing.</em></h2><p>Career Compass brings together your RIASEC interest profile, subject information, career exploration and an optional career assistant in one place.</p><a href="register.php" class="text-link">Create your free account <span>↗</span></a></div><div class="benefit-list"><div><span>✓</span><div><strong>Understand your strengths</strong><p>See your six RIASEC interest dimensions, clearly explained.</p></div></div><div><span>✓</span><div><strong>Recommendations with reasons</strong><p>Know what influenced a match, rather than seeing a mystery score.</p></div></div><div><span>✓</span><div><strong>Relevant to Lesotho</strong><p>Explore local pathways where information is available; confirm admissions with each institution.</p></div></div><div><span>✓</span><div><strong>Your choices stay yours</strong><p>Use recommendations as a starting point and discuss options with teachers or counsellors.</p></div></div></div></div></section>
<section class="final-cta wide"><span class="eyebrow">ONE SMALL STEP, MANY POSSIBILITIES</span><h2>Your future is worth exploring.</h2><p>Take the first step towards a career that feels right for you.</p><a href="register.php" class="btn btn-light btn-lg">Let's get started ↗</a></section>
</main>
<?php page_end(); ?>
