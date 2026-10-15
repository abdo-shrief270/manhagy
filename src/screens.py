"""App screens for Manhagy (phone 390x844 and a desktop dashboard), written as HTML."""
import json
import random

from common import icon, mark, page, status_bar, write, ROOT

SIZES = {}

BRANDS = {
    'physics': dict(
        teacher='أ. محمد سامي', initials='م.س', subject='فيزياء · تالتة ثانوي', url='mr-mohamed.@DOMAIN@',
        b1='#4F46E5', b2='#312E81', bl='#EEF2FF', bm='#C7D2FE', student='يوسف',
        tagline='فاضلك 3 دروس وتخلّص الفصل الأول',
        lesson='قانون أوم — الجزء التاني', chapter='الفصل الأول: التيار الكهربي', left='فاضل 9 دقايق', progress=62,
        stats=[('12', 'درس خلّصتهم'), ('85%', 'متوسط درجاتك'), ('14', 'ترتيبك في الدفعة')],
        exam='امتحان الفصل الأول', exam_when='الخميس 8:00 م', exam_meta='30 دقيقة · 20 سؤال',
        courses=[('شهر نوفمبر', 'صالح لحد 30 نوفمبر', 'atom', False), ('مراجعة الفصل الأول', 'افتحه بكود', 'zap', True)],
        thumb='physics',
    ),
    'arabic': dict(
        teacher='أ. هبة عادل', initials='هـ.ع', subject='لغة عربية · رابعة ابتدائي', url='miss-heba.@DOMAIN@',
        b1='#E11D48', b2='#9F1239', bl='#FFF1F2', bm='#FECDD3', student='ملك',
        tagline='جمعتي 12 نجمة الأسبوع ده، برافو!',
        lesson='الجملة الاسمية', chapter='الوحدة التانية · الدرس الخامس', left='فاضل 4 دقايق', progress=70,
        stats=[('12', 'نجمة'), ('3', 'شارات'), ('5', 'أيام ورا بعض')],
        exam='تدريب: المبتدأ والخبر', exam_when='بكرة', exam_meta='10 أسئلة بالصور',
        courses=[('شهر نوفمبر', 'صالح لحد 30 نوفمبر', 'book-open', False), ('قصص وحكايات', 'افتحه بكود', 'sparkles', True)],
        thumb='arabic',
    ),
    'uni': dict(
        teacher='د. كريم فؤاد', initials='ك.ف', subject='محاسبة تكاليف · تجارة — الفرقة التانية', url='dr-karim.@DOMAIN@',
        b1='#0D9488', b2='#134E4A', bl='#F0FDFA', bm='#99F6E4', student='عمر',
        tagline='الميدترم بعد 6 أيام، يلا بينا',
        lesson='نظام تكاليف المراحل', chapter='الفصل التالت · المحاضرة 2', left='فاضل 18 دقيقة', progress=40,
        stats=[('9', 'محاضرات'), ('78%', 'متوسط التمارين'), ('6', 'أيام للميدترم')],
        exam='مراجعة ليلة الميدترم', exam_when='السبت 9:00 م', exam_meta='لايف · ساعتين',
        courses=[('كورس الترم الأول', 'صالح لحد آخر الترم', 'calculator', False), ('حل امتحانات سابقة', 'افتحه بكود', 'file-text', True)],
        thumb='uni',
    ),
}


def thumb_art(kind, w=96, h=96):
    """Small cover art for a lesson thumbnail."""
    if kind == 'physics':
        return f"""<svg width="{w}" height="{h}" viewBox="0 0 96 96"><rect width="96" height="96" fill="#1d3b31"/>
<g fill="none" stroke="#e8f5e9" stroke-width="2" stroke-linecap="round" opacity=".9">
<path d="M18 30h18M42 30h36M78 30v34H18V30"/><path d="M36 22v16M42 25v10"/>
<path d="M40 64l4-6 4 12 4-12 4 12 4-6"/></g>
<text x="48" y="86" fill="#fde68a" font-family="Cairo" font-weight="800" font-size="14" text-anchor="middle">V = I R</text></svg>"""
    if kind == 'arabic':
        return f"""<svg width="{w}" height="{h}" viewBox="0 0 96 96"><rect width="96" height="96" fill="#fff1f2"/>
<circle cx="74" cy="22" r="10" fill="#fbbf24"/><path d="M0 76c20-14 40-14 60-4s28 8 36 2v22H0z" fill="#fecdd3"/>
<text x="48" y="58" fill="#9f1239" font-family="Cairo" font-weight="900" font-size="26" text-anchor="middle">أ ب ت</text></svg>"""
    return f"""<svg width="{w}" height="{h}" viewBox="0 0 96 96"><rect width="96" height="96" fill="#134e4a"/>
<g fill="#5eead4"><rect x="16" y="56" width="12" height="24" rx="2"/><rect x="34" y="44" width="12" height="36" rx="2"/><rect x="52" y="34" width="12" height="46" rx="2"/><rect x="70" y="22" width="12" height="58" rx="2"/></g>
<path d="M14 50l20-10 18 6 28-24" stroke="#fde68a" stroke-width="3" fill="none" stroke-linecap="round"/></svg>"""


def bottom_nav(active=0, color='var(--brand)'):
    items = [('house', 'الرئيسية'), ('library', 'كورساتي'), ('clipboard-check', 'الامتحانات'), ('message-circle', 'أسئلتي'), ('user-round', 'حسابي')]
    cells = ''.join(
        f'<div class="nv{" on" if i == active else ""}">{icon(n, 22, color if i == active else "#94A3B8", 2.2 if i == active else 2)}<span>{t}</span></div>'
        for i, (n, t) in enumerate(items))
    return f'<nav class="bn">{cells}</nav><div class="hi"></div>'


NAV_CSS = """
.bn { position: absolute; bottom: 0; left: 0; right: 0; height: 86px; background: #fff; border-top: 1px solid var(--line);
  display: flex; justify-content: space-around; padding: 10px 6px 0; }
.nv { display: flex; flex-direction: column; align-items: center; gap: 3px; font-size: 11px; color: #94A3B8; font-weight: 600; width: 70px; }
.nv.on { color: var(--b1, var(--brand)); font-weight: 800; }
.hi { position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); width: 134px; height: 5px; border-radius: 3px; background: #0F172A; }
"""


