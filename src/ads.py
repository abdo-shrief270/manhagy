"""Facebook / Instagram ad creatives and the OG image for Manhagy, written as HTML."""
import json

from common import icon, mark, page, write, ROOT
from screens import code_card, CARD_CSS

SIZES = {}
SCR = '../out/screens'


def phone(img, width, extra_style='', cls=''):
    return (f'<div class="phone {cls}" style="width:{width}px;{extra_style}">'
            f'<img src="{SCR}/{img}.png" alt=""><span class="island"></span></div>')


PHONE_CSS = """
.phone { position: absolute; border-radius: 9% / 4.3%; background: #0B0B12; padding: 2.9%; box-shadow: 0 50px 90px rgba(10,8,40,.45), 0 0 0 2px #2b2a3a inset; }
.phone img { display: block; width: 100%; border-radius: 7.4% / 3.45%; }
.phone .island { position: absolute; top: 3.1%; left: 50%; transform: translateX(-50%); width: 29%; height: 3.3%; border-radius: 30px; background: #000; }
"""

BRAND = lambda dark=True, size=60, uid='b': f"""
<div class="brand {'dark' if dark else 'light'}">{mark(size, uid)}<div><b>منهجي</b><small>MANHAGY</small></div></div>"""

BRAND_CSS = """
.brand { display: flex; align-items: center; gap: 14px; }
.brand b { display: block; font-size: 44px; font-weight: 800; line-height: 1; }
.brand small { display: block; font-size: 13px; letter-spacing: .4em; font-weight: 800; direction: ltr; text-align: right; margin-top: 7px; }
.brand.dark b { color: #fff; } .brand.dark small { color: #A5B4FC; }
.brand.light b { color: #1E1B4B; } .brand.light small { color: #6366F1; }
.cta { display: inline-flex; align-items: center; gap: 14px; height: 92px; padding: 0 44px; border-radius: 26px; font-size: 36px; font-weight: 800; }
.cta.amber { background: #F59E0B; color: #1E1B4B; box-shadow: 0 16px 34px rgba(245,158,11,.35); }
.cta.indigo { background: #4F46E5; color: #fff; box-shadow: 0 16px 34px rgba(79,70,229,.35); }
.chip { display: inline-flex; align-items: center; gap: 10px; border-radius: 999px; font-weight: 800; }
"""

DARK_BG = """
background: radial-gradient(900px 700px at 15% 70%, rgba(99,102,241,.45), transparent 60%),
            radial-gradient(700px 600px at 100% 0%, rgba(245,158,11,.16), transparent 60%),
            linear-gradient(180deg, #14113A, #0B0920);
"""


def lens(x, y, w, h, cx, cy, zoom, scale):
    """A magnified crop of the player screen around (cx, cy) in screen CSS px."""
    bw = 390 * zoom
    px = -(cx * zoom - w / 2)
    py = -(cy * zoom - h / 2)
    return (f'<div class="lens" style="left:{x}px;top:{y}px;width:{w}px;height:{h}px;'
            f'background-image:url({SCR}/player.png);background-size:{bw:.0f}px auto;background-position:{px:.0f}px {py:.0f}px"></div>')


