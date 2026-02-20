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

## Paso 1: Generar dominios públicos

En Railway, para cada servicio que necesite acceso externo:

**Settings → Networking → Generate Domain**

Dominios públicos asignados:
- **app-mxo** (puerto 8000) → `https://mxo-track-production.up.railway.app`
- **mercure-mxo** (puerto 80) → `https://mxo-track-production.up.railway.app`
- **traccar-mxo** (puerto 5055) → `https://mxo-track-traccar-db4e.up.railway.app`

> **Traccar**: Generar el dominio público mapeado al **puerto 5055** (OsmAnd GPS).
> Los servicios internos (app-mxo, worker-mxo) acceden a Traccar API (8082) vía URL interna — no necesita dominio público para 8082.

## Paso 2: Variables de entorno por servicio

Ir a cada servicio → **Variables → Raw Editor** y pegar el bloque correspondiente.

### app-mxo

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=8bdbc8f3fc515442faf5f8f955f2a0b714b2620ede7b64480734c28fcce7c236
APP_URL=https://mxo-track-production.up.railway.app
DATABASE_URL=postgresql://postgres:BGBYqGcZewWwMOtfnFzTFDnEHpEpgNoZ@postgres.railway.internal:5432/railway?serverVersion=16&charset=utf8
REDIS_URL=redis://default:nisOENRFqAgwVqYgEYmbquCdZfaLYKvJ@redis.railway.internal:6379
REDIS_SESSION_PREFIX=sess:transporte:
MERCURE_URL=http://mercure-mxo.railway.internal/.well-known/mercure
MERCURE_PUBLIC_URL=https://mxo-track-production.up.railway.app/.well-known/mercure
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

> **Nota**: `TRACCAR_BASE_URL` usa URL interna (`railway.internal`) — más rápido y sin egress. No cambiar a dominio público.

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

```env
SERVER_NAME=:80
MERCURE_PUBLISHER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_PUBLISHER_JWT_ALG=HS256
MERCURE_SUBSCRIBER_JWT_KEY=75ea28a79d9c88144fda4448226969a45f013e6e4b8ac016586257a302567daa
MERCURE_SUBSCRIBER_JWT_ALG=HS256
MERCURE_EXTRA_DIRECTIVES=anonymous 0
publish_origins *
cors_origins https://mxo-track-production.up.railway.app
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

## Paso 4: Configurar puerto público de Traccar

En Railway, **traccar-mxo → Settings → Networking**:

1. **Generate Domain** → apuntar al **puerto 5055**
2. Anotar el dominio generado (ej: `traccar-mxo-production.up.railway.app`)

Esto expone el protocolo OsmAnd (HTTP) para que dispositivos Android envíen posiciones GPS.

El puerto 8082 (API REST) **no necesita dominio público** — app-mxo y worker-mxo lo acceden vía `traccar-mxo.railway.internal:8082`.

## Paso 5: Configurar app Android (Traccar Client)

Instalar **Traccar Client** desde Google Play Store.

### Configuración en la app:

| Campo | Valor |
|-------|-------|
| **Device identifier** | (se genera automáticamente, anotarlo) |
| **Server URL** | `https://mxo-track-traccar-db4e.up.railway.app:443` |
| **Frequency** | 10 (segundos, ajustar según necesidad) |
| **Status** | Service status → ON |

> **IMPORTANTE**: Usar `https://` y puerto `443` (no `5055`). Railway termina SSL en el edge y reenvía al puerto 5055 internamente.

### Crear el dispositivo en Traccar

Antes de que el dispositivo envíe posiciones, hay que registrarlo en Traccar. Desde dentro del contenedor app:

```bash
# Crear dispositivo con el identifier de la app Android
curl -u admin:6Mrby8dT37ytpvcpaKc \
  -X POST 'http://traccar-mxo.railway.internal:8082/api/devices' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Mi Android","uniqueId":"DEVICE_IDENTIFIER_DE_LA_APP"}'
```

O usar el comando Symfony:
```bash
php bin/console app:traccar:sync-devices
```

### Flujo completo

```
Android (Traccar Client)
  → HTTPS → Railway edge (SSL termination)
    → HTTP → traccar-mxo:5055 (OsmAnd protocol)
      → Traccar almacena posición
        → worker-mxo (app:traccar:stream) lee posición via API 8082
          → Publica a Mercure
            → Frontend actualiza mapa en tiempo real
```

## Paso 6: Verificar deploy

Después del deploy, el script `railway-start.sh` automáticamente:

1. Ejecuta migraciones (`doctrine:migrations:migrate`)
2. Calienta la caché de Symfony
3. Inicializa Traccar (crea usuario admin si es la primera vez)
4. Arranca nginx + PHP-FPM en el puerto asignado

### Logs a verificar

- **app-mxo**: Debe mostrar "Starting nginx" y migraciones OK
- **worker-mxo**: Debe mostrar "Traccar stream started" (polling cada 5s)
- **mercure-mxo**: Debe arrancar Caddy sin errores
- **traccar-mxo**: Debe mostrar "Starting Traccar Server"

### Verificar que Traccar GPS funciona

```bash
# Desde cualquier máquina con internet, simular un envío GPS:
curl -v "https://mxo-track-traccar-db4e.up.railway.app/?id=test123&lat=40.4168&lon=-3.7038&timestamp=1708100000&speed=0"
```

Si devuelve HTTP 200, el protocolo OsmAnd está funcionando correctamente.

## Secrets generados

| Variable | Valor | Usado en |
|----------|-------|----------|
| `APP_SECRET` | `8bdbc8f3...c236` | app-mxo, worker-mxo |
| `MERCURE_JWT_KEY` | `75ea28a7...7daa` | app-mxo, worker-mxo, mercure-mxo |
| `TRACCAR_PASSWORD` | `6Mrby8dT37ytpvcpaKc` | app-mxo, worker-mxo |

> Para regenerar secrets: `openssl rand -hex 32` (APP_SECRET, MERCURE keys) o `openssl rand -base64 15` (TRACCAR_PASSWORD).

## Notas

- Las URLs internas (`*.railway.internal`) solo funcionan entre servicios del mismo proyecto Railway. Son más rápidas y no generan tráfico de egress.
- Railway asigna `$PORT` dinámicamente. Los scripts de arranque ya lo usan.
- Si Traccar se recrea (volumen perdido), el script de arranque de app-mxo reinicializa el admin automáticamente.
- Las sesiones se almacenan en Redis con prefijo `sess:transporte:`.
- El protocolo OsmAnd de Traccar es HTTP — compatible con la terminación SSL de Railway.
