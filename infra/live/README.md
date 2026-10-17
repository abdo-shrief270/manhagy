# سيرفر البث لوحده

لما الحصص اللايف تكبر على السيرفر الأساسي (الباندويدث أو المعالج)، انقل البث لسيرفر تاني من غير ما تغيّر في الكود:

1. سيرفر جديد عليه Docker، و DNS لـ `live.manhagy.online` يشاور عليه.
2. انسخ الريبو، وادخل `infra/live`، و `cp .env.example .env` واملاه:
   - `LIVE_SECRET` نفس اللي في `.env` السيرفر الأساسي.
   - `MAIN_SERVER_IP` = IP السيرفر الأساسي، و `LIVE_PUBLIC_IP` = IP السيرفر ده.
3. افتح في الـ firewall: `80`, `443`, `1935/tcp` (OBS), `8189/udp` (البث من المتصفح).
4. `docker compose up -d`.
5. على السيرفر الأساسي، في `infra/production/.env`:
   ```
   LIVE_DOMAIN=            # فاضي: الدومين بقى على السيرفر الجديد
   LIVE_HLS_URL=https://live.manhagy.online
   LIVE_WHIP_URL=https://live.manhagy.online
   LIVE_RTMP_URL=rtmp://live.manhagy.online:1935
   LIVE_PLAYBACK_URL=https://live.manhagy.online/rec
   LIVE_API_URL=https://live.manhagy.online/mtx
   ```
   وشيل خدمة `live` من `compose.yml` (أو سيبها مقفولة)، وبعدين `./deploy.sh`.

الحصص اللي شغالة وقت النقل بتقف، فانقل في وقت مفيش فيه لايف. التسجيلات بتتحفظ على السيرفر الجديد أسبوع، والسيرفر الأساسي بيسحبها بعد كل حصة زي ما هو.

لو عدد الطلاب كبر أكتر من سيرفر واحد: MediaMTX بيدعم CDN قدامه (`hlsCDNSecret`)، أو كذا سيرفر بث كل واحد لمجموعة مدرسين — ده الخطوة اللي بعدها.
