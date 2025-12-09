(function () {
  const navLinks = document.querySelectorAll('.nav-link');
  const sections = Array.from(navLinks).map((link) =>
    document.querySelector(link.getAttribute('href'))
  );

  function setActiveLink() {
    const fromTop = window.scrollY + 120;
    navLinks.forEach((link, idx) => {
      const section = sections[idx];
      if (!section) return;
      const offset = section.offsetTop;
      const height = section.offsetHeight;
      const active = fromTop >= offset && fromTop < offset + height;
      link.classList.toggle('active', active);
    });
  }

  function smoothNav() {
    navLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  function fadeInOnScroll() {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
          }
        });
      },
      { threshold: 0.2 }
    );

    document.querySelectorAll('.section, .card').forEach((el) => {
      el.classList.add('fade-in');
      observer.observe(el);
    });
  }

  function maskPhone() {
    const inputs = document.querySelectorAll('[data-phone-mask]');
    inputs.forEach((input) => {
      const format = (value) => {
        const digits = value.replace(/\D/g, '');
        let cleaned = digits;
        if (cleaned.startsWith('7') || cleaned.startsWith('8')) {
          cleaned = cleaned.slice(1);
        }
        cleaned = cleaned.slice(0, 10);
        const parts = [
          cleaned.slice(0, 3),
          cleaned.slice(3, 6),
          cleaned.slice(6, 8),
          cleaned.slice(8, 10)
        ];
        let result = '+7';
        if (parts[0]) result += '-' + parts[0];
        if (parts[1]) result += '-' + parts[1];
        if (parts[2]) result += '-' + parts[2];
        if (parts[3]) result += '-' + parts[3];
        return result;
      };

      input.addEventListener('input', () => {
        input.value = format(input.value);
      });

      input.addEventListener('blur', () => {
        if (input.value.replace(/\D/g, '').length === 10) {
          input.value = format(input.value);
        }
      });
    });
  }

  function blockRussianInPassword() {
    const inputs = document.querySelectorAll('[data-password-block-ru]');
    inputs.forEach((input) => {
      const hasRu = (text) => /[А-Яа-яЁё]/.test(text);

      input.addEventListener('beforeinput', (e) => {
        if (hasRu(e.data || '')) {
          e.preventDefault();
        }
      });

      input.addEventListener('input', () => {
        if (hasRu(input.value)) {
          input.value = input.value.replace(/[А-Яа-яЁё]/g, '');
        }
      });

      input.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        const sanitized = text.replace(/[А-Яа-яЁё]/g, '');
        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const value = input.value;
        input.value = value.slice(0, start) + sanitized + value.slice(end);
        input.setSelectionRange(start + sanitized.length, start + sanitized.length);
      });
    });
  }

  function togglePasswordOnHold() {
    const toggles = document.querySelectorAll('[data-password-toggle]');
    toggles.forEach((btn) => {
      const input = btn.previousElementSibling;
      if (!input) return;
      const show = () => {
        input.type = 'text';
      };
      const hide = () => {
        input.type = 'password';
      };
      btn.addEventListener('mousedown', (e) => {
        e.preventDefault();
        show();
      });
      btn.addEventListener('mouseup', hide);
      btn.addEventListener('mouseleave', hide);
      btn.addEventListener('touchstart', (e) => {
        e.preventDefault();
        show();
      });
      btn.addEventListener('touchend', hide);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    smoothNav();
    setActiveLink();
    fadeInOnScroll();
    maskPhone();
    blockRussianInPassword();
    togglePasswordOnHold();
  });

  window.addEventListener('scroll', setActiveLink);
})();






