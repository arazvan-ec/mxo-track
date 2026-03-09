# Deploy en Railway

Guía para configurar las variables de entorno de cada servicio en Railway.
No se necesita Railway CLI — todo se configura desde el dashboard web.

## Servicios en Railway

| Servicio | Dockerfile | Puertos internos | Volumen |
|----------|-----------|------------------|---------|
| **app-mxo** | `Dockerfile.railway` | 8000 | — |
| **worker-mxo** | `Dockerfile.worker` | — (no expone puerto) | — |
| **mercure-mxo** | `Dockerfile.mercure` | 80 | — |
| **traccar-mxo** | `Dockerfile.traccar` | 8082 (API), 5055 (GPS) | — |
| **osrm-mxo** | `Dockerfile.osrm` | 5000 | `/data` |
| **vroom-mxo** | `Dockerfile.vroom` | 3000 | — |
| **bbdd-mxo** | Gestionado por Railway (PostgreSQL) | 5432 | Gestionado |
| **redis-mxo** | Gestionado por Railway (Redis) | 6379 | Gestionado |

## Paso 1: Crear servicios gestionados

1. **PostgreSQL**: Add → Database → PostgreSQL → nombrar `bbdd-mxo`
2. **Redis**: Add → Database → Redis → nombrar `redis-mxo`

Railway genera automáticamente las variables de conexión (`DATABASE_URL`, `REDIS_URL`, etc.) en cada servicio gestionado.

## Paso 2: Crear servicios custom

Para cada servicio custom (app, worker, mercure, traccar, osrm, vroom):

1. **Add → GitHub Repo** → seleccionar el repositorio `mxo-track`
2. Renombrar el servicio al nombre correspondiente
3. En **Settings → Source → Dockerfile Path**, configurar:

| Servicio | Dockerfile Path |
|----------|----------------|
| app-mxo | `Dockerfile.railway` |
| worker-mxo | `Dockerfile.worker` |
| mercure-mxo | `Dockerfile.mercure` |
| traccar-mxo | `Dockerfile.traccar` |
| osrm-mxo | `Dockerfile.osrm` |
| vroom-mxo | `Dockerfile.vroom` |

4. Para **osrm-mxo**: crear volumen persistente en **Settings → Volumes → Add Volume** → mount path `/data`. El primer arranque descarga y procesa el mapa (~5-10 min). Arranques posteriores son instantáneos.

## Paso 3: Generar dominios públicos

En Railway, para cada servicio que necesite acceso externo:

**Settings → Networking → Generate Domain**

Solo necesitan dominio público:
- **app-mxo** (puerto 8000) — la web principal
- **mercure-mxo** (puerto 80) — SSE realtime para el frontend
- **traccar-mxo** (puerto 5055) — protocolo OsmAnd GPS para dispositivos Android

No necesitan dominio público (solo acceso interno):
- **osrm-mxo** (puerto 5000) — motor de rutas, accedido por vroom-mxo
- **vroom-mxo** (puerto 3000) — optimizador VRP, accedido por app-mxo y worker-mxo

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
VROOM_URL=http://vroom-mxo.railway.internal:3000
OSRM_URL=http://osrm-mxo.railway.internal:5000
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
VROOM_URL=http://vroom-mxo.railway.internal:3000
OSRM_URL=http://osrm-mxo.railway.internal:5000
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

```env
TRACCAR_DB_HOST=bbdd-mxo.railway.internal
TRACCAR_DB_PORT=5432
TRACCAR_DB_NAME=traccar
TRACCAR_DB_USER=${{bbdd-mxo.PGUSER}}
TRACCAR_DB_PASSWORD=${{bbdd-mxo.PGPASSWORD}}
```

> Traccar ahora usa PostgreSQL (bbdd-mxo) en vez de H2 embebida. El script `traccar-start.sh` configura la conexión, espera a que PostgreSQL esté disponible, crea la base de datos `traccar` si no existe, y arranca Traccar.

### osrm-mxo

No necesita variables de entorno. El mapa se descarga y procesa automáticamente en el primer arranque (almacenado en volumen persistente `/data`).

### vroom-mxo

No necesita variables de entorno. La configuración de conexión a OSRM va en `docker/vroom/config-railway.yml` (apunta a `osrm-mxo.railway.internal:5000`).

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

### Orden de deploy recomendado

1. **osrm-mxo** primero (sin dependencias, primer boot tarda ~5-10 min)
2. **vroom-mxo** segundo (depende de osrm-mxo)
3. **traccar-mxo** (depende de bbdd-mxo)
4. **app-mxo** + **worker-mxo** (dependen de todo lo anterior)

### Logs a verificar

- **app-mxo**: Debe mostrar `Target: <host>:<port>/<db>`, luego `Database is reachable`, luego `Starting nginx`
- **worker-mxo**: Debe mostrar `Starting Traccar stream worker`
- **mercure-mxo**: Debe arrancar Caddy sin errores
- **traccar-mxo**: Debe mostrar `Configuring PostgreSQL connection`, luego `PostgreSQL is reachable`, luego `Starting Traccar server`
- **osrm-mxo**: Primer boot: `Downloading Comunidad de Madrid map`, luego `Starting osrm-routed`. Siguientes boots: `Map data already processed, starting server`
- **vroom-mxo**: Debe arrancar sin errores, log de VROOM

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

### VROOM: "optimization failed" o timeout

VROOM no puede conectar con OSRM.
- Verificar que osrm-mxo está corriendo y el mapa está procesado (logs: `Starting osrm-routed`)
- Verificar que vroom-mxo puede resolver `osrm-mxo.railway.internal` (private networking habilitado)
- OSRM puede tardar ~30s en cargar el mapa en memoria tras arrancar

### OSRM: primer arranque lento

El primer deploy de osrm-mxo tarda ~5-10 min (descarga ~75 MB + procesamiento del mapa). Deploys posteriores arrancan en segundos (datos ya en volumen `/data`).
- Si el volumen se borra, osrm-mxo reprocesará el mapa en el siguiente arranque

### Traccar: "connection refused" a PostgreSQL

El script `traccar-start.sh` espera hasta 60s. Si falla:
- Verificar que bbdd-mxo está activo
- Verificar que las variables `TRACCAR_DB_*` son correctas
- Verificar que `${{bbdd-mxo.PGUSER}}` resuelve (nombre de servicio debe coincidir)

## Notas

- Las URLs internas (`*.railway.internal`) solo funcionan entre servicios del mismo proyecto Railway. Son más rápidas y no generan tráfico de egress.
- Railway asigna `$PORT` dinámicamente. Los scripts de arranque ya lo usan.
- Si Traccar se recrea, el script de arranque de app-mxo reinicializa el admin automáticamente. Traccar ahora usa PostgreSQL — sus datos persisten en bbdd-mxo.
- OSRM guarda los datos del mapa procesado en un volumen persistente de Railway (`/data`). Para actualizar el mapa: borrar el volumen y hacer redeploy de osrm-mxo.
- VROOM y OSRM son servicios internos — no necesitan dominio público ni SSL.
- Las sesiones se almacenan en Redis con prefijo `sess:transporte:`.
- El protocolo OsmAnd de Traccar es HTTP — compatible con la terminación SSL de Railway.
- `doctrine.yaml` ya tiene `server_version: '16'`, así que no es necesario incluir `?serverVersion=16` en DATABASE_URL.
