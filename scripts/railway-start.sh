#!/bin/bash
set -e

PORT="${PORT:-8000}"
export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

# Wait for database to be reachable (Railway internal DNS can take a moment)
echo "==> Waiting for database connection..."

# Debug: show parsed connection target (no password)
php -r "
    \$url = getenv('DATABASE_URL');
    if (!\$url) { echo \"    ERROR: DATABASE_URL is not set\n\"; exit(1); }
    \$p = parse_url(\$url);
    if (!\$p || empty(\$p['host'])) { echo \"    ERROR: Cannot parse DATABASE_URL (raw scheme: \" . substr(\$url, 0, 20) . \"...)\n\"; exit(1); }
    \$host = \$p['host'];
    \$port = \$p['port'] ?? 5432;
    \$db = ltrim(\$p['path'] ?? '', '/');
    \$query = \$p['query'] ?? '';
    echo \"    Target: \$host:\$port/\$db\" . (\$query ? \"?\$query\" : '') . \"\n\";
" || { echo "    FATAL: DATABASE_URL is missing or unparseable. Exiting."; exit 1; }

MAX_ATTEMPTS=30
for i in $(seq 1 $MAX_ATTEMPTS); do
    RESULT=$(php -r "
        \$url = getenv('DATABASE_URL');
        \$p = parse_url(\$url);
        if (!\$p || empty(\$p['host'])) { echo 'PARSE_ERROR'; exit(1); }
        \$host = \$p['host'];
        \$port = \$p['port'] ?? 5432;
        \$dbname = ltrim(\$p['path'] ?? '', '/');
        \$user = urldecode(\$p['user'] ?? '');
        \$pass = urldecode(\$p['pass'] ?? '');
        // Parse query params for sslmode
        parse_str(\$p['query'] ?? '', \$params);
        \$dsn = \"pgsql:host=\$host;port=\$port;dbname=\$dbname\";
        if (!empty(\$params['sslmode'])) {
            \$dsn .= ';sslmode=' . \$params['sslmode'];
        }
        try {
            new PDO(\$dsn, \$user, \$pass, [PDO::ATTR_TIMEOUT => 5]);
            echo 'OK';
        } catch (Exception \$e) {
            echo 'FAIL:' . \$e->getMessage();
        }
    " 2>&1)

    if [ "$RESULT" = "OK" ]; then
        echo "    Database is reachable (attempt $i/$MAX_ATTEMPTS)."
        break
    fi

    if [ "$i" -eq "$MAX_ATTEMPTS" ]; then
        echo "    ERROR: Could not connect to database after $MAX_ATTEMPTS attempts."
        echo "    Last error: $RESULT"
        exit 1
    fi
    echo "    Waiting for database... (attempt $i/$MAX_ATTEMPTS) [$RESULT]"
    sleep 2
done

echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod

echo "==> Updating database schema..."
php bin/console doctrine:schema:update --force --env=prod --no-interaction || {
    echo "    WARNING: schema:update failed (non-fatal). Continuing startup..."
    echo "    Review pending schema changes with: doctrine:schema:update --dump-sql"
}

echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod

# Load demo data if LOAD_DEMO is set
if [ "${LOAD_DEMO:-false}" = "true" ]; then
    echo "==> Loading demo scenario..."
    php bin/console app:demo:setup --env=prod --no-interaction || echo "    Demo setup failed (may already exist)"
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