def ad_leak():
    w, h = 1080, 1350
    body = f"""
<div class="top">{BRAND(True, 62, 'l1')}<span class="chip tag">{icon('graduation-cap', 26, '#FBBF24')} للمدرسين والسناتر</span></div>
<h1>فيديوهاتك بتتسرّب<br><em>على تليجرام؟</em></h1>
<p class="sub">مع منهجي، كل فيديو عليه <b>اسم الطالب ورقمه</b>.<br>لو اتسرّب… تعرف من مين.</p>
{phone('player', 400, 'left:60px;top:610px;transform:rotate(-4deg)')}
<svg class="link" width="1080" height="1350"><path d="M196 818 C 300 800, 420 736, 504 722" fill="none" stroke="#FBBF24" stroke-width="4" stroke-dasharray="2 10" stroke-linecap="round"/><circle cx="194" cy="819" r="9" fill="#FBBF24"/></svg>
{lens(504, 652, 504, 140, 132, 210, 2.25, 1)}
<p class="lens-cap">{icon('scan-eye', 30, '#FBBF24')} اسمه ورقمه بيتحرّك على الفيديو</p>
<ul class="pts">
  <li>{icon('link', 30, '#A5B4FC')}<span>لينك الفيديو بيقفل بعد دقايق، ومفيش تحميل</span></li>
  <li>{icon('smartphone', 30, '#A5B4FC')}<span>جهازين بالكتير لكل طالب</span></li>
  <li>{icon('bell-ring', 30, '#A5B4FC')}<span>تنبيه لو الحساب اتفتح من مكانين</span></li>
</ul>
<div class="bottom"><span class="cta amber">احجز مكانك في أول دفعة {icon('arrow-left', 34, '#1E1B4B', 2.6)}</span></div>
"""
    css = PHONE_CSS + BRAND_CSS + """
body { %s color: #fff; position: relative; }
body::after { content: ''; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.05) 1.2px, transparent 1.3px); background-size: 26px 26px; pointer-events: none; }
.top { position: absolute; top: 64px; right: 72px; left: 72px; display: flex; justify-content: space-between; align-items: center; }
.tag { font-size: 26px; color: #FDE68A; background: rgba(251,191,36,.12); border: 1.5px solid rgba(251,191,36,.35); padding: 10px 22px; }
h1 { position: absolute; top: 190px; right: 72px; font-size: 96px; font-weight: 900; line-height: 1.12; letter-spacing: -.01em; }
h1 em { font-style: normal; color: #FBBF24; }
.sub { position: absolute; top: 418px; right: 72px; font-size: 36px; font-weight: 600; line-height: 1.5; color: #E0E7FF; }
.sub b { color: #fff; font-weight: 800; }
.lens { position: absolute; border-radius: 28px; border: 4px solid #FBBF24; box-shadow: 0 30px 60px rgba(0,0,0,.45), 0 0 0 10px rgba(251,191,36,.12); }
.link { position: absolute; inset: 0; }
.lens-cap { position: absolute; top: 836px; right: 72px; width: 504px; justify-content: center; display: flex; align-items: center; gap: 12px; font-size: 30px; font-weight: 800; color: #FDE68A; }
.pts { position: absolute; top: 930px; right: 80px; width: 480px; list-style: none; display: grid; gap: 22px; }
.pts li { display: flex; gap: 16px; align-items: flex-start; font-size: 29px; font-weight: 700; color: #E0E7FF; line-height: 1.45; }
.pts svg { margin-top: 4px; }
.bottom { position: absolute; bottom: 64px; right: 72px; display: flex; }
.note { font-size: 26px; font-weight: 700; color: #A5B4FC; text-align: left; line-height: 1.4; }
""" % DARK_BG
    write('ads/ad-1-leak.html', page(body, css, w=w, h=h, bg='#0B0920', title='ad-1-leak'))
    SIZES['ad-1-leak'] = [w, h]


def ad_brand():
    w, h = 1080, 1350
    body = f"""
<div class="top">{BRAND(False, 62, 'l2')}<span class="chip tag">{icon('sparkles', 24, '#4F46E5')} من أولى ابتدائي لحد الجامعة</span></div>
<h1>منصة وتطبيق باسمك<br><em>لطلابك</em></h1>
<p class="sub">مش اسم منصة تانية. لينك واحد فيه شرحك وأكوادك<br>وامتحاناتك وتقرير ولي الأمر.</p>
{phone('home-uni', 330, 'left:96px;top:640px;transform:rotate(-8deg);z-index:1')}
{phone('home-arabic', 330, 'left:654px;top:640px;transform:rotate(8deg);z-index:1')}
{phone('home-physics', 380, 'left:350px;top:590px;z-index:2')}
<div class="urls">
  <span class="url main" style="left:342px;top:1236px;z-index:3">{icon('link', 24, '#fff')}<span class="ltr">mr-mohamed.@DOMAIN@</span></span>
</div>
"""
    css = PHONE_CSS + BRAND_CSS + """
body { background: radial-gradient(800px 600px at 50% 85%, rgba(99,102,241,.22), transparent 65%), linear-gradient(180deg, #F5F3FF, #FFFFFF 55%, #EEF2FF); position: relative; color: #1E1B4B; }
.top { position: absolute; top: 64px; right: 72px; left: 72px; display: flex; justify-content: space-between; align-items: center; }
.tag { font-size: 25px; color: #3730A3; background: #fff; border: 1.5px solid #C7D2FE; padding: 10px 22px; box-shadow: 0 6px 16px rgba(79,70,229,.1); }
h1 { position: absolute; top: 190px; right: 72px; font-size: 96px; font-weight: 900; line-height: 1.12; letter-spacing: -.01em; }
h1 em { font-style: normal; color: #4F46E5; }
.sub { position: absolute; top: 420px; right: 72px; font-size: 34px; font-weight: 600; line-height: 1.55; color: #475569; }
.url { position: absolute; display: inline-flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 800; color: #3730A3; background: #fff; border: 1.5px solid #C7D2FE; border-radius: 999px; padding: 8px 20px; box-shadow: 0 10px 24px rgba(30,27,75,.12); }
.url.main { font-size: 27px; color: #fff; background: #4F46E5; border-color: #4F46E5; padding: 12px 28px; box-shadow: 0 16px 34px rgba(79,70,229,.4); }
"""
    write('ads/ad-2-brand.html', page(body, css, w=w, h=h, bg='#fff', title='ad-2-brand'))
    SIZES['ad-2-brand'] = [w, h]


