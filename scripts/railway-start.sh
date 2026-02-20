#!/bin/bash
set -e

echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

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

echo "==> Starting PHP server on port ${PORT:-8000}..."
exec php -S 0.0.0.0:${PORT:-8000} -t public
