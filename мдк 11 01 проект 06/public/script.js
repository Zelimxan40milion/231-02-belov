const burger = document.getElementById('burger');
const navLinks = document.getElementById('navLinks');
const topNav = document.getElementById('topNav');
const yearEl = document.getElementById('year');
const sliderTrack = document.getElementById('sliderTrack');
const sliderDots = document.getElementById('sliderDots');
const slider = document.getElementById('slider');
const sliderToggle = document.getElementById('sliderToggle');
const slides = Array.from(sliderTrack.children);

let currentIndex = 0;
let autoPlayTimer;
const autoPlayInterval = 5000;
let isAutoPlayPaused = false;

const updateYear = () => {
  yearEl.textContent = `© ${new Date().getFullYear()} ServiceX`;
};

const closeMenu = () => {
  navLinks.classList.remove('open');
  burger.classList.remove('active');
};

const handleBurger = () => {
  navLinks.classList.toggle('open');
  burger.classList.toggle('active');
};

const handleScroll = () => {
  if (window.scrollY > 10) {
    topNav.classList.add('scrolled');
  } else {
    topNav.classList.remove('scrolled');
  }
};

const smoothScroll = (event) => {
  const targetId = event.target.getAttribute('href');
  if (targetId?.startsWith('#')) {
    event.preventDefault();
    document.querySelector(targetId)?.scrollIntoView({ behavior: 'smooth' });
    closeMenu();
  }
};

const createDots = () => {
  slides.forEach((_, idx) => {
    const dot = document.createElement('button');
    if (idx === 0) dot.classList.add('active');
    dot.addEventListener('click', () => goToSlide(idx, true));
    sliderDots.appendChild(dot);
  });
};

const setActiveDot = (index) => {
  Array.from(sliderDots.children).forEach((dot, idx) => {
    dot.classList.toggle('active', idx === index);
  });
};

const goToSlide = (index, pauseAuto = false) => {
  const total = slides.length;
  currentIndex = (index + total) % total;
  const offset = -currentIndex * (100 / getSlidesPerView());
  sliderTrack.style.transform = `translateX(${offset}%)`;
  setActiveDot(currentIndex);
  if (pauseAuto && !isAutoPlayPaused) restartAutoPlay();
};

const nextSlide = () => goToSlide(currentIndex + 1);
const prevSlide = () => goToSlide(currentIndex - 1);

const getSlidesPerView = () => {
  if (window.innerWidth >= 1024) return 3;
  if (window.innerWidth >= 768) return 2;
  return 1;
};

const restartAutoPlay = () => {
  clearInterval(autoPlayTimer);
  if (isAutoPlayPaused) return;
  autoPlayTimer = setInterval(nextSlide, autoPlayInterval);
};

const pauseAutoPlay = () => clearInterval(autoPlayTimer);

const toggleAutoPlay = () => {
  isAutoPlayPaused = !isAutoPlayPaused;
  sliderToggle?.setAttribute('aria-pressed', String(isAutoPlayPaused));
  sliderToggle.textContent = isAutoPlayPaused ? '▶' : '⏸';
  if (isAutoPlayPaused) {
    pauseAutoPlay();
  } else {
    restartAutoPlay();
  }
};

const initSlider = () => {
  createDots();
  restartAutoPlay();
};

const handleResize = () => {
  goToSlide(currentIndex);
};

const initSwipe = () => {
  let startX = 0;
  let deltaX = 0;
  const resetSwipe = () => {
    startX = 0;
    deltaX = 0;
  };

  slider.addEventListener('pointerdown', (e) => {
    startX = e.clientX;
    deltaX = 0;
    slider.setPointerCapture(e.pointerId);
  });

  slider.addEventListener('pointermove', (e) => {
    if (!startX) return;
    deltaX = e.clientX - startX;
  });

  slider.addEventListener('pointerup', () => {
    if (Math.abs(deltaX) > 50) {
      if (deltaX < 0) {
        goToSlide(currentIndex + 1, true);
      } else {
        goToSlide(currentIndex - 1, true);
      }
    }
    resetSwipe();
  });

  slider.addEventListener('pointercancel', resetSwipe);
  slider.addEventListener('pointerleave', resetSwipe);
};

document.addEventListener('DOMContentLoaded', () => {
  updateYear();
  handleScroll();
  initSlider();
  initSwipe();

  burger.addEventListener('click', handleBurger);
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('resize', handleResize);
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', smoothScroll);
  });
  document.querySelector('[data-direction="next"]').addEventListener('click', () => goToSlide(currentIndex + 1, true));
  document.querySelector('[data-direction="prev"]').addEventListener('click', () => goToSlide(currentIndex - 1, true));
  if (sliderToggle) {
    sliderToggle.addEventListener('click', toggleAutoPlay);
  }

  slider.addEventListener('mouseenter', pauseAutoPlay);
  slider.addEventListener('mouseleave', () => {
    if (!isAutoPlayPaused) restartAutoPlay();
  });
});