def student_home(key):
    v = BRANDS[key]
    kid = key == 'arabic'
    stats = ''.join(f'<div class="st"><b class="num">{a}</b><span>{b}</span></div>' for a, b in v['stats'])
    if kid:
        stats = ''.join(
            f'<div class="st kid">{icon(ic, 22, col)}<b class="num">{a}</b><span>{b}</span></div>'
            for (a, b), (ic, col) in zip(v['stats'], [('star', '#F59E0B'), ('award', '#E11D48'), ('flame', '#F97316')]))
    courses = ''
    for title, sub, ic, locked in v['courses']:
        foot = (f'<span class="lk">{icon("lock", 14, "#B45309")} {sub}</span>' if locked
                else f'<span class="ok">{icon("circle-check", 14, "#16A34A")} {sub}</span>')
        courses += f"""<div class="cc{' locked' if locked else ''}">
  <div class="cc-top">{icon(ic, 26, '#fff')}<b>{title}</b></div>
  <div class="cc-foot">{foot}</div></div>"""
    body = f"""
<div class="hero">
  {status_bar(dark=True)}
  <div class="top">
    <div class="who"><div class="av">{v['initials']}</div><div><b>{v['teacher']}</b><small>{v['subject']}</small></div></div>
    <div class="bell">{icon('bell', 22, '#fff')}<i></i></div>
  </div>
  <div class="hello"><h1>أهلاً يا {v['student']}</h1><p>{v['tagline']}</p></div>
</div>
<main>
  <div class="card resume">
    <div class="thumb">{thumb_art(v['thumb'])}<span class="pl">{icon('play', 15, v['b1'], 2.6)}</span></div>
    <div class="ri">
      <small>كمّل من حيث وقفت</small>
      <b>{v['lesson']}</b>
      <span class="mu">{v['chapter']}</span>
      <div class="bar"><i style="width:{v['progress']}%"></i></div>
      <span class="lf">{v['left']}</span>
    </div>
  </div>
  <div class="stats">{stats}</div>
  <div class="card exam">
    <div class="ex-ic">{icon('clipboard-check', 22, '#B45309')}</div>
    <div class="ex-t"><b>{v['exam']}</b><small>{v['exam_when']}</small><small>{v['exam_meta']}</small></div>
    <span class="rem">{icon('bell-ring', 15, v['b1'])} فكّرني</span>
  </div>
  <div class="sec"><b>كورساتك</b><span>الكل</span></div>
  <div class="courses">{courses}</div>
</main>
{bottom_nav(0, v['b1'])}"""
    css = """
body { --b1: %(b1)s; --b2: %(b2)s; --bl: %(bl)s; --bm: %(bm)s; position: relative; }
.hero { background: radial-gradient(260px 200px at 10%% 0%%, rgba(255,255,255,.16), transparent 70%%), linear-gradient(160deg, var(--b1), var(--b2));
  color: #fff; padding-bottom: 62px; border-radius: 0 0 30px 30px; }
.top { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px 0; }
.who { display: flex; align-items: center; gap: 10px; }
.av { width: 44px; height: 44px; border-radius: 14px; background: rgba(255,255,255,.18); border: 1.5px solid rgba(255,255,255,.35);
  display: grid; place-items: center; font-weight: 800; font-size: 14px; }
.who b { display: block; font-size: 16px; font-weight: 800; line-height: 1.3; }
.who small { display: block; font-size: 12px; opacity: .8; font-weight: 600; }
.bell { position: relative; width: 42px; height: 42px; border-radius: 14px; background: rgba(255,255,255,.12); display: grid; place-items: center; }
.bell i { position: absolute; top: 9px; right: 10px; width: 9px; height: 9px; border-radius: 50%%; background: #FBBF24; border: 2px solid var(--b1); }
.hello { padding: 16px 20px 0; }
.hello h1 { font-size: 26px; font-weight: 800; line-height: 1.25; }
.hello p { font-size: 14px; opacity: .85; font-weight: 600; margin-top: 2px; }
main { padding: 0 16px; margin-top: -48px; }
.card { background: #fff; border-radius: 20px; box-shadow: 0 1px 2px rgba(15,23,42,.05), 0 8px 24px rgba(15,23,42,.07); }
.resume { display: flex; gap: 14px; padding: 12px; align-items: center; }
.thumb { position: relative; width: 96px; height: 96px; border-radius: 16px; overflow: hidden; flex: none; }
.pl { position: absolute; left: 7px; top: 7px; width: 32px; height: 32px; border-radius: 50%%; background: #fff; display: grid; place-items: center; box-shadow: 0 4px 12px rgba(0,0,0,.25); }
.ri { flex: 1; min-width: 0; }
.ri small { font-size: 12px; color: var(--b1); font-weight: 800; }
.ri b { display: block; font-size: 16px; font-weight: 800; line-height: 1.35; }
.mu { font-size: 12px; color: var(--muted); font-weight: 600; }
.bar { height: 7px; border-radius: 4px; background: var(--line-2); margin: 8px 0 4px; overflow: hidden; }
.bar i { display: block; height: 100%%; border-radius: 4px; background: linear-gradient(90deg, var(--b1), var(--b2)); margin-right: 0; }
.lf { font-size: 11.5px; color: var(--muted); font-weight: 700; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 12px 0; }
.st { background: #fff; border-radius: 16px; padding: 10px 8px; text-align: center; box-shadow: 0 1px 2px rgba(15,23,42,.05); border: 1px solid var(--line-2); }
.st b { display: block; font-size: 20px; font-weight: 800; color: var(--ink); line-height: 1.3; }
.st span { font-size: 11.5px; color: var(--muted); font-weight: 700; }
.st.kid { display: flex; flex-direction: column; align-items: center; gap: 0; }
.exam { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1.5px solid #FDE68A; background: linear-gradient(90deg, #FFFBEB, #fff); box-shadow: none; }
.ex-ic { width: 44px; height: 44px; border-radius: 14px; background: #FEF3C7; display: grid; place-items: center; flex: none; }
.ex-t { flex: 1; } .ex-t b { display: block; font-size: 15px; font-weight: 800; line-height: 1.4; } .ex-t small { display: block; font-size: 12px; color: var(--muted); font-weight: 700; line-height: 1.5; }
.rem { display: inline-flex; align-items: center; gap: 4px; font-size: 12.5px; font-weight: 800; color: var(--b1); background: var(--bl); border-radius: 12px; padding: 6px 10px; }
.sec { display: flex; justify-content: space-between; align-items: baseline; margin: 14px 4px 8px; }
.sec b { font-size: 17px; font-weight: 800; } .sec span { font-size: 13px; color: var(--b1); font-weight: 800; }
.courses { display: flex; gap: 12px; margin-left: -16px; }
.cc { width: 206px; flex: none; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.05); border: 1px solid var(--line-2); }
.cc-top { height: 78px; padding: 14px; color: #fff; background: linear-gradient(140deg, var(--b1), var(--b2)); display: flex; flex-direction: column; justify-content: space-between; }
.cc.locked .cc-top { background: linear-gradient(140deg, #475569, #1E293B); }
.cc-top b { font-size: 15px; font-weight: 800; }
.cc-foot { padding: 10px 14px; font-size: 12px; font-weight: 700; }
.cc-foot span { display: inline-flex; align-items: center; gap: 5px; }
.ok { color: #15803D; } .lk { color: #B45309; }
""" % v
    write(f'screens/home-{key}.html', page(body, css + NAV_CSS, title=f'home-{key}'))
    SIZES[f'home-{key}'] = [390, 844]


