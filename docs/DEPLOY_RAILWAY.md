# Deploy en Railway

Guía para configurar las variables de entorno de cada servicio en Railway.
No se necesita Railway CLI — todo se configura desde el dashboard web.

## Servicios en Railway

| Servicio | Dockerfile | Puertos internos |
|----------|-----------|------------------|
| **app-mxo** | `Dockerfile.railway` | 8000 |
| **worker-mxo** | `Dockerfile.worker` | — (no expone puerto) |
| **mercure-mxo** | `Dockerfile.mercure` | 80 |
| **traccar-mxo** | `Dockerfile.traccar` | 8082 (API), 5055 (GPS) |
| **bbdd-mxo** | Gestionado por Railway (PostgreSQL) | 5432 |
| **redis-mxo** | Gestionado por Railway (Redis) | 6379 |

## Paso 1: Crear servicios gestionados

1. **PostgreSQL**: Add → Database → PostgreSQL → nombrar `bbdd-mxo`
2. **Redis**: Add → Database → Redis → nombrar `redis-mxo`

Railway genera automáticamente las variables de conexión (`DATABASE_URL`, `REDIS_URL`, etc.) en cada servicio gestionado.

## Paso 2: Crear servicios custom

Para cada servicio custom (app, worker, mercure, traccar):

1. **Add → GitHub Repo** → seleccionar el repositorio `mxo-track`
2. Renombrar el servicio al nombre correspondiente
3. En **Settings → Source → Dockerfile Path**, configurar:

| Servicio | Dockerfile Path |
|----------|----------------|
| app-mxo | `Dockerfile.railway` |
| worker-mxo | `Dockerfile.worker` |
| mercure-mxo | `Dockerfile.mercure` |
| traccar-mxo | `Dockerfile.traccar` |

## Paso 3: Generar dominios públicos

En Railway, para cada servicio que necesite acceso externo:

**Settings → Networking → Generate Domain**

Solo necesitan dominio público:
- **app-mxo** (puerto 8000) — la web principal
- **mercure-mxo** (puerto 80) — SSE realtime para el frontend
- **traccar-mxo** (puerto 5055) — protocolo OsmAnd GPS para dispositivos Android

> **Traccar**: Al generar el dominio, asegurarse de que apunta al **puerto 5055** (no al 8082). El puerto 8082 (API REST) se accede internamente.

## Paso 4: Generar secrets

Antes de configurar variables, generar estos 3 secrets (desde terminal local):

```bash
# APP_SECRET (para Symfony)
openssl rand -hex 32

# MERCURE_JWT_KEY (compartido entre app, worker y mercure)
openssl rand -hex 32

# TRACCAR_PASSWORD (para el admin de Traccar)
openssl rand -base64 15
```

Guardar estos valores — se usan en varios servicios.

## Paso 5: Variables de entorno por servicio

Ir a cada servicio → **Variables → Raw Editor** y pegar el bloque correspondiente.

### app-mxo

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<APP_SECRET generado>
APP_URL=<URL pública de app-mxo, ej: https://mxo-track-production.up.railway.app>
DATABASE_URL=${{bbdd-mxo.DATABASE_URL}}
REDIS_URL=${{redis-mxo.REDIS_URL}}
REDIS_SESSION_PREFIX=sess:transporte:
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLIC_URL=<URL pública de mercure-mxo>/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=<MERCURE_JWT_KEY generado>
MERCURE_SUBSCRIBER_JWT_KEY=<MERCURE_JWT_KEY generado>
MERCURE_SUBSCRIBER_TOKEN_TTL=3600
TRACCAR_BASE_URL=http://traccar-mxo.railway.internal:8082
TRACCAR_WS_URL=ws://traccar-mxo.railway.internal:8082/api/socket
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=<TRACCAR_PASSWORD generado>
POD_STORAGE=database
TRUSTED_PROXIES=REMOTE_ADDR
```

> **IMPORTANTE sobre DATABASE_URL y REDIS_URL**: Usar **variables de referencia** de Railway (`${{bbdd-mxo.DATABASE_URL}}` y `${{redis-mxo.REDIS_URL}}`). Railway las resuelve automáticamente con la URL correcta. NO hardcodear la URL — si Railway rota credenciales o cambia el host, la referencia se actualiza sola.

> Los nombres `bbdd-mxo` y `redis-mxo` deben coincidir **exactamente** con el nombre del servicio PostgreSQL/Redis en el dashboard.

> Si Railway ofrece `DATABASE_PRIVATE_URL` en las variables del servicio PostgreSQL, usar `${{bbdd-mxo.DATABASE_PRIVATE_URL}}` en su lugar (usa hostname interno, más rápido).

### worker-mxo

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<mismo APP_SECRET>
DATABASE_URL=${{bbdd-mxo.DATABASE_URL}}
REDIS_URL=${{redis-mxo.REDIS_URL}}
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLISHER_JWT_KEY=<mismo MERCURE_JWT_KEY>
MERCURE_SUBSCRIBER_JWT_KEY=<mismo MERCURE_JWT_KEY>
TRACCAR_BASE_URL=http://traccar-mxo.railway.internal:8082
TRACCAR_WS_URL=ws://traccar-mxo.railway.internal:8082/api/socket
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=<mismo TRACCAR_PASSWORD>
```

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

