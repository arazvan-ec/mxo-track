#!/bin/bash
set -e

echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod

# Wait for Traccar to be ready
if [ -n "$TRACCAR_BASE_URL" ]; then
    echo "==> Waiting for Traccar at $TRACCAR_BASE_URL..."
    for i in 1 2 3 4 5 6; do
        if curl -sf "$TRACCAR_BASE_URL/api/server" > /dev/null 2>&1; then
            echo "    Traccar is ready."
            break
        fi
        echo "    Waiting... (attempt $i/6)"
        sleep 10
    done
fi

echo "==> Starting Traccar stream worker (poll mode, sleep=5)..."
exec php bin/console app:traccar:stream --mode=poll --sleep=5