def player():
    lessons = [
        ('done', 'شدة التيار الكهربي', '18 د'),
        ('done', 'فرق الجهد والمقاومة', '21 د'),
        ('done', 'قانون أوم — الجزء الأول', '19 د'),
        ('now', 'قانون أوم — الجزء التاني', '24 د'),
        ('quiz', 'امتحان قصير: قانون أوم', 'لازم تنجح عشان يفتح اللي بعده'),
        ('lock', 'توصيل المقاومات على التوالي', '22 د'),
        ('lock', 'توصيل المقاومات على التوازي', '26 د'),
    ]
    rows = ''
    for i, (state, title, meta) in enumerate(lessons, 1):
        ic = {
            'done': icon('circle-check', 22, '#16A34A'),
            'now': f'<span class="eq"><i></i><i></i><i></i></span>',
            'quiz': icon('clipboard-check', 21, '#B45309'),
            'lock': icon('lock', 19, '#94A3B8'),
        }[state]
        rows += f'<div class="ls {state}"><span class="n num">{i}</span><div class="lt"><b>{title}</b><small>{meta}</small></div>{ic}</div>'
    body = f"""
<div class="top">
  {status_bar(dark=True)}
  <div class="video">
    <svg class="board" viewBox="0 0 390 232" style="direction:ltr">
      <defs><radialGradient id="bg" cx=".55" cy=".45" r=".8"><stop offset="0" stop-color="#25493d"/><stop offset="1" stop-color="#132a23"/></radialGradient></defs>
      <rect width="390" height="232" fill="url(#bg)"/>
      <g fill="none" stroke="#ecfdf5" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" opacity=".85">
        <path d="M246 54h40M302 54h62v104H246V54"/>
        <path d="M286 40v28M302 46v16"/>
        <path d="M282 158l6-10 8 20 8-20 8 20 6-10"/>
        <circle cx="364" cy="106" r="13"/>
      </g>
      <text x="364" y="112" fill="#ecfdf5" font-family="Cairo" font-weight="800" font-size="15" text-anchor="middle" opacity=".9">A</text>
      <text x="300" y="138" fill="#ecfdf5" font-family="Cairo" font-weight="800" font-size="15" text-anchor="middle" opacity=".85">R</text>
      <text x="30" y="80" fill="#fde68a" font-family="Cairo" font-weight="800" font-size="42" opacity=".95">V = I R</text>
      <text x="34" y="116" fill="#ecfdf5" font-family="Cairo" font-weight="700" font-size="17" opacity=".85">I = 2 A ,  R = 10 Ω</text>
    </svg>
    <span class="wm">يوسف أحمد · <span class="num">01012•••487</span></span>
    <span class="tag">{icon('shield-check', 13, '#fff')} محمي</span>
    <div class="ctl">
      <div class="prog"><i></i><b></b></div>
      <div class="row">
        {icon('pause', 22, '#fff', 2.4)}
        <span class="num t">12:40 / 24:10</span>
        <span class="sp"></span>
        <span class="num spd">1.25x</span>
        {icon('settings', 19, '#fff')}
        {icon('maximize', 19, '#fff')}
      </div>
    </div>
  </div>
</div>
<div class="sheet">
  <h1>قانون أوم — الجزء التاني</h1>
  <p class="sub">الفصل الأول: التيار الكهربي وقانون أوم · أ. محمد سامي</p>
  <div class="chips">
    <span class="ch amber">{icon('eye', 15, '#B45309')} باقي مشاهدتين</span>
    <span class="ch">{icon('clock', 15, '#475569')} <span class="num">24</span> دقيقة</span>
    <span class="ch">{icon('file-text', 15, '#475569')} ملزمة PDF</span>
  </div>
  <div class="note">{icon('shield-check', 20, '#4F46E5')}<span>الفيديو عليه اسمك ورقمك. لو اتشارك، الحساب بيتقفل.</span></div>
  <div class="lh"><b>دروس الفصل</b><span><span class="num">4</span> من <span class="num">7</span></span></div>
  <div class="list">{rows}</div>
</div>
<div class="hi"></div>"""
    css = """
body { background: #fff; position: relative; }
.top { background: #000; }
.video { position: relative; height: 232px; overflow: hidden; }
.board { position: absolute; inset: 0; width: 100%; height: 100%; }
.wm { position: absolute; top: 138px; left: 34px; transform: rotate(-5deg); color: rgba(255,255,255,.55); font-size: 15px; font-weight: 800;
  text-shadow: 0 1px 2px rgba(0,0,0,.35); white-space: nowrap; letter-spacing: .01em; }
.tag { position: absolute; top: 12px; right: 12px; display: inline-flex; align-items: center; gap: 4px; background: rgba(0,0,0,.35); color: #fff;
  font-size: 11px; font-weight: 800; border-radius: 10px; padding: 3px 8px; backdrop-filter: blur(4px); }
.ctl { position: absolute; left: 0; right: 0; bottom: 0; padding: 26px 14px 10px; background: linear-gradient(transparent, rgba(0,0,0,.7)); direction: ltr; }
.prog { position: relative; height: 4px; border-radius: 2px; background: rgba(255,255,255,.3); margin-bottom: 9px; }
.prog i { position: absolute; left: 0; top: 0; bottom: 0; width: 52%; border-radius: 2px; background: #F59E0B; }
.prog b { position: absolute; left: 52%; top: 50%; width: 13px; height: 13px; margin: -6.5px 0 0 -6.5px; border-radius: 50%; background: #fff; }
.row { display: flex; align-items: center; gap: 12px; color: #fff; }
.t { font-size: 13px; font-weight: 700; } .sp { flex: 1; }
.spd { font-size: 12px; font-weight: 800; border: 1.5px solid rgba(255,255,255,.7); border-radius: 7px; padding: 0 5px; }
.sheet { padding: 16px 18px 0; }
.sheet h1 { font-size: 20px; font-weight: 800; line-height: 1.35; }
.sub { font-size: 12.5px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.chips { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; }
.ch { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 800; color: #334155; background: #F1F5F9; border-radius: 999px; padding: 5px 11px; }
.ch.amber { background: #FEF3C7; color: #92400E; }
.note { display: flex; align-items: center; gap: 10px; background: #EEF2FF; color: #3730A3; border-radius: 14px; padding: 10px 12px; font-size: 13px; font-weight: 700; }
.lh { display: flex; justify-content: space-between; align-items: baseline; margin: 16px 2px 6px; }
.lh b { font-size: 16px; font-weight: 800; } .lh span { font-size: 12.5px; color: var(--muted); font-weight: 700; }
.ls { display: flex; align-items: center; gap: 12px; padding: 9px 4px; border-bottom: 1px solid var(--line-2); }
.ls .n { width: 26px; height: 26px; border-radius: 9px; background: #F1F5F9; color: #64748B; display: grid; place-items: center; font-size: 12.5px; font-weight: 800; flex: none; }
.lt { flex: 1; } .lt b { display: block; font-size: 14px; font-weight: 700; line-height: 1.35; } .lt small { font-size: 11.5px; color: var(--muted); font-weight: 600; }
.ls.now { background: #EEF2FF; border-radius: 14px; border-bottom-color: transparent; padding: 9px 10px; margin: 2px -6px; }
.ls.now .n { background: #4F46E5; color: #fff; } .ls.now b { color: #3730A3; font-weight: 800; }
.ls.quiz small { color: #B45309; font-weight: 700; }
.ls.lock b { color: #94A3B8; }
.eq { display: flex; gap: 2px; align-items: flex-end; height: 16px; width: 20px; justify-content: center; }
.eq i { width: 4px; border-radius: 2px; background: #4F46E5; }
.eq i:nth-child(1) { height: 9px; } .eq i:nth-child(2) { height: 16px; } .eq i:nth-child(3) { height: 12px; }
.hi { position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); width: 134px; height: 5px; border-radius: 3px; background: #0F172A; }
"""
    write('screens/player.html', page(body, css, bg='#fff', title='player'))
    SIZES['player'] = [390, 844]


