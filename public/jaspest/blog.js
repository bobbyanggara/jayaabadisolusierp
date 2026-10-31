(() => {
  'use strict';
  const $ = (selector) => document.querySelector(selector);
  const menu = $('#menu-toggle');
  const nav = $('#mobile-nav');
  const dialog = $('#content-dialog');
  const close = () => { dialog.close(); document.body.classList.remove('modal-open'); };
  menu.addEventListener('click', () => {
    const open = nav.hidden;
    nav.hidden = !open;
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
  });
  $('#dialog-close').addEventListener('click', close);
  dialog.addEventListener('close', () => document.body.classList.remove('modal-open'));
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-portal]')) {
      const configured = window.JASPEST_CONFIG?.portalUrl;
      let portal;
      try { portal = new URL(configured, location.origin); } catch {}
      if (configured && portal && ['http:', 'https:'].includes(portal.protocol)) { location.assign(portal.href); return; }
      $('#dialog-content').innerHTML = '<div class="dialog-body"><div class="eyebrow">PORTAL KLIEN JASPEST</div><h2 id="dialog-title">Portal klien sedang dipersiapkan.</h2><p>Untuk informasi kebutuhan atau laporan pekerjaan Anda, silakan hubungi tim JASPEST melalui formulir konsultasi.</p><a class="button button-blue button-small" href="/#konsultasi">Hubungi tim JASPEST</a></div>';
      dialog.showModal(); document.body.classList.add('modal-open');
    }
    if (event.target.closest('[data-privacy]')) {
      $('#dialog-content').innerHTML = '<div class="dialog-body"><div class="eyebrow">INFORMASI PENGGUNAAN DATA</div><h2 id="dialog-title">Privasi pengajuan Anda.</h2><p>Informasi pengajuan digunakan untuk memahami kebutuhan, menghubungi Anda, dan menindaklanjuti konsultasi. Foto temuan dan informasi pengajuan tidak otomatis dipublikasikan.</p>'+(window.JASPEST_CONFIG?.previewMode !== false ? '<p>Pada pratinjau saat ini, formulir hanya memvalidasi dan menampilkan ringkasan. Data belum dikirim ke tim JASPEST.</p>' : '<p>Anda dapat menyampaikan koreksi informasi atau meminta penghentian tindak lanjut kepada tim JASPEST saat dihubungi.</p>')+'<a class="text-link blue-text" href="/#konsultasi">Lihat formulir konsultasi</a></div>';
      dialog.showModal(); document.body.classList.add('modal-open');
    }
  });
  window.addEventListener('scroll', () => $('#site-header').classList.toggle('scrolled', scrollY > 20), { passive: true });
  $('#year').textContent = String(new Date().getFullYear());
})();