### traccar-mxo

No necesita variables de entorno. La configuración va embebida en `Dockerfile.traccar` con H2.

### bbdd-mxo y redis-mxo

Gestionados por Railway. No requieren variables adicionales.

## Paso 6: Configurar puerto público de Traccar

En Railway, **traccar-mxo → Settings → Networking**:

1. **Generate Domain** → apuntar al **puerto 5055**
2. Anotar el dominio generado (ej: `traccar-mxo-production.up.railway.app`)

Esto expone el protocolo OsmAnd (HTTP) para que dispositivos Android envíen posiciones GPS.

El puerto 8082 (API REST) **no necesita dominio público** — app-mxo y worker-mxo lo acceden vía `traccar-mxo.railway.internal:8082`.

## Paso 7: Deploy y verificación

Hacer deploy de todos los servicios (git push o desde el dashboard).

El script `railway-start.sh` de app-mxo automáticamente:

1. Espera a que PostgreSQL esté disponible (hasta 60s)
2. Ejecuta `doctrine:schema:update --force`
3. Calienta la caché de Symfony
4. Inicializa Traccar (crea usuario admin si es la primera vez)
5. Arranca nginx + PHP-FPM

### Logs a verificar

- **app-mxo**: Debe mostrar `Target: <host>:<port>/<db>`, luego `Database is reachable`, luego `Starting nginx`
- **worker-mxo**: Debe mostrar `Starting Traccar stream worker`
- **mercure-mxo**: Debe arrancar Caddy sin errores
- **traccar-mxo**: Debe mostrar `Starting Traccar Server`

### Cargar fixtures (primera vez)

```bash
railway run -s app-mxo -- php bin/console doctrine:fixtures:load -n
```

## Troubleshooting

### "could not translate host name"

La URL interna (`*.railway.internal`) no resuelve. Causas:
- El nombre del servicio en `DATABASE_URL` no coincide con el nombre real en Railway
- Private networking no está habilitado
- **Solución**: Usar `${{bbdd-mxo.DATABASE_URL}}` en vez de hardcodear la URL

### "password authentication failed"

Las credenciales de la DATABASE_URL están desactualizadas.
- **Solución**: Usar `${{bbdd-mxo.DATABASE_URL}}` (se actualiza automáticamente)

### "connection refused"

PostgreSQL no está corriendo o el puerto es incorrecto.
- Verificar que el servicio bbdd-mxo está activo en el dashboard
- Verificar que no hay errores en los logs de bbdd-mxo

### Mercure 401 al publicar

Los JWT keys no coinciden entre app y mercure.
- Verificar que `MERCURE_PUBLISHER_JWT_KEY` es **idéntico** en app-mxo, worker-mxo y mercure-mxo

## Notas

- Las URLs internas (`*.railway.internal`) solo funcionan entre servicios del mismo proyecto Railway. Son más rápidas y no generan tráfico de egress.
- Railway asigna `$PORT` dinámicamente. Los scripts de arranque ya lo usan.
- Si Traccar se recrea (volumen perdido), el script de arranque de app-mxo reinicializa el admin automáticamente.
- Las sesiones se almacenan en Redis con prefijo `sess:transporte:`.
- El protocolo OsmAnd de Traccar es HTTP — compatible con la terminación SSL de Railway.
- `doctrine.yaml` ya tiene `server_version: '16'`, así que no es necesario incluir `?serverVersion=16` en DATABASE_URL.
