/* Menjaga agar halaman admin tidak "terkunci" (tidak bisa di-scroll / digerakkan)
   setelah modal ditutup atau setelah kembali dari tab cetak invoice. */
(() => {
  const isOpen = (el) => el && !el.hidden && getComputedStyle(el).display !== 'none';

  function sync() {
    const body = document.body;
    if (!body || body.style.overflow !== 'hidden') return;
    const modalOpen = Array.from(document.querySelectorAll('.modal-backdrop')).some(isOpen);
    const lightboxOpen = isOpen(document.getElementById('photoLightbox'));
    if (!modalOpen && !lightboxOpen) body.style.overflow = '';
  }

  window.JASPEST_SCROLL_GUARD = { sync };

  // Kembali dari tab cetak / dialog print: pastikan kunci scroll tidak tersisa.
  window.addEventListener('focus', sync);
  window.addEventListener('pageshow', sync);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
  // Setelah invoice terbit, daftar dimuat ulang; cek lagi sesudah modal tertutup.
  window.addEventListener('jaspest:finance-open', () => { window.setTimeout(sync, 0); window.setTimeout(sync, 600); });
})();
