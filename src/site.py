"""Builds the static landing page (site/index.html + site/privacy.html) with inline icons."""
import re

from common import icon, mark, write

ICON_RE = re.compile(r'\[\[i:([a-z0-9-]+)(?::(\d+))?(?::([^\]]+))?\]\]')


def icons(html):
    return ICON_RE.sub(lambda m: icon(m.group(1), int(m.group(2) or 22), m.group(3) or 'currentColor', cls='ic'), html)


HEAD = """<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{title}</title>
<meta name="description" content="{description}">
<meta name="theme-color" content="#1E1B4B">
<link rel="canonical" href="https://@DOMAIN@{path}">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta property="og:type" content="website">
<meta property="og:locale" content="ar_EG">
<meta property="og:site_name" content="منهجي">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{description}">
<meta property="og:url" content="https://@DOMAIN@{path}">
<meta property="og:image" content="https://@DOMAIN@/assets/og.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<!-- Meta domain verification: paste the tag Meta gives you here -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/site.css">
</head>
"""

CONFIG = """<script>
/* ===== الإعدادات: غيّر هنا بس ===== */
window.MANHAGY = {
  whatsapp: '201555440882',   // رقم الواتساب بالصيغة الدولية من غير + ولا مسافات
  metaPixelId: '',            // Meta Pixel ID (سيبه فاضي لو مش عايز قياس)
  gaId: '',                   // Google Analytics 4 مثلاً G-XXXXXXX (اختياري)
};
</script>
"""

LOGO = lambda uid: f'<a class="logo" href="/" aria-label="منهجي — الرئيسية">{mark(36, uid)}<span><b>منهجي</b><small>MANHAGY</small></span></a>'

FLOAT_WA = """<a class="wa-fab" data-wa data-place="fab" href="https://wa.me/201555440882" target="_blank" rel="noopener" aria-label="كلمنا واتساب">[[i:message-circle:28:#fff]]</a>"""

FOOTER = """<footer class="foot">
  <div class="wrap foot-in">
    <div>{logo}<p>منصة تعليمية للمدرسين والسناتر في مصر، من أولى ابتدائي لحد الجامعة.</p></div>
    <nav aria-label="روابط"><a href="/privacy.html">الخصوصية</a><a data-wa data-place="footer" href="https://wa.me/201555440882" target="_blank" rel="noopener">واتساب</a></nav>
  </div>
  <div class="wrap foot-copy">© <span data-year>2026</span> منهجي</div>
</footer>"""