def exam():
    dots = ''
    for i in range(1, 21):
        cls = 'a' if i <= 6 else ('c' if i == 7 else '')
        if i == 4:
            cls += ' f'
        dots += f'<span class="d {cls} num">{i}</span>'
    opts = [('أ', '5 فولت', False), ('ب', '12 فولت', False), ('ج', '20 فولت', True), ('د', '0.2 فولت', False)]
    options = ''.join(
        f'<div class="op{" on" if on else ""}"><span class="ltr-badge">{k}</span><b>{t}</b><span class="rd"></span></div>'
        for k, t, on in opts)
    body = f"""
{status_bar()}
<header>
  <span class="x">{icon('x', 22, '#334155')}</span>
  <div class="ttl"><b>امتحان الفصل الأول</b><small>فيزياء · أ. محمد سامي</small></div>
  <span class="timer">{icon('timer', 17, '#92400E', 2.4)}<span class="num">18:42</span></span>
</header>
<div class="pr">
  <div class="pr-h"><b>سؤال <span class="num">7</span> من <span class="num">20</span></b><span>جاوبت <span class="num">6</span></span></div>
  <div class="bar"><i></i></div>
  <div class="dots">{dots}</div>
</div>
<div class="q">
  <span class="kind">اختيار من متعدد · درجة واحدة</span>
  <p>سلك مقاومته <span class="num">10</span> أوم يمر به تيار شدته <span class="num">2</span> أمبير، فإن فرق الجهد بين طرفيه يساوي:</p>
</div>
<div class="ops">{options}</div>
<div class="flag">{icon('flag', 17, '#B45309')} علّم السؤال وارجعله بعدين</div>
<div class="nav">
  <span class="btn ghost">{icon('arrow-right', 18, '#334155')} السابق</span>
  <span class="btn">التالي {icon('arrow-left', 18, '#fff')}</span>
</div>
<div class="sec-note">{icon('lock', 15, '#64748B')} الوقت محسوب على السيرفر، ولو قفلت الصفحة مش بيقف. وترتيب الأسئلة مختلف لكل طالب.</div>
<div class="hi"></div>"""
    css = """
body { background: #F8FAFC; position: relative; }
header { display: flex; align-items: center; gap: 12px; padding: 6px 18px 12px; }
.x { width: 40px; height: 40px; border-radius: 13px; background: #fff; border: 1px solid var(--line); display: grid; place-items: center; }
.ttl { flex: 1; } .ttl b { display: block; font-size: 16px; font-weight: 800; line-height: 1.3; } .ttl small { font-size: 12px; color: var(--muted); font-weight: 600; }
.timer { display: inline-flex; align-items: center; gap: 6px; background: #FEF3C7; color: #92400E; font-weight: 800; font-size: 17px; border-radius: 13px; padding: 6px 12px; border: 1.5px solid #FCD34D; }
.pr { padding: 0 18px; }
.pr-h { display: flex; justify-content: space-between; align-items: baseline; }
.pr-h b { font-size: 14px; font-weight: 800; } .pr-h span { font-size: 12.5px; color: var(--muted); font-weight: 700; }
.bar { height: 7px; border-radius: 4px; background: #E2E8F0; margin: 8px 0 12px; overflow: hidden; }
.bar i { display: block; width: 33%; height: 100%; background: linear-gradient(90deg, #4F46E5, #6366F1); border-radius: 4px; margin-right: 0; }
.dots { display: grid; grid-template-columns: repeat(10, 1fr); gap: 7px; }
.d { position: relative; height: 28px; border-radius: 9px; display: grid; place-items: center; font-size: 12px; font-weight: 800; color: #94A3B8; background: #fff; border: 1.5px solid #E2E8F0; }
.d.a { background: #4F46E5; border-color: #4F46E5; color: #fff; }
.d.c { border: 2px solid #4F46E5; color: #4F46E5; background: #EEF2FF; }
.d.f::after { content: ''; position: absolute; top: -4px; left: -4px; width: 10px; height: 10px; border-radius: 50%; background: #F59E0B; border: 2px solid #F8FAFC; }
.q { margin: 18px 18px 0; background: #fff; border-radius: 20px; padding: 16px 16px 18px; border: 1px solid var(--line); box-shadow: 0 6px 18px rgba(15,23,42,.05); }
.kind { display: inline-block; font-size: 11.5px; font-weight: 800; color: #4F46E5; background: #EEF2FF; border-radius: 8px; padding: 2px 9px; }
.q p { font-size: 17px; font-weight: 700; line-height: 1.75; margin-top: 10px; color: #0F172A; }
.ops { padding: 12px 18px 0; display: grid; gap: 10px; }
.op { display: flex; align-items: center; gap: 12px; background: #fff; border: 1.5px solid #E2E8F0; border-radius: 16px; padding: 12px 14px; }
.op b { flex: 1; font-size: 16px; font-weight: 700; }
.ltr-badge { width: 30px; height: 30px; border-radius: 10px; background: #F1F5F9; color: #475569; display: grid; place-items: center; font-weight: 800; font-size: 14px; }
.rd { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #CBD5E1; }
.op.on { border-color: #4F46E5; background: #EEF2FF; box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
.op.on .ltr-badge { background: #4F46E5; color: #fff; } .op.on b { color: #312E81; font-weight: 800; }
.op.on .rd { border: 7px solid #4F46E5; background: #fff; }
.flag { display: flex; align-items: center; gap: 7px; margin: 14px 18px 0; font-size: 13px; font-weight: 700; color: #92400E; }
.nav { display: flex; gap: 12px; padding: 16px 18px 0; }
.btn { flex: 1; display: inline-flex; justify-content: center; align-items: center; gap: 8px; height: 52px; border-radius: 16px; background: #4F46E5; color: #fff; font-weight: 800; font-size: 16px; box-shadow: 0 8px 18px rgba(79,70,229,.3); }
.btn.ghost { background: #fff; color: #334155; border: 1.5px solid #E2E8F0; box-shadow: none; }
.sec-note { display: flex; gap: 7px; align-items: flex-start; margin: 16px 22px 0; font-size: 12px; color: #64748B; font-weight: 600; line-height: 1.6; }
.sec-note svg { margin-top: 2px; }
.hi { position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); width: 134px; height: 5px; border-radius: 3px; background: #0F172A; }
"""
    write('screens/exam.html', page(body, css, bg='#F8FAFC', title='exam'))
    SIZES['exam'] = [390, 844]


