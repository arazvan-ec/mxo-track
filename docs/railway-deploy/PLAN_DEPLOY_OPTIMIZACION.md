# Plan de Deploy: Servicios de Optimización de Rutas

## Resumen

Desplegar los servicios de optimización de rutas (VROOM + OSRM) en Railway y migrar Traccar de H2 embebida a PostgreSQL. Incluye recomendación de base de datos externa para futuras integraciones con IA.

## Estado Actual

### Servicios existentes en Railway
- app-mxo, worker-mxo, mercure-mxo, traccar-mxo, bbdd-mxo, redis-mxo

### Problema
VROOM y OSRM solo existen en `docker-compose.local.yml` (desarrollo local). Las variables `VROOM_URL` y `OSRM_URL` no están configuradas en Railway. La optimización de rutas (RouteBuilder, VroomRequestMapper) falla en producción.

### Funcionalidad que ya funciona sin VROOM/OSRM
- `GET /api/routes/{publicId}/loading-manifest` — lectura de DB
- `GET /api/routes/{publicId}/analysis` — lectura de DB
- Nuevas columnas de entidades (priority, skills, serviceTimeSeconds) — se crean automáticamente

### Funcionalidad bloqueada sin VROOM/OSRM
- Creación de rutas optimizadas (RouteBuilder → VroomApiClient)
- Re-optimización de rutas (RouteOptimizationService)
- VroomRequestMapper (priority, skills, service time, max_tasks)

---

## Nuevos Servicios

### osrm-mxo (Motor de Rutas)

| Campo | Valor |
|-------|-------|
| Imagen base | `osrm/osrm-backend:latest` |
| Dockerfile | `Dockerfile.osrm` |
| Puerto | 5000 (interno) |
| Dominio público | No |
| Volumen | `/data` (mapa procesado, ~200 MB) |
| RAM estimada | ~200-300 MB (mapa de Madrid en memoria) |

**Primer arranque**: Descarga mapa de Geofabrik (~75 MB), procesa con osrm-extract/partition/customize (~5-10 min). Datos guardados en volumen persistente.

**Arranques posteriores**: Instantáneo (datos ya en volumen).

### vroom-mxo (Optimizador VRP)

| Campo | Valor |
|-------|-------|
| Imagen base | `ghcr.io/vroom-project/vroom-docker:v1.14.0` |
| Dockerfile | `Dockerfile.vroom` |
| Puerto | 3000 (interno) |
| Dominio público | No |
| Volumen | No necesita |
| RAM estimada | ~100-200 MB |
| Dependencia | osrm-mxo (red interna) |

---

## Migración: Traccar H2 → PostgreSQL

### Motivo
H2 embebida es frágil — los datos se pierden si el volumen se borra. PostgreSQL centraliza toda la persistencia en bbdd-mxo.

### Cambios
- `docker/traccar-railway/traccar.xml` — driver cambiado de H2 a `org.postgresql.Driver`
- `scripts/traccar-start.sh` — configura conexión, espera PostgreSQL, crea DB `traccar`
- `Dockerfile.traccar` — instala `postgresql-client`, usa nuevo entrypoint

### Variables nuevas para traccar-mxo
```env
TRACCAR_DB_HOST=bbdd-mxo.railway.internal
TRACCAR_DB_PORT=5432
TRACCAR_DB_NAME=traccar
TRACCAR_DB_USER=${{bbdd-mxo.PGUSER}}
TRACCAR_DB_PASSWORD=${{bbdd-mxo.PGPASSWORD}}
```

### Migración de datos
Si hay datos en la H2 de Traccar que se quieran conservar, exportar antes de migrar. Si Traccar se usa como fuente de posiciones GPS en tiempo real (no como almacén histórico), no hay datos que migrar — Traccar recrea las tablas al arrancar con PostgreSQL.

---

## Orden de Deploy

1. **osrm-mxo** — crear servicio + volumen `/data`. Esperar a que el mapa se procese (~5-10 min).
2. **vroom-mxo** — crear servicio. Verificar que resuelve `osrm-mxo.railway.internal`.
3. **traccar-mxo** — añadir variables `TRACCAR_DB_*`, redeploy.
4. **app-mxo** + **worker-mxo** — añadir `VROOM_URL` y `OSRM_URL`, redeploy.

---

## Variables de Entorno (resumen de cambios)

### Nuevas en app-mxo y worker-mxo
```env
VROOM_URL=http://vroom-mxo.railway.internal:3000
OSRM_URL=http://osrm-mxo.railway.internal:5000
```

### Nuevas en traccar-mxo
```env
TRACCAR_DB_HOST=bbdd-mxo.railway.internal
TRACCAR_DB_PORT=5432
TRACCAR_DB_NAME=traccar
TRACCAR_DB_USER=${{bbdd-mxo.PGUSER}}
TRACCAR_DB_PASSWORD=${{bbdd-mxo.PGPASSWORD}}
```

---

## Verificación Post-Deploy

