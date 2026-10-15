"""Shared pieces for the Manhagy mockups and ads: icons, tokens, phone chrome."""
import json
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
# The product's domain, written into every screen, ad and page (teacher sites are {slug}.DOMAIN).
DOMAIN = os.environ.get('MANHAGY_DOMAIN', 'manhagy.online')
LUCIDE = json.load(open('/home/user/muhasebi-egypt/apps/web/node_modules/@iconify-json/lucide/icons.json'))
LOGO_MARK = open(os.path.join(ROOT, 'site/assets/logo-mark.svg')).read()


def icon(name, size=20, color='currentColor', stroke=None, cls=''):
    """Inline lucide icon as SVG markup."""
    icons = LUCIDE['icons']
    if name not in icons:
        name = LUCIDE.get('aliases', {}).get(name, {}).get('parent', name)
    body = icons[name]['body']
    if stroke:
        body = body.replace('stroke-width="2"', f'stroke-width="{stroke}"')
    c = f' class="{cls}"' if cls else ''
    return (f'<svg{c} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="{size}" height="{size}" '
            f'style="color:{color};flex:none">{body}</svg>')


def mark(size=40, uid='a'):
    """The logo mark with a unique gradient id (several on one page)."""
    return LOGO_MARK.replace('mh-g', f'mh-g-{uid}').replace('<svg ', f'<svg width="{size}" height="{size}" ', 1)


TOKENS = """
@font-face { font-family: Cairo; src: local('Cairo'); }
:root {
  --brand: #4F46E5; --brand-d: #3730A3; --brand-dd: #1E1B4B; --brand-l: #EEF2FF; --brand-ll: #E0E7FF; --brand-m: #A5B4FC;
  --amber: #F59E0B; --amber-d: #B45309; --amber-l: #FEF3C7; --amber-m: #FBBF24;
  --ink: #0F172A; --ink-2: #334155; --muted: #64748B; --line: #E2E8F0; --line-2: #F1F5F9;
  --bg: #F5F6FB; --card: #FFFFFF;
  --green: #16A34A; --green-l: #DCFCE7; --red: #DC2626; --red-l: #FEE2E2; --teal: #0D9488; --teal-l: #CCFBF1;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Cairo, sans-serif; direction: rtl; color: var(--ink); -webkit-font-smoothing: antialiased; }
.num { direction: ltr; unicode-bidi: isolate; font-variant-numeric: tabular-nums; }
.ltr { direction: ltr; unicode-bidi: isolate; }
"""


def status_bar(dark=False):
    color = '#fff' if dark else '#0F172A'
    return f"""
<div class="sb" style="color:{color}">
  <span class="sb-time">9:41</span>
  <span class="sb-icons">
    <svg width="18" height="12" viewBox="0 0 18 12"><rect x="0" y="8" width="3" height="4" rx="1" fill="{color}"/><rect x="5" y="5.5" width="3" height="6.5" rx="1" fill="{color}"/><rect x="10" y="3" width="3" height="9" rx="1" fill="{color}"/><rect x="15" y="0" width="3" height="12" rx="1" fill="{color}"/></svg>
    <svg width="16" height="12" viewBox="0 0 16 12"><path d="M8 11.5l2.3-2.6a3.3 3.3 0 0 0-4.6 0z" fill="{color}"/><path d="M3.9 7.1a5.9 5.9 0 0 1 8.2 0l1.4-1.6a8 8 0 0 0-11 0z" fill="{color}"/><path d="M1.4 4.3a9.6 9.6 0 0 1 13.2 0L16 2.7a11.7 11.7 0 0 0-16 0z" fill="{color}"/></svg>
    <svg width="27" height="13" viewBox="0 0 27 13"><rect x=".5" y=".5" width="22" height="12" rx="3.5" fill="none" stroke="{color}" opacity=".4"/><rect x="2" y="2" width="17" height="9" rx="2" fill="{color}"/><path d="M24.5 4.5v4c.8-.3 1.4-1.1 1.4-2s-.6-1.7-1.4-2z" fill="{color}" opacity=".45"/></svg>
  </span>
</div>"""


STATUS_CSS = """
.sb { height: 50px; display: flex; align-items: center; justify-content: space-between; padding: 6px 30px 0 34px; direction: ltr; font-weight: 700; font-size: 16px; letter-spacing: -.01em; }
.sb-icons { display: flex; gap: 6px; align-items: center; }
"""


def page(body, css='', w=390, h=844, bg='var(--bg)', title='screen'):
    return f"""<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>{title}</title>
<style>{TOKENS}{STATUS_CSS}
html, body {{ width: {w}px; height: {h}px; overflow: hidden; background: {bg}; }}
{css}</style></head><body>{body}</body></html>"""


def write(path, html):
    full = os.path.join(ROOT, path)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, 'w', encoding='utf-8') as f:
        f.write(html.replace('@DOMAIN@', DOMAIN))
