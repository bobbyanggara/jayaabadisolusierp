/* Kolom rupiah otomatis bertitik pemisah ribuan: 2500000 -> 2.500.000 (desimal memakai koma: 1.250,50).
   Dipakai pada input bertanda data-rupiah. Kode lain tetap membaca/menulis nilai seperti biasa:
   input.value mengembalikan angka mentah ("2500000" atau "1250.5"), dan FormData juga berisi angka mentah,
   sehingga tidak ada perubahan pada pengiriman data ke server. */
(() => {
  'use strict';
  const proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  if (!proto || !proto.get || !proto.set) return;
  const MAX_DECIMALS = 2;

  /** Teks apa pun (yang diketik) -> tampilan bertitik. Titik dianggap pemisah ribuan, koma = desimal. */
  function format(text) {
    const cleaned = String(text ?? '').replace(/[^\d,]/g, '');
    const comma = cleaned.indexOf(',');
    let integer = comma === -1 ? cleaned : cleaned.slice(0, comma);
    const decimals = comma === -1 ? null : cleaned.slice(comma + 1).replace(/,/g, '').slice(0, MAX_DECIMALS);
    integer = integer.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return decimals === null ? integer : `${integer || '0'},${decimals}`;
  }

  /** Angka mentah (titik = desimal, mis. "1250.5") -> tampilan bertitik. */
  function fromRaw(value) {
    const text = String(value ?? '').trim();
    if (text === '') return '';
    const normalized = text.replace(',', '.');
    if (!/^\d*\.?\d*$/.test(normalized)) return format(text);
    const [integer, decimals = ''] = normalized.split('.');
    // Nilai bawaan seperti "1000000.00" tampil tanpa ",00".
    return format(normalized.includes('.') && !/^0*$/.test(decimals) ? `${integer},${decimals.slice(0, MAX_DECIMALS)}` : integer);
  }

  /** Tampilan bertitik -> angka mentah. */
  function toRaw(display) {
    const text = String(display ?? '').replace(/\./g, '').replace(',', '.').trim();
    return text === '' || text === '.' ? '' : text;
  }

  function enhance(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.rupiahReady) return;
    input.dataset.rupiahReady = '1';
    const min = input.getAttribute('min');
    if (min !== null && min !== '') input.dataset.rupiahMin = min;
    input.type = 'text';
    input.inputMode = 'decimal';
    input.autocomplete = 'off';

    const validate = () => {
      const raw = toRaw(proto.get.call(input));
      const limit = input.dataset.rupiahMin;
      const tooLow = raw !== '' && limit !== undefined && Number(raw) < Number(limit);
      input.setCustomValidity(tooLow ? `Nilai minimal ${format(String(limit).replace('.', ','))}` : '');
    };

    // Kode lain membaca dan menulis input.value sebagai angka mentah.
    Object.defineProperty(input, 'value', {
      configurable: true,
      get() { return toRaw(proto.get.call(input)); },
      set(next) { proto.set.call(input, fromRaw(next)); validate(); },
    });

    input.addEventListener('input', () => {
      const typed = proto.get.call(input);
      const caret = input.selectionStart ?? typed.length;
      const significant = typed.slice(0, caret).replace(/[^\d,]/g, '').length;
      const formatted = format(typed);
      proto.set.call(input, formatted);
      let position = 0;
      for (let seen = 0; position < formatted.length && seen < significant; position += 1) {
        if (/[\d,]/.test(formatted[position])) seen += 1;
      }
      try { input.setSelectionRange(position, position); } catch (_) { /* abaikan */ }
      validate();
    });

    // Tempel angka seperti "200000.50" (titik desimal): ubah ke koma agar tidak dianggap ribuan.
    input.addEventListener('paste', (event) => {
      const pasted = event.clipboardData?.getData('text') ?? '';
      if (/^\s*\d+\.\d{1,2}\s*$/.test(pasted)) {
        event.preventDefault();
        document.execCommand('insertText', false, pasted.trim().replace('.', ','));
      }
    });

    proto.set.call(input, fromRaw(proto.get.call(input)));
    validate();
  }

  const scan = (root) => {
    if (!(root instanceof Element)) return;
    if (root.matches('input[data-rupiah]')) enhance(root);
    root.querySelectorAll('input[data-rupiah]').forEach(enhance);
  };

  scan(document.documentElement);
  new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach(scan)))
    .observe(document.documentElement, { childList: true, subtree: true });

  // FormData (dan form.submit biasa) berisi angka mentah, bukan teks bertitik.
  document.addEventListener('formdata', (event) => {
    event.target.querySelectorAll?.('input[data-rupiah-ready]').forEach((input) => {
      if (input.name && !input.disabled) event.formData.set(input.name, input.value);
    });
  });

  // form.reset() mengembalikan nilai bawaan (angka mentah); tampilkan kembali bertitik.
  document.addEventListener('reset', (event) => {
    setTimeout(() => {
      event.target.querySelectorAll?.('input[data-rupiah-ready]').forEach((input) => {
        proto.set.call(input, fromRaw(proto.get.call(input)));
        input.setCustomValidity('');
      });
    }, 0);
  });
})();
