(() => {
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('.nav-links');
  toggle?.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? '✕' : '☰';
  });
  const form = document.querySelector('[data-assessment-form]');
  if (form) {
    const update = () => {
      const total = form.querySelectorAll('.question-card').length;
      const answered = form.querySelectorAll('.question-card:has(input:checked)').length;
      const text = document.querySelector('[data-progress-text]');
      const bar = document.querySelector('[data-progress-bar]');
      if (text) text.textContent = `${answered} of ${total} answered`;
      if (bar) bar.style.width = (total ? answered / total * 100 : 0) + '%';
    };
    form.addEventListener('change', update);
    update();
  }
})();
