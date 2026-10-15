# منهجي — عُدّة اختبار السوق

| الفولدر / الملف | فيه إيه |
|---|---|
| `site/` | صفحة الحجز جاهزة للرفع: `index.html` و `privacy.html` والصور |
| `out/ads/` | صور الإعلانات (3 للفيد + ستوري + صورة اللينك) |
| `out/screens/` | شاشات التطبيق بجودة عالية (طالب، فيديو، امتحان، كود، ولي الأمر، لوحة المدرس) |
| `ADS.md` | كلام الإعلانات وإعداد الحملة وإزاي نقرر |
| `INTERVIEW.md` | أسئلة مكالمة الـ 15 دقيقة مع المدرس |
| `docs/` | خطة المنتج والخطة التقنية |
| `src/` | الكود اللي بيرسم الشاشات والإعلانات والصفحة (لو حبيت تغيّر حاجة) |

## قبل ما ترفع الصفحة
1. **الإعدادات:** في أول `site/index.html` و `site/privacy.html`:
   ```js
   window.MANHAGY = {
     whatsapp: '201555440882',   // رقم الواتساب بالصيغة الدولية
     metaPixelId: '',            // Meta Pixel ID
     gaId: '',                   // Google Analytics 4 (اختياري)
   };
   ```
2. **الدومين:** كل حاجة مبنية على `manhagy.app` (الصفحة، ولينكات المدرسين في الصور، والإعلانات). لو اشتريت دومين تاني، غيّر `DOMAIN` في `src/common.py` وشغّل أوامر البناء اللي في آخر الملف، أو من غير ما تعدّل الملف:
   ```bash
   cd src && export MANHAGY_DOMAIN=your-domain.net   # وبعدين أوامر البناء
   ```
   وغيّر اللينكات في `ADS.md`.
3. **Meta domain verification:** حط الـ meta tag مكان التعليق اللي في الـ `<head>`.

## الرفع (اختار واحدة)
- **Cloudflare Pages** (ببلاش): Workers & Pages ← Create ← Pages ← Upload assets ← ارفع فولدر `site` ← اربط الدومين.
- **Netlify Drop** (ببلاش): اسحب فولدر `site` على app.netlify.com/drop.
- **على سيرفرك (المعمول هنا):**
  1. الدومين: اعمل A record لـ `manhagy.app` و `www` على IP السيرفر.
  2. على السيرفر (root) مرة واحدة: `./deploy/setup-server.sh manhagy.app you@example.com` — بيعمل موقع nginx + شهادة HTTPS + مستخدم للرفع مفتاحه يقدر يكتب في `/var/www/manhagy` بس، وبيطبع 3 قيم.
  3. حط القيم دي في GitHub: Settings ← Secrets and variables ← Actions: `DEPLOY_HOST` و `DEPLOY_USER` و `DEPLOY_KEY`.
  4. أي push على `main` بيغيّر في `site/` بيترفع لوحده (أو Actions ← deploy ← Run workflow).

## الأحداث اللي بتتبعت (لو حطيت الـ Pixel)
| الحدث | إمتى |
|---|---|
| `PageView` | فتح الصفحة |
| `Contact` | دوسة على أي زرار واتساب |
| `Lead` | دوسة «ابعت على واتساب» في الفورم بعد ما يكمّله (حسّن الحملة على ده) |

مفيش اسم ولا رقم بيتبعت لميتا. رسالة الواتساب فيها مصدر الإعلان (`utm_source` / `utm_campaign`) عشان تعرف كل مدرس جه منين.

## العنوان حسب الإعلان
- `/?v=leak`: «فيديوهاتك عليها اسم كل طالب…»
- `/?v=codes`: «بطّل تراجع سكرينات التحويل…» (وصورة تفعيل الكود)
- من غيرهم: «منصة وتطبيق باسمك لطلابك»

## لو عايز تغيّر الصور أو الكلام
```bash
cd src
python3 screens.py && NODE_PATH=/opt/node22/lib/node_modules node render.cjs screens 2   # شاشات التطبيق
python3 ads.py && NODE_PATH=/opt/node22/lib/node_modules node render.cjs ads 1           # الإعلانات
python3 site.py                                                                         # الصفحة
```
الأسماء والأرقام في الصور تخيّلية (أ. محمد سامي، يوسف أحمد…).