def ad_codes():
    w, h = 1080, 1350
    body = f"""
<div class="top">{BRAND(False, 62, 'l3')}<span class="chip tag">{icon('ticket', 26, '#B45309')} أكواد وكروت باسمك</span></div>
<h1>بطّل تراجع<br><em>سكرينات التحويل</em></h1>
<p class="sub">اطبع كروت أكواد باسمك ووزّعها على المكتبات والسناتر.<br>كل كود بيتفعّل <b>مرة واحدة</b>، وتعرف مين استخدمه وإمتى.</p>
<div class="cards">
  <div class="c c3">{code_card('Z9MC-4TQW-4F', 'كارت مراجعة الفصل الأول', 3, 470)}</div>
  <div class="c c2">{code_card('P2HX-7KDL-9A', 'كارت شهر نوفمبر', 5, 470)}</div>
  <div class="c c1">{code_card('K7Q4-9MXP-2D', 'كارت شهر نوفمبر', 7, 470)}</div>
</div>
{phone('redeem', 360, 'left:84px;top:600px;transform:rotate(-3deg);z-index:2')}
<div class="stat">
  <div class="s-i">{icon('ticket', 30, '#4F46E5')}</div>
  <div><small>اتفعّل النهارده</small><b><span class="num">37</span> كود</b></div>
  <svg width="120" height="46" viewBox="0 0 120 46"><path d="M2 40 L20 34 L38 36 L56 24 L74 27 L92 14 L118 6" fill="none" stroke="#16A34A" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
</div>
<div class="bottom"><span class="cta indigo">احجز مكانك في أول دفعة {icon('arrow-left', 34, '#fff', 2.6)}</span></div>
"""
    css = PHONE_CSS + BRAND_CSS + CARD_CSS + """
body { background: radial-gradient(700px 500px at 75% 70%, rgba(245,158,11,.25), transparent 65%), linear-gradient(180deg, #FFFBEB, #FFF7E0 60%, #FEF3C7); position: relative; color: #1E1B4B; }
.top { position: absolute; top: 64px; right: 72px; left: 72px; display: flex; justify-content: space-between; align-items: center; }
.tag { font-size: 25px; color: #92400E; background: #fff; border: 1.5px solid #FCD34D; padding: 10px 22px; }
h1 { position: absolute; top: 190px; right: 72px; font-size: 96px; font-weight: 900; line-height: 1.12; letter-spacing: -.01em; }
h1 em { font-style: normal; color: #B45309; }
.sub { position: absolute; top: 420px; right: 72px; font-size: 33px; font-weight: 600; line-height: 1.55; color: #57534E; }
.sub b { color: #1E1B4B; font-weight: 800; }
.cards { position: absolute; top: 600px; right: 80px; width: 520px; height: 560px; }
.c { position: absolute; right: 0; transform-origin: center; }
.c .kard { box-shadow: 0 24px 50px rgba(120,53,15,.18); }
.c .k-code { font-size: 36px; } .c .k-h b { font-size: 19px; } .c .k-h small { font-size: 16px; } .c .k-av { width: 46px; height: 46px; font-size: 15px; }
.c .k-f { font-size: 15px; } .c .k-body { padding: 18px 20px 14px; } .c .k-side { width: 18px; }
.c1 { top: 300px; transform: rotate(-3deg); z-index: 3; }
.c2 { top: 150px; transform: rotate(4deg); z-index: 2; right: 20px; }
.c3 { top: 0; transform: rotate(-6deg); z-index: 1; right: 6px; opacity: .95; }
.stat { position: absolute; top: 1030px; right: 112px; display: flex; align-items: center; gap: 18px; background: #fff; border-radius: 24px; padding: 16px 22px; box-shadow: 0 20px 44px rgba(120,53,15,.16); border: 1px solid #FDE68A; z-index: 4; }
.s-i { width: 58px; height: 58px; border-radius: 18px; background: #EEF2FF; display: grid; place-items: center; }
.stat small { display: block; font-size: 20px; font-weight: 700; color: #64748B; } .stat b { font-size: 34px; font-weight: 900; line-height: 1.2; }
.bottom { position: absolute; bottom: 64px; right: 72px; }
"""
    write('ads/ad-3-codes.html', page(body, css, w=w, h=h, bg='#FFFBEB', title='ad-3-codes'))
    SIZES['ad-3-codes'] = [w, h]


