/* Dropdown "Keuangan & Akuntansi": Invoice, Kas & Bank, Piutang.
   Mandiri (tidak bergantung pada admin.js) dan hanya menangani halaman miliknya sendiri. */
(function () {
  'use strict';
  var OWN = { cash: 'Kas & Bank', receivables: 'Piutang' };
  var group = document.getElementById('financeNavGroup');
  var toggle = document.getElementById('financeNavToggle');
  var submenu = document.getElementById('financeSubmenu');
  if (!group || !toggle || !submenu) return;

  function setOpen(open) {
    submenu.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    group.classList.toggle('open', open);
    group.classList.toggle('is-open', open);
  }
  function deactivateOwnPages() {
    Object.keys(OWN).forEach(function (n) {
      var el = document.getElementById('page-' + n);
      if (el) el.classList.remove('active');
    });
  }
  function activateOwn(name, link) {
    var page = document.getElementById('page-' + name);
    if (!page) return;
    document.querySelectorAll('.page-view.active').forEach(function (el) { el.classList.remove('active'); });
    document.querySelectorAll('.nav-link.active').forEach(function (el) { el.classList.remove('active'); });
    page.classList.add('active');
    var target = link || submenu.querySelector('[data-page="' + name + '"]');
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
    if (t.closest('#financeNavToggle')) {
      event.stopImmediatePropagation();
      setOpen(submenu.hidden);
      return;
    }
    // Tombol/tautan data-page ke halaman milik dropdown ini (di menu maupun di dalam halaman).
    var go = t.closest('[data-page]');
    if (go && OWN[go.getAttribute('data-page')]) {
      event.stopImmediatePropagation();
      activateOwn(go.getAttribute('data-page'), submenu.contains(go) ? go : null);
      return;
    }
    if (go || t.closest('[data-go]')) {
      deactivateOwnPages();
      if (go && go.getAttribute('data-page') === 'finance') setOpen(true);
    }
  }, true);

  function fromHash() {
    var name = (location.hash || '').replace('#', '');
    if (OWN[name] && document.getElementById('page-' + name)) activateOwn(name);
  }
  window.addEventListener('hashchange', fromHash);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(fromHash, 0); });
  else setTimeout(fromHash, 0);

  /* Tab di halaman Invoice: Daftar Invoice | Order Siap Ditagihkan */
  var tabInv = document.getElementById('finTabInvoices');
  var tabOrd = document.getElementById('finTabOrders');
  var panelInv = document.getElementById('financeInvoicesPanel');
  var panelOrd = document.getElementById('financeOrdersPanel');
  if (tabInv && tabOrd && panelInv && panelOrd) {
    var showTab = function (which) {
      var orders = which === 'orders';
      panelInv.hidden = orders;
      panelOrd.hidden = !orders;
      tabInv.classList.toggle('active', !orders);
      tabOrd.classList.toggle('active', orders);
      tabInv.setAttribute('aria-selected', orders ? 'false' : 'true');
      tabOrd.setAttribute('aria-selected', orders ? 'true' : 'false');
    };
    tabInv.addEventListener('click', function () { showTab('invoices'); });
    tabOrd.addEventListener('click', function () { showTab('orders'); });

    // Badge jumlah order mengikuti angka yang diisi modul Keuangan ("18 order").
    var count = document.getElementById('financeOrderCount');
    var badge = document.getElementById('finTabOrdersBadge');
    if (count && badge) {
      var sync = function () {
        var n = parseInt((count.textContent || '').replace(/\D/g, ''), 10) || 0;
        badge.textContent = n;
        badge.hidden = n === 0;
      };
      new MutationObserver(sync).observe(count, { childList: true, characterData: true, subtree: true });
      sync();
    }
  }
})();
