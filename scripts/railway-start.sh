#!/bin/bash
set -e

PORT="${PORT:-8000}"
export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --env=prod --no-interaction --allow-no-migration

echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod

# Auto-create Traccar admin user if Traccar is available and new
if [ -n "$TRACCAR_BASE_URL" ]; then
    echo "==> Checking Traccar at $TRACCAR_BASE_URL..."
    for i in 1 2 3 4 5; do
        TRACCAR_SERVER=$(curl -sf "$TRACCAR_BASE_URL/api/server" 2>/dev/null || true)
        if [ -n "$TRACCAR_SERVER" ]; then
            break
        fi
        echo "    Waiting for Traccar... (attempt $i/5)"
        sleep 5
    done

    if echo "$TRACCAR_SERVER" | grep -q '"newServer":true'; then
        echo "==> Creating Traccar admin user..."
        curl -sf -X POST "$TRACCAR_BASE_URL/api/users" \
            -H 'Content-Type: application/json' \
            -d "{\"name\":\"admin\",\"email\":\"${TRACCAR_USERNAME:-admin}\",\"password\":\"${TRACCAR_PASSWORD:-admin}\"}" \
            && echo " OK" || echo " (already exists or failed)"
    else
        echo "    Traccar already initialized."
    fi
fi

# Configure nginx with the dynamic PORT from Railway
echo "==> Configuring nginx on port $PORT..."
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx-railway.conf.template > /etc/nginx/nginx.conf

# Start PHP-FPM in background
echo "==> Starting PHP-FPM..."
php-fpm -D

# Start nginx in foreground
echo "==> Starting nginx on port $PORT..."
exec nginx -g 'daemon off;'
