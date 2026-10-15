#!/usr/bin/env bash
# One-time setup on the server (run as root): ./setup-server.sh manhagy.app you@example.com
# Serves /var/www/manhagy through nginx with a Let's Encrypt certificate, and creates a
# deploy user whose SSH key can only rsync into that folder (GitHub Actions uses it).
set -euo pipefail

DOMAIN="${1:?usage: $0 <domain> <email>}"
EMAIL="${2:?usage: $0 <domain> <email>}"
WEB_ROOT=/var/www/manhagy
DEPLOY_USER=manhagy-deploy

apt-get install -y nginx certbot python3-certbot-nginx rsync >/dev/null

id "$DEPLOY_USER" >/dev/null 2>&1 || useradd --system --create-home --shell /bin/sh "$DEPLOY_USER"
mkdir -p "$WEB_ROOT"
chown -R "$DEPLOY_USER":www-data "$WEB_ROOT"
chmod 755 "$WEB_ROOT"

# Deploy key: rsync into WEB_ROOT only (rrsync), nothing else.
HOME_DIR=$(getent passwd "$DEPLOY_USER" | cut -d: -f6)
KEY_DIR=$(mktemp -d)
ssh-keygen -q -t ed25519 -N '' -C manhagy-deploy -f "$KEY_DIR/key"
RRSYNC=$(command -v rrsync || echo /usr/share/doc/rsync/scripts/rrsync)
mkdir -p "$HOME_DIR/.ssh"
echo "command=\"$RRSYNC -wo $WEB_ROOT\",restrict $(cat "$KEY_DIR/key.pub")" > "$HOME_DIR/.ssh/authorized_keys"
chown -R "$DEPLOY_USER" "$HOME_DIR/.ssh"
chmod 700 "$HOME_DIR/.ssh"; chmod 600 "$HOME_DIR/.ssh/authorized_keys"

cat > /etc/nginx/sites-available/manhagy <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    root $WEB_ROOT;
    index index.html;

    location / { try_files \$uri \$uri/ =404; }
    location /assets/ { expires 7d; add_header Cache-Control "public"; }
    location = /index.html { add_header Cache-Control "no-cache"; }
}
NGINX
ln -sf /etc/nginx/sites-available/manhagy /etc/nginx/sites-enabled/manhagy
nginx -t && systemctl reload nginx

certbot --nginx --non-interactive --agree-tos -m "$EMAIL" -d "$DOMAIN" -d "www.$DOMAIN" --redirect

echo
echo "==== Done. Add these GitHub secrets in the manhagy repo (Settings → Secrets → Actions):"
echo "DEPLOY_HOST = $(curl -s https://api.ipify.org || hostname -I | awk '{print $1}')"
echo "DEPLOY_USER = $DEPLOY_USER"
echo "DEPLOY_KEY  = (the private key below, all lines)"
cat "$KEY_DIR/key"
rm -rf "$KEY_DIR"
