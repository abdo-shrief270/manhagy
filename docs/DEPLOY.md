# تشغيل منهجي على سيرفر

المنصة كلها بتشتغل بـ Docker Compose من `infra/production`:

| الحاجة | العنوان |
|---|---|
| صفحة المدرسين (اللاندينج) | `manhagy.online` و `www` |
| تطبيق المدرس / السنتر + الـ API | `app.manhagy.online` |
| منصات الطلاب | `{slug}.manhagy.online` (لكل مدرس لينك) |
| لوحة الأدمن | دومين تختاره (`ADMIN_DOMAIN`) |

الخدمات: `caddy` (HTTPS والتطبيقات الثلاثة والصفحة)، `api` (Laravel على FrankenPHP)، `worker` (Horizon)، `scheduler`، `pgsql` 17، `redis`، `backup` (نسخة يومية).

## 1. السيرفر والدومين
- سيرفر Ubuntu، 2 جيجا رام على الأقل (4 أحسن)، وعليه Docker و Docker Compose.
- في الـ DNS: سجلات A لـ `manhagy.online` و `www` و `app` و `*` (wildcard) على IP السيرفر، ودومين الأدمن لو هتستخدمه.

## 2. أول مرة
```bash
git clone https://github.com/abdo-shrief270/manhagy.git /opt/manhagy
cd /opt/manhagy/infra/production
./init.sh manhagy.online you@email.com      # بيعمل .env بأسرار عشوائية
nano .env                                    # ADMIN_DOMAIN, SIGNUP_OPEN, BILLING_INSTAPAY_*, VERIFY_WHATSAPP …
./deploy.sh
```
- **شهادات HTTPS:** Caddy بيجيبها لوحده. منصات الطلاب بتاخد شهادة أول ما حد يفتحها، بعد ما الـ API يتأكد إن المنصة موجودة (`public/sites-tls`)، فمحدش يقدر يخلّي السيرفر يطلب شهادات لأسماء مش موجودة.
- **حساب الأدمن:**
  ```bash
  docker compose exec api php artisan billing:admin you@email.com "اسمك"
  docker compose exec api php artisan billing:admin-2fa you@email.com    # امسح الكود بتطبيق المصادقة
  ```

### لو السيرفر عليه nginx شغال بالفعل
`./init.sh manhagy.online you@email.com nginx` بيخلي Caddy يسمع على `127.0.0.1:8088` بس، وnginx يفضل ماسك 80/443. انسخ `nginx-site.conf` لـ `/etc/nginx/sites-available/manhagy` واعمل شهادة wildcard بتحدي الـ DNS (مثلاً Cloudflare):
```bash
apt install python3-certbot-dns-cloudflare
certbot certonly --dns-cloudflare --dns-cloudflare-credentials ~/.cloudflare.ini -d manhagy.online -d '*.manhagy.online'
```
(لو اللاندينج اترفع قبل كده بـ `deploy/setup-server.sh`، الـ nginx site ده بيحل محلّه: الصفحة بقت جوه الـ stack.)

## 3. كل تحديث
```bash
cd /opt/manhagy/infra/production && ./deploy.sh
```
بياخد نسخة من الداتابيز قبل الـ migrations، ويبني، ويشغّل.

**تلقائي من GitHub:** بعد ما تيستات الـ API وبناء التطبيقات ينجحوا على `main`، الـ workflow `deploy-app` بيكلّم السيرفر. حط في Secrets الريبو:
- `APP_DEPLOY_HOST`, `APP_DEPLOY_USER`, `APP_DEPLOY_KEY` (مفتاح SSH خاص).
- وعلى السيرفر، في `~/.ssh/authorized_keys` بتاع اليوزر ده، اربط المفتاح بالسكريبت بس:
  ```
  restrict,command="/opt/manhagy/infra/production/ci-deploy.sh" ssh-ed25519 AAAA… github-deploy
  ```
  كده المفتاح ده ما يقدرش يعمل غير «انشر الـ commit ده» لو موجود على `main`.

