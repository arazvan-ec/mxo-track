#!/usr/bin/env bash
# Deploy script for VPS1 (Web) — run on the VPS1 server itself
set -euo pipefail

REPO_DIR="/var/www/transporte-tracking"
BRANCH="${1:-main}"

echo "=== VPS1 WEB Deploy (branch: $BRANCH) ==="

# -------------------------------------------------------
# 1. Pull latest code
# -------------------------------------------------------
echo "[1/7] Pulling latest code..."
cd "$REPO_DIR"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

# -------------------------------------------------------
# 2. Install PHP dependencies (no dev in production)
# -------------------------------------------------------
echo "[2/7] Installing PHP dependencies..."
cd "$REPO_DIR/backend"
composer install --no-dev --optimize-autoloader --no-interaction

# -------------------------------------------------------
# 3. Clear and warm Symfony cache
# -------------------------------------------------------
echo "[3/7] Clearing cache..."
php bin/console cache:clear --env=prod --no-interaction
php bin/console cache:warmup --env=prod --no-interaction

# -------------------------------------------------------
# 4. Run database migrations
# -------------------------------------------------------
echo "[4/7] Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# -------------------------------------------------------
# 5. Restart Docker services (Mercure + Redis)
# -------------------------------------------------------
echo "[5/7] Restarting Docker services (Mercure + Redis)..."
cd "$REPO_DIR"
docker compose -f infra/vps1-web/docker-compose.yml up -d

# -------------------------------------------------------
# 6. Restart PHP-FPM
# -------------------------------------------------------
echo "[6/7] Restarting PHP-FPM..."
sudo systemctl restart php8.4-fpm

# -------------------------------------------------------
# 7. Restart Traccar stream worker
# -------------------------------------------------------
echo "[7/7] Restarting Traccar stream worker..."
sudo systemctl restart traccar-stream

echo ""
echo "=== Deploy complete ==="
echo "Check status:"
echo "  systemctl status php8.4-fpm"
echo "  systemctl status traccar-stream"
echo "  docker compose -f infra/vps1-web/docker-compose.yml ps"
