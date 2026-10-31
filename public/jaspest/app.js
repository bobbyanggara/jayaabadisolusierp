(() => {
  'use strict';
  const content = window.JASPEST_CONTENT;
  const config = window.JASPEST_CONFIG;
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const iconNames = new Set(['shield','house','building','bug','mouse','warehouse','sparkles','chat','lock','check','close','menu','camera','plus','file','send','play','clock','info']);
  const icon = (name, cls = '') => `<svg${cls ? ` class="${escape(cls)}"` : ''} aria-hidden="true"><use href="#i-${iconNames.has(name) ? name : 'info'}"/></svg>`;
  const safeUrl = (value) => {try {const url = new URL(value, location.href);return ['https:','http:'].includes(url.protocol) ? url.href : '';} catch {return '';}};
  const dialog = $('#content-dialog');
  const dialogContent = $('#dialog-content');
  let lightboxItems = null;
  let lightboxIndex = 0;
  let activeGalleryFilter = 'Semua';
  let photoFiles = [];
  let submitting = false;

  function openDialog(html, {gallery = false} = {}) {
    if (!gallery) lightboxItems = null;
    dialogContent.innerHTML = html;
    if (!dialog.open) {dialog.showModal();document.body.classList.add('modal-open');}
    dialog.scrollTop = 0;
  }
  function closeDialog() {dialog.close();}
  $('#dialog-close').addEventListener('click', closeDialog);
  dialog.addEventListener('close', () => {document.body.classList.remove('modal-open');lightboxItems = null;});
  dialog.addEventListener('click', (event) => {
    if (event.target !== dialog) return;
    const r = dialog.getBoundingClientRect();
    if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) closeDialog();
  });
  dialog.addEventListener('keydown', (event) => {
    if (!lightboxItems) return;
    if (event.key === 'ArrowRight') {event.preventDefault();changeGallery(1);}
    if (event.key === 'ArrowLeft') {event.preventDefault();changeGallery(-1);}
  });

  function goToForm(need = '') {
    if (dialog.open) closeDialog();
    closeChat(false);
    if (need) $('#need').value = need;
    $('#konsultasi').scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    $('#full-name').focus({preventScroll:true});
  }

  function renderServices() {
    $('#services-grid').innerHTML = content.services.map((service, index) => `<article class="service-card"><div class="service-card-top"><span class="service-icon">${service.image && safeUrl(service.image) ? `<img src="${escape(safeUrl(service.image))}" alt="" loading="lazy">` : icon(service.icon)}</span><span class="service-number">0${index+1}</span></div><h3>${escape(service.name)}</h3><div class="service-label">${escape(service.label)}</div><p>${escape(service.description)}</p><button class="text-link" data-service="${escape(service.id)}" aria-label="Lihat detail ${escape(service.name)}">Lihat detail</button></article>`).join('') + `<article class="service-card service-question"><span class="service-icon">${icon('chat')}</span><h3>Belum tahu jenis hamanya?</h3><p>Ceritakan saja temuan Anda. Tim kami akan membantu membahasnya.</p><button class="text-link" data-enquire="konsultasi">Konsultasi dahulu</button></article>`;
    const needs = $('#need');
    content.services.forEach((service) => needs.add(new Option(service.name, service.id)));
    needs.add(new Option('Belum tahu / konsultasi dahulu', 'konsultasi'));
  }

  function renderDirectoryCard(item, index, kind) {
    const image = item.image && safeUrl(item.image)
      ? `<img src="${escape(safeUrl(item.image))}" alt="${escape(item.name)}" loading="lazy">`
      : `<span class="directory-icon">${icon(kind === 'pest' ? 'bug' : 'shield')}</span>`;
    const action = kind === 'pest' ? 'data-pest' : 'data-method';
    return `<article class="directory-card"><div class="directory-card-visual">${image}<span class="directory-number">0${index + 1}</span></div><div class="directory-card-copy"><span class="directory-category">${escape(item.category || '')}</span><h3>${escape(item.name || 'Informasi')}</h3><p>${escape(item.description || '')}</p><button class="text-link" ${action}="${escape(item.id)}">Lihat informasi</button></div></article>`;
  }
  function renderPests() {
    const section = $('#jenis-hama');
    const items = Array.isArray(content.pests) ? content.pests : [];
    section.hidden = items.length === 0;
    $('#pests-grid').innerHTML = items.map((item, index) => renderDirectoryCard(item, index, 'pest')).join('');
  }
  function renderMethods() {
    const section = $('#metode-penanganan');
    const items = Array.isArray(content.methods) ? content.methods : [];
    section.hidden = items.length === 0;
    $('#methods-grid').innerHTML = items.map((item, index) => renderDirectoryCard(item, index, 'method')).join('');
  }
  function openDirectoryDetail(item, titleLabel) {
    if (!item) return;
    openDialog(`<div class="dialog-body"><div class="eyebrow">${escape(titleLabel)}</div><h2 id="dialog-title">${escape(item.name)}</h2>${item.image && safeUrl(item.image) ? `<img class="directory-dialog-image" src="${escape(safeUrl(item.image))}" alt="${escape(item.name)}">` : ''}<p>${escape(item.description || '')}</p>${item.detail ? `<p>${escape(item.detail)}</p>` : ''}<div class="dialog-actions"><button class="button button-red" data-enquire="konsultasi">Konsultasikan kebutuhan</button></div></div>`);
  }
  function openPest(id) { openDirectoryDetail(content.pests.find((item) => item.id === id), 'INFORMASI JENIS HAMA'); }
  function openMethod(id) { openDirectoryDetail(content.methods.find((item) => item.id === id), 'METODE PENANGANAN'); }

  function openService(id) {
    const service = content.services.find((item) => item.id === id);
    if (!service) return;
    openDialog(`<div class="dialog-body"><div class="dialog-service-icon">${icon(service.icon)}</div><div class="eyebrow">LAYANAN JASPEST</div><h2 id="dialog-title">${escape(service.name)}</h2><p>${escape(service.detail)}</p><div class="dialog-info">${icon('chat')}<span>Penjelasan rincian survei, treatment, dan perawatan lanjutan disampaikan langsung oleh tim JASPEST melalui WhatsApp atau kontak langsung.</span></div><div class="dialog-actions"><button class="button button-red" data-enquire="${escape(service.id)}">Ajukan kebutuhan</button></div></div>`);
  }

  function renderGallery(filter = 'Semua') {
    const filters = ['Semua', ...new Set(content.gallery.map((item) => item.category).filter(Boolean))];
    if (!filters.includes(filter)) filter = 'Semua';
    activeGalleryFilter = filter;
    const toolbar = $('.gallery-toolbar');
    toolbar.innerHTML = filters.map((name) => `<button class="filter-button${name === filter ? ' active' : ''}" data-gallery-filter="${escape(name)}" aria-pressed="${String(name === filter)}">${escape(name)}</button>`).join('');
    const items = content.gallery.filter((item) => filter === 'Semua' || item.category === filter);
    $('#gallery-grid').innerHTML = items.map((item) => `<button class="gallery-card" data-gallery="${escape(item.id)}" aria-label="Perbesar ${escape(item.title)}"><div class="gallery-image"><img src="${escape(safeUrl(item.image))}" alt="${escape(item.subtitle)}" loading="lazy" width="1536" height="1024"><span class="gallery-category">${escape(item.category)}</span><span class="gallery-expand">${icon('plus')}</span></div><div class="gallery-info"><h3>${escape(item.title)}</h3><p>${escape(item.subtitle)}</p></div></button>`).join('') || '<p class="muted-empty">Belum ada dokumentasi galeri yang dipublikasikan.</p>';
  }
  function openGallery(id) {
    lightboxItems = content.gallery.filter(item=>activeGalleryFilter==='Semua'||item.category===activeGalleryFilter);
    lightboxIndex = lightboxItems.findIndex((item) => item.id === id);
    if (lightboxIndex < 0) {lightboxItems = null;return;}
    showGallery();
  }
  function showGallery() {
    const item = lightboxItems[lightboxIndex];
    openDialog(`<img class="gallery-lightbox-image" src="${escape(safeUrl(item.image))}" alt="${escape(item.subtitle)}"><div class="lightbox-copy"><div class="eyebrow">${escape(item.category)}</div><h2 id="dialog-title">${escape(item.title)}</h2><p>${escape(item.subtitle)}</p><div class="dialog-gallery-controls"><button data-gallery-prev>Sebelumnya</button><span class="field-hint">${lightboxIndex+1} / ${lightboxItems.length}</span><button data-gallery-next>Berikutnya</button></div></div>`,{gallery:true});
  }
  function changeGallery(delta) {if(!lightboxItems)return;lightboxIndex=(lightboxIndex+delta+lightboxItems.length)%lightboxItems.length;showGallery();}

  function openArticle(id) {
    const article = content.articles.find((item) => item.id === id);
    location.assign(article ? `/blog/${encodeURIComponent(article.id)}/` : '/blog/');
  }
  function renderFaq() {
    $('#faq-list').innerHTML = content.faqs.slice(0, 6).map((faq) => `<details><summary>${escape(faq.question)}${icon('plus')}</summary><p>${escape(faq.answer)}</p></details>`).join('');
  }

  function openPortal() {
    if (config.portalUrl && safeUrl(config.portalUrl)) {location.assign(safeUrl(config.portalUrl));return;}
    openDialog(`<div class="dialog-body"><div class="dialog-service-icon">${icon('lock')}</div><div class="eyebrow">PORTAL KLIEN JASPEST</div><h2 id="dialog-title">Laporan pekerjaan,<br>dalam satu tempat.</h2><p>Portal klien sedang dipersiapkan untuk membantu Anda mengakses informasi dan laporan pekerjaan JASPEST.</p><p>Untuk informasi kebutuhan atau pekerjaan Anda, silakan hubungi tim melalui formulir pengajuan.</p><div class="dialog-actions"><button class="button button-blue" data-enquire="">Hubungi tim JASPEST</button></div></div>`);
  }
  function openPrivacy() {
    openDialog(`<div class="dialog-body"><div class="eyebrow">INFORMASI PENGGUNAAN DATA</div><h2 id="dialog-title">Privasi pengajuan Anda.</h2><p>Informasi yang Anda isi digunakan oleh tim JASPEST untuk memahami kebutuhan, menghubungi Anda melalui WhatsApp atau telepon, dan menindaklanjuti pengajuan terkait.</p><ul><li>Nama, kontak, alamat, luasan, kebutuhan, jenis lokasi, nama perusahaan, keterangan, dan foto opsional dipakai dalam proses konsultasi.</li><li>Foto temuan dan informasi pengajuan tidak otomatis dipublikasikan dalam galeri.</li><li>Pengajuan dapat diakses oleh petugas yang berwenang untuk menanganinya.</li><li>Anda dapat menyampaikan koreksi data atau meminta penghentian tindak lanjut kepada tim JASPEST saat dihubungi.</li></ul>${config.previewMode ? '<div class="dialog-info">'+icon('info')+'<span>Pada pratinjau ini, formulir hanya memvalidasi dan menampilkan ringkasan di perangkat Anda. Data tidak dikirim ke tim JASPEST atau layanan WhatsApp.</span></div>' : ''}<p>Video TikTok, jika diputar, dimuat dari layanan TikTok dan mengikuti ketentuan privasi layanan tersebut.</p></div>`);
  }

  function setupTikTok() {
    const entry = content.tiktokVideos?.[0];
    if (!entry) return;
    try {
      const url = new URL(entry.url);
      const allowed = ['www.tiktok.com','tiktok.com','m.tiktok.com'];
      const match = url.pathname.match(/\/video\/(\d{15,22})(?:\/|$)/);
      if (url.protocol !== 'https:' || !allowed.includes(url.hostname) || !match) return;
      const host = $('#tiktok-host');
      host.style.display = 'block';host.style.textAlign='center';
      const sourceLabel = entry.sourceLabel || url.pathname.split('/')[1];
      host.innerHTML = `<div class="video-placeholder" style="margin:auto"><img src="${escape(safeUrl(entry.cover || '/jaspest/assets/commercial-inspection.webp'))}" alt="Ilustrasi sampul pemutar TikTok" loading="lazy"><div class="video-shade"></div><div class="video-placeholder-copy"><span class="video-coming">${entry.isTest ? 'UJI PEMUTAR TIKTOK' : 'VIDEO JASPEST'}</span><strong>${escape(entry.title || 'Cerita dari lapangan.')}</strong><button class="button button-red" id="load-tiktok">Putar video ${icon('play')}</button></div></div><a class="video-source-link" href="${escape(url.href)}" target="_blank" rel="noopener noreferrer">${entry.isTest ? 'Video uji · ' : 'Sumber: '}${escape(sourceLabel)} · Buka di TikTok</a>`;
      $('#load-tiktok').addEventListener('click',()=>{
        const frame=document.createElement('iframe');frame.className='tiktok-frame';frame.src=`https://www.tiktok.com/player/v1/${match[1]}?autoplay=1&loop=0`;frame.title=entry.title || 'Video kegiatan JASPEST';frame.allow='autoplay; encrypted-media; fullscreen';frame.allowFullscreen=true;
        $('.video-placeholder',host).replaceWith(frame);
      });
      $('.video-mini-note div>span').textContent=entry.isTest ? `Video uji dari ${sourceLabel}.` : 'Putar video kegiatan kami.';
      if(entry.isTest){$('.video-mini-note strong').textContent='Uji integrasi TikTok';$('.video-copy>p').textContent='Pemutar berikut menggunakan video uji yang dipilih untuk pratinjau. Sumber video dicantumkan di bawah pemutar.';$('.video-copy h2').innerHTML='Video pilihan,<br>langsung di sini.';$('.video-copy .eyebrow').textContent='TIKTOK · VIDEO UJI';}
    } catch { /* Kosongkan video yang tidak memiliki URL TikTok valid. */ }
  }

  const normalize = (value) => String(value).toLocaleLowerCase('id-ID').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9\s]/g,' ').replace(/\s+/g,' ').trim();
  let chatStarted = false;
  function appendChat(text, sender = 'bot', faq = null) {
    const bubble = document.createElement('div');bubble.className=`chat-message ${sender}`;bubble.textContent=text;
    if (faq) {
      const source = document.createElement('button');source.type='button';source.className='chat-source';source.innerHTML=icon('file')+escape(faq.sourceLabel);
      source.addEventListener('click',()=>{
        if (faq.sourceType==='article') openArticle(faq.sourceId);
        else if (faq.sourceType==='portal') openPortal();
        else if (faq.sourceType==='services') {closeChat(false);$('#layanan').scrollIntoView({behavior:'smooth'});}
        else goToForm();
      });
      bubble.append(source);
    }
    $('#chat-messages').append(bubble);$('#chat-messages').scrollTop=$('#chat-messages').scrollHeight;
  }
  function startChat() {
    if (chatStarted) return;
    chatStarted=true;
    appendChat('Halo! Saya asisten FAQ JASPEST. Saya membantu menemukan informasi dari website ini. Apa yang ingin Anda ketahui?');
    const quick=document.createElement('div');quick.className='quick-questions';
    [['Layanan','Layanan apa saja yang tersedia?'],['Cara pengajuan','Bagaimana cara mengajukan konsultasi?'],['Biaya layanan','Bagaimana mengetahui biaya layanan?']].forEach(([label,question])=>{const button=document.createElement('button');button.type='button';button.textContent=label;button.addEventListener('click',()=>answerChat(question));quick.append(button);});
    $('#chat-messages').append(quick);
  }
  function openChat() {startChat();$('#chat-panel').hidden=false;$('#chat-launcher').setAttribute('aria-expanded','true');$('#chat-input').focus({preventScroll:true});}
  function closeChat(focus = true) {$('#chat-panel').hidden=true;$('#chat-launcher').setAttribute('aria-expanded','false');if(focus)$('#chat-launcher').focus({preventScroll:true});}
  function answerChat(question) {
    const query=question.trim().slice(0,500);if(!query)return;
    appendChat(query,'user');
    const normalized=normalize(query);
    if (/^(halo|hai|hi|hello|selamat (pagi|siang|sore|malam))\b/.test(normalized) && normalized.split(' ').length<5) {appendChat('Halo! Anda bisa menanyakan layanan, cara pengajuan, foto temuan, atau biaya layanan JASPEST.');return;}
    if (/^(terima kasih|makasih|thanks|thank you)/.test(normalized)) {appendChat('Sama-sama. Semoga informasinya membantu. Tim JASPEST siap membahas kebutuhan Anda melalui pengajuan konsultasi.');return;}
    const scored=content.faqs.map(faq=>({faq,score:faq.keywords.reduce((score,key)=>score+(normalized.includes(normalize(key)) ? (key.includes(' ') ? 3 : 1) : 0),0)+(normalize(faq.question)===normalized ? 30 : 0)})).sort((a,b)=>b.score-a.score);
    if (scored[0]?.score>0) {appendChat(scored[0].faq.answer,'bot',scored[0].faq);return;}
    appendChat('Informasi itu belum tersedia dalam FAQ JASPEST. Silakan ajukan konsultasi agar tim dapat membantu membahas kebutuhan Anda secara langsung.','bot',{sourceType:'form',sourceLabel:'Ajukan konsultasi'});
  }
  $('#chat-launcher').addEventListener('click',()=>$('#chat-panel').hidden ? openChat() : closeChat());
  $('#chat-close').addEventListener('click',()=>closeChat());
  $('#chat-form').addEventListener('submit',event=>{event.preventDefault();const input=$('#chat-input');answerChat(input.value);input.value='';input.focus();});
  $('#chat-panel').addEventListener('keydown',event=>{if(event.key==='Escape'){event.stopPropagation();closeChat();}});

  const form=$('#enquiry-form');
  const fieldByName=(name)=>form.elements.namedItem(name);
  function clearErrors() {
    $$('.field-error',form).forEach(element=>element.textContent='');
    $$('[aria-invalid]',form).forEach(element=>element.removeAttribute('aria-invalid'));
    $('#form-error-summary').hidden=true;
  }
  function setErrors(errors) {
    let first=null;
    Object.entries(errors).forEach(([name,message])=>{
      const field=fieldByName(name),error=$(`#error-${name}`);
      if(error)error.textContent=message;
      if(field && field instanceof HTMLElement){field.setAttribute('aria-invalid','true');if(error)field.setAttribute('aria-describedby',error.id);if(!first)first=field;}
      else if(name==='property_type'){for(const radio of $$('input[name="property_type"]')){radio.setAttribute('aria-invalid','true');radio.setAttribute('aria-describedby','error-property_type');}if(!first)first=$('input[name="property_type"]');}
    });
    const summary=$('#form-error-summary');summary.textContent=`Periksa kembali ${Object.keys(errors).length} bagian yang ditandai di bawah.`;summary.hidden=false;
    if(first){first.focus();first.scrollIntoView({block:'center',behavior:'smooth'});}else summary.focus();
  }
  function normalizePhone(value) {let digits=String(value).replace(/[\s()+-]/g,'');if(digits.startsWith('0'))digits='62'+digits.slice(1);return digits;}
  function validateForm() {
    const data=new FormData(form),errors={};
    if(String(data.get('full_name')||'').trim().length<2)errors.full_name='Isi nama lengkap Anda.';
    const phone=normalizePhone(data.get('whatsapp')||'');
    if(!/^628[1-9]\d{7,11}$/.test(phone))errors.whatsapp='Gunakan nomor WhatsApp Indonesia yang benar, misalnya 081234567890.';
    if(String(data.get('address')||'').trim().length<5)errors.address='Isi alamat lokasi dengan lebih lengkap.';
    const area=Number(data.get('estimated_area'));
    if(!Number.isFinite(area)||area<=0||area>1e9)errors.estimated_area='Isi perkiraan luasan lebih dari 0 m².';
    const validNeeds=new Set([...content.services.map(s=>s.id),'konsultasi']);
    if(!validNeeds.has(data.get('need')))errors.need='Pilih kebutuhan layanan Anda.';
    if(!['rumah','bisnis'].includes(data.get('property_type')))errors.property_type='Pilih Rumah atau Bisnis.';
    if(data.get('property_type')==='bisnis' && !String(data.get('company_name')||'').trim())errors.company_name='Isi nama perusahaan Anda.';
    if(!String(data.get('description')||'').trim())errors.description='Ceritakan temuan atau kebutuhan Anda.';
    if(!data.get('consent'))errors.consent='Persetujuan diperlukan agar tim dapat menghubungi Anda.';
    data.set('whatsapp',phone);data.set('estimated_area',String(area));
    return {data,errors};
  }
  function syncProperty() {
    const business=$('input[name="property_type"]:checked')?.value==='bisnis';
    $('#company-field').hidden=!business;$('#company').disabled=!business;$('#company').required=business;
    if(!business){$('#error-company_name').textContent='';$('#company').removeAttribute('aria-invalid');}
  }
  $$('input[name="property_type"]').forEach(radio=>radio.addEventListener('change',syncProperty));

  function renderPhotos() {
    const container=$('#photo-list');container.replaceChildren();
    photoFiles.forEach((entry,index)=>{
      const item=document.createElement('div');item.className='photo-item';
      const image=document.createElement('img');image.src=entry.url;image.alt=`Pratinjau foto ${index+1}`;
      const remove=document.createElement('button');remove.type='button';remove.setAttribute('aria-label',`Hapus foto ${entry.file.name}`);remove.innerHTML=icon('close');remove.addEventListener('click',()=>{URL.revokeObjectURL(entry.url);photoFiles=photoFiles.filter(item=>item!==entry);renderPhotos();});
      const name=document.createElement('span');name.textContent=entry.file.name;item.append(image,remove,name);container.append(item);
    });
  }
  async function addPhotos(files) {
    const errors=[];
    for(const file of files){
      if(photoFiles.length>=config.maxPhotoCount){errors.push(`Maksimal ${config.maxPhotoCount} foto.`);break;}
      if(!['image/jpeg','image/png','image/webp'].includes(file.type)){errors.push(`${file.name}: gunakan JPG, PNG, atau WebP.`);continue;}
      if(file.size>config.maxPhotoBytes||file.size===0){errors.push(`${file.name}: ukuran harus di atas 0 dan maksimal 5 MB.`);continue;}
      if(photoFiles.some(entry=>entry.file.name===file.name&&entry.file.size===file.size&&entry.file.lastModified===file.lastModified))continue;
      try {const bitmap=await createImageBitmap(file);bitmap.close();}catch{errors.push(`${file.name}: foto tidak dapat dibaca.`);continue;}
      photoFiles.push({file,url:URL.createObjectURL(file)});
    }
    $('#error-photos').textContent=[...new Set(errors)].join(' ');renderPhotos();$('#photos').value='';
  }
  $('#photos').addEventListener('change',event=>addPhotos([...event.target.files]));
  const uploadZone=$('#upload-zone');
  ['dragenter','dragover'].forEach(event=>uploadZone.addEventListener(event,e=>{e.preventDefault();uploadZone.classList.add('drag-over');}));
  ['dragleave','drop'].forEach(event=>uploadZone.addEventListener(event,e=>{e.preventDefault();uploadZone.classList.remove('drag-over');}));
  uploadZone.addEventListener('drop',event=>addPhotos([...event.dataTransfer.files]));

  function reviewSubmission(data) {
    const need=content.services.find(service=>service.id===data.get('need'))?.name || 'Konsultasi dahulu';
    const rows=[['Nama lengkap',data.get('full_name')],['WhatsApp',data.get('whatsapp')],['Alamat',data.get('address')],['Luasan',`${data.get('estimated_area')} m²`],['Kebutuhan',need],['Jenis lokasi',data.get('property_type')==='bisnis'?'Bisnis':'Rumah']];
    if(data.get('property_type')==='bisnis')rows.push(['Perusahaan',data.get('company_name')]);
    rows.push(['Keterangan',data.get('description')],['Foto temuan',photoFiles.length ? `${photoFiles.length} foto terlampir` : 'Tidak dilampirkan'],['Persetujuan','Bersedia dihubungi oleh team JASPEST']);
    const preview=Boolean(config.previewMode||!config.enquiryEndpoint);
    openDialog(`<div class="dialog-body"><div class="eyebrow">${preview?'PRATINJAU FORMULIR':'PENGAJUAN KONSULTASI'}</div><h2 id="dialog-title">Periksa pengajuan Anda.</h2>${preview?`<div class="dialog-info">${icon('info')}<span>Ini adalah pratinjau formulir. Data telah lolos validasi, tetapi belum dikirim kepada tim JASPEST. Pengiriman akan tersedia setelah layanan pengajuan diaktifkan.</span></div>`:'<p>Pastikan informasi berikut sudah benar sebelum dikirim kepada tim JASPEST.</p>'}<dl class="review-table">${rows.map(([label,value])=>`<dt>${escape(label)}</dt><dd>${escape(value)}</dd>`).join('')}</dl><div class="dialog-actions"><button class="button button-outline" id="review-back">${preview?'Kembali ke formulir':'Perbaiki data'}</button>${!preview?`<button class="button button-red" id="confirm-submit">Kirim pengajuan ${icon('send')}</button>`:''}</div><div id="submission-error" class="field-error" role="alert"></div></div>`);
    $('#review-back').addEventListener('click',closeDialog);
    if(!preview)$('#confirm-submit').addEventListener('click',()=>submitEnquiry(data));
  }
  async function submitEnquiry(data) {
    if(submitting)return;
    submitting=true;const button=$('#confirm-submit');button.disabled=true;button.textContent='Mengirim pengajuan…';
    const request=new FormData();
    for(const [name,value] of data.entries())if(!name.startsWith('photos')&&name!=='company_name')request.set(name,value);
    if(data.get('property_type')==='bisnis')request.set('company_name',data.get('company_name'));
    photoFiles.forEach(entry=>request.append('photos[]',entry.file));
    const headers={Accept:'application/json'};const csrf=$('meta[name="csrf-token"]')?.content;if(csrf)headers['X-CSRF-TOKEN']=csrf;
    try {
      const response=await fetch(config.enquiryEndpoint,{method:'POST',body:request,headers,credentials:'same-origin'});
      const result=await response.json().catch(()=>null);
      if(!response.ok||!result?.reference)throw new Error(response.status===422?'Ada data yang perlu diperiksa kembali. Tutup ringkasan dan periksa isian Anda.':'Pengajuan belum berhasil dikirim. Silakan coba kembali.');
      openDialog(`<div class="dialog-body"><div class="dialog-service-icon">${icon('check')}</div><div class="eyebrow">PENGAJUAN DITERIMA</div><h2 id="dialog-title">Terima kasih, ${escape(String(data.get('full_name')).split(' ')[0])}.</h2><p>Tim JASPEST telah menerima pengajuan Anda dan akan menindaklanjutinya melalui kontak yang diberikan.</p><div class="dialog-info">${icon('file')}<span>Nomor pengajuan: <strong>${escape(result.reference)}</strong></span></div><div class="dialog-actions"><button class="button button-blue" id="submission-done">Selesai ${icon('check')}</button></div></div>`);
      $('#submission-done').addEventListener('click',closeDialog);form.reset();syncProperty();photoFiles.forEach(entry=>URL.revokeObjectURL(entry.url));photoFiles=[];renderPhotos();clearErrors();
    } catch(error){const box=$('#submission-error');if(box)box.textContent=error.message;}
    finally{submitting=false;if(button.isConnected){button.disabled=false;button.innerHTML='Kirim pengajuan '+icon('send');}}
  }
  form.addEventListener('submit',event=>{event.preventDefault();clearErrors();const {data,errors}=validateForm();if(Object.keys(errors).length){setErrors(errors);return;}reviewSubmission(data);});

  document.addEventListener('click',event=>{
    const service=event.target.closest('[data-service]');if(service){openService(service.dataset.service);return;}
    const pest=event.target.closest('[data-pest]');if(pest){openPest(pest.dataset.pest);return;}
    const method=event.target.closest('[data-method]');if(method){openMethod(method.dataset.method);return;}
    const enquire=event.target.closest('[data-enquire]');if(enquire){goToForm(enquire.dataset.enquire);return;}
    const gallery=event.target.closest('[data-gallery]');if(gallery){openGallery(gallery.dataset.gallery);return;}
    const filter=event.target.closest('[data-gallery-filter]');if(filter){renderGallery(filter.dataset.galleryFilter);return;}
    const article=event.target.closest('[data-article]');if(article){openArticle(article.dataset.article);return;}
    if(event.target.closest('[data-gallery-prev]')){changeGallery(-1);return;}
    if(event.target.closest('[data-gallery-next]')){changeGallery(1);return;}
    if(event.target.closest('[data-portal]')){openPortal();return;}
    if(event.target.closest('[data-privacy]')){openPrivacy();return;}
    if(event.target.closest('[data-open-chat]')){openChat();return;}
    const anchor=event.target.closest('a[href^="#"]');
    if(anchor){$('#mobile-nav').hidden=true;$('#menu-toggle').setAttribute('aria-expanded','false');$('#menu-toggle').setAttribute('aria-label','Buka menu');}
  });
  $('#menu-toggle').addEventListener('click',()=>{const open=$('#mobile-nav').hidden;$('#mobile-nav').hidden=!open;$('#menu-toggle').setAttribute('aria-expanded',String(open));$('#menu-toggle').setAttribute('aria-label',open?'Tutup menu':'Buka menu');});
  window.addEventListener('scroll',()=>$('#site-header').classList.toggle('scrolled',scrollY>20),{passive:true});
  window.addEventListener('pagehide',()=>photoFiles.forEach(entry=>URL.revokeObjectURL(entry.url)));
  $('#year').textContent=String(new Date().getFullYear());
  if(!config.previewMode&&config.enquiryEndpoint)$('#preview-form-note').hidden=true;
  if (content.brand && typeof content.brand === 'object') {
    $$('.brand-name').forEach(element => { element.textContent = content.brand.name || 'JASPEST'; });
    $$('.brand-sub').forEach(element => { element.textContent = content.brand.company || 'Jaya Abadi Solusi'; });
    const footerTagline = $('.footer-main>p');
    if (footerTagline && content.brand.tagline) footerTagline.textContent = content.brand.tagline;
    const copyright = $('.footer-bottom>span:first-child');
    if (copyright) copyright.textContent = `© ${new Date().getFullYear()} ${content.brand.name || 'JASPEST'} · ${content.brand.company || 'Jaya Abadi Solusi'}`;
  }
  if(content.brand.logo && safeUrl(content.brand.logo)){
    $$('.brand').forEach(brand=>{const logo=document.createElement('img');logo.src=safeUrl(content.brand.logo);logo.alt=content.brand.name;logo.className='brand-uploaded-logo';brand.replaceChildren(logo);});
  }
  const companyWhatsapp = $('[data-company-whatsapp]');
  const companyWhatsappNumber = normalizePhone(config.whatsappNumber || '');
  if (companyWhatsapp && /^628[1-9]\d{7,11}$/.test(companyWhatsappNumber)) {
    companyWhatsapp.href = `https://wa.me/${companyWhatsappNumber}?text=${encodeURIComponent('Halo JASPEST, saya ingin berkonsultasi mengenai layanan pengendalian hama.')}`;
    companyWhatsapp.hidden = false;
  }
  renderServices();renderPests();renderMethods();renderGallery();renderFaq();setupTikTok();syncProperty();
})();
