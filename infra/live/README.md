# سيرفر اللايف لوحده

لما الحصص اللايف تكبر على السيرفر الأساسي (الباندويدث أو المعالج)، انقل LiveKit لسيرفر تاني من غير ما تغيّر في الكود:

1. سيرفر جديد عليه Docker، و DNS لـ `live.manhagy.online` يشاور عليه.
2. انسخ الريبو، وادخل `infra/live`، و `cp .env.example .env` واملاه:
   - `LIVEKIT_API_KEY` و `LIVEKIT_API_SECRET` نفس اللي في `.env` السيرفر الأساسي.
   - `MAIN_SERVER_IP` = IP السيرفر الأساسي، و `LIVE_PUBLIC_IP` = IP السيرفر ده.
3. افتح في الـ firewall: `80`, `443`, `7881/tcp`, `7882/udp` (و `1935/tcp` لو هتستخدم OBS).
4. `docker compose up -d`.
5. على السيرفر الأساسي، في `infra/production/.env`:
   ```
   LIVE_DOMAIN=            # فاضي: الدومين بقى على السيرفر الجديد
   LIVEKIT_URL=wss://live.manhagy.online
   LIVEKIT_API_URL=https://live.manhagy.online
   LIVEKIT_RECORDINGS_URL=https://live.manhagy.online/recordings
   ```
   وشيل خدمات `livekit` و `egress` و `ingress` من `compose.yml`، وبعدين `./deploy.sh`.

الحصص اللي شغالة وقت النقل بتقف، فانقل في وقت مفيش فيه لايف. التسجيلات بتفضل على السيرفر الجديد أسبوع، والسيرفر الأساسي بينزّلها بعد كل حصة (`live:sync`).

لو عدد الطلاب كبر أكتر من سيرفر واحد: LiveKit بيشتغل كذا سيرفر مع بعض على نفس Redis (الغرفة الواحدة بتتوزع عليهم) — ده الخطوة اللي بعدها.
