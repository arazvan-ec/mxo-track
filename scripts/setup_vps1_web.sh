#!/usr/bin/env bash
# Initial setup script for VPS1 (Web) — run ONCE on fresh Ubuntu 22.04/24.04 VPS
set -euo pipefail

DOMAIN="${1:?Usage: $0 <portal-domain> (e.g. portal.example.com)}"
REPO_URL="${2:-https://github.com/arazvan-ec/mxo-track.git}"
REPO_DIR="/var/www/transporte-tracking"

echo "=== VPS1 WEB Initial Setup ==="
echo "Domain: $DOMAIN"
echo ""

# -------------------------------------------------------
# 1. System packages
# -------------------------------------------------------
echo "[1/8] Installing system packages..."
sudo apt-get update
sudo apt-get install -y \
    software-properties-common \
    curl git unzip ufw \
    nginx certbot python3-certbot-nginx \
    docker.io docker-compose-plugin

# PHP 8.4 (Ondrej PPA)
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y \
    php8.4-fpm php8.4-cli php8.4-pgsql php8.4-xml \
    php8.4-mbstring php8.4-curl php8.4-zip php8.4-intl \
    php8.4-redis php8.4-opcache

# Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# -------------------------------------------------------
# 2. Firewall
# -------------------------------------------------------
echo "[2/8] Configuring firewall..."
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
echo "y" | sudo ufw enable

# -------------------------------------------------------
# 3. Clone repository
# -------------------------------------------------------
echo "[3/8] Cloning repository..."
if [ ! -d "$REPO_DIR" ]; then
    sudo mkdir -p "$(dirname "$REPO_DIR")"
    sudo git clone "$REPO_URL" "$REPO_DIR"
    sudo chown -R www-data:www-data "$REPO_DIR"
fi

# -------------------------------------------------------
# 4. PHP-FPM config (production tuning)
# -------------------------------------------------------
echo "[4/8] Configuring PHP-FPM..."
sudo tee /etc/php/8.4/fpm/pool.d/www.conf > /dev/null <<'POOL'
[www]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
POOL

# OPcache production settings
sudo tee /etc/php/8.4/fpm/conf.d/99-production.ini > /dev/null <<'INI'
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
realpath_cache_size=4096K
realpath_cache_ttl=600
INI

sudo systemctl restart php8.4-fpm

# -------------------------------------------------------
# 5. Nginx config
# -------------------------------------------------------
echo "[5/8] Configuring Nginx..."
sudo cp "$REPO_DIR/infra/vps1-web/nginx/portal.conf" /etc/nginx/sites-available/portal.conf

# Replace domain placeholder
sudo sed -i "s/portal.midominio.com/$DOMAIN/g" /etc/nginx/sites-available/portal.conf

sudo ln -sf /etc/nginx/sites-available/portal.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test nginx config (will warn about missing SSL cert — that's OK, certbot will fix it)
sudo nginx -t 2>/dev/null || echo "Nginx config test failed (expected if SSL cert not yet issued)"

# -------------------------------------------------------
# 6. SSL Certificate
# -------------------------------------------------------
echo "[6/8] Obtaining SSL certificate..."
sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --redirect --email admin@"$DOMAIN" || {
    echo "WARNING: Certbot failed. Make sure DNS for $DOMAIN points to this server."
    echo "Run manually: sudo certbot --nginx -d $DOMAIN"
}

sudo systemctl reload nginx

# -------------------------------------------------------
# 7. Docker services (Mercure + Redis)
# -------------------------------------------------------
echo "[7/8] Starting Docker services..."
cd "$REPO_DIR"

# Create .env for Docker Compose if not exists
if [ ! -f infra/vps1-web/.env ]; then
    echo "MERCURE_PUBLISHER_JWT_KEY=$(openssl rand -hex 32)" > infra/vps1-web/.env
    echo "MERCURE_SUBSCRIBER_JWT_KEY=$(openssl rand -hex 32)" >> infra/vps1-web/.env
    echo "PORTAL_DOMAIN=$DOMAIN" >> infra/vps1-web/.env
    echo ""
    echo ">>> IMPORTANT: Save these Mercure JWT keys!"
    echo ">>> They must match the values in $REPO_DIR/.env.local"
    cat infra/vps1-web/.env
    echo ""
fi

docker compose -f infra/vps1-web/docker-compose.yml up -d

# -------------------------------------------------------
# 8. Systemd worker
# -------------------------------------------------------
echo "[8/8] Installing Traccar stream worker..."
sudo cp "$REPO_DIR/infra/vps1-web/systemd/traccar-stream.service" /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable traccar-stream

echo ""
echo "=== Initial setup complete ==="
echo ""
echo "NEXT STEPS:"
echo "1. Create .env.local in $REPO_DIR with production values:"
echo "   cp $REPO_DIR/.env.example $REPO_DIR/.env.local"
echo "   nano $REPO_DIR/.env.local"
echo ""
echo "2. Make sure Mercure JWT keys in .env.local match infra/vps1-web/.env"
echo ""
echo "3. Run first deploy:"
echo "   bash $REPO_DIR/scripts/deploy_vps1_web.sh main"
echo ""
echo "4. Load initial data (fixtures):"
echo "   cd $REPO_DIR/backend && php bin/console doctrine:fixtures:load -n --env=prod"
echo ""