### 1. OSRM
```bash
# Desde app-mxo (railway run o exec):
curl -s http://osrm-mxo.railway.internal:5000/nearest/v1/driving/-3.7038,40.4168
# Esperado: {"code":"Ok", ...}
```

### 2. VROOM
```bash
curl -s -X POST http://vroom-mxo.railway.internal:3000 \
  -H 'Content-Type: application/json' \
  -d '{"vehicles":[{"id":1,"start":[-3.7038,40.4168]}],"jobs":[{"id":1,"location":[-3.6883,40.4530]}]}'
# Esperado: {"code":0, ...}
```

### 3. Traccar con PostgreSQL
- Logs muestran `Configuring PostgreSQL connection` y `Starting Traccar server`
- `GET /api/server` en traccar-mxo responde (acceder via app-mxo internamente)

### 4. Optimización de rutas end-to-end
- Crear ruta con envíos → VROOM asigna y ordena paradas
- Verificar distancias reales (no Haversine)
- Verificar que priority y skills se respetan

### 5. Endpoints read-only
- `GET /api/routes/{id}/loading-manifest` → JSON con orden LIFO
- `GET /api/routes/{id}/analysis` → JSON con análisis de ruta completada

### 6. Columnas nuevas en DB
```sql
-- Verificar desde app-mxo:
SELECT column_name FROM information_schema.columns WHERE table_name = 'shipment' AND column_name IN ('priority', 'required_skills', 'service_time_seconds');
SELECT column_name FROM information_schema.columns WHERE table_name = 'vehicle' AND column_name = 'skills';
```

---

## Arquitectura Final

```
                    ┌──────────────┐
                    │   Internet   │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
         ┌────▼────┐ ┌────▼────┐ ┌────▼────┐
         │ app-mxo │ │mercure  │ │traccar  │
         │ :8000   │ │ :80     │ │ :5055   │
         └────┬────┘ └─────────┘ └────┬────┘
              │                       │
    ┌─────────┼───────────┐     ┌────▼────┐
    │         │           │     │ bbdd-mxo│
    │    ┌────▼────┐ ┌────▼────┐│ :5432   │
    │    │worker   │ │vroom-mxo││(app DB +│
    │    │ -mxo    │ │ :3000   ││traccar) │
    │    └─────────┘ └────┬────┘└─────────┘
    │                     │
    │                ┌────▼────┐  ┌─────────┐
    │                │osrm-mxo│  │redis-mxo│
    │                │ :5000  │  │ :6379   │
    │                │[vol/data]│ └─────────┘
    │                └─────────┘
    └─────────────────────────────┘
```

---

## Recomendación: Base de Datos Externa para IA

### Neon (PostgreSQL Serverless con pgvector)

Para futuras integraciones de IA (embeddings + analytics), recomendamos **Neon**:

| Característica | Detalle |
|---------------|---------|
| pgvector | Nativo, sin extensión extra — embeddings para búsqueda semántica |
| PostgreSQL 16 | Compatible con bbdd-mxo actual |
| Branching | Copias de la BD para experimentar ML sin afectar producción |
| Serverless | Escala a cero — ideal para workloads analíticos intermitentes |
| Tier gratis | 0.5 GB storage, suficiente para empezar |

### Casos de uso IA para logística
- **Embeddings de direcciones** — vectorizar para detectar duplicados, zonas similares
- **Predicción de tiempos de servicio** — ML sobre datos históricos de RouteAnalysisService
- **Clustering de entregas** — agrupar envíos por zona para pre-asignar vehículos
- **RAG sobre datos operativos** — consultas en lenguaje natural sobre rendimiento de rutas

### Integración futura
No se implementa ahora. Cuando se necesite, añadir a app-mxo:
```env
ANALYTICS_DATABASE_URL=postgresql://user:pass@ep-xxx.us-east-2.aws.neon.tech/analytics?sslmode=require
```
Y configurar una segunda conexión Doctrine en `doctrine.yaml` para la BD analítica.

### Alternativas
- **Supabase**: pgvector + auth integrado, pero más opinionado
- **Timescale**: Excelente para series temporales (posiciones GPS), pero sin branching ni tier gratis comparable
- **Railway PostgreSQL**: Ya integrado, pero sin pgvector ni branching

---

## Archivos Creados/Modificados

| Archivo | Acción | Propósito |
|---------|--------|-----------|
| `Dockerfile.osrm` | Creado | OSRM con script de arranque y volumen |
| `Dockerfile.vroom` | Creado | VROOM con config Railway |
| `Dockerfile.traccar` | Modificado | PostgreSQL client + nuevo entrypoint |
| `scripts/osrm-start.sh` | Creado | Preparación de mapa + arranque OSRM |
| `scripts/traccar-start.sh` | Creado | Configuración PostgreSQL + arranque Traccar |
| `docker/vroom/config-railway.yml` | Creado | VROOM apuntando a osrm-mxo.railway.internal |
| `docker/traccar-railway/traccar.xml` | Modificado | H2 → PostgreSQL con placeholders |
| `docs/DEPLOY_RAILWAY.md` | Modificado | Nuevos servicios, env vars, verificación |