def index():
    pains = [
        ('video-off', 'الفيديو بيتسرّب على تليجرام في نفس اليوم', 'وبيتباع ببلاش، ومش عارف مين اللي سرّبه.'),
        ('receipt', 'سكرينات تحويلات كل ليلة', 'تراجعها واحدة واحدة، وتفتح لكل طالب بإيدك.'),
        ('file-question', 'امتحانات على Google Forms', 'من غير بنك أسئلة، ولا تحليل يقولك مين محتاج متابعة.'),
        ('phone-call', 'ولي الأمر بيسأل: «هو ابني بيذاكر؟»', 'وانت مش فاضي ترد على كل واحد لوحده.'),
    ]
    pains_html = ''.join(
        f'<div class="pain">[[i:{ic}:24:#DC2626]]<div><b>{t}</b><p>{d}</p></div></div>' for ic, t, d in pains)
    features = [
        ('shield-check', '#4F46E5', '#EEF2FF', 'فيديو محمي باسم كل طالب', 'علامة مائية متحركة باسم الطالب ورقمه، ولينك بيقفل بعد دقايق، ومفيش تحميل. لو حد سرّب، تعرف مين.', ''),
        ('ticket', '#B45309', '#FEF3C7', 'أكواد وكروت باسمك', 'اطبع كروت ووزّعها على المكتبات والسناتر. كل كود بيتفعّل مرة واحدة، وبتعرف مين استخدمه وإمتى.', ''),
        ('clipboard-check', '#0D9488', '#CCFBF1', 'امتحانات بتتصحّح لوحدها', 'بنك أسئلة، ووقت محسوب على السيرفر، وترتيب مختلف لكل طالب. والمقالي يصحّحه مساعدك من شاشة واحدة.', ''),
        ('send', '#16A34A', '#DCFCE7', 'تقرير لولي الأمر', 'كل أسبوع: شاف كام درس، ودرجاته، وحضوره. رسالة واتساب جاهزة تتبعت بضغطة.', ''),
        ('user-check', '#7C3AED', '#EDE9FE', 'مساعدينك بصلاحيات', 'كل مساعد بحسابه: يصحّح، ويرد على الأسئلة، ويفعّل أكواد، ويفك جهاز. وكل حاجة متسجّلة باسم اللي عملها.', ''),
        ('smartphone', '#E11D48', '#FFE4E6', 'لينك وتطبيق باسمك', 'طلابك بيشوفوا اسمك ولوجوك، مش اسم منصة تانية. ويتنزّل على الموبايل زي أي تطبيق.', ''),
        ('chart-column', '#2563EB', '#DBEAFE', 'لوحة تعرّفك كل حاجة', 'الإيراد، والطلاب النشطين، والمشاهدات، وتنبيهات المشاركة… من موبايلك.', ''),
        ('school', '#475569', '#F1F5F9', 'السنتر في نفس المكان', 'حضور بالـ QR شغال حتى لو النت قاطع، وتحصيل الحصص، ونصيب كل مدرس آخر الشهر.', 'قريباً'),
    ]
    feat_html = ''.join(
        f'<article class="feat"><span class="feat-ic" style="background:{bg};color:{c}">[[i:{ic}:24]]</span>'
        f'<h3>{t}{f" <em>{badge}</em>" if badge else ""}</h3><p>{d}</p></article>'
        for ic, c, bg, t, d, badge in features)
    shots = [
        ('player', 'الفيديو عليه اسم الطالب', 'ورقمه بيتحرّك على الشاشة، وعدد مرات المشاهدة محدود.'),
        ('exam', 'امتحان بوقت على السيرفر', 'ترتيب مختلف لكل طالب، والتصحيح لوحده.'),
        ('redeem', 'الكود بيتفعّل في ثانية', 'من كارت مطبوع باسمك، ومرة واحدة بس.'),
        ('parent', 'التقرير بيوصل ولي الأمر', 'كل أسبوع على واتساب: دروس ودرجات وحضور.'),
        ('home-arabic', 'وللأطفال كمان', 'شاشة بسيطة ونجوم وشارات، وولي الأمر متابع.'),
    ]
    shots_html = ''.join(
        f'<figure class="shot"><div class="phone"><img src="/assets/img/{img}.webp" width="600" height="1298" loading="lazy" decoding="async" alt="{t}"></div>'
        f'<figcaption><b>{t}</b><span>{d}</span></figcaption></figure>' for img, t, d in shots)
    stages = ['ابتدائي', 'إعدادي', 'ثانوي عام', 'ثانوي أزهري', 'لغات و IG', 'الجامعة', 'السناتر']
    stages_html = ''.join(f'<span>{s}</span>' for s in stages)
    faqs = [
        ('هل كده الفيديو مستحيل يتسرّب؟',
         'بصراحة، مفيش منصة تقدر تقول كده. اللي بنعمله إن السرقة تبقى صعبة ومكشوفة: الفيديو مش بيتحمّل، واللينك بيقفل بعد دقايق، وعليه اسم الطالب ورقمه بيتحرّك على الشاشة، وكل طالب ليه جهازين بالكتير. لو حد صوّره وسرّبه، هتعرف من مين وتقفل حسابه.'),
        ('طلابي هيدفعوا إزاي؟',
         'بالأكواد (كروت بتطبعها وتوزّعها على المكتبات والسناتر)، أو بالتحويل (إنستاباي أو محفظة) ومساعدك يأكّد التحويل، والكورس يتفتح للطالب لوحده. والفلوس بتوصلك انت.'),
        ('بكام؟',
         'اشتراك شهري على حسب عدد طلابك النشطين، فالمدرس اللي لسه بيبدأ بيدفع قليل. أول دفعة من المدرسين بتجرّب ببلاش، وهتعرف السعر بالظبط قبل ما تدفع أي حاجة.'),
        ('طلابي في محافظات والنت عندهم ضعيف',
         'الفيديو بيتظبط على سرعة النت لوحده. وفي تطبيق الموبايل الجاي، الطالب هيقدر ينزّل الدرس جوه التطبيق متشفّر ويتفرج من غير نت، والفيديو مش هيطلع برا التطبيق.'),
        ('ينفع لمدرس ابتدائي أو جامعة؟',
         'أيوه. في ابتدائي ولي الأمر هو اللي بيعمل الحساب وبيتابع أكتر من طفل، والشاشة أبسط للطفل. وطالب الجامعة حسابه لنفسه، والكورسات بالكلية والمادة.'),
        ('أنا سنتر مش مدرس',
         'السنتر هياخد حضور بالـ QR شغال حتى لو النت قاطع، وتحصيل الحصص، ونصيب كل مدرس آخر الشهر، ومعاه منصة لمدرسينه. احجز وقولنا عدد القاعات والمدرسين.'),
        ('هتبدأوا إمتى؟',
         'بنجهّز أول دفعة دلوقتي. لما تحجز، هنكلمك على واتساب ونتفق على ميعاد يناسب بداية الشهر أو الترم عندك، ونجهّز منصتك معاك.'),
        ('بيانات طلابي في أمان؟',
         'بيانات طلابك ملكك: مش بنبيعها، ومش بنعمل عليها إعلانات، وماشيين على قانون حماية البيانات الشخصية المصري. والطالب اللي تحت 18 حسابه بموافقة ولي أمره.'),
    ]
    faq_html = ''.join(f'<details><summary>{q}[[i:chevron-down:20]]</summary><p>{a}</p></details>' for q, a in faqs)

    body = f"""<body>
<a class="skip" href="#main">انتقل للمحتوى</a>
<header class="top">
  <div class="wrap top-in">
    {LOGO('h')}
    <nav class="top-nav" aria-label="أقسام الصفحة"><a href="#features">المميزات</a><a href="#how">إزاي بيشتغل</a><a href="#faq">أسئلة</a></nav>
    <a class="btn btn-amber btn-sm" href="#join" data-cta="header">احجز مكانك</a>
  </div>
</header>

<main id="main">
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-text">
      <span class="eyebrow">[[i:graduation-cap:18]] للمدرسين والسناتر · لكل المراحل</span>
      <h1 data-h1>منصة وتطبيق <em>باسمك</em> لطلابك</h1>
      <p class="lead" data-lead>ارفع شرحك محمي بعلامة مائية باسم كل طالب، وبيع بالأكواد أو التحويل، وامتحن طلابك وتابع مستواهم، وولي الأمر يوصله تقرير كل أسبوع على واتساب.</p>
      <div class="cta-row">
        <a class="btn btn-amber" href="#join" data-cta="hero">احجز مكانك في أول دفعة [[i:arrow-left:20]]</a>
        <a class="btn btn-ghost" data-wa data-place="hero" href="https://wa.me/201555440882" target="_blank" rel="noopener">[[i:message-circle:20]] اسألنا على واتساب</a>
      </div>
      <p class="hero-note">[[i:circle-check:18:#86EFAC]] بنجهّز المنصة مع أول دفعة من المدرسين بنفسنا، والتجربة مجانية.</p>
    </div>
    <div class="hero-visual">
      <div class="phone hero-phone"><img src="/assets/img/player.webp" width="600" height="1298" fetchpriority="high" alt="فيديو درس عليه اسم الطالب ورقمه"></div>
      <div class="float f1">[[i:scan-eye:20:#92400E]] <span data-f1>اسم الطالب ورقمه على الفيديو</span></div>
      <div class="float f2"><span class="ok">[[i:check:16:#fff]]</span><span><b>كود اتفعّل</b><small>يوسف أحمد · شهر نوفمبر</small></span></div>
      <div class="float f3"><span class="wa">[[i:send:15:#fff]]</span><span><b>تقرير ولي الأمر اتبعت</b><small>17 من 20 في امتحان الفصل</small></span></div>
    </div>
  </div>
  <div class="wrap stages" aria-label="المراحل">{stages_html}</div>
</section>

<section class="pains">
  <div class="wrap">
    <div class="sec-head"><span class="kicker">المشكلة</span><h2>لو بتشرح أونلاين أو في سنتر، أكيد قابلت ده</h2></div>
    <div class="pain-grid">{pains_html}</div>
    <p class="pain-fix">[[i:sparkles:20:#4F46E5]] منهجي معمول عشان يحل الحاجات دي بالظبط.</p>
  </div>
</section>

<section class="features" id="features">
  <div class="wrap">
    <div class="sec-head"><span class="kicker">المميزات</span><h2>كل اللي محتاجه عشان تشتغل أونلاين باحتراف</h2>
      <p>منصة واحدة: الشرح، والبيع، والامتحانات، والمتابعة. وانت بتركّز في الشرح.</p></div>
    <div class="feat-grid">{feat_html}</div>
  </div>
</section>

<section class="showcase">
  <div class="wrap">
    <div class="sec-head"><span class="kicker">من جوه</span><h2>شوف منهجي بعين الطالب وولي الأمر</h2></div>
  </div>
  <div class="shots" tabindex="0" aria-label="صور من التطبيق">{shots_html}</div>
</section>

<section class="dash">
  <div class="wrap dash-in">
    <div class="dash-text">
      <span class="kicker">لوحة المدرس</span>
      <h2>وانت شايف كل حاجة من لوحة واحدة</h2>
      <ul class="ticks">
        <li>[[i:circle-check:22:#16A34A]] الإيراد من الأكواد والتحويلات والسنتر، يوم بيوم</li>
        <li>[[i:circle-check:22:#16A34A]] الطلاب النشطين، والمشاهدات، والامتحانات اللي مستنية تصحيح</li>
        <li>[[i:circle-check:22:#16A34A]] تنبيه لو حساب اتفتح من مكانين، أو كود اتسرق</li>
        <li>[[i:circle-check:22:#16A34A]] أكتر الأسئلة اللي طلابك غلطوا فيها، عشان تراجعها معاهم</li>
      </ul>
    </div>
    <div class="browser"><div class="bar"><i></i><i></i><i></i><span class="ltr">mr-mohamed.@DOMAIN@/dashboard</span></div>
      <img src="/assets/img/dashboard.webp" width="1600" height="1033" loading="lazy" decoding="async" alt="لوحة المدرس: الإيراد والطلاب والتنبيهات"></div>
  </div>
</section>

<section class="brand">
  <div class="wrap brand-in">
    <div class="brand-phones" aria-hidden="true">
      <div class="phone p-side p-r"><img src="/assets/img/home-arabic.webp" width="600" height="1298" loading="lazy" decoding="async" alt=""></div>
      <div class="phone p-side p-l"><img src="/assets/img/home-uni.webp" width="600" height="1298" loading="lazy" decoding="async" alt=""></div>
      <div class="phone p-mid"><img src="/assets/img/home-physics.webp" width="600" height="1298" loading="lazy" decoding="async" alt=""></div>
    </div>
    <div class="brand-text">
      <span class="kicker">باسمك انت</span>
      <h2>كل مدرس ليه منصته</h2>
      <p>طلابك بيشوفوا اسمك ولوجوك ولونك، مش اسم منصة تانية. لينك واحد تبعته في الجروب، وفيه كل حاجة.</p>
      <div class="urls"><span class="ltr">mr-mohamed.@DOMAIN@</span><span class="ltr">miss-heba.@DOMAIN@</span><span class="ltr">dr-karim.@DOMAIN@</span></div>
      <p class="muted">ولو عندك دومين خاص بيك، ينفع نربطه.</p>
    </div>
  </div>
</section>

<section class="how" id="how">
  <div class="wrap">
    <div class="sec-head"><span class="kicker">إزاي بيشتغل</span><h2>3 خطوات وتبدأ</h2></div>
    <ol class="steps">
      <li><span class="n">1</span><h3>احجز مكانك</h3><p>املأ الفورم، وهنكلمك على واتساب نعرف طريقتك في الشغل.</p></li>
      <li><span class="n">2</span><h3>نجهّز منصتك معاك</h3><p>اسمك ولوجوك، وكورساتك، وأول دفعة أكواد. إحنا معاك خطوة بخطوة.</p></li>
      <li><span class="n">3</span><h3>ابعت اللينك لطلابك</h3><p>يسجّلوا برقم الموبايل ويبدأوا يتفرجوا، وانت تتابع من لوحتك.</p></li>
    </ol>
    <div class="price">
      <div><span class="kicker">الأسعار</span><h3>اشتراك شهري على قد طلابك</h3>
        <p>بتدفع على حسب عدد طلابك النشطين، فالمدرس اللي لسه بيبدأ بيدفع قليل. والأكواد تولّدها براحتك، والفلوس اللي طلابك بيدفعوها بتوصلك انت.</p></div>
      <a class="btn btn-indigo" href="#join" data-cta="pricing">احجز وجرّب ببلاش [[i:arrow-left:20]]</a>
    </div>
  </div>
</section>

<section class="faq" id="faq">
  <div class="wrap faq-in">
    <div class="sec-head"><span class="kicker">أسئلة</span><h2>أسئلة بتتسأل كتير</h2></div>
    <div class="faq-list">{faq_html}</div>
  </div>
</section>

<section class="join" id="join">
  <div class="wrap join-in">
    <div class="join-text">
      <h2>احجز مكانك في أول دفعة</h2>
      <p>اكتب بياناتك، وهتفتح لك محادثة واتساب جاهزة تبعتها لنا. بنجهّز أول دفعة من المدرسين بنفسنا، والتجربة مجانية.</p>
      <ul class="ticks light">
        <li>[[i:circle-check:22:#86EFAC]] من غير ما تدفع حاجة دلوقتي</li>
        <li>[[i:circle-check:22:#86EFAC]] بنجهّز المنصة معاك خطوة بخطوة</li>
        <li>[[i:circle-check:22:#86EFAC]] رأيك هيشكّل المنتج</li>
      </ul>
    </div>
    <form class="form" id="lead" novalidate>
      <fieldset class="seg"><legend>انت</legend>
        <label><input type="radio" name="role" value="مدرس" checked><span>مدرس</span></label>
        <label><input type="radio" name="role" value="سنتر"><span>سنتر</span></label>
      </fieldset>
      <label class="fld"><span>الاسم</span><input name="name" autocomplete="name" required placeholder="مثلاً: محمد سامي"></label>
      <label class="fld"><span data-subject-label>بتدرّس إيه؟</span><input name="subject" required placeholder="فيزياء، إنجليزي، رياضيات…"></label>
      <div class="two">
        <label class="fld"><span>المرحلة</span><select name="stage" required>
          <option value="">اختار</option><option>ابتدائي</option><option>إعدادي</option><option>ثانوي عام</option><option>ثانوي أزهري</option><option>لغات و IG</option><option>جامعة</option><option>أكتر من مرحلة</option></select></label>
        <label class="fld"><span>عدد طلابك تقريباً</span><select name="students" required>
          <option value="">اختار</option><option>أقل من 100</option><option>من 100 لـ 500</option><option>من 500 لـ 2000</option><option>أكتر من 2000</option></select></label>
      </div>
      <label class="fld"><span>بتبيع شرحك إزاي دلوقتي؟ <small>(اختياري)</small></span><select name="now">
        <option value="">اختار</option><option>لسه مش ببيع أونلاين</option><option>يوتيوب أو تليجرام</option><option>جروبات واتساب</option><option>أكواد ورق</option><option>منصة تانية</option></select></label>
      <p class="err" role="alert" hidden>كمّل الخانات اللي عليها علامة.</p>
      <button class="btn btn-wa" type="submit">[[i:message-circle:22]] ابعت على واتساب</button>
      <p class="fine">[[i:lock:15]] الصفحة دي مش بتحفظ بياناتك. الرسالة بتتكتب جاهزة، وانت اللي بتبعتها.</p>
    </form>
  </div>
</section>
</main>
{FOOTER.format(logo=LOGO('f'))}
{FLOAT_WA}
<script src="/assets/site.js" defer></script>
</body>
</html>
"""
    head = HEAD.format(
        title='منهجي — منصة وتطبيق باسمك لطلابك',
        description='منصة للمدرسين والسناتر في مصر: فيديو محمي باسم كل طالب، أكواد وكروت باسمك، امتحانات بتتصحّح لوحدها، وتقرير لولي الأمر كل أسبوع.',
        path='/')
    write('site/index.html', head + CONFIG + icons(body))