def parent_report():
    body = f"""
<div class="wa-top">
  {status_bar(dark=True)}
  <div class="wa-h">
    {icon('arrow-right', 22, '#fff')}
    <div class="wa-av">م.س</div>
    <div class="wa-n"><b>أ. محمد سامي — فيزياء</b><small>حساب أعمال</small></div>
    {icon('video', 22, '#fff')}{icon('phone', 20, '#fff')}{icon('ellipsis-vertical', 20, '#fff')}
  </div>
</div>
<div class="chat">
  <div class="day">النهارده</div>
  <div class="msg in">
    <div class="pv">
      <div class="pv-img">{mark(34, 'pv')}</div>
      <div class="pv-t"><b>تقرير يوسف — الأسبوع ده</b><small class="ltr">mr-mohamed.@DOMAIN@</small></div>
    </div>
    <p><b>تقرير الأسبوع: يوسف أحمد</b><br>فيزياء · تالتة ثانوي · أ. محمد سامي</p>
    <p class="ln">✅ شاف <span class="num">4</span> دروس من <span class="num">5</span></p>
    <p class="ln">📝 امتحان الفصل الأول: <span class="num">17</span> من <span class="num">20</span><br><span class="soft">ترتيبه <span class="num">14</span> من <span class="num">312</span></span></p>
    <p class="ln">🏫 حضر حصة السبت وحصة التلات في السنتر</p>
    <p class="ln">⏳ لسه ما شافش: «توصيل المقاومات»</p>
    <p class="link ltr">mr-mohamed.@DOMAIN@/p/8KQ2</p>
    <span class="tm">8:02 م</span>
  </div>
  <div class="msg out">
    <p>تسلم يا أستاذ، هخليه يخلّص الدرس النهارده إن شاء الله</p>
    <span class="tm">8:15 م <svg width="17" height="11" viewBox="0 0 17 11"><path d="M1 6l3 3 6-7M7 8l1.2 1.2L15 2" fill="none" stroke="#53BDEB" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
  </div>
  <div class="msg in">
    <p>العفو 🙏 ولو حابب تتابع أول بأول، فيه حساب لولي الأمر على المنصة من نفس اللينك.</p>
    <span class="tm">8:16 م</span>
  </div>
</div>
<div class="wa-in">
  <div class="fld">{icon('smile', 22, '#8696A0')}<span>رسالة</span>{icon('paperclip', 21, '#8696A0')}{icon('camera', 21, '#8696A0')}</div>
  <div class="mic">{icon('mic', 22, '#fff')}</div>
</div>
<div class="hi"></div>"""
    css = """
body { background: #EFEAE2; position: relative; }
.wa-top { background: #008069; color: #fff; }
.wa-h { display: flex; align-items: center; gap: 12px; padding: 4px 14px 12px; }
.wa-av { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(140deg, #6366F1, #312E81); display: grid; place-items: center; font-weight: 800; font-size: 13px; flex: none; }
.wa-n { flex: 1; } .wa-n b { display: block; font-size: 16px; font-weight: 700; line-height: 1.25; } .wa-n small { font-size: 12px; opacity: .85; }
.chat { padding: 12px 12px 0; background-image: radial-gradient(rgba(0,0,0,.035) 1.2px, transparent 1.3px); background-size: 18px 18px; height: 640px; }
.day { width: max-content; margin: 0 auto 12px; background: #fff; color: #54656F; font-size: 12px; font-weight: 700; border-radius: 8px; padding: 3px 12px; box-shadow: 0 1px 1px rgba(0,0,0,.06); }
.msg { position: relative; max-width: 312px; border-radius: 12px; padding: 7px 10px 20px; margin-bottom: 8px; font-size: 14.5px; line-height: 1.6; color: #111B21; box-shadow: 0 1px 1px rgba(0,0,0,.08); }
.msg.in { background: #fff; margin-left: auto; border-top-right-radius: 4px; }
.msg.out { background: #D9FDD3; margin-right: auto; border-top-left-radius: 4px; }
.msg p + p { margin-top: 4px; }
.msg .ln { font-weight: 600; }
.soft { color: #667781; font-size: 13px; padding-right: 26px; }
.link { color: #027EB5; font-size: 13.5px; text-align: right; }
.tm { position: absolute; bottom: 3px; left: 9px; font-size: 11px; color: #667781; display: inline-flex; align-items: center; gap: 3px; }
.pv { display: flex; gap: 10px; align-items: center; background: #F0F2F5; border-radius: 8px; padding: 6px; margin: -1px -4px 8px; }
.pv-img { width: 52px; height: 52px; border-radius: 6px; background: #fff; display: grid; place-items: center; }
.pv-t b { display: block; font-size: 13.5px; font-weight: 700; line-height: 1.35; } .pv-t small { font-size: 12px; color: #667781; display: block; text-align: right; }
.wa-in { position: absolute; bottom: 26px; left: 8px; right: 8px; display: flex; gap: 8px; align-items: center; }
.fld { flex: 1; display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 26px; height: 50px; padding: 0 14px; color: #8696A0; font-size: 16px; }
.fld span { flex: 1; }
.mic { width: 50px; height: 50px; border-radius: 50%; background: #00A884; display: grid; place-items: center; }
.hi { position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); width: 134px; height: 5px; border-radius: 3px; background: #0F172A; }
"""
    write('screens/parent.html', page(body, css, bg='#EFEAE2', title='parent'))
    SIZES['parent'] = [390, 844]


def fake_qr(size=64, seed=7):
    rnd = random.Random(seed)
    n = 21
    cell = size / n
    rects = []
    finder = lambda x, y: (x < 7 and y < 7) or (x >= n - 7 and y < 7) or (x < 7 and y >= n - 7)
    for y in range(n):
        for x in range(n):
            if finder(x, y):
                continue
            if rnd.random() < .48:
                rects.append(f'<rect x="{x * cell:.2f}" y="{y * cell:.2f}" width="{cell + .1:.2f}" height="{cell + .1:.2f}"/>')
    for fx, fy in [(0, 0), (n - 7, 0), (0, n - 7)]:
        rects.append(f'<rect x="{fx * cell:.2f}" y="{fy * cell:.2f}" width="{7 * cell:.2f}" height="{7 * cell:.2f}"/>')
        rects.append(f'<rect x="{(fx + 1) * cell:.2f}" y="{(fy + 1) * cell:.2f}" width="{5 * cell:.2f}" height="{5 * cell:.2f}" fill="#fff"/>')
        rects.append(f'<rect x="{(fx + 2) * cell:.2f}" y="{(fy + 2) * cell:.2f}" width="{3 * cell:.2f}" height="{3 * cell:.2f}"/>')
    return f'<svg width="{size}" height="{size}" viewBox="0 0 {size} {size}" fill="#0F172A">{"".join(rects)}</svg>'


