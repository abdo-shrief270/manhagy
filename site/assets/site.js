/* منهجي — landing page: WhatsApp links, the lead form, headline per ad, and optional measurement. */
(function () {
  var C = window.MANHAGY || {};
  var num = String(C.whatsapp || '').replace(/\D/g, '');
  var qs = new URLSearchParams(location.search);

  function remember(key, value) {
    try {
      if (value === undefined) return sessionStorage.getItem(key) || '';
      sessionStorage.setItem(key, value);
    } catch (e) { /* private mode: ignore */ }
    return value || '';
  }

  // Where the visitor came from (rides on the WhatsApp message so each lead shows its ad).
  var source = [qs.get('utm_source'), qs.get('utm_campaign'), qs.get('utm_content')].filter(Boolean).join(' / ');
  if (!source && qs.get('fbclid')) source = 'facebook';
  if (source) remember('mh-src', source); else source = remember('mh-src');

  // Headline that matches the ad the visitor clicked: ?v=leak | ?v=codes (default = your own platform).
  var variants = {
    leak: {
      h1: 'فيديوهاتك عليها <em>اسم كل طالب</em>… ولو اتسرّبت تعرف من مين',
      lead: 'منهجي بيحمي شرحك: علامة مائية متحركة باسم الطالب ورقمه، ولينكات بتقفل بعد دقايق، وجهازين بالكتير لكل طالب. وكمان أكواد وامتحانات وتقرير لولي الأمر، على منصة باسمك.'
    },
    codes: {
      h1: 'بطّل تراجع <em>سكرينات التحويل</em> كل ليلة',
      lead: 'اطبع كروت أكواد باسمك ووزّعها على المكتبات والسناتر. الطالب يفعّل الكود في ثانية والكورس يتفتح له لوحده، وانت تعرف مين استخدم كل كود وإمتى.',
      img: '/assets/img/redeem.webp',
      alt: 'الطالب بيفعّل كود من كارت مطبوع باسم المدرس',
      f1: 'كل كود بيتفعّل مرة واحدة بس'
    }
  };
  var v = variants[qs.get('v')];
  if (v) {
    var h1 = document.querySelector('[data-h1]');
    var lead = document.querySelector('[data-lead]');
    if (h1) h1.innerHTML = v.h1;
    if (lead) lead.textContent = v.lead;
    var heroImg = document.querySelector('.hero-phone img');
    if (v.img && heroImg) { heroImg.src = v.img; heroImg.alt = v.alt; }
    var f1 = document.querySelector('[data-f1]');
    if (v.f1 && f1) f1.textContent = v.f1;
  }

  // Measurement, only when an id is set in window.MANHAGY.
  if (C.metaPixelId) {
    !function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
      t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', C.metaPixelId);
    window.fbq('track', 'PageView');
  }
  if (C.gaId) {
    var g = document.createElement('script');
    g.async = true;
    g.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(C.gaId);
    document.head.appendChild(g);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', C.gaId);
  }
  function track(metaEvent, gaEvent, data) {
    try { if (metaEvent && window.fbq) window.fbq('track', metaEvent, data || {}); } catch (e) { /* ignore */ }
    try { if (gaEvent && window.gtag) window.gtag('event', gaEvent, data || {}); } catch (e) { /* ignore */ }
  }

  function waLink(text) { return 'https://wa.me/' + num + '?text=' + encodeURIComponent(text); }
  function openWhatsApp(url) {
    var w = window.open(url, '_blank');
    if (w) { try { w.opener = null; } catch (e) { /* ignore */ } } else { location.href = url; }
  }

  var hello = 'أهلاً، عايز أعرف أكتر عن منهجي' + (source ? '\n(من: ' + source + ')' : '');
  document.querySelectorAll('[data-wa]').forEach(function (a) {
    if (!num) { a.hidden = true; return; }
    a.href = waLink(hello);
    a.addEventListener('click', function () { track('Contact', 'contact_whatsapp', { place: a.getAttribute('data-place') || '' }); });
  });
  document.querySelectorAll('[data-cta]').forEach(function (a) {
    a.addEventListener('click', function () { track(null, 'cta_click', { place: a.getAttribute('data-cta') }); });
  });

  var form = document.getElementById('lead');
  if (form) {
    var f = form.elements;
    var subjectLabel = form.querySelector('[data-subject-label]');
    form.addEventListener('change', function (e) {
      if (e.target.name !== 'role') return;
      var center = e.target.value === 'سنتر';
      if (subjectLabel) subjectLabel.textContent = center ? 'اسم السنتر والمدينة' : 'بتدرّس إيه؟';
      f.subject.placeholder = center ? 'مثلاً: سنتر الأوائل — المنصورة' : 'فيزياء، إنجليزي، رياضيات…';
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var ok = true;
      ['name', 'subject', 'stage', 'students'].forEach(function (n) {
        var el = f[n];
        var bad = !String(el.value).trim();
        el.closest('.fld').classList.toggle('bad', bad);
        el.setAttribute('aria-invalid', bad ? 'true' : 'false');
        if (bad && ok) { el.focus(); ok = false; }
      });
      form.querySelector('.err').hidden = ok;
      if (!ok) return;
      var center = f.role.value === 'سنتر';
      var lines = [
        'أهلاً، أنا ' + f.name.value.trim() + (center ? ' من ' : ' — مدرس ') + f.subject.value.trim(),
        'المرحلة: ' + f.stage.value,
        'عدد الطلاب تقريباً: ' + f.students.value
      ];
      if (f.now.value) lines.push('ببيع دلوقتي: ' + f.now.value);
      lines.push('عايز أحجز مكان في أول دفعة من منهجي.');
      if (source) lines.push('(من: ' + source + ')');
      track('Lead', 'generate_lead', { content_name: center ? 'center' : 'teacher', content_category: f.stage.value });
      if (num) openWhatsApp(waLink(lines.join('\n')));
    });
  }

  document.querySelectorAll('[data-year]').forEach(function (el) { el.textContent = String(new Date().getFullYear()); });
})();