def privacy():
    body = f"""<body class="doc">
<header class="top"><div class="wrap top-in">{LOGO('h')}<a class="btn btn-amber btn-sm" href="/#join">احجز مكانك</a></div></header>
<main class="wrap prose">
  <h1>الخصوصية</h1>
  <p class="muted">آخر تحديث: أكتوبر 2026</p>
  <h2>مين إحنا</h2>
  <p>منهجي منصة تعليمية للمدرسين والسناتر في مصر، لسه بنجهّزها مع أول دفعة من المدرسين.</p>
  <h2>الصفحة دي بتجمع إيه</h2>
  <p>الفورم اللي في الصفحة <b>مش بيحفظ أي حاجة</b>. بيكتب رسالة واتساب جاهزة باللي كتبته، وانت اللي بتقرر تبعتها أو لأ.</p>
  <h2>القياس والإعلانات</h2>
  <p>لو مفعّلين، بنستخدم Meta Pixel و Google Analytics عشان نعرف عدد الزيارات واللي ضغطوا على أزرار التواصل، ونقيس الإعلانات اللي جابتهم. الأدوات دي بتستخدم كوكيز، ومش بنبعت لهم اسمك ولا رقمك ولا اللي كتبته في الفورم. تقدر تمنعها من إعدادات المتصفح أو بإضافة لمنع التتبع.</p>
  <h2>محادثة واتساب</h2>
  <p>لما تبعت لنا على واتساب، بنستخدم اسمك ورقمك واللي قلته عشان نرد عليك ونتابع معاك بس. مش بنبيع بياناتك ولا بنديها لحد. والمحادثة نفسها بتمشي على شروط واتساب.</p>
  <h2>حقك</h2>
  <p>تقدر تطلب نمسح محادثتك وبياناتك في أي وقت، على نفس رقم الواتساب.</p>
  <p><a class="btn btn-wa" data-wa data-place="privacy" href="https://wa.me/201555440882" target="_blank" rel="noopener">[[i:message-circle:20]] كلمنا واتساب</a></p>
</main>
{FOOTER.format(logo=LOGO('f'))}
<script src="/assets/site.js" defer></script>
</body>
</html>
"""
    head = HEAD.format(title='الخصوصية — منهجي', description='إزاي صفحة منهجي بتتعامل مع بياناتك: الفورم مش بيحفظ حاجة، والقياس بـ Meta Pixel و Google Analytics لو مفعّلين.', path='/privacy.html')
    write('site/privacy.html', head + CONFIG + icons(body))


if __name__ == '__main__':
    index()
    privacy()
    print('site built')