## 4. الفيديو على السيرفر
- الفيديو بيترفع على السيرفر نفسه وبيتحوّل بـ ffmpeg (موجود في صورة الـ API) لكذا جودة متشفّرة. التحويل بيشتغل في الـ worker على طابور `video`، فيديو واحد في المرة (`VIDEO_WORKERS` في `.env` لو السيرفر عنده أنوية كتير).
- **المساحة:** ساعة فيديو 1080p بتاخد حوالي 2.4 جيجا بكل جوداتها، و 720p حوالي 0.9 جيجا. اختار سيرفر بديسك كبير أو ضيف ديسك واركبه على volume الـ `storage`.
- **التحويل بياخد وقت:** على 4 أنوية، ساعة فيديو 1080p بتاخد تقريباً من نص ساعة لساعة. الفيديو بيظهر «بيتحوّل للجودات» لحد ما يخلص.
- **النسخ الاحتياطي:** الفيديوهات مش في الأرشيف اليومي (كبيرة)؛ لو `BACKUP_RCLONE_REMOTE` متظبط، بتتنسخ على `<remote>/videos` بالتغييرات بس كل يوم.
- **الباندويدث:** كل طالب بيتفرّج 720p بيسحب حوالي 0.7 جيجا في الساعة. خد ده في الحساب في باقة السيرفر.

## 5. الحصص اللايف
- سيرفر البث (MediaMTX) شغال كخدمة `live` جنب الـ API. `init.sh` بيعمل `LIVE_SECRET` والعناوين على `live.<الدومين>`.
- في `.env`: `LIVE_PUBLIC_IP` = الـ IP العام للسيرفر (المتصفح بيبعت الصورة عليه مباشرة).
- افتح في الـ firewall: `1935/tcp` (البث من OBS) و `8189/udp` (البث من المتصفح).
- DNS لـ `live.<الدومين>` على نفس السيرفر.
- **المدرس يبث من Chrome أو Edge** (أو OBS). متصفحات من غير H.264 بتطلّع صوت بس عند الطلاب، والاستوديو بينبّه على ده.
- **الباندويدث:** كل طالب بيسحب سرعة بث المدرس (حوالي 2.5 ميجابت). كارت 1 جيجابت ≈ 400 طالب في نفس اللحظة. لما العدد يكبر انقل البث لسيرفر لوحده: `infra/live/README.md` (من غير تغيير كود، بس `LIVE_*`).
- بعد ما المدرس يدوس «خلّص الحصة»، التسجيل بيتسحب ويتحوّل ويتحط في الكورس لوحده (التسجيلات بتتمسح من سيرفر البث بعد أسبوع).

## 6. تأكيد رقم الطالب بواتساب
الطالب بيبعت رسالة للمنصة، ومحتاجين نستقبلها:
1. رقم واتساب للمنصة على WhatsApp Cloud API (Meta for Developers → تطبيق → WhatsApp).
2. الـ Webhook: `https://app.manhagy.online/api/v1/public/whatsapp/webhook`، و Verify token = `WHATSAPP_VERIFY_TOKEN` من `.env`، واشترك في `messages`.
3. في `.env`: `VERIFY_WHATSAPP` (الرقم بالصيغة الدولية 2010…)، `WHATSAPP_APP_SECRET` (App Secret)، واختياري `WHATSAPP_TOKEN` + `WHATSAPP_PHONE_ID` عشان يرد «اتأكد رقمك».
4. `./deploy.sh`.

من غير `VERIFY_WHATSAPP` التأكيد مقفول، والطلاب بيتفرّجوا من غيره.

## 7. سوق منهجي (اختياري)
- اعمل DNS لـ `courses.manhagy.online` (أو أي اسم تختاره) على نفس السيرفر.
- في `.env`: `MARKET_DOMAIN=courses.manhagy.online` و `MARKET_URL=https://courses.manhagy.online`.
- `./deploy.sh` بيبني خدمة `market` ويطلّع لها شهادة لوحدها (اسم محدد بيكسب على `*.manhagy.online`).
- الكورسات بتظهر لما المدرس يفتح «سوق منهجي» من التطبيق؛ `market:sync` بيحدّث كل 5 دقايق، وكل ليلة بيعيد البناء كله.

## 8. النسخ الاحتياطي
- كل يوم في `BACKUP_TIME` في `infra/production/backups/` (الداتابيز + الملفات)، وبيتمسح القديم بعد `BACKUP_KEEP_DAYS`.
- نسخة برّه السيرفر: `BACKUP_RCLONE_REMOTE` + إعدادات rclone في `.env`.
- `./backup.sh` = نسخة دلوقتي، `./restore.sh backups/…dump` = ترجيع.

## 9. المتابعة
`./setup-monitoring.sh` بيضيف فحص كل دقيقتين ورسالة تليجرام لو حاجة وقعت (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`).
