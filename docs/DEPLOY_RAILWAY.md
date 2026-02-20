# Deploy en Railway

Guía para configurar las variables de entorno de cada servicio en Railway.
No se necesita Railway CLI — todo se configura desde el dashboard web.

## Servicios en Railway

| Servicio | Dockerfile | Puerto interno |
|----------|-----------|----------------|
| **app-mxo** | `Dockerfile.railway` | 8000 |
| **worker-mxo** | `Dockerfile.worker` | — (no expone puerto) |
| **mercure-mxo** | `Dockerfile.mercure` | 80 |
| **traccar-mxo** | `Dockerfile.traccar` | 8082 |
| **bbdd-mxo** | Gestionado por Railway (PostgreSQL) | 5432 |
| **redis-mxo** | Gestionado por Railway (Redis) | 6379 |

## Paso 1: Generar dominios públicos

En Railway, para cada servicio que necesite acceso externo:

**Settings → Networking → Generate Domain**

Servicios que necesitan dominio público:
- **app-mxo** (puerto 8000) → genera algo como `app-mxo-production.up.railway.app`
- **mercure-mxo** (puerto 80) → genera algo como `mercure-mxo-production.up.railway.app`

Traccar no necesita dominio público (solo se accede internamente desde app y worker).

## Paso 2: Variables de entorno por servicio

Ir a cada servicio → **Variables → Raw Editor** y pegar el bloque correspondiente.

> **IMPORTANTE**: Reemplazar `TU_DOMINIO_APP` y `TU_DOMINIO_MERCURE` con los dominios reales generados en el paso 1.

### app-mxo

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=8bdbc8f3fc515442faf5f8f955f2a0b714b2620ede7b64480734c28fcce7c236
APP_URL=https://TU_DOMINIO_APP.up.railway.app
DATABASE_URL=postgresql://postgres:BGBYqGcZewWwMOtfnFzTFDnEHpEpgNoZ@postgres.railway.internal:5432/railway?serverVersion=16&charset=utf8
REDIS_URL=redis://default:nisOENRFqAgwVqYgEYmbquCdZfaLYKvJ@redis.railway.internal:6379
REDIS_SESSION_PREFIX=sess:transporte:
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLIC_URL=https://TU_DOMINIO_MERCURE.up.railway.app/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_SUBSCRIBER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_SUBSCRIBER_TOKEN_TTL=3600
TRACCAR_BASE_URL=http://traccar-mxo.railway.internal:8082
TRACCAR_WS_URL=ws://traccar-mxo.railway.internal:8082/api/socket
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=6Mrby8dT37ytpvcpaKc
POD_STORAGE=database
TRUSTED_PROXIES=REMOTE_ADDR
TRUSTED_HEADERS=x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port,x-forwarded-prefix
```

### worker-mxo

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=8bdbc8f3fc515442faf5f8f955f2a0b714b2620ede7b64480734c28fcce7c236
DATABASE_URL=postgresql://postgres:BGBYqGcZewWwMOtfnFzTFDnEHpEpgNoZ@postgres.railway.internal:5432/railway?serverVersion=16&charset=utf8
REDIS_URL=redis://default:nisOENRFqAgwVqYgEYmbquCdZfaLYKvJ@redis.railway.internal:6379
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_SUBSCRIBER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
TRACCAR_BASE_URL=http://traccar-mxo.railway.internal:8082
TRACCAR_WS_URL=ws://traccar-mxo.railway.internal:8082/api/socket
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=6Mrby8dT37ytpvcpaKc
```

### mercure-mxo

> Reemplazar `TU_DOMINIO_APP` con el dominio real de app-mxo.

```env
SERVER_NAME=:80
MERCURE_PUBLISHER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_PUBLISHER_JWT_ALG=HS256
MERCURE_SUBSCRIBER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_SUBSCRIBER_JWT_ALG=HS256
MERCURE_EXTRA_DIRECTIVES=anonymous 0
publish_origins *
cors_origins https://TU_DOMINIO_APP.up.railway.app
```

### traccar-mxo

No necesita variables de entorno. La configuración va embebida en `Dockerfile.traccar` con H2.

### bbdd-mxo y redis-mxo

Gestionados por Railway. No requieren variables adicionales.

## Paso 3: Configurar Dockerfile por servicio

En cada servicio → **Settings → Build → Dockerfile Path**:

| Servicio | Dockerfile Path |
|----------|----------------|
| app-mxo | `Dockerfile.railway` |
| worker-mxo | `Dockerfile.worker` |
| mercure-mxo | `Dockerfile.mercure` |
| traccar-mxo | `Dockerfile.traccar` |

## Paso 4: Verificar deploy

Después del deploy, el script `railway-start.sh` automáticamente:

1. Ejecuta migraciones (`doctrine:migrations:migrate`)
2. Calienta la caché de Symfony
3. Inicializa Traccar (crea usuario admin si es la primera vez)
4. Arranca el servidor PHP en el puerto asignado

### Logs a verificar

- **app-mxo**: Debe mostrar "Symfony server running" y migraciones OK
- **worker-mxo**: Debe mostrar "Traccar stream started" (polling cada 5s)
- **mercure-mxo**: Debe arrancar Caddy sin errores
- **traccar-mxo**: Debe mostrar "Starting Traccar Server"

## Secrets generados

| Variable | Valor | Usado en |
|----------|-------|----------|
| `APP_SECRET` | `8bdbc8f3...c236` | app-mxo, worker-mxo |
| `MERCURE_JWT_KEY` | `75ea28a7...7daa` | app-mxo, worker-mxo, mercure-mxo |
| `TRACCAR_PASSWORD` | `6Mrby8dT37ytpvcpaKc` | app-mxo, worker-mxo |

> Para regenerar secrets: `openssl rand -hex 32` (APP_SECRET, MERCURE keys) o `openssl rand -base64 15` (TRACCAR_PASSWORD).

## Notas

- Las URLs internas (`*.railway.internal`) solo funcionan entre servicios del mismo proyecto Railway.
- Railway asigna `$PORT` dinámicamente. Los scripts de arranque ya lo usan.
- Si Traccar se recrea (volumen perdido), el script de arranque de app-mxo reinicializa el admin automáticamente.
- Las sesiones se almacenan en Redis con prefijo `sess:transporte:`.
