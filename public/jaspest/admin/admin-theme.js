/* JASPEST admin — pengalih mode terang/gelap. Pilihan disimpan di localStorage; default mengikuti sistem. */
(function () {
  var KEY = 'jaspest-admin-theme';
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function saved() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function current() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      var lightColor = meta.getAttribute('data-light-theme-color') || '#f5f7f9';
      var darkColor = meta.getAttribute('data-dark-theme-color') || '#101a24';
      meta.setAttribute('content', theme === 'dark' ? darkColor : lightColor);
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      var dark = theme === 'dark';
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
      btn.setAttribute('aria-label', dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap');
      var label = btn.querySelector('.theme-label');
      if (label) label.textContent = dark ? 'Mode terang' : 'Mode gelap';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    apply(current());
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = current() === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(KEY, next); } catch (e) {}
        apply(next);
      });
    });
  });

  // Ikuti perubahan tema sistem selama admin belum memilih manual.
  if (media && media.addEventListener) {
    media.addEventListener('change', function (event) { if (!saved()) apply(event.matches ? 'dark' : 'light'); });
  }
})();
