#!/bin/bash
set -e

PORT="${PORT:-8000}"
export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

# Wait for database to be reachable (Railway internal DNS can take a moment)
echo "==> Waiting for database connection..."
for i in $(seq 1 15); do
    if php -r "
        \$url = getenv('DATABASE_URL');
        if (!preg_match('#//([^:]+):([^@]+)@([^:/]+):?(\d+)?/([^?]+)#', \$url, \$m)) exit(1);
        try { new PDO(\"pgsql:host={\$m[3]};port=\" . (\$m[4] ?: '5432') . \";dbname={\$m[5]}\", \$m[1], \$m[2], [PDO::ATTR_TIMEOUT => 3]); exit(0); }
        catch (Exception \$e) { exit(1); }
    " 2>/dev/null; then
        echo "    Database is reachable."
        break
    fi
    if [ "$i" -eq 15 ]; then
        echo "    ERROR: Could not connect to database after 15 attempts."
        exit 1
    fi
    echo "    Waiting for database... (attempt $i/15)"
    sleep 2
done

echo "==> Updating database schema..."
php bin/console doctrine:schema:update --force --env=prod --no-interaction

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
