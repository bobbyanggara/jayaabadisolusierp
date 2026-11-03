/* JASPEST · Jaya Abadi Solusi — animasi halaman utama.
   Dirancang agar aman: bila terjadi masalah, semua konten langsung ditampilkan. */
(function () {
  'use strict';
  var root = document.documentElement;

  function showAll() {
    var els = document.querySelectorAll('.reveal, .stagger');
    for (var i = 0; i < els.length; i++) els[i].classList.add('is-visible');
  }

  try {
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hasIO = 'IntersectionObserver' in window;

    /* ---------- 1. Elemen yang muncul saat di-scroll ---------- */
    var revealTargets = [
      ['.section-heading', ''],
      ['.gallery-toolbar', ''],
      ['.about-visual', 'from-left'],
      ['.about-copy', 'from-right'],
      ['.video-copy', 'from-left'],
      ['.video-host', 'zoom'],
      ['.faq-layout > div:first-child', 'from-left'],
      ['.contact-copy', 'from-left'],
      ['.form-card', 'from-right'],
      ['.gallery-disclaimer', '']
    ];
    var staggerTargets = ['.assurance-inner', '.services-grid', '.directory-grid', '.gallery-grid', '.articles-grid', '.faq-list'];

    var observed = [];
    revealTargets.forEach(function (pair) {
      document.querySelectorAll(pair[0]).forEach(function (el) {
        el.classList.add('reveal');
        if (pair[1]) el.classList.add(pair[1]);
        observed.push(el);
      });
    });
    staggerTargets.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        el.classList.add('stagger');
        observed.push(el);
      });
    });

    if (reduceMotion || !hasIO) {
      showAll();
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0, rootMargin: '0px 0px -8% 0px' });
      observed.forEach(function (el) { io.observe(el); });
    }

    /* ---------- 2. Penanda menu aktif (scrollspy) ---------- */
    if (hasIO) {
      var links = {};
      document.querySelectorAll('.desktop-nav a[href^="#"]').forEach(function (a) {
        links[a.getAttribute('href').slice(1)] = a;
      });
      var ids = Object.keys(links);
      if (ids.length) {
        var clearActive = function () {
          ids.forEach(function (id) { links[id].classList.remove('is-active'); });
        };
        var spy = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            clearActive();
            var link = links[entry.target.id];
            if (link) link.classList.add('is-active');
          });
        }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });
        /* amati semua bagian halaman: bagian tanpa menu akan menghapus penanda */
        document.querySelectorAll('main > section, main > .section').forEach(function (section) {
          spy.observe(section);
        });
        window.addEventListener('scroll', function () {
          if (window.scrollY < 200) clearActive();
        }, { passive: true });
      }
    }
  } catch (err) {
    /* Jika ada kendala, tampilkan semua konten apa adanya. */
    showAll();
    if (window.console && console.warn) console.warn('Animasi dinonaktifkan:', err);
  }
})();
