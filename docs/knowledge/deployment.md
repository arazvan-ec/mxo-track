# Deployment

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Docker Local

### Imagen y Servidor

- Imagen: `php:8.4-cli-bookworm` (sin Apache/nginx)
- Servidor web: PHP built-in server (`php -S 0.0.0.0:8000 -t public`)
- Single-threaded, solo para desarrollo

### Servicios Docker

| Servicio | Puerto | Propósito |
|----------|--------|-----------|
| app (PHP 8.4) | 8000 | Symfony backend |
| db (PostgreSQL 16) | 5432 | Base de datos principal (user: `mxo`, DB: `mxo_track`) |
| redis (Redis 7) | 6379 | Sesiones y caché |
| mercure | 3000 | SSE realtime |
| traccar | 8082 (API/Web) + 5055 (GPS) | GPS tracking |
| traccar_db (PostgreSQL) | 5433 | BD dedicada Traccar (user: `traccar`) |
| osrm | 5000 (interno) | Routing engine |
| vroom | 5100 (host) / 3000 (interno) | VRP optimizer |

### Arranque Rápido

```bash
# Desde la raíz del proyecto:
docker compose -f docker-compose.local.yml up -d --build
docker compose -f docker-compose.local.yml exec app bash

# Dentro del contenedor:
composer install
php bin/console doctrine:schema:create          # primera vez (DB vacía)
php bin/console doctrine:migrations:migrate -n  # siguientes veces
php bin/console doctrine:fixtures:load -n
php -S 0.0.0.0:8000 -t public
```

### Arranque en Una Línea

```bash
docker compose -f docker-compose.local.yml up -d --build
docker compose -f docker-compose.local.yml exec app bash -c \
  "composer install && php bin/console doctrine:migrations:migrate -n && php -S 0.0.0.0:8000 -t public"
```

### Preparación OSRM (Primera Vez)

```bash
./docker/osrm/prepare-map.sh
```

Descarga ~75 MB de Geofabrik (Madrid) y genera ficheros `.osrm.*` en `docker/osrm/data/`.

## Railway (Producción)

### Deploy Mínimo (4 servicios)

| Servicio | Tipo | Puerto |
|----------|------|--------|
| **app-mxo** | Dockerfile.railway | 8000 |
| **mercure-mxo** | Dockerfile.mercure | 80 |
| **bbdd-mxo** | PostgreSQL (gestionado) | 5432 |
| **redis-mxo** | Redis (gestionado) | 6379 |

Providers lite: Google Directions (routing), Greedy (optimizer), Webhook (GPS), Mercure (realtime).
Requiere: `GOOGLE_DIRECTIONS_API_KEY` con Directions API habilitada.

### Deploy Completo (9 servicios)

Añade: traccar, traccar_db, osrm, vroom, worker.

### Variables de Entorno (app-mxo)

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<generado>
APP_URL=<URL pública de app-mxo>
DATABASE_URL=${{bbdd-mxo.DATABASE_URL}}
REDIS_URL=${{redis-mxo.REDIS_URL}}
REDIS_SESSION_PREFIX=sess:transporte:
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLIC_URL=<URL pública de mercure-mxo>/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=<generado>
MERCURE_SUBSCRIBER_JWT_KEY=<mismo que publisher>
MERCURE_SUBSCRIBER_TOKEN_TTL=3600
POD_STORAGE=database
TRUSTED_PROXIES=REMOTE_ADDR
DEFAULT_ROUTE_OPTIMIZER=greedy
DEFAULT_ROUTING_ENGINE=google_directions
DEFAULT_GPS_PROVIDER=webhook
DEFAULT_REALTIME_PUBLISHER=mercure
GOOGLE_DIRECTIONS_API_KEY=<API key de Google Maps>
```

### Variables de Entorno (mercure-mxo)

```env
SERVER_NAME=:80
MERCURE_PUBLISHER_JWT_KEY=<mismo key>
MERCURE_PUBLISHER_JWT_ALG=HS256
MERCURE_SUBSCRIBER_JWT_KEY=<mismo key>
MERCURE_SUBSCRIBER_JWT_ALG=HS256
MERCURE_EXTRA_DIRECTIVES=anonymous 0
publish_origins *
cors_origins <URL pública de app-mxo>
```

### Deploy y Verificación

1. Git push o deploy desde dashboard
2. app-mxo automáticamente: espera PostgreSQL → migraciones → schema update → caché → nginx + PHP-FPM
3. Verificar logs: debe mostrar `Database is reachable` → `Starting nginx`

### Cargar Fixtures (Primera Vez)

```bash
railway run -s app-mxo -- php bin/console doctrine:fixtures:load -n
```

### Guías Completas

- `docs/DEPLOY_RAILWAY.md` — guía completa de 8 servicios
- `docs/DEPLOY_RAILWAY_LITE.md` — guía lite de 4 servicios

## Historial

- 2026-03-11: Creación inicial
