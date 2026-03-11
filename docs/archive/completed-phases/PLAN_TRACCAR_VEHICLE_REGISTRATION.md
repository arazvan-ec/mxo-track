# Plan: Registro Traccar con checkbox + PostgreSQL separada

## Contexto

Cada vez que se redespliega `traccar-mxo` en Railway, la base de datos H2 embebida se pierde (devices, posiciones, usuario admin). Esto obliga a recrear manualmente los devices y actualizar `traccarDeviceId` en cada Vehicle. Además, el admin tiene que introducir manualmente el `traccarDeviceId` al crear un vehiculo.

Este plan resuelve ambos problemas:
1. **Checkbox en formulario**: Al crear un vehiculo, un checkbox permite elegir si registrarlo en Traccar
2. **Persistencia**: Migrar Traccar de H2 a PostgreSQL separada (nueva instancia, no compartida con app)

---

## Feature 1: Checkbox "Registrar en Traccar" en formulario de vehiculos

### Cambios

**1. `backend/src/Form/VehicleType.php`**
- Reemplazar campo `traccarDeviceId` (IntegerType manual) por un checkbox "Registrar en Traccar"
- El checkbox es un campo no-mapeado (`mapped: false`) — solo indica la intención
- Solo se muestra al crear (nuevo vehiculo), no al editar uno que ya tiene device

**2. `backend/src/Controller/Admin/VehicleAdminController.php`**
- Inyectar `TraccarApiClient` en el constructor
- En `new()`, después de validar el form:
  1. Comprobar si el checkbox "registrar en Traccar" está marcado
  2. Si sí: llamar `$vehicle->initializePublicId()` para generar ULID
  3. Generar `$uniqueId = 'v-' . strtolower($vehicle->getPublicIdString())`
  4. Llamar `$this->traccarApiClient->createDevice($vehicle->getName(), $uniqueId)`
  5. Guardar el ID devuelto: `$vehicle->setTraccarDeviceId((int) $device['id'])`
  6. Envolver en try/catch — si Traccar falla, vehiculo se crea igual (flash warning)

**3. `backend/templates/admin/vehicle/form.html.twig`**
- Mostrar `traccarDeviceId` como texto read-only cuando se edita un vehiculo que ya tiene device (informativo)

### Degradación graceful
- Si Traccar no está disponible: vehiculo se crea con `traccarDeviceId = null`, admin ve warning
- Se puede vincular después con `app:traccar:sync-devices --apply`

---

## Feature 2: Traccar con PostgreSQL separada

### Por qué PostgreSQL separada
- Traccar puede generar mucho tráfico de posiciones GPS — no afecta a la BD de la app
- Aislamiento: si una BD tiene problemas, la otra sigue funcionando
- Traccar crea sus tablas con prefijo `tc_` pero es más limpio en BD separada
- En Railway: se crea una nueva instancia PostgreSQL (managed) solo para Traccar

### Cambios

**1. `docker/traccar-railway/entrypoint.sh`** (NUEVO)
- Script que genera `traccar.xml` desde variables de entorno si `TRACCAR_DB_DRIVER` está definido
- Si no está definida, usa el `traccar.xml` estático (H2, para desarrollo local)
- Arranca Traccar con `exec java -jar tracker-server.jar conf/traccar.xml`

**2. `Dockerfile.traccar`**
- Copiar `entrypoint.sh` y darle permisos de ejecución
- Mantener copia de `traccar.xml` como fallback para local
- Usar `ENTRYPOINT ["/opt/traccar/entrypoint.sh"]`

**3. `scripts/railway-setup-vars.sh`**
- Añadir prompt para la nueva BD de Traccar (TRACCAR_DATABASE_URL)
- En la sección de Traccar, añadir variables:
  - `TRACCAR_DB_DRIVER=org.postgresql.Driver`
  - `TRACCAR_DB_URL=jdbc:postgresql://host:port/dbname` (parsear de TRACCAR_DATABASE_URL)
  - `TRACCAR_DB_USER` / `TRACCAR_DB_PASSWORD`
- Actualizar el summary

**4. Railway Dashboard**
- Crear nueva instancia PostgreSQL: `bbdd-traccar` (o similar)
- Copiar la TRACCAR_DATABASE_URL pública de esta nueva instancia
- Configurar las variables en traccar-mxo service

**5. `docker-compose.local.yml`** (opcional)
- Cambiar traccar de `image:` a `build: Dockerfile.traccar` para testear el entrypoint
- Sin variables `TRACCAR_DB_*`, usará H2 + volumen `traccar_data` (comportamiento actual)

### Nota sobre migración
- Al primer deploy con PostgreSQL, Traccar auto-crea las tablas `tc_*`
- Los devices H2 previos se pierden — se recrean con el checkbox al crear vehiculos
- `railway-start.sh` ya maneja la creación del admin user de Traccar (detecta `newServer:true`)

