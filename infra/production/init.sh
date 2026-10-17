#!/usr/bin/env bash
# First-time setup: creates .env with strong random secrets.
# Usage: ./init.sh <root domain> <email> [nginx]
#   e.g. ./init.sh manhagy.online you@email.com
#   nginx: the server already runs nginx on 80/443 (see compose.nginx.yml, nginx-site.conf).
set -euo pipefail
cd "$(dirname "$0")"

ROOT="${1:?usage: ./init.sh <root domain> <email> [nginx]}"
EMAIL="${2:?usage: ./init.sh <root domain> <email> [nginx]}"
EDGE="${3:-caddy}"

if [[ -f .env ]]; then
  echo ".env already exists — not touching it." >&2
  exit 1
fi

rand() { openssl rand -hex "$1"; }

sed \
  -e "s|^SITES_HOST=.*|SITES_HOST=${ROOT}|" \
  -e "s|^APP_DOMAIN=.*|APP_DOMAIN=app.${ROOT}|" \
  -e "s|^LANDING_ADDRESS=.*|LANDING_ADDRESS=${ROOT}, www.${ROOT}|" \
  -e "s|^ACME_EMAIL=.*|ACME_EMAIL=${EMAIL}|" \
  -e "s|^APP_URL=.*|APP_URL=https://app.${ROOT}|" \
  -e "s|^FRONTEND_URL=.*|FRONTEND_URL=https://app.${ROOT}|" \
  -e "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(rand 24)|" \
  -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$(rand 24)|" \
  -e "s|^WHATSAPP_VERIFY_TOKEN=.*|WHATSAPP_VERIFY_TOKEN=$(rand 16)|" \
  -e "s|^LIVE_SECRET=.*|LIVE_SECRET=$(rand 24)|" \
  -e "s|^LIVE_DOMAIN=.*|LIVE_DOMAIN=live.${ROOT}|" \
  -e "s|^LIVE_HLS_URL=.*|LIVE_HLS_URL=https://live.${ROOT}|" \
  -e "s|^LIVE_WHIP_URL=.*|LIVE_WHIP_URL=https://live.${ROOT}|" \
  -e "s|^LIVE_RTMP_URL=.*|LIVE_RTMP_URL=rtmp://live.${ROOT}:1935|" \
  .env.example > .env

if [[ "$EDGE" == "nginx" ]]; then
  printf '\n# Behind the host nginx (Caddy on 127.0.0.1:8088 only)\nCOMPOSE_FILE=compose.yml:compose.nginx.yml\n' >> .env
fi

chmod 600 .env
echo "Created .env for ${ROOT} (app.${ROOT}, *.${ROOT}). Keep a copy of it somewhere safe (APP_KEY, DB password)."
echo "DNS: point ${ROOT}, www.${ROOT}, app.${ROOT}, live.${ROOT} and *.${ROOT} (and the admin domain, if any) at this server."
echo "Live lessons: set LIVE_PUBLIC_IP in .env and open 1935/tcp and 8189/udp in the firewall."