def code_card(code='K7Q4-9MXP-2D', label='كارت شهر نوفمبر', seed=7, w=330):
    return f"""<div class="kard" style="width:{w}px">
  <div class="k-side"></div>
  <div class="k-body">
    <div class="k-h"><div class="k-av">م.س</div><div><b>أ. محمد سامي — فيزياء</b><small>{label} · تالتة ثانوي</small></div></div>
    <div class="k-code ltr">{code}</div>
    <div class="k-f"><span>فعّله على <b class="ltr">mr-mohamed.@DOMAIN@</b></span>{fake_qr(46, seed)}</div>
  </div>
</div>"""


CARD_CSS = """
.kard { display: flex; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,.14); border: 1px solid #E2E8F0; }
.k-side { width: 14px; background: repeating-linear-gradient(180deg, #4F46E5 0 14px, #6366F1 14px 28px); flex: none; }
.k-body { flex: 1; padding: 12px 14px 10px; }
.k-h { display: flex; gap: 10px; align-items: center; }
.k-av { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(140deg, #6366F1, #312E81); color: #fff; font-weight: 800; font-size: 11.5px; display: grid; place-items: center; }
.k-h b { display: block; font-size: 13.5px; font-weight: 800; line-height: 1.3; } .k-h small { font-size: 11.5px; color: #64748B; font-weight: 700; }
.k-code { margin: 10px 0 8px; text-align: center; font-family: 'DejaVu Sans Mono', monospace; font-weight: 700; font-size: 25px; letter-spacing: .06em; color: #1E1B4B;
  background: #F5F3FF; border: 1.5px dashed #A5B4FC; border-radius: 12px; padding: 4px 0; }
.k-f { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 11px; color: #64748B; font-weight: 600; }
.k-f b { color: #3730A3; font-weight: 800; }
"""


def redeem():
    body = f"""
{status_bar()}
<header>{icon('arrow-right', 22, '#334155')}<b>فعّل كود</b><span></span></header>
<div class="wrap">
  <div class="cardbox">{code_card()}</div>
  <label>اكتب الكود اللي على الكارت</label>
  <div class="fld"><span class="ltr code">K7Q4 - 9MXP - 2D</span>{icon('circle-check', 22, '#16A34A')}</div>
  <div class="or"><i></i><span>أو</span><i></i></div>
  <div class="scan">{icon('scan-line', 20, '#4F46E5')} امسح الكارت بالكاميرا</div>
</div>
<div class="dim"></div>
<div class="sheet">
  <span class="grab"></span>
  <div class="ok">{icon('check', 34, '#fff', 3)}</div>
  <h2>اتفعّل!</h2>
  <p class="lead">اتفتح لك <b>شهر نوفمبر — تالتة ثانوي</b></p>
  <div class="rows">
    <div><span>صالح لحد</span><b><span class="num">30</span> نوفمبر</b></div>
    <div><span>فيه</span><b><span class="num">12</span> درس + <span class="num">4</span> امتحانات</b></div>
  </div>
  <p class="fine">{icon('lock', 14, '#64748B')} الكود اتسجّل باسمك، ومينفعش يتستخدم تاني.</p>
  <span class="btn">ابدأ أول درس {icon('arrow-left', 18, '#fff')}</span>
</div>
<div class="hi"></div>"""
    css = CARD_CSS + """
body { background: #F8FAFC; position: relative; }
header { display: flex; align-items: center; justify-content: space-between; padding: 6px 20px 8px; }
header b { font-size: 17px; font-weight: 800; } header span { width: 22px; }
.wrap { padding: 8px 20px 0; }
.cardbox { display: flex; justify-content: center; margin: 2px 0 14px; transform: rotate(-2deg); }
label { display: block; font-size: 13.5px; font-weight: 800; color: #334155; margin-bottom: 8px; }
.fld { display: flex; align-items: center; justify-content: space-between; height: 56px; border-radius: 16px; background: #fff; border: 2px solid #16A34A; padding: 0 16px; box-shadow: 0 0 0 4px rgba(22,163,74,.1); }
.code { font-family: 'DejaVu Sans Mono', monospace; font-size: 20px; font-weight: 700; letter-spacing: .04em; color: #0F172A; }
.or { display: flex; align-items: center; gap: 12px; color: #94A3B8; font-size: 12.5px; font-weight: 700; margin: 14px 0; }
.or i { flex: 1; height: 1px; background: #E2E8F0; }
.scan { display: flex; justify-content: center; align-items: center; gap: 8px; height: 50px; border-radius: 16px; border: 1.5px dashed #A5B4FC; color: #4F46E5; font-weight: 800; font-size: 15px; background: #EEF2FF; }
.dim { position: absolute; inset: 0; background: rgba(15,23,42,.32); }
.sheet { position: absolute; left: 0; right: 0; bottom: 0; background: #fff; border-radius: 28px 28px 0 0; padding: 10px 22px 34px; text-align: center; box-shadow: 0 -10px 30px rgba(15,23,42,.18); }
.grab { display: block; width: 40px; height: 5px; border-radius: 3px; background: #E2E8F0; margin: 0 auto 14px; }
.ok { width: 58px; height: 58px; border-radius: 50%; background: #16A34A; display: grid; place-items: center; margin: 0 auto; box-shadow: 0 0 0 8px #DCFCE7; }
.sheet h2 { font-size: 24px; font-weight: 800; margin-top: 10px; line-height: 1.4; }
.lead { font-size: 15px; color: #334155; font-weight: 600; } .lead b { font-weight: 800; color: #0F172A; }
.rows { margin: 12px 0 10px; background: #F8FAFC; border-radius: 16px; padding: 4px 14px; text-align: right; }
.rows div { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #EEF2F7; font-size: 14px; }
.rows div:last-child { border-bottom: 0; }
.rows span { color: #64748B; font-weight: 600; } .rows b { font-weight: 800; }
.fine { display: flex; justify-content: center; align-items: center; gap: 6px; font-size: 12.5px; color: #64748B; font-weight: 600; margin-bottom: 14px; }
.btn { display: flex; justify-content: center; align-items: center; gap: 8px; height: 54px; border-radius: 16px; background: #4F46E5; color: #fff; font-weight: 800; font-size: 16px; box-shadow: 0 8px 18px rgba(79,70,229,.3); }
.hi { position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); width: 134px; height: 5px; border-radius: 3px; background: #0F172A; }
"""
    write('screens/redeem.html', page(body, css, bg='#F8FAFC', title='redeem'))
    SIZES['redeem'] = [390, 844]


