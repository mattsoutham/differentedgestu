// Shared nav component — included on every page
(function () {
  const CALENDLY = 'https://calendly.com/dan_de/bootcamp-application'; // update this link

  const html = `
  <div class="announce-bar">
    <div class="announce-bar__inner">
      <span class="announce-bar__text">2025 National Sales Awards — Finalist</span>
      <img src="/images/NSA-Logo.svg" alt="National Sales Awards 2025 Finalist" class="announce-bar__logo" />
      <span class="announce-bar__divider" aria-hidden="true"></span>
      <a href="https://www.google.com/search?q=Different+Edge+Studio+Reviews" target="_blank" rel="noopener" class="announce-bar__reviews" aria-label="5 star Google reviews">
        <span class="announce-bar__stars" aria-hidden="true">★★★★★</span>
        <svg class="announce-bar__google" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
      </a>
    </div>
  </div>
  <header class="nav">
    <div class="nav__inner container">
      <a href="/" class="nav__logo">
        <img src="/images/Logo-light.svg" alt="Different Edge Studio" />
      </a>
      <button class="nav__burger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="nav__links" aria-label="Primary navigation">
        <ul>
          <li class="nav__has-dropdown">
            <button class="nav__dropdown-toggle" aria-expanded="false">
              The System
              <svg class="nav__chevron" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
            <ul class="nav__dropdown" aria-hidden="true">
              <li><a href="/sales-engine">Sales Engine</a></li>
              <li><a href="/visibility">Visibility</a></li>
              <li><a href="/showcase">Showcase</a></li>
            </ul>
          </li>
          <li><a href="/sales-calculator">Sales Calculator</a></li>
          <li><a href="/#about">About</a></li>
          <li><a href="/faq">FAQ</a></li>
          <li><a href="/blog/">Blog</a></li>
        </ul>
      </nav>
      <a href="${CALENDLY}" target="_blank" rel="noopener" class="btn btn--primary nav__cta">Book Strategy Call &rarr;</a>
    </div>
  </header>`;

  document.body.insertAdjacentHTML('afterbegin', html);

  // Services dropdown toggle
  const dropdownToggle = document.querySelector('.nav__dropdown-toggle');
  const dropdown = document.querySelector('.nav__dropdown');
  const dropdownParent = document.querySelector('.nav__has-dropdown');

  dropdownToggle?.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = dropdownParent.classList.toggle('is-open');
    dropdownToggle.setAttribute('aria-expanded', isOpen);
    dropdown.setAttribute('aria-hidden', !isOpen);
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', () => {
    dropdownParent?.classList.remove('is-open');
    dropdownToggle?.setAttribute('aria-expanded', 'false');
    dropdown?.setAttribute('aria-hidden', 'true');
  });

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
      // Close dropdown too
      dropdownParent?.classList.remove('is-open');
      dropdownToggle?.setAttribute('aria-expanded', 'false');
      dropdown?.setAttribute('aria-hidden', 'true');
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

  // Microsoft Clarity
  (function(c,l,a,r,i,t,y){
    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
  })(window, document, "clarity", "script", "x8wqptmlwc");

  // Active nav link highlight
  const path = window.location.pathname.replace(/\/$/, '') || '/';
  document.querySelectorAll('.nav__links a').forEach(a => {
    const href = a.getAttribute('href').replace(/\/$/, '') || '/';
    if (href === path || (href !== '/' && href !== '/#about' && path.startsWith(href))) {
      a.classList.add('nav__active');
    }
  });
})();