---

## Orden de implementación

1. **Feature 2** primero (PostgreSQL) — establecer persistencia antes de crear devices
2. **Feature 1** después (checkbox) — los devices creados sobreviven redeploys

## Verificación

1. **Local (Feature 1)**: Crear vehiculo con checkbox marcado → verificar `traccarDeviceId` asignado → verificar device en Traccar (`curl http://localhost:8082/api/devices`)
2. **Local (Feature 2)**: Añadir `TRACCAR_DB_*` al servicio traccar en docker-compose → rebuild → verificar tablas `tc_*` en PostgreSQL separada
3. **Railway**: Push a main → verificar logs de traccar-mxo → crear vehiculo con checkbox → verificar tracking end-to-end

## Archivos a modificar

| Archivo | Acción |
|---------|--------|
| `backend/src/Form/VehicleType.php` | Reemplazar `traccarDeviceId` por checkbox no-mapeado |
| `backend/src/Controller/Admin/VehicleAdminController.php` | Inyectar TraccarApiClient, registro condicional en `new()` |
| `backend/templates/admin/vehicle/form.html.twig` | Mostrar traccarDeviceId read-only en edición |
| `docker/traccar-railway/entrypoint.sh` | NUEVO — genera traccar.xml desde env vars |
| `Dockerfile.traccar` | Usar entrypoint.sh |
| `scripts/railway-setup-vars.sh` | Añadir TRACCAR_DATABASE_URL y variables |

---

## Referencia: Setup Traccar tras redeploy (H2 se borra)

Mientras Traccar use H2 embebida, cada redeploy borra todo. Pasos para reconstruir:

### 1. Crear admin user
```bash
curl -sf -X POST 'https://mxo-track-traccar-db4e.up.railway.app/api/users' \
  -H 'Content-Type: application/json' \
  -d '{"name":"admin","email":"admin","password":"admin"}'
```
No incluir `"administrator"` en el JSON (provoca NullPointerException en Traccar vacío).

### 2. Crear device
```bash
curl -sf -X POST 'https://mxo-track-traccar-db4e.up.railway.app/api/devices' \
  -u admin:admin \
  -H 'Content-Type: application/json' \
  -d '{"name":"Android Test","uniqueId":"1234567"}'
```

### 3. Verificar que acepta posiciones
```bash
curl -s -o /dev/null -w "%{http_code}" \
  "http://shuttle.proxy.rlwy.net:28058/?id=1234567&lat=40.41&lon=-3.70&timestamp=$(date +%s)&speed=0"
# Debe devolver 200
```

### 4. Actualizar credenciales en Railway
Tras recrear Traccar, las credenciales vuelven a `admin`/`admin`. Actualizar `TRACCAR_PASSWORD=admin` en **app-mxo** y **worker-mxo**.

### 5. Actualizar Vehicle en Symfony
En `/admin/vehicles`, editar el vehiculo y poner `traccarDeviceId` = el **`id`** interno devuelto por Traccar (ej. `1`).

---

## Referencia: IDs de Traccar (importante)

Traccar tiene **dos identificadores distintos** por device:

| | `id` (interno) | `uniqueId` (identificador) |
|---|---|---|
| **Ejemplo** | `1` | `1234567` |
| **Quién lo genera** | Traccar (auto-increment) | El usuario al crear el device |
| **Quién lo usa** | API REST (`/api/positions?deviceId=1`) | App Android / protocolo OsmAnd (puerto 5055) |
| **En Symfony** | `Vehicle.traccarDeviceId = 1` | No se guarda (solo existe en Traccar + app Android) |

**El worker de Symfony usa la API REST**, que filtra por `id` interno:
- `/api/positions?deviceId=1` → devuelve posiciones
- `/api/positions?deviceId=1234567` → no encuentra nada

**Regla**: `Vehicle.traccarDeviceId` siempre debe ser el `id` interno de Traccar, NO el `uniqueId`.

---

## Referencia: Configurar Traccar Client en Android

- **Server URL**: `http://shuttle.proxy.rlwy.net`
- **Port**: `28058`
- **Device identifier**: `1234567` (debe coincidir con el `uniqueId` del device en Traccar)

### URLs Railway

| Servicio | URL |
|----------|-----|
| App | https://mxo-track-app.up.railway.app |
| Traccar API (8082) | https://mxo-track-traccar-db4e.up.railway.app |
| Traccar GPS (5055) | http://shuttle.proxy.rlwy.net:28058 |
| Mercure | https://mxo-track-mercure.up.railway.app |
| PostgreSQL | switchback.proxy.rlwy.net:44967 |
| Redis | metro.proxy.rlwy.net:45436 |