def revenue_chart(w=640, h=236):
    days = ['27', '28', '29', '30', '31', '1', '2', '3', '4', '5', '6', '7', '8', '9']
    rnd = random.Random(11)
    data = []
    for i, _ in enumerate(days):
        base = 1.0 + (0.9 if i % 7 in (4, 5) else 0) + i * .04
        codes = round((1500 + rnd.random() * 900) * base)
        transfers = round((500 + rnd.random() * 500) * base)
        center = round((700 + rnd.random() * 700) * (1.4 if i % 7 in (0, 3) else .6))
        data.append((codes, transfers, center))
    top = max(sum(d) for d in data) * 1.12
    pad_l, pad_b, pad_t = 46, 26, 8
    cw = (w - pad_l) / len(days)
    bw = cw * .56
    bars, labels, grid = [], [], []
    for g in range(5):
        y = pad_t + (h - pad_b - pad_t) * g / 4
        val = round(top * (1 - g / 4) / 1000, 1)
        val = f'{val:g}k' if val else '0'
        grid.append(f'<line x1="{pad_l}" x2="{w}" y1="{y:.1f}" y2="{y:.1f}" stroke="#EEF2F7"/>'
                    f'<text x="{pad_l - 8}" y="{y + 4:.1f}" font-size="11" fill="#94A3B8" text-anchor="end" font-family="Cairo" font-weight="600">{val}</text>')
    colors = ['#4F46E5', '#F59E0B', '#14B8A6']
    for i, (vals, d) in enumerate(zip(data, days)):
        x = pad_l + cw * i + (cw - bw) / 2
        y = h - pad_b
        for j, v in enumerate(vals):
            bh = (h - pad_b - pad_t) * v / top
            y -= bh
            r = 4 if j == 2 else 0
            bars.append(f'<rect x="{x:.1f}" y="{y:.1f}" width="{bw:.1f}" height="{bh:.1f}" rx="{r}" fill="{colors[j]}"/>')
        labels.append(f'<text x="{x + bw / 2:.1f}" y="{h - 7}" font-size="11" fill="#94A3B8" text-anchor="middle" font-family="Cairo" font-weight="700">{d}</text>')
    return f'<svg width="{w}" height="{h}" viewBox="0 0 {w} {h}" style="direction:ltr">{"".join(grid)}{"".join(bars)}{"".join(labels)}</svg>'


