document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('[data-header]');
  const nav = document.querySelector('[data-nav]');
  const burger = document.querySelector('[data-burger]');
  const navLinks = document.querySelectorAll('.nav__link');
  const yearEl = document.querySelector('[data-year]');
  const form = document.querySelector('[data-contact-form]');
  const formStatus = document.querySelector('[data-form-status]');

  // Dynamic year in footer
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  // Header style on scroll
  const handleScroll = () => {
    if (!header) return;
    header.classList.toggle('scrolled', window.scrollY > 12);
  };
  handleScroll();
  window.addEventListener('scroll', handleScroll, { passive: true });

  // Mobile burger toggle
  const closeNav = () => {
    nav?.classList.remove('is-open');
    burger?.classList.remove('is-open');
    burger?.setAttribute('aria-expanded', 'false');
  };

  burger?.addEventListener('click', () => {
    nav?.classList.toggle('is-open');
    const isOpen = nav?.classList.contains('is-open');
    burger.classList.toggle('is-open', isOpen);
    burger.setAttribute('aria-expanded', String(!!isOpen));
  });

  // Smooth scroll for anchors
  const smoothScroll = (event, targetId) => {
    const target = document.querySelector(targetId);
    if (target) {
      event.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
      closeNav();
    }
  };

  navLinks.forEach((link) =>
    link.addEventListener('click', (e) => smoothScroll(e, link.getAttribute('href')))
  );

  // Slider setup
  const sliderEl = document.querySelector('[data-slider]');
  if (sliderEl) {
    initSlider(sliderEl);
  }

  // Contact form (demo)
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      formStatus.textContent = 'Отправляем…';

      try {
        const res = await fetch('/api/contact', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json();
        formStatus.textContent = data.message || (data.success ? 'Отправлено' : 'Ошибка');
        formStatus.style.color = data.success ? '#7dd1ff' : '#ff8a8a';
        if (data.success) form.reset();
      } catch (err) {
        console.error(err);
        formStatus.textContent = 'Не удалось отправить. Попробуйте позже.';
        formStatus.style.color = '#ff8a8a';
      }
    });
  }
});

/**
 * Слайдер с кнопками, индикаторами, автопрокруткой и свайпами.
 */
function initSlider(sliderEl) {
  const viewport = sliderEl.querySelector('[data-slider-viewport]');
  const slides = Array.from(sliderEl.querySelectorAll('.slide'));
  const dotsContainer = sliderEl.querySelector('[data-dots]');
  const prevBtn = sliderEl.querySelector('[data-prev]');
  const nextBtn = sliderEl.querySelector('[data-next]');
  const toggleAutoplayBtn = sliderEl.querySelector('[data-toggle-autoplay]');

  let active = 0;
  let autoplayId;
  let autoplayEnabled = true;
  let startX = 0;
  let deltaX = 0;

  const buildDots = () => {
    slides.forEach((_, idx) => {
      const dot = document.createElement('button');
      dot.className = 'slider__dot';
      dot.setAttribute('aria-label', `Слайд ${idx + 1}`);
      dot.addEventListener('click', () => goTo(idx));
      dotsContainer?.appendChild(dot);
    });
  };

  const updateDots = () => {
    dotsContainer?.querySelectorAll('.slider__dot').forEach((dot, idx) => {
      dot.classList.toggle('is-active', idx === active);
    });
  };

  const updateSlides = () => {
    slides.forEach((slide, idx) => {
      slide.classList.toggle('is-active', idx === active);
    });
    updateDots();
  };

  const goTo = (idx) => {
    active = (idx + slides.length) % slides.length;
    updateSlides();
  };

  const next = () => goTo(active + 1);
  const prev = () => goTo(active - 1);

  const stopAutoplay = () => {
    autoplayEnabled = false;
    clearInterval(autoplayId);
    if (toggleAutoplayBtn) {
      toggleAutoplayBtn.textContent = 'Запустить автопрокрутку';
      toggleAutoplayBtn.setAttribute('aria-pressed', 'false');
    }
  };

  const startAutoplay = () => {
    clearInterval(autoplayId);
    autoplayEnabled = true;
    autoplayId = setInterval(next, 5000);
    if (toggleAutoplayBtn) {
      toggleAutoplayBtn.textContent = 'Пауза автопрокрутки';
      toggleAutoplayBtn.setAttribute('aria-pressed', 'true');
    }
  };

  prevBtn?.addEventListener('click', prev);
  nextBtn?.addEventListener('click', next);

  toggleAutoplayBtn?.addEventListener('click', () => {
    autoplayEnabled ? stopAutoplay() : startAutoplay();
  });

  sliderEl.addEventListener('mouseenter', () => autoplayEnabled && clearInterval(autoplayId));
  sliderEl.addEventListener('mouseleave', () => autoplayEnabled && startAutoplay());

  // Touch/drag support
  const onPointerDown = (e) => {
    startX = e.clientX || e.touches?.[0]?.clientX || 0;
    deltaX = 0;
    viewport?.addEventListener('pointermove', onPointerMove);
    viewport?.addEventListener('pointerup', onPointerUp);
    viewport?.addEventListener('pointercancel', onPointerUp);
    viewport?.addEventListener('touchmove', onPointerMove);
    viewport?.addEventListener('touchend', onPointerUp);
  };

  const onPointerMove = (e) => {
    const currentX = e.clientX || e.touches?.[0]?.clientX || 0;
    deltaX = currentX - startX;
  };

  const onPointerUp = () => {
    viewport?.removeEventListener('pointermove', onPointerMove);
    viewport?.removeEventListener('pointerup', onPointerUp);
    viewport?.removeEventListener('pointercancel', onPointerUp);
    viewport?.removeEventListener('touchmove', onPointerMove);
    viewport?.removeEventListener('touchend', onPointerUp);

    if (Math.abs(deltaX) > 40) {
      deltaX > 0 ? prev() : next();
    }
  };

  viewport?.addEventListener('pointerdown', onPointerDown);
  viewport?.addEventListener('touchstart', onPointerDown);

  buildDots();
  updateSlides();
  startAutoplay();
}
