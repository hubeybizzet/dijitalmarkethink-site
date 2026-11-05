/* Aksar Ege — vitrin scripti (bağımlılıksız) */
(function () {
  'use strict';

  var AE = window.AE || { products: [], settings: {}, payments: {} };
  var S = AE.settings;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var byId = {};
  AE.products.forEach(function (p) { byId[p.id] = p; });
  var CART_KEY = 'aksarege_cart_v1';

  function money(n) {
    return (Math.round(n * 100) / 100).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  /* ---------------- Meta Pixel + Dönüşümler API'si ----------------
     Her olay için tek bir event_id üretilir: Pixel'e (tarayıcı) ve api/event.php üzerinden
     Meta Dönüşümler API'sine (sunucu) aynı kimlikle gider; Meta ikisini tekilleştirir. */
  function eventId(ev) { return ev.toLowerCase() + '.' + Date.now().toString(36) + '.' + Math.random().toString(36).slice(2, 10); }
  function customData(items) {
    var contents = [], value = 0, num = 0;
    (items || []).forEach(function (it) {
      var p = byId[it.id]; if (!p) return;
      var q = Math.max(1, Math.min(99, it.qty || 1));
      contents.push({ id: String(p.id), quantity: q, item_price: p.price });
      value += p.price * q; num += q;
    });
    if (!contents.length) return {};
    var cd = { currency: 'TRY', value: Math.round(value * 100) / 100, content_type: 'product', content_ids: contents.map(function (c) { return c.id; }), contents: contents, num_items: num };
    if (contents.length === 1) cd.content_name = byId[contents[0].id].name;
    return cd;
  }
  function beacon(payload) {
    if (!S.capi) return;
    var body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon && navigator.sendBeacon('api/event.php', new Blob([body], { type: 'application/json' }))) return;
    } catch (e) {}
    try { fetch('api/event.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'same-origin' }); } catch (e) {}
  }
  /**
   * @param {string} ev   Meta standart olay adı
   * @param {Array}  items [{id, qty}]
   * @param {Object} extra { method, payment, data, eventId, serverSide:false }
   */
  function track(ev, items, extra) {
    extra = extra || {};
    var id = extra.eventId || eventId(ev);
    var data = extra.data || customData(items);
    if (extra.method) data.contact_method = extra.method;
    if (extra.payment) data.payment_method = extra.payment;
    try { if (window.fbq) window.fbq('track', ev, data, { eventID: id }); } catch (e) {}
    if (extra.serverSide !== false) {
      beacon({ event_name: ev, event_id: id, url: location.href, items: (items || []).map(function (i) { return { id: +i.id, qty: i.qty || 1 }; }), method: extra.method, payment: extra.payment });
    }
    try {
      if (window.gtag) {
        var map = { AddToCart: 'add_to_cart', InitiateCheckout: 'begin_checkout', Purchase: 'purchase', Contact: 'contact', ViewContent: 'view_item', AddPaymentInfo: 'add_payment_info' };
        window.gtag('event', map[ev] || ev, { currency: 'TRY', value: data.value, items: (data.contents || []).map(function (c) { return { item_id: c.id, item_name: (byId[c.id] || {}).name, price: c.item_price, quantity: c.quantity }; }), transaction_id: data.order_id });
      }
    } catch (e) {}
  }
  function cartItems() { return Object.keys(cart).map(function (k) { return { id: k, qty: cart[k] }; }); }
  // Sayfa görüntüleme: Pixel'in head'de gönderdiği PageView ile aynı event_id
  if (window.AE_PV) beacon({ event_name: 'PageView', event_id: window.AE_PV, url: location.href, items: [] });

  /* ---------------- Sepet ---------------- */
  var cart = load();
  var startedCheckout = false;
  function load() {
    try {
      var c = JSON.parse(localStorage.getItem(CART_KEY) || '{}');
      Object.keys(c).forEach(function (k) { if (!byId[k] || !byId[k].orderable) delete c[k]; });
      return c;
    } catch (e) { return {}; }
  }
  function save() { try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) {} render(); }
  function add(id, qty) {
    id = String(id);
    if (!byId[id] || !byId[id].orderable) return;
    cart[id] = Math.min(99, (cart[id] || 0) + (qty || 1));
    save();
  }
  function setQty(id, qty) {
    if (qty <= 0) delete cart[id]; else cart[id] = Math.min(99, qty);
    save();
  }
  function count() { return Object.keys(cart).reduce(function (a, k) { return a + cart[k]; }, 0); }
  function subtotal() { return Object.keys(cart).reduce(function (a, k) { return a + byId[k].price * cart[k]; }, 0); }
  function payment() { var r = $('input[name="payment"]:checked'); return r ? r.value : ''; }
  function totals() {
    var sub = subtotal(), pay = payment();
    var discount = pay === 'havale' ? Math.round(sub * (S.transfer_discount || 0)) / 100 : 0;
    var shipping = (S.shipping_fee > 0 && !(S.free_shipping_min > 0 && sub >= S.free_shipping_min)) ? S.shipping_fee : 0;
    var cod = (pay === 'kapida_nakit' || pay === 'kapida_kart') ? (S.cod_fee || 0) : 0;
    return { sub: sub, discount: discount, shipping: shipping, cod: cod, total: sub - discount + shipping + cod };
  }

  function lineHTML(id, small) {
    var p = byId[id], q = cart[id];
    return '<div class="line" data-line="' + id + '">' +
      '<img src="' + esc(p.image) + '" alt="" width="56" height="70" loading="lazy">' +
      '<div><div class="line__name">' + esc(p.name) + '</div><div class="line__meta">' +
      '<div class="qty qty--sm" data-line-qty="' + id + '"><button type="button" data-d="-1" aria-label="Azalt"><svg class="ic"><use href="#i-minus"/></svg></button>' +
      '<input type="number" min="1" max="99" value="' + q + '" aria-label="Adet"><button type="button" data-d="1" aria-label="Arttır"><svg class="ic"><use href="#i-plus"/></svg></button></div>' +
      '<button type="button" class="line__rm" data-rm="' + id + '">Kaldır</button></div></div>' +
      '<div class="line__price">' + money(p.price * q) + '</div></div>';
  }

  function render() {
    var n = count(), ids = Object.keys(cart), t = totals();
    $$('[data-cart-count]').forEach(function (el) { el.textContent = n; el.classList.toggle('has', n > 0); });

    // Çekmece
    var di = $('[data-drawer-items]');
    if (di) {
      di.innerHTML = ids.length ? ids.map(function (id) { return lineHTML(id); }).join('') :
        '<div class="drawer__empty"><svg class="ic"><use href="#i-bag"/></svg>Sepetiniz boş.<br>Ege\'nin doğal lezzetlerine göz atın 🫒</div>';
      $('[data-drawer-total]').textContent = money(t.sub);
    }
    var sp = $('[data-ship-progress]');
    if (sp) {
      if (S.shipping_fee > 0 && S.free_shipping_min > 0) {
        var left = S.free_shipping_min - t.sub, pct = Math.min(100, (t.sub / S.free_shipping_min) * 100);
        sp.innerHTML = (left > 0 ? 'Ücretsiz kargo için <b>' + money(left) + '</b> kaldı!' : '🎉 Kargonuz ücretsiz!') + '<div class="bar"><i style="width:' + pct + '%"></i></div>';
      } else if (!(S.shipping_fee > 0)) {
        sp.innerHTML = '🚚 Kargonuz <b>ücretsiz</b> · Kapıda ödeme imkânı';
      }
    }

    // Sipariş özeti
    var si = $('[data-summary-items]');
    if (si) {
      si.innerHTML = ids.length ? ids.map(function (id) { return lineHTML(id); }).join('') :
        '<p class="summary__empty">Sepetiniz henüz boş. <a href="#urunler">Zeytinlere göz atın</a> ya da aşağıdan hızlıca ekleyin.</p>';
      setT('subtotal', money(t.sub));
      setT('discount', '−' + money(t.discount)); toggleRow('discount', t.discount > 0);
      setT('cod', money(t.cod)); toggleRow('cod', t.cod > 0);
      var sh = $('[data-t="shipping"]');
      if (sh) sh.innerHTML = t.shipping > 0 ? money(t.shipping) : '<span class="free">Ücretsiz</span>';
      setT('total', money(t.total));
      var lbl = $('[data-submit] .btn__label');
      if (lbl) lbl.innerHTML = '<svg class="ic"><use href="#i-check"/></svg> ' + (ids.length ? 'Siparişi Tamamla · ' + money(t.total) : 'Siparişi Tamamla');
    }
    var ml = $('[data-mbar-label]');
    if (ml) ml.textContent = n ? 'Sepet (' + n + ') · ' + money(t.sub) : 'Sipariş Ver';
  }
  function setT(k, v) { var el = $('[data-t="' + k + '"]'); if (el) el.textContent = v; }
  function toggleRow(k, show) { var el = $('[data-row="' + k + '"]'); if (el) el.hidden = !show; }

  /* ---------------- Sepete ekleme animasyonu ---------------- */
  function flyToCart(fromEl, img) {
    var target = $('.cart-btn');
    if (!fromEl || !target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return bump();
    var a = fromEl.getBoundingClientRect(), b = target.getBoundingClientRect();
    var f = document.createElement('div');
    f.className = 'fly';
    f.innerHTML = '<img src="' + esc(img) + '" alt="">';
    f.style.left = (a.left + a.width / 2 - 28) + 'px';
    f.style.top = (a.top + a.height / 2 - 35) + 'px';
    document.body.appendChild(f);
    requestAnimationFrame(function () {
      f.style.transform = 'translate(' + (b.left + b.width / 2 - (a.left + a.width / 2)) + 'px,' + (b.top + b.height / 2 - (a.top + a.height / 2)) + 'px) scale(.25) rotate(20deg)';
      f.style.opacity = '.4';
    });
    setTimeout(function () { f.remove(); bump(); }, 820);
  }
  function bump() {
    var c = $('.cart-btn'); if (!c) return;
    c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump');
  }

  var toastTimer;
  function toast(html) {
    var t = $('.toast');
    if (!t) return;
    t.innerHTML = html; t.hidden = false;
    t.style.animation = 'none'; void t.offsetWidth; t.style.animation = '';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.hidden = true; }, 3800);
  }

  /* ---------------- Çekmece ---------------- */
  var drawer = $('#cartDrawer');
  function openCart() { if (!drawer) return; drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function closeCart() { if (!drawer) return; drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }

  /* ---------------- Modallar ---------------- */
  var lastFocus;
  function openModal(id) {
    var m = document.getElementById(id); if (!m) return;
    lastFocus = document.activeElement;
    m.hidden = false; document.body.style.overflow = 'hidden';
    var f = m.querySelector('.btn, a, button:not(.modal__x)'); if (f) setTimeout(function () { f.focus({ preventScroll: true }); }, 60);
  }
  function closeModal(m) {
    m.hidden = true;
    if (!$$('.modal:not([hidden])').length && !(drawer && drawer.classList.contains('open'))) document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }

  function openProduct(id) {
    var p = byId[id]; if (!p) return;
    var imgs = p.images.length ? p.images : [p.image];
    var paras = String(p.description || '').split(/\n\s*\n/).map(function (x) { return '<p>' + esc(x) + '</p>'; }).join('');
    var price = p.price > 0
      ? (p.old_price > p.price ? '<del>' + money(p.old_price) + '</del>' : '') + '<strong>' + money(p.price) + '</strong>' + (p.unit ? '<small>' + esc(p.unit) + '</small>' : '')
      : '<strong class="price--soon">Fiyat için sorun</strong>';
    var buy = p.orderable
      ? '<div class="qty" data-qty><button type="button" data-step="-1" aria-label="Azalt"><svg class="ic"><use href="#i-minus"/></svg></button><input type="number" value="1" min="1" max="99" aria-label="Adet"><button type="button" data-step="1" aria-label="Arttır"><svg class="ic"><use href="#i-plus"/></svg></button></div>' +
        '<button class="btn btn--primary btn--lg btn--add" type="button" data-add="' + p.id + '"><svg class="ic"><use href="#i-bag"/></svg> Sepete Ekle</button>'
      : '<a class="btn btn--wa btn--lg" target="_blank" rel="noopener" href="https://wa.me/' + S.whatsapp + '?text=' + encodeURIComponent('Merhaba, ' + p.name + ' hakkında bilgi almak istiyorum.') + '"><svg class="ic"><use href="#i-wa"/></svg> WhatsApp\'tan Sipariş</a>';
    $('[data-pm]').innerHTML =
      '<div class="pm__gallery"><div class="pm__main"><img src="' + esc(imgs[0]) + '" alt="' + esc(S.brand + ' ' + p.name) + '" width="800" height="1000"></div>' +
      (imgs.length > 1 ? '<div class="pm__thumbs">' + imgs.map(function (im, i) { return '<button type="button" class="' + (i ? '' : 'on') + '" data-thumb="' + esc(im) + '" aria-label="Görsel ' + (i + 1) + '"><img src="' + esc(im) + '" alt=""></button>'; }).join('') + '</div>' : '') +
      '</div><div class="pm__info" data-product="' + p.id + '">' +
      '<p class="pcard__sub">' + esc(p.subtitle) + '</p><h2 id="pmTitle">' + esc(p.name) + '</h2>' +
      (p.badge ? '<p class="pm__badge"><svg class="ic"><use href="#i-spark"/></svg>' + esc(p.badge) + '</p>' : '') +
      '<div class="pm__desc">' + paras + '</div>' +
      (p.features && p.features.length ? '<ul class="pcard__feats">' + p.features.map(function (f) { return '<li><svg class="ic"><use href="#i-check"/></svg>' + esc(f) + '</li>'; }).join('') + '</ul>' : '') +
      (p.pairing ? '<div class="pm__pair"><strong>Yanına yakışanlar:</strong> ' + esc(p.pairing) + '</div>' : '') +
      '<div class="price">' + price + '</div><div class="pm__buy">' + buy + '</div></div>';
    openModal('productModal');
    track('ViewContent', [{ id: p.id, qty: 1 }]);
  }

  function openLegal(key) {
    var tpl = document.getElementById('legal-' + key); if (!tpl) return;
    $('#legalTitle').textContent = tpl.getAttribute('data-title');
    $('#legalModal .legal__body').innerHTML = tpl.innerHTML;
    openModal('legalModal');
  }

  /* ---------------- Olay yönetimi ---------------- */
  document.addEventListener('click', function (e) {
    var t = e.target;
    var el;

    if ((el = t.closest('[data-contact]'))) { e.preventDefault(); openModal('contactModal'); return; }
    if ((el = t.closest('[data-close]'))) { closeModal(el.closest('.modal')); return; }
    if ((el = t.closest('[data-open-cart]'))) { openCart(); return; }
    if ((el = t.closest('[data-close-cart]'))) { closeCart(); return; }
    if ((el = t.closest('[data-goto-checkout]'))) {
      closeCart();
      if (count() && !startedCheckout) { startedCheckout = true; track('InitiateCheckout', cartItems()); }
      return;
    }
    if ((el = t.closest('[data-quickview]'))) { openProduct(el.getAttribute('data-quickview')); return; }
    if ((el = t.closest('[data-legal]'))) { e.preventDefault(); openLegal(el.getAttribute('data-legal')); return; }
    if ((el = t.closest('[data-thumb]'))) {
      var main = $('.pm__main img'); main.src = el.getAttribute('data-thumb');
      $$('[data-thumb]').forEach(function (b) { b.classList.toggle('on', b === el); });
      return;
    }
    if ((el = t.closest('[data-step]'))) {
      var inp = el.parentNode.querySelector('input');
      inp.value = Math.max(1, Math.min(99, (parseInt(inp.value, 10) || 1) + parseInt(el.getAttribute('data-step'), 10)));
      return;
    }
    if ((el = t.closest('[data-add]'))) {
      var id = el.getAttribute('data-add');
      var scope = el.closest('[data-product]');
      var qi = !el.hasAttribute('data-qty-fixed') && scope ? scope.querySelector('[data-qty] input') : null;
      var q = qi ? Math.max(1, parseInt(qi.value, 10) || 1) : 1;
      add(id, q);
      if (qi) qi.value = 1;
      var p = byId[id];
      var img = scope && scope.querySelector('img') ? scope.querySelector('img') : el;
      flyToCart(img, p.image);
      var inModal = el.closest('.modal');
      if (inModal) closeModal(inModal);
      toast('<img src="' + esc(p.image) + '" alt=""><span><b>' + esc(p.name) + '</b> sepete eklendi' + (q > 1 ? ' (' + q + ' adet)' : '') + '</span><button type="button" data-open-cart>Sepete Git</button>');
      track('AddToCart', [{ id: p.id, qty: q }]);
      return;
    }
    if ((el = t.closest('[data-rm]'))) { setQty(el.getAttribute('data-rm'), 0); return; }
    if ((el = t.closest('[data-d]'))) {
      var lid = el.closest('[data-line-qty]').getAttribute('data-line-qty');
      setQty(lid, (cart[lid] || 0) + parseInt(el.getAttribute('data-d'), 10));
      return;
    }
    if ((el = t.closest('a[data-track="wa"]'))) { track('Contact', [], { method: 'whatsapp' }); }
    if ((el = t.closest('a[data-track="call"]'))) { track('Contact', [], { method: 'phone' }); }

    // Mobil menü linkleri
    if (t.closest('.nav a')) toggleNav(false);
  });

  document.addEventListener('change', function (e) {
    var w = e.target.closest('[data-line-qty]');
    if (w) { var v = parseInt(e.target.value, 10); setQty(w.getAttribute('data-line-qty'), isNaN(v) ? 1 : v); }
    if (e.target.name === 'payment') render();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var open = $$('.modal:not([hidden])'); if (open.length) closeModal(open[open.length - 1]);
      else if (drawer && drawer.classList.contains('open')) closeCart();
      else toggleNav(false);
    }
  });

  /* ---------------- Header & menü ---------------- */
  var header = $('.header'), burger = $('.burger'), nav = $('#nav'), mbar = $('.mbar'), wa = $('.wa-float');
  function toggleNav(force) {
    if (!nav) return;
    var open = typeof force === 'boolean' ? force : !nav.classList.contains('open');
    nav.classList.toggle('open', open);
    if (burger) burger.setAttribute('aria-expanded', String(open));
  }
  if (burger) burger.addEventListener('click', function () { toggleNav(); });
  var ticking = false;
  function onScroll() {
    var y = window.scrollY;
    if (header) header.classList.toggle('is-scrolled', y > 10);
    if (mbar) mbar.classList.toggle('show', y > 420);
    if (wa) wa.classList.toggle('show', y > 420);
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(onScroll); ticking = true; } }, { passive: true });
  onScroll();
  if (wa) {
    setTimeout(function () { wa.classList.add('show-tip'); setTimeout(function () { wa.classList.remove('show-tip'); }, 5000); }, 9000);
  }

  /* ---------------- Scroll reveal ---------------- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    $$('.reveal').forEach(function (el) { el.classList.add('is-in'); });
  }

  // Hero paralaks (yalnızca masaüstü)
  var visual = $('.hero__visual');
  if (visual && window.matchMedia('(pointer: fine)').matches && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var hero = $('.hero');
    hero.addEventListener('mousemove', function (e) {
      var r = hero.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      visual.style.transform = 'translate3d(' + (x * -14) + 'px,' + (y * -10) + 'px,0)';
    });
    hero.addEventListener('mouseleave', function () { visual.style.transform = ''; });
    visual.style.transition = 'transform .6s cubic-bezier(.22,1,.36,1)';
  }

  /* ---------------- Sipariş formu ---------------- */
  var form = $('#orderForm');
  if (form) {
    var phoneInput = form.querySelector('[name="phone"]');
    phoneInput.addEventListener('input', function () {
      var d = this.value.replace(/\D/g, '');
      if (d.indexOf('90') === 0 && d.length > 11) d = d.slice(2);
      if (d && d[0] !== '0') d = '0' + d;
      d = d.slice(0, 11);
      var o = d.slice(0, 4);
      if (d.length > 4) o += ' ' + d.slice(4, 7);
      if (d.length > 7) o += ' ' + d.slice(7, 9);
      if (d.length > 9) o += ' ' + d.slice(9, 11);
      this.value = o;
    });
    $$('input, select, textarea', form).forEach(function (f) {
      f.addEventListener('input', function () { var w = f.closest('.field, .consent'); if (w) w.classList.remove('invalid'); $('[data-form-error]').hidden = true; });
      f.addEventListener('change', function () { var w = f.closest('.field, .consent'); if (w) w.classList.remove('invalid'); $('[data-form-error]').hidden = true; });
    });
    form.addEventListener('focusin', function () {
      if (!startedCheckout && count()) { startedCheckout = true; track('InitiateCheckout', cartItems()); }
    });
    var paymentTracked = false;
    form.addEventListener('change', function (e) {
      if (e.target.name === 'payment' && !paymentTracked && count()) { paymentTracked = true; track('AddPaymentInfo', cartItems(), { payment: e.target.value }); }
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errBox = $('[data-form-error]');
      errBox.hidden = true;
      var errors = [];
      var data = {};
      ['name', 'phone', 'city', 'district', 'address', 'email', 'note', 'website'].forEach(function (k) {
        var f = form.querySelector('[name="' + k + '"]'); data[k] = f ? f.value.trim() : '';
      });
      data.payment = payment();
      data.consent = form.querySelector('[name="consent"]').checked;
      data.items = Object.keys(cart).map(function (id) { return { id: +id, qty: cart[id] }; });

      function bad(name, msg) {
        var f = form.querySelector('[name="' + name + '"]');
        var w = f && f.closest('.field, .consent'); if (w) w.classList.add('invalid');
        errors.push(msg);
      }
      if (!data.items.length) errors.push('Sepetiniz boş. Lütfen en az bir ürün ekleyin.');
      if (data.name.length < 3) bad('name', 'Lütfen adınızı ve soyadınızı yazın.');
      if (data.phone.replace(/\D/g, '').length !== 11) bad('phone', 'Lütfen geçerli bir cep telefonu numarası yazın (05XX XXX XX XX).');
      if (!data.city) bad('city', 'Lütfen ilinizi seçin.');
      if (data.district.length < 2) bad('district', 'Lütfen ilçenizi yazın.');
      if (data.address.length < 10) bad('address', 'Lütfen açık adresinizi eksiksiz yazın.');
      if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) bad('email', 'E-posta adresi geçersiz görünüyor.');
      if (!data.payment) errors.push('Lütfen bir ödeme yöntemi seçin.');
      if (!data.consent) bad('consent', 'Devam etmek için sözleşmeyi onaylamanız gerekiyor.');
      if (S.min_order > 0 && subtotal() < S.min_order) errors.push('Minimum sipariş tutarı ' + money(S.min_order) + '.');

      if (errors.length) {
        errBox.textContent = errors[0]; errBox.hidden = false;
        var first = form.querySelector('.invalid input, .invalid select, .invalid textarea');
        if (first) first.focus(); else errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      var btn = $('[data-submit]');
      btn.disabled = true; btn.classList.add('loading');
      fetch('api/order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Sunucu yanıtı okunamadı.' }; }); })
        .then(function (res) {
          btn.disabled = false; btn.classList.remove('loading');
          if (!res.ok) { errBox.textContent = res.error || 'Bir sorun oluştu, lütfen tekrar deneyin.'; errBox.hidden = false; return; }
          success(res, data);
        })
        .catch(function () {
          btn.disabled = false; btn.classList.remove('loading');
          errBox.innerHTML = 'Bağlantı hatası oluştu. Lütfen tekrar deneyin ya da <a href="https://wa.me/' + S.whatsapp + '" target="_blank" rel="noopener">WhatsApp\'tan sipariş verin</a>.';
          errBox.hidden = false;
        });
    });
  }

  function success(res, data) {
    $('[data-ok-no]').textContent = res.order_no;
    $('[data-ok-total]').textContent = money(res.total);
    var bank = $('[data-ok-bank]');
    if (res.bank) {
      bank.hidden = false;
      bank.innerHTML = res.bank.iban
        ? '<strong>Havale / EFT bilgileri</strong>' + (res.bank.bank ? esc(res.bank.bank) + '<br>' : '') + 'Alıcı: ' + esc(res.bank.holder) + '<br>IBAN: <code>' + esc(res.bank.iban) + '</code><br><small>Açıklama kısmına sipariş numaranızı (' + esc(res.order_no) + ') yazmayı unutmayın.</small>'
        : '<strong>Havale / EFT</strong>IBAN bilgilerimiz WhatsApp veya telefon ile size hemen iletilecektir.';
    } else { bank.hidden = true; }
    var lines = res.lines.map(function (l) { return '• ' + l.qty + ' x ' + l.name; }).join('\n');
    var msg = 'Merhaba, web sitenizden sipariş verdim.\nSipariş No: ' + res.order_no + '\n' + lines + '\nToplam: ' + money(res.total) + '\nÖdeme: ' + res.payment_label + '\nAd Soyad: ' + data.name;
    $('[data-ok-wa]').href = 'https://wa.me/' + S.whatsapp + '?text=' + encodeURIComponent(msg);
    if (res.meta_event_id) {
      // Gelişmiş eşleştirme: Pixel, müşteri bilgilerini tarayıcıda SHA-256 ile şifreleyerek gönderir
      if (window.fbq && S.pixel_id) {
        var norm = function (v) { return String(v || '').replace(/İ/g, 'i').replace(/I/g, 'ı').toLowerCase().replace(/[çğıöşüâîû]/g, function (c) { return { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'â': 'a', 'î': 'i', 'û': 'u' }[c]; }).replace(/[^a-z]/g, ''); };
        var parts = data.name.trim().split(/\s+/);
        var am = { ph: '9' + data.phone.replace(/\D/g, ''), fn: norm(parts[0]), ct: norm(data.city), country: 'tr' };
        if (parts.length > 1) am.ln = norm(parts[parts.length - 1]);
        if (data.email) am.em = data.email;
        if (S.vid) am.external_id = S.vid;
        try { window.fbq('init', S.pixel_id, am); } catch (e) {}
      }
      // Sunucu Purchase olayını zaten Dönüşümler API'sine gönderdi; Pixel aynı event_id ile gönderir
      var pd = customData(res.lines);
      pd.value = res.total; pd.order_id = res.order_no;
      track('Purchase', res.lines, { eventId: res.meta_event_id, data: pd, serverSide: false });
    }
    cart = {}; save();
    form.reset();
    var first = form.querySelector('input[name="payment"]'); if (first) first.checked = true;
    render();
    openModal('successModal');
  }

  render();
})();