def dashboard():
    nav = [('layout-dashboard', 'الرئيسية', None, True), ('library', 'الكورسات', None, False), ('users-round', 'الطلاب', None, False),
           ('ticket', 'الأكواد', None, False), ('clipboard-check', 'الامتحانات', None, False), ('pen-line', 'التصحيح', '23', False),
           ('message-circle', 'أسئلة الطلاب', '8', False), ('user-check', 'المساعدين', None, False), ('school', 'السنتر', None, False),
           ('chart-column', 'التقارير', None, False), ('settings', 'الإعدادات', None, False)]
    navh = ''.join(
        f'<div class="ni{" on" if on else ""}">{icon(ic, 19, "#4F46E5" if on else "#64748B")}<span>{t}</span>{f"<em class=num>{b}</em>" if b else ""}</div>'
        for ic, t, b, on in nav)
    kpis = [
        ('wallet', 'إيراد الشهر', '48,350', 'ج', '+12% عن أكتوبر', 'up', '#4F46E5', '#EEF2FF'),
        ('users-round', 'طلاب نشطين', '1,284', '', '+86 الأسبوع ده', 'up', '#0D9488', '#CCFBF1'),
        ('circle-play', 'مشاهدات الأسبوع', '7,912', '', 'درس اتشاف', '', '#7C3AED', '#EDE9FE'),
        ('ticket', 'أكواد اتفعّلت النهارده', '37', '', 'من 3 مكتبات وسنتر', '', '#B45309', '#FEF3C7'),
    ]
    kh = ''
    for ic, t, val, unit, sub, trend, c, bg in kpis:
        tr = f'<span class="tr">{icon("trending-up", 14, "#16A34A")} <span class="num">{sub}</span></span>' if trend else f'<span class="tr mu">{sub}</span>'
        kh += f'<div class="kpi"><div class="k-top"><span class="k-t">{t}</span><div class="k-i" style="background:{bg}">{icon(ic, 19, c)}</div></div><b class="k-v"><span class="num">{val}</span>{f" <small>{unit}</small>" if unit else ""}</b>{tr}</div>'
    alerts = [
        ('#DC2626', '#FEE2E2', 'triangle-alert', 'حساب «كريم ع.» اتفتح من محافظتين', 'القاهرة وأسيوط النهارده · ممكن يكون بيشاركه', 'راجع'),
        ('#B45309', '#FEF3C7', 'pen-line', '23 إجابة مقالي مستنية تصحيح', 'امتحان الفصل الأول · المساعد: مصطفى', 'صحّح'),
        ('#4F46E5', '#EEF2FF', 'ticket', 'مكتبة النور: اتفعّل 142 من 200 كود', 'فاضل 58 كارت مع المكتبة', 'تفاصيل'),
        ('#16A34A', '#DCFCE7', 'send', '312 تقرير لأولياء الأمور جاهزين', 'تقرير آخر الأسبوع · واتساب', 'ابعت'),
    ]
    ah = ''.join(
        f'<div class="al"><div class="a-i" style="background:{bg}">{icon(ic, 18, c)}</div><div class="a-t"><b>{t}</b><small>{s}</small></div><span class="a-b">{b}</span></div>'
        for c, bg, ic, t, s, b in alerts)
    codes = [
        ('K7Q4-••••-2D', 'شهر نوفمبر', 'يوسف أحمد', 'مكتبة النور — المنصورة', 'من 3 دقايق'),
        ('P2HX-••••-9A', 'شهر نوفمبر', 'مريم خالد', 'سنتر الأوائل', 'من 11 دقيقة'),
        ('Z9MC-••••-4F', 'مراجعة الفصل الأول', 'عمر حسن', 'تحويل · أكّده مصطفى', 'من 26 دقيقة'),
        ('B4TR-••••-7K', 'شهر نوفمبر', 'سلمى محمود', 'مكتبة النور — المنصورة', 'من 41 دقيقة'),
    ]
    rows = ''.join(f'<tr><td class="ltr mono">{a}</td><td>{b}</td><td>{c}</td><td class="mu">{d}</td><td class="mu">{e}</td></tr>' for a, b, c, d, e in codes)
    mist = [('س 7 · فرق الجهد بين طرفي سلك', 41), ('س 12 · التوصيل على التوازي', 38), ('س 3 · وحدة قياس المقاومة', 27), ('س 15 · قانون أوم بالرسم البياني', 22)]
    mh = ''.join(f'<div class="ms"><div class="ms-h"><span>{t}</span><b class="num">{p}%</b></div><div class="ms-b"><i style="width:{p * 2}%"></i></div></div>' for t, p in mist)
    body = f"""
<aside>
  <div class="tb"><div class="tb-av">م.س</div><div><b>أ. محمد سامي</b><small>فيزياء · تالتة ثانوي</small></div></div>
  <nav>{navh}</nav>
  <div class="aside-f">
    <div class="link"><span class="ltr">mr-mohamed.@DOMAIN@</span>{icon('copy', 15, '#94A3B8')}</div>
    <div class="by">{mark(18, 'side')} بواسطة منهجي</div>
  </div>
</aside>
<main>
  <div class="topbar">
    <div><h1>صباح الخير يا أستاذ محمد</h1><p>الأحد 9 نوفمبر · عندك حصة السنتر الساعة 4 العصر</p></div>
    <div class="tools">
      <div class="search">{icon('search', 17, '#94A3B8')}<span>دوّر على طالب أو كود…</span></div>
      <div class="ib">{icon('bell', 19, '#334155')}<i></i></div>
      <span class="cta">{icon('plus', 17, '#fff', 2.6)} دفعة أكواد</span>
    </div>
  </div>
  <div class="kpis">{kh}</div>
  <div class="grid">
    <div class="card chart">
      <div class="c-h"><b>الإيراد آخر 14 يوم</b>
        <div class="lg"><span><i style="background:#4F46E5"></i>أكواد</span><span><i style="background:#F59E0B"></i>تحويلات</span><span><i style="background:#14B8A6"></i>السنتر</span></div></div>
      {revenue_chart()}
    </div>
    <div class="card alerts"><div class="c-h"><b>محتاج انتباهك</b><span class="cnt num">4</span></div>{ah}</div>
    <div class="card codes"><div class="c-h"><b>آخر الأكواد المتفعّلة</b><span class="mu sm">الكل</span></div>
      <table><thead><tr><th>الكود</th><th>الكورس</th><th>الطالب</th><th>منين</th><th>إمتى</th></tr></thead><tbody>{rows}</tbody></table></div>
    <div class="card mist"><div class="c-h"><b>أكتر أسئلة اتغلط فيها</b><span class="mu sm">امتحان الفصل الأول</span></div>{mh}
      <span class="fix">{icon('sparkles', 15, '#4F46E5')} اعمل واجب مراجعة للي غلطوا</span></div>
  </div>
</main>"""
    css = """
body { display: flex; background: #F5F6FB; }
aside { width: 252px; flex: none; background: #fff; border-left: 1px solid #E8EBF3; padding: 22px 16px; display: flex; flex-direction: column; height: 930px; }
.tb { display: flex; gap: 10px; align-items: center; padding: 0 6px 18px; border-bottom: 1px solid #F1F5F9; }
.tb-av { width: 42px; height: 42px; border-radius: 13px; background: linear-gradient(140deg, #6366F1, #312E81); color: #fff; font-weight: 800; font-size: 13px; display: grid; place-items: center; }
.tb b { display: block; font-size: 15px; font-weight: 800; line-height: 1.3; } .tb small { font-size: 12px; color: #64748B; font-weight: 600; }
nav { padding: 14px 0; display: grid; gap: 2px; }
.ni { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 12px; font-size: 14.5px; font-weight: 700; color: #475569; }
.ni span { flex: 1; } .ni em { font-style: normal; font-size: 11.5px; font-weight: 800; color: #fff; background: #F59E0B; border-radius: 8px; padding: 0 7px; }
.ni.on { background: #EEF2FF; color: #3730A3; font-weight: 800; }
.aside-f { margin-top: auto; display: grid; gap: 10px; }
.link { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #3730A3; background: #F5F3FF; border: 1px solid #E0E7FF; border-radius: 12px; padding: 9px 10px; }
.link span { flex: 1; }
.by { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #94A3B8; font-weight: 700; padding: 0 4px; }
main { flex: 1; padding: 24px 30px; min-width: 0; }
.topbar { display: flex; justify-content: space-between; align-items: center; }
.topbar h1 { font-size: 24px; font-weight: 800; line-height: 1.3; } .topbar p { font-size: 13.5px; color: #64748B; font-weight: 600; }
.tools { display: flex; gap: 10px; align-items: center; }
.search { display: flex; align-items: center; gap: 8px; width: 260px; height: 42px; background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 0 12px; font-size: 13.5px; color: #94A3B8; font-weight: 600; }
.ib { position: relative; width: 42px; height: 42px; border-radius: 12px; background: #fff; border: 1px solid #E2E8F0; display: grid; place-items: center; }
.ib i { position: absolute; top: 9px; right: 10px; width: 8px; height: 8px; border-radius: 50%; background: #DC2626; }
.cta { display: inline-flex; align-items: center; gap: 6px; height: 42px; padding: 0 16px; border-radius: 12px; background: #4F46E5; color: #fff; font-weight: 800; font-size: 14px; box-shadow: 0 6px 14px rgba(79,70,229,.28); }
.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 18px 0 16px; }
.kpi { background: #fff; border-radius: 18px; padding: 14px 18px; border: 1px solid #EDF0F6; display: grid; gap: 2px; }
.k-top { display: flex; justify-content: space-between; align-items: center; }
.k-i { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; }
.k-t { font-size: 13px; color: #64748B; font-weight: 700; }
.k-v { font-size: 27px; font-weight: 800; line-height: 1.25; } .k-v small { font-size: 15px; color: #64748B; font-weight: 700; }
.tr { display: inline-flex; align-items: center; gap: 4px; font-size: 12.5px; font-weight: 700; color: #16A34A; }
.tr.mu, .mu { color: #64748B; }
.grid { display: grid; grid-template-columns: 1.75fr 1fr; gap: 16px; }
.card { background: #fff; border-radius: 18px; border: 1px solid #EDF0F6; padding: 16px 18px; }
.c-h { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.c-h b { font-size: 16px; font-weight: 800; }
.sm { font-size: 12.5px; font-weight: 700; }
.lg { display: flex; gap: 14px; font-size: 12.5px; font-weight: 700; color: #475569; }
.lg span { display: inline-flex; align-items: center; gap: 6px; } .lg i { width: 10px; height: 10px; border-radius: 3px; }
.chart svg { display: block; width: 100%; height: auto; }
.cnt { background: #FEE2E2; color: #DC2626; font-weight: 800; font-size: 12px; border-radius: 8px; padding: 0 8px; }
.al { display: flex; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid #F1F5F9; }
.al:last-child { border-bottom: 0; }
.a-i { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; flex: none; }
.a-t { flex: 1; min-width: 0; } .a-t b { display: block; font-size: 13.5px; font-weight: 800; line-height: 1.5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } .a-t small { font-size: 11.5px; color: #64748B; font-weight: 600; }
.a-b { font-size: 12px; font-weight: 800; color: #4F46E5; background: #EEF2FF; border-radius: 9px; padding: 4px 10px; flex: none; }
table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
th { text-align: right; font-size: 12px; color: #94A3B8; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #F1F5F9; }
td { padding: 8px 8px; border-bottom: 1px solid #F8FAFC; font-weight: 700; white-space: nowrap; }
td.mu { font-weight: 600; }
.mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 12.5px; color: #3730A3; text-align: right; }
.ms { padding: 4px 0; } .ms-h { display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; color: #334155; }
.ms-h b { color: #DC2626; font-weight: 800; }
.ms-b { height: 7px; border-radius: 4px; background: #F1F5F9; margin-top: 5px; overflow: hidden; }
.ms-b i { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, #F87171, #DC2626); margin-right: 0; }
.fix { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; font-size: 13px; font-weight: 800; color: #4F46E5; background: #EEF2FF; border-radius: 11px; padding: 7px 12px; }
"""
    write('screens/dashboard.html', page(body, css, w=1440, h=930, bg='#F5F6FB', title='dashboard'))
    SIZES['dashboard'] = [1440, 930]


if __name__ == '__main__':
    for k in BRANDS:
        student_home(k)
    player()
    exam()
    parent_report()
    redeem()
    dashboard()
    with open(f'{ROOT}/screens/sizes.json', 'w') as f:
        json.dump(SIZES, f, indent=1)
    print('screens:', ', '.join(SIZES))