def story_leak():
    w, h = 1080, 1920
    body = f"""
<div class="top">{BRAND(True, 62, 's1')}</div>
<h1>فيديوهاتك بتتسرّب<br><em>على تليجرام؟</em></h1>
<p class="sub">مع منهجي، كل فيديو عليه <b>اسم الطالب ورقمه</b>.<br>لو اتسرّب… تعرف من مين.</p>
{phone('player', 470, 'left:305px;top:790px', 'fade')}
{lens(140, 1122, 800, 200, 132, 210, 3.6, 1)}
<p class="lens-cap">{icon('scan-eye', 32, '#FBBF24')} اسم الطالب ورقمه بيتحرّك على الفيديو</p>
<div class="bottom"><span class="cta amber">احجز مكانك في أول دفعة {icon('arrow-left', 34, '#1E1B4B', 2.6)}</span></div>
"""
    css = PHONE_CSS + BRAND_CSS + """
body { %s color: #fff; position: relative; text-align: center; }
body::after { content: ''; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.05) 1.2px, transparent 1.3px); background-size: 26px 26px; pointer-events: none; }
.top { position: absolute; top: 250px; left: 0; right: 0; display: flex; justify-content: center; }
h1 { position: absolute; top: 370px; left: 0; right: 0; font-size: 98px; font-weight: 900; line-height: 1.12; }
h1 em { font-style: normal; color: #FBBF24; }
.sub { position: absolute; top: 600px; left: 0; right: 0; font-size: 38px; font-weight: 600; line-height: 1.5; color: #E0E7FF; }
.sub b { color: #fff; font-weight: 800; }
.lens { position: absolute; border-radius: 30px; border: 4px solid #FBBF24; box-shadow: 0 30px 60px rgba(0,0,0,.5), 0 0 0 10px rgba(251,191,36,.12); z-index: 2; }
.lens-cap { position: absolute; top: 1346px; left: 50%%; transform: translateX(-50%%); white-space: nowrap; display: flex; align-items: center; gap: 12px; font-size: 32px; font-weight: 800; color: #FDE68A; z-index: 2;
  background: rgba(11,9,32,.85); padding: 10px 22px; border-radius: 18px; }
.bottom { position: absolute; top: 1470px; left: 0; right: 0; display: flex; justify-content: center; z-index: 3; }
.phone.fade { -webkit-mask-image: linear-gradient(180deg, #000 52%%, transparent 74%%); mask-image: linear-gradient(180deg, #000 52%%, transparent 74%%); }
""" % DARK_BG
    write('ads/story-1-leak.html', page(body, css, w=w, h=h, bg='#0B0920', title='story-1-leak'))
    SIZES['story-1-leak'] = [w, h]


def og():
    w, h = 1200, 630
    body = f"""
<div class="txt">
  {BRAND(True, 56, 'og')}
  <h1>منصتك التعليمية <em>باسمك</em></h1>
  <p>فيديو محمي باسم كل طالب، أكواد، امتحانات،<br>وتقرير لولي الأمر. للمدرسين والسناتر.</p>
</div>
{phone('home-physics', 250, 'left:250px;top:90px;transform:rotate(6deg)')}
{phone('player', 260, 'left:60px;top:60px;transform:rotate(-5deg)')}
"""
    css = PHONE_CSS + BRAND_CSS + """
body { %s color: #fff; position: relative; overflow: hidden; }
.txt { position: absolute; top: 120px; right: 70px; width: 560px; }
h1 { font-size: 62px; font-weight: 900; line-height: 1.2; margin-top: 34px; }
h1 em { font-style: normal; color: #FBBF24; }
.txt p { font-size: 27px; font-weight: 600; color: #E0E7FF; line-height: 1.55; margin-top: 14px; }
.phone { box-shadow: 0 30px 60px rgba(0,0,0,.5), 0 0 0 2px #2b2a3a inset; }
""" % DARK_BG
    write('ads/og.html', page(body, css, w=w, h=h, bg='#0B0920', title='og'))
    SIZES['og'] = [w, h]


if __name__ == '__main__':
    ad_leak()
    ad_brand()
    ad_codes()
    story_leak()
    og()
    with open(f'{ROOT}/ads/sizes.json', 'w') as f:
        json.dump(SIZES, f, indent=1)
    print('ads:', ', '.join(SIZES))
