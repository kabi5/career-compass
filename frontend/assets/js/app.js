(() => {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // 1. Mobile Navigation Drawer with Smooth State
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('.nav-links');
  toggle?.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.innerHTML = open ? '&times;' : '&#9776;';
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && nav?.classList.contains('open')) {
      nav.classList.remove('open');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '&#9776;';
      }
    }
  });

  // 2. Progressive Page Transitions
  if (!prefersReducedMotion) {
    document.addEventListener('click', e => {
      const link = e.target.closest('a[href]');
      if (!link) return;
      const href = link.getAttribute('href');
      if (
        !href ||
        href.startsWith('#') ||
        href.startsWith('javascript:') ||
        href.includes('report.php') ||
        href.includes('export=') ||
        link.target === '_blank' ||
        e.metaKey || e.ctrlKey || e.shiftKey || e.altKey
      ) {
        return;
      }
      const targetUrl = new URL(link.href, window.location.href);
      if (targetUrl.origin === window.location.origin) {
        document.body.classList.add('cc-page-leaving');
      }
    });

    window.addEventListener('pageshow', () => {
      document.body.classList.remove('cc-page-leaving');
    });
  }

  // 3. Staggered Scroll-Triggered Reveal Animations
  const revealSelectors = [
    '.feature-card',
    '.career-card',
    '.directory-card',
    '.next-card',
    '.panel',
    '.question-card',
    '.match-row',
    '.stat-card',
    '.benefit-list > div'
  ];
  const revealElements = document.querySelectorAll(revealSelectors.join(','));

  if (!prefersReducedMotion && 'IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

    revealElements.forEach((el, index) => {
      el.classList.add('cc-reveal');
      // Group stagger delay by sibling index (caps at 350ms so there's never a long delay)
      const siblingIndex = Array.from(el.parentElement?.children || []).indexOf(el);
      const delay = Math.min(Math.max(0, siblingIndex) * 55, 330);
      el.style.setProperty('--stagger-delay', `${delay}ms`);
      revealObserver.observe(el);
    });
  } else {
    revealElements.forEach(el => el.classList.add('is-visible'));
  }

  // 4. Animated Number Counters & Progress Bars (Dashboard, Admin Stats & Career Detail)
  const animateNumber = (el, targetValue, suffix = '') => {
    if (prefersReducedMotion || isNaN(targetValue)) {
      el.textContent = `${Math.round(targetValue)}${suffix}`;
      return;
    }
    const duration = 950;
    const startTime = performance.now();
    const step = currentTime => {
      const progress = Math.min((currentTime - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // Cubic ease-out
      el.textContent = `${Math.round(targetValue * eased)}${suffix}`;
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  // Animate RIASEC chart bars and percentage labels
  document.querySelectorAll('.interest-bar').forEach(bar => {
    const fill = bar.querySelector('.bar-fill');
    const strong = bar.querySelector('.bar-info strong');
    if (fill && strong) {
      const rawPercent = parseFloat(strong.textContent.replace('%', '').trim());
      if (!isNaN(rawPercent)) {
        if (!prefersReducedMotion) {
          fill.style.width = '0%';
          requestAnimationFrame(() => {
            setTimeout(() => { fill.style.width = `${rawPercent}%`; }, 90);
          });
        }
        animateNumber(strong, rawPercent, '%');
      }
    }
  });

  // Animate match badges, big hero score, and admin stat cards
  document.querySelectorAll('.match-percent, .stat-card strong').forEach(el => {
    const rawText = el.textContent.trim();
    const isPercent = rawText.endsWith('%');
    const num = parseFloat(rawText.replace(/[^0-9.]/g, ''));
    if (!isNaN(num)) animateNumber(el, num, isPercent ? '%' : '');
  });

  // 5. Interactive Career Assessment Experience
  const assessmentForm = document.querySelector('[data-assessment-form]');
  if (assessmentForm) {
    const cards = Array.from(assessmentForm.querySelectorAll('.question-card'));
    const textEl = document.querySelector('[data-progress-text]');
    const barEl = document.querySelector('[data-progress-bar]');
    let lastPct = 0;

    const updateAssessmentProgress = (changedCard = null) => {
      const total = cards.length;
      let answered = 0;
      cards.forEach(card => {
        const checked = card.querySelector('input:checked');
        card.classList.toggle('is-answered', Boolean(checked));
        if (checked) answered++;
      });

      const pct = total ? Math.round((answered / total) * 100) : 0;
      if (textEl) {
        textEl.textContent = `${answered} of ${total} answered (${pct}%)`;
      }
      if (barEl) {
        barEl.style.width = `${pct}%`;
      }
      lastPct = pct;

      // Subtle highlight & gentle guide to next unanswered question
      if (changedCard && !prefersReducedMotion) {
        cards.forEach(c => c.classList.remove('is-active-focus'));
        const currentIndex = cards.indexOf(changedCard);
        const nextUnanswered = cards.slice(currentIndex + 1).find(c => !c.querySelector('input:checked'));
        if (nextUnanswered) {
          nextUnanswered.classList.add('is-active-focus');
          const rect = nextUnanswered.getBoundingClientRect();
          if (rect.top > window.innerHeight * 0.72 || rect.bottom < 120) {
            setTimeout(() => {
              nextUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 160);
          }
        }
      }
    };

    assessmentForm.addEventListener('change', e => {
      const card = e.target.closest('.question-card');
      updateAssessmentProgress(card);
    });
    updateAssessmentProgress();
  }

  // 6. Reusable Processing Overlay & Form Loading Spinners (with Fail-Safe Reset)
  const createProcessingOverlay = () => {
    let overlay = document.querySelector('.cc-processing-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'cc-processing-overlay';
      overlay.setAttribute('role', 'status');
      overlay.setAttribute('aria-live', 'polite');
      overlay.innerHTML = `
        <div class="cc-processing-card">
          <div class="cc-compass-loader">
            <svg viewBox="0 0 64 64" fill="none">
              <circle cx="32" cy="32" r="28" stroke="#d6efe4" stroke-width="5"/>
              <path d="M42 22L36 36L22 42L28 28L42 22Z" fill="#2b776a"/>
              <circle cx="32" cy="32" r="3.5" fill="#103f3c"/>
            </svg>
          </div>
          <h3 data-overlay-title style="margin-bottom:8px;">Working on your request...</h3>
          <p class="small muted" data-overlay-subtitle style="margin:0;">Please wait a moment.</p>
        </div>
      `;
      document.body.appendChild(overlay);
    }
    return overlay;
  };

  const spinnerSvg = `<svg viewBox="0 0 24 24" fill="none" class="cc-spinner" aria-hidden="true"><circle class="cc-spinner-track" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5"/><path class="cc-spinner-head" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>`;

  document.querySelectorAll('form').forEach(form => {
    if (form.id === 'chat-form') return; // Handled by chat controller

    form.addEventListener('submit', e => {
      if (!form.checkValidity()) return;

      const button = form.querySelector('button[type="submit"], button:not([type])');
      const action = (form.getAttribute('action') || window.location.pathname).toLowerCase();

      // Context-specific loading label
      let loadingLabel = 'Processing...';
      let showOverlay = false;
      let overlayTitle = 'Processing your request...';
      let overlaySub = 'Hang tight while we update your career guidance.';

      if (form.hasAttribute('data-assessment-form') || action.includes('assessment.php')) {
        loadingLabel = 'Calculating RIASEC matches...';
        showOverlay = true;
        overlayTitle = 'Analysing your interests...';
        overlaySub = 'Comparing your 24 responses with career pathways and academic preparation.';
      } else if (action.includes('recalculate.php')) {
        loadingLabel = 'Recalculating matches...';
        showOverlay = true;
        overlayTitle = 'Refreshing your career matches...';
        overlaySub = 'Applying your updated academic profile to your latest assessment.';
      } else if (action.includes('login.php')) {
        loadingLabel = 'Signing you in...';
      } else if (action.includes('register.php')) {
        loadingLabel = 'Creating your account...';
      } else if (action.includes('profile.php')) {
        loadingLabel = 'Saving profile...';
      } else if (action.includes('compare.php')) {
        loadingLabel = 'Comparing careers...';
      } else if (action.includes('eligibility.php') || action.includes('simulate.php')) {
        loadingLabel = 'Checking requirements...';
      } else if (form.method.toLowerCase() === 'get') {
        loadingLabel = 'Searching...';
      }

      if (button && !button.disabled) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = `${spinnerSvg}<span>${loadingLabel}</span>`;
      }

      if (showOverlay) {
        const overlay = createProcessingOverlay();
        overlay.querySelector('[data-overlay-title]').textContent = overlayTitle;
        overlay.querySelector('[data-overlay-subtitle]').textContent = overlaySub;
        requestAnimationFrame(() => overlay.classList.add('is-active'));
      }

      // Fail-safe reset after 25s so UI can never stay permanently locked if network drops
      setTimeout(() => {
        if (button && button.disabled) {
          button.disabled = false;
          button.removeAttribute('aria-busy');
          if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        }
        document.querySelector('.cc-processing-overlay')?.classList.remove('is-active');
      }, 25000);
    });
  });

  // 7. Auto-Toast Feedback for Completed Actions
  const showToast = message => {
    let container = document.querySelector('.cc-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'cc-toast-container';
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'cc-toast';
    toast.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="#a8d8bb" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="ui-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span></span>
    `;
    toast.querySelector('span').textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(8px)';
      setTimeout(() => toast.remove(), 260);
    }, 4200);
  };

  const params = new URLSearchParams(window.location.search);
  if (params.get('completed') === '1') showToast('Assessment complete! Here are your personalized matches.');
  if (params.get('refreshed') === '1') showToast('Recommendations recalculated with your latest profile.');

  // Register Service Worker
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }
})();