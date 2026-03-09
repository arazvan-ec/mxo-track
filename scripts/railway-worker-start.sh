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

# Start Messenger consumer in background (async + ml queues)
# Time-limited to 1h so Railway restarts keep memory fresh
echo "==> Starting Messenger consumer (async + ml queues)..."
php bin/console messenger:consume async ml failed \
    --time-limit=3600 --memory-limit=128M &
MESSENGER_PID=$!

# Start Traccar stream in background
echo "==> Starting Traccar stream worker (poll mode, sleep=5)..."
php bin/console app:traccar:stream --mode=poll --sleep=5 &
TRACCAR_PID=$!

# Wait for either process to exit, then shut down both
# Railway restart policy (ON_FAILURE, max 10) will restart the container
wait -n $MESSENGER_PID $TRACCAR_PID
EXIT_CODE=$?
echo "==> A worker process exited (code=$EXIT_CODE). Shutting down..."
kill $MESSENGER_PID $TRACCAR_PID 2>/dev/null || true
exit $EXIT_CODE
