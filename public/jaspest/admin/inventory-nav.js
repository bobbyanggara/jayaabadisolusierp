/* Dropdown "Inventaris" (Stok & Barang, Kas & Bank, Piutang).
   Berdiri sendiri: tidak bergantung pada admin.js, dan tidak mengubah perilaku menu lain. */
(function () {
  'use strict';
  var OWN = { cash: 'Kas & Bank', receivables: 'Piutang' };
  var group = document.getElementById('inventoryNavGroup');
  var toggle = document.getElementById('inventoryNavToggle');
  var submenu = document.getElementById('inventorySubmenu');
  if (!group || !toggle || !submenu) return;

  function setOpen(open) {
    submenu.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    group.classList.toggle('open', open);
    group.classList.toggle('is-open', open);
  }

  function deactivateOwnPages() {
    Object.keys(OWN).forEach(function (name) {
      var el = document.getElementById('page-' + name);
      if (el) el.classList.remove('active');
    });
  }

  function activateOwn(name, link) {
    var page = document.getElementById('page-' + name);
    if (!page) return;
    document.querySelectorAll('.page-view.active').forEach(function (el) { el.classList.remove('active'); });
    document.querySelectorAll('.nav-link.active').forEach(function (el) { el.classList.remove('active'); });
    page.classList.add('active');
    var target = link || document.querySelector('#inventorySubmenu [data-page="' + name + '"]');
    if (target) target.classList.add('active');
    var crumb = document.getElementById('breadcrumbCurrent');
    if (crumb) crumb.textContent = OWN[name];
    setOpen(true);
    if (location.hash !== '#' + name) history.replaceState(null, '', '#' + name);
    window.scrollTo({ top: 0 });
    document.dispatchEvent(new CustomEvent('jaspest:page', { detail: { name: name } }));
  }

  document.addEventListener('click', function (event) {
    var t = event.target;
    if (!(t instanceof Element)) return;

    if (t.closest('#inventoryNavToggle')) {
      event.stopImmediatePropagation();
      setOpen(submenu.hidden);
      return;
    }
    var own = t.closest('#inventorySubmenu [data-page]');
    if (own && OWN[own.getAttribute('data-page')]) {
      event.stopImmediatePropagation();
      activateOwn(own.getAttribute('data-page'), own);
      return;
    }
    // Berpindah ke halaman lain: pastikan halaman Kas/Piutang ditutup.
    if (t.closest('[data-page], [data-go]')) deactivateOwnPages();
  }, true);

  function fromHash() {
    var name = (location.hash || '').replace('#', '');
    if (OWN[name] && document.getElementById('page-' + name)) activateOwn(name);
  }
  window.addEventListener('hashchange', fromHash);
  document.addEventListener('DOMContentLoaded', function () { setTimeout(fromHash, 0); });
  if (document.readyState !== 'loading') setTimeout(fromHash, 0);
})();
