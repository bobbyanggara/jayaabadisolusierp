/* JASPEST — pemberitahuan & pengaturan cookie (versi 2).
   Esensial selalu aktif. Konten pihak ketiga (video TikTok) hanya dimuat setelah diizinkan.
   Pilihan disimpan di cookie pihak pertama "jaspest_consent" selama 180 hari.
   API: window.JaspestConsent.{allowed(kategori), set(kategori, nilai), acceptAll(), rejectAll(), open()}
   Event: "jaspest:consent" pada document setiap pilihan berubah. */
(() => {
  'use strict';
  const NAME = 'jaspest_consent', VERSION = 2, MAX_AGE = 60 * 60 * 24 * 180;
  const CATS = [
    { id: 'necessary', locked: true, title: 'Cookie esensial',
      desc: 'Diperlukan agar situs dan formulir konsultasi berfungsi dengan aman. Tidak dipakai untuk iklan atau pelacakan.',
      items: ['<code>*_session</code> menjaga sesi dan keamanan formulir (hingga 2 jam atau sampai browser ditutup).',
              '<code>XSRF-TOKEN</code> melindungi formulir dari pengiriman palsu (selama sesi).',
              '<code>jaspest_consent</code> mengingat pilihan Anda (180 hari).'] },
    { id: 'media', locked: false, title: 'Video TikTok',
      desc: 'Memuat pemutar dari TikTok. TikTok dapat memasang cookie dan memproses data perangkat Anda sesuai kebijakan privasinya. Jika dimatikan, video tidak diputar di halaman ini.',
      items: ['Layanan pihak ketiga: <code>tiktok.com</code>. Cookie dan masa berlakunya ditentukan oleh TikTok.'] }
  ];
  const OPTIONAL = CATS.filter((c) => !c.locked).map((c) => c.id);

  const read = () => {
    try {
      const row = document.cookie.split('; ').find((r) => r.startsWith(NAME + '='));
      if (!row) return null;
      const d = JSON.parse(decodeURIComponent(row.slice(NAME.length + 1)));
      return d && d.v === VERSION && d.c && typeof d.c === 'object' ? d : null;
    } catch (e) { return null; }
  };
  const write = (d) => {
    try {
      document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(d)) + '; Max-Age=' + MAX_AGE +
        '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    } catch (e) { /* cookie diblokir: pilihan berlaku selama halaman terbuka */ }
  };

  let state = read(), bar = null, panel = null, opener = null;
  const allowed = (id) => id === 'necessary' || !!(state && state.c && state.c[id] === true);
  const all = (v) => Object.fromEntries(OPTIONAL.map((id) => [id, v]));

  function commit(choices) {
    const c = {};
    OPTIONAL.forEach((id) => { c[id] = choices[id] === true; });
    state = { v: VERSION, ts: Date.now(), c };
    write(state);
    hideBar();
    document.dispatchEvent(new CustomEvent('jaspest:consent', { detail: { choices: { ...c } } }));
  }

  function syncOffset() {
    if (bar) document.documentElement.style.setProperty('--ck-h', bar.offsetHeight + 'px');
  }
  function showBar() {
    if (bar || state) return;
    bar = document.createElement('section');
    bar.className = 'ck-bar';
    bar.setAttribute('role', 'region');
    bar.setAttribute('aria-label', 'Persetujuan cookie');
    bar.innerHTML =
      '<div class="ck-msg"><span class="ck-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 9 9 4 4 0 0 1-4-4 4 4 0 0 1-4-4 1 1 0 0 0-1-1Z"/><circle cx="8.5" cy="11" r=".9" fill="currentColor"/><circle cx="12" cy="16" r=".9" fill="currentColor"/><circle cx="15.5" cy="13" r=".9" fill="currentColor"/></svg></span>' +
      '<div><p class="ck-title">Cookie di situs ini</p><p class="ck-text">Kami memakai cookie esensial agar formulir konsultasi berjalan aman. Cookie opsional, seperti video TikTok, baru dipasang bila Anda mengizinkan. ' +
      '<button type="button" class="ck-inline" data-privacy>Kebijakan privasi</button></p></div></div>' +
      '<div class="ck-actions"><button type="button" class="ck-btn ck-ghost" data-ck="settings">Atur</button>' +
      '<button type="button" class="ck-btn ck-line" data-ck="reject">Tolak</button>' +
      '<button type="button" class="ck-btn ck-solid" data-ck="accept">Terima semua</button></div>';
    document.body.appendChild(bar);
    document.documentElement.classList.add('ck-open');
    syncOffset();
    window.addEventListener('resize', syncOffset, { passive: true });
  }
  function hideBar() {
    if (!bar) return;
    bar.remove(); bar = null;
    document.documentElement.classList.remove('ck-open');
    document.documentElement.style.removeProperty('--ck-h');
    window.removeEventListener('resize', syncOffset);
  }

  function buildPanel() {
    panel = document.createElement('dialog');
    panel.className = 'ck-panel';
    panel.setAttribute('aria-labelledby', 'ck-panel-title');
    const rows = CATS.map((c) => {
      const ctl = c.locked ? '<span class="ck-always">Selalu aktif</span>'
        : '<label class="ck-switch"><input type="checkbox" role="switch" data-ck-cat="' + c.id + '"><span class="ck-track" aria-hidden="true"></span><span class="ck-sr">Izinkan ' + c.title + '</span></label>';
      return '<div class="ck-cat"><div class="ck-cat-head"><h3>' + c.title + '</h3>' + ctl + '</div><p>' + c.desc + '</p><ul>' +
        c.items.map((i) => '<li>' + i + '</li>').join('') + '</ul></div>';
    }).join('');
    panel.innerHTML =
      '<div class="ck-panel-in"><header><div><h2 id="ck-panel-title">Pengaturan cookie</h2><p>Pilih cookie yang boleh dipasang. Pilihan dapat diubah kapan saja lewat tautan di bagian bawah halaman.</p></div>' +
      '<button type="button" class="ck-x" data-ck="close" aria-label="Tutup pengaturan cookie">&times;</button></header>' +
      '<div class="ck-body">' + rows + '</div>' +
      '<footer><button type="button" class="ck-btn ck-solid" data-ck="save">Simpan pilihan</button>' +
      '<button type="button" class="ck-btn ck-line" data-ck="accept">Terima semua</button>' +
      '<button type="button" class="ck-btn ck-line" data-ck="reject">Tolak</button></footer></div>';
    panel.addEventListener('click', (e) => { if (e.target === panel) closePanel(); });
    panel.addEventListener('close', () => { if (opener && opener.isConnected) opener.focus(); opener = null; });
    document.body.appendChild(panel);
  }
  function closePanel() {
    if (!panel) return;
    if (typeof panel.close === 'function') panel.close(); else panel.removeAttribute('open');
  }
  function openPanel() {
    const dlg = document.getElementById('content-dialog');
    if (dlg && dlg.open && typeof dlg.close === 'function') dlg.close();
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!panel) buildPanel();
    panel.querySelectorAll('[data-ck-cat]').forEach((i) => { i.checked = allowed(i.dataset.ckCat); });
    if (typeof panel.showModal === 'function') { if (!panel.open) panel.showModal(); } else panel.setAttribute('open', '');
    const first = panel.querySelector('[data-ck-cat]') || panel.querySelector('button');
    if (first) first.focus();
  }

  document.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    if (t.closest('[data-cookie-settings]')) { e.preventDefault(); openPanel(); return; }
    const a = t.closest('[data-ck]');
    if (!a) return;
    const k = a.dataset.ck;
    if (k === 'settings') openPanel();
    else if (k === 'accept') { commit(all(true)); closePanel(); }
    else if (k === 'reject') { commit(all(false)); closePanel(); }
    else if (k === 'close') closePanel();
    else if (k === 'save' && panel) {
      const c = all(false);
      panel.querySelectorAll('[data-ck-cat]').forEach((i) => { c[i.dataset.ckCat] = i.checked; });
      commit(c); closePanel();
    }
  });

  window.JaspestConsent = {
    allowed,
    set(id, value) {
      if (!OPTIONAL.includes(id)) return;
      const c = all(false);
      OPTIONAL.forEach((o) => { c[o] = allowed(o); });
      c[id] = value === true;
      commit(c);
    },
    acceptAll() { commit(all(true)); },
    rejectAll() { commit(all(false)); },
    open: openPanel
  };

  const init = () => showBar();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
