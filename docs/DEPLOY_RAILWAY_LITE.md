# Deploy Lite en Railway (sin infraestructura pesada)

Deploy simplificado sin OSRM/VROOM/Traccar. Solo necesitas **4 servicios** en Railway:

| Servicio | Tipo | Puerto |
|----------|------|--------|
| **app-mxo** | Dockerfile.railway | 8000 |
| **mercure-mxo** | Dockerfile.mercure | 80 |
| **bbdd-mxo** | PostgreSQL (gestionado) | 5432 |
| **redis-mxo** | Redis (gestionado) | 6379 |

**No necesitas**: OSRM, VROOM, Traccar, worker. **Necesitas**: API key de Google Maps (Directions API).

## Providers usados

| Servicio | Provider | Requisito |
|----------|----------|-----------|
| Routing | **Google Directions** | API key de Google Maps |
| Optimización | **Greedy** | Ninguno (PHP puro, nearest-neighbor) |
| GPS | **Webhook** | Ninguno (push-based) |
| Realtime | **Mercure** | Servicio mercure-mxo |

## Paso 1: Crear servicios

1. **PostgreSQL**: Add → Database → PostgreSQL → nombrar `bbdd-mxo`
2. **Redis**: Add → Database → Redis → nombrar `redis-mxo`
3. **app-mxo**: Add → GitHub Repo → Dockerfile Path: `Dockerfile.railway`
4. **mercure-mxo**: Add → GitHub Repo → Dockerfile Path: `Dockerfile.mercure`

## Paso 2: Generar dominios públicos

Solo necesitan dominio público:
- **app-mxo** (puerto 8000) — la web
- **mercure-mxo** (puerto 80) — SSE realtime

## Paso 3: Generar secrets

```bash
# APP_SECRET
openssl rand -hex 32

# MERCURE_JWT_KEY (compartido entre app y mercure)
openssl rand -hex 32
```

## Paso 4: Variables de entorno

### app-mxo

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<APP_SECRET generado>
APP_URL=<URL pública de app-mxo>
DATABASE_URL=${{bbdd-mxo.DATABASE_URL}}
REDIS_URL=${{redis-mxo.REDIS_URL}}
REDIS_SESSION_PREFIX=sess:transporte:
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLIC_URL=<URL pública de mercure-mxo>/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=<MERCURE_JWT_KEY generado>
MERCURE_SUBSCRIBER_JWT_KEY=<MERCURE_JWT_KEY generado>
MERCURE_SUBSCRIBER_TOKEN_TTL=3600
POD_STORAGE=database
TRUSTED_PROXIES=REMOTE_ADDR
DEFAULT_ROUTE_OPTIMIZER=greedy
DEFAULT_ROUTING_ENGINE=google_directions
DEFAULT_GPS_PROVIDER=webhook
DEFAULT_REALTIME_PUBLISHER=mercure
GOOGLE_DIRECTIONS_API_KEY=<tu API key de Google Maps>
```

> **GOOGLE_DIRECTIONS_API_KEY**: Necesitas una API key de Google Cloud con la Directions API habilitada. Consíguela en https://console.cloud.google.com/apis/credentials

> **No necesitas** TRACCAR_*, VROOM_URL, OSRM_URL — son opcionales.

> Usa `${{bbdd-mxo.DATABASE_URL}}` y `${{redis-mxo.REDIS_URL}}` (variables de referencia Railway).

### mercure-mxo

```env
SERVER_NAME=:80
MERCURE_PUBLISHER_JWT_KEY=<mismo MERCURE_JWT_KEY>
MERCURE_PUBLISHER_JWT_ALG=HS256
MERCURE_SUBSCRIBER_JWT_KEY=<mismo MERCURE_JWT_KEY>
MERCURE_SUBSCRIBER_JWT_ALG=HS256
MERCURE_EXTRA_DIRECTIVES=anonymous 0
publish_origins *
cors_origins <URL pública de app-mxo>
```

### bbdd-mxo y redis-mxo

Gestionados por Railway. No requieren variables adicionales.

## Paso 5: Deploy y verificación

1. Hacer deploy (git push o desde dashboard)
2. app-mxo automáticamente:
   - Espera a PostgreSQL
   - Ejecuta migraciones + schema update
   - Calienta caché
   - Arranca nginx + PHP-FPM
3. Verificar logs: debe mostrar `Database is reachable` → `Starting nginx`

### Cargar fixtures (primera vez)

```bash
railway run -s app-mxo -- php bin/console doctrine:fixtures:load -n
```

## Paso 6: Añadir infraestructura más adelante (opcional)

Para activar providers con infraestructura real, añade los servicios y cambia las variables:

```env
# En app-mxo, añadir/cambiar:
DEFAULT_ROUTE_OPTIMIZER=vroom
DEFAULT_ROUTING_ENGINE=osrm
# Or keep google_directions and remove OSRM
DEFAULT_GPS_PROVIDER=traccar
TRACCAR_BASE_URL=http://traccar-mxo.railway.internal:8082
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=<generado>
VROOM_URL=http://vroom-mxo.railway.internal:3000
OSRM_URL=http://osrm-mxo.railway.internal:5000
```

Consulta `docs/DEPLOY_RAILWAY.md` para la guía completa de 8 servicios.

## Troubleshooting

Ver la sección de troubleshooting en `docs/DEPLOY_RAILWAY.md` — los problemas de PostgreSQL, Redis y Mercure aplican igual.
