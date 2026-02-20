#!/bin/bash
set -e

echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod

echo "==> Starting PHP server on port ${PORT:-8000}..."
exec php -S 0.0.0.0:${PORT:-8000} -t public
