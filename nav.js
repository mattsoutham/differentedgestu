// Shared nav component — included on every page
(function () {
  const CALENDLY = 'https://calendly.com/differentedgestudio'; // update this link

  const html = `
  <header class="nav">
    <div class="nav__inner container">
      <a href="/" class="nav__logo">
        <img src="/images/logo.svg" alt="Different Edge Studio" />
      </a>
      <button class="nav__burger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="nav__links" aria-label="Primary navigation">
        <ul>
          <li><a href="/the-system">The System</a></li>
          <li><a href="/#about">About</a></li>
          <li><a href="/faq">FAQ</a></li>
          <li><a href="/blog">Blog</a></li>
        </ul>
      </nav>
      <a href="${CALENDLY}" target="_blank" rel="noopener" class="btn btn--primary nav__cta">Book Strategy Call &rarr;</a>
    </div>
  </header>`;

  document.body.insertAdjacentHTML('afterbegin', html);

  // Mobile burger toggle
  const burger = document.querySelector('.nav__burger');
  const navLinks = document.querySelector('.nav__links');

  burger?.addEventListener('click', () => {
    const isOpen = burger.classList.toggle('is-open');
    burger.setAttribute('aria-expanded', isOpen);
    navLinks?.classList.toggle('is-open', isOpen);
  });

  navLinks?.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      burger?.classList.remove('is-open');
      navLinks?.classList.remove('is-open');
      burger?.setAttribute('aria-expanded', 'false');
    });
  });

  // Scroll-reveal
  setTimeout(() => {
    const revealEls = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
      revealEls.forEach(el => observer.observe(el));
    } else {
      revealEls.forEach(el => el.classList.add('is-visible'));
    }
  }, 50);

  // Sticky nav
  const nav = document.querySelector('.nav');
  window.addEventListener('scroll', () => {
    nav?.classList.toggle('nav--scrolled', window.scrollY > 20);
  }, { passive: true });

  // Active nav link highlight
  const path = window.location.pathname.replace(/\/$/, '') || '/';
  document.querySelectorAll('.nav__links a').forEach(a => {
    const href = a.getAttribute('href').replace(/\/$/, '') || '/';
    if (href === path || (href !== '/' && path.startsWith(href))) {
      a.classList.add('nav__active');
    }
  });
})();
