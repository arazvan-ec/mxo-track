# Evaluación de Tech Stack — MXO Track

**Fecha:** 2026-03-06
**Estado:** Propuesta aprobada
**Decisión:** Migrar de PHP/Symfony a **Go (backend)** + **React/Next.js (frontend)**

---

## 1. Contexto

MXO Track es una plataforma de logística y tracking de entregas construida actualmente sobre Symfony 7.4 (PHP 8.4). El sistema funciona, pero el stack actual presenta fricciones en las áreas que más importan para el negocio: real-time, concurrencia e ingesta continua de datos GPS.

### Características del negocio

| Área | Descripción |
|------|-------------|
| GPS Tracking | 50-500 vehículos simultáneos con posiciones cada 1-5s |
| Real-time | Fleet map en vivo, notificaciones instantáneas, actualizaciones de estado |
| APIs REST | App móvil de drivers, tracking público, integraciones externas |
| Multi-tenant | Aislamiento por customer con filtros SQL automáticos |
| Ingesta continua | Stream de posiciones GPS desde Traccar (polling/WebSocket) |
| CRUD admin | Gestión de rutas, vehículos, shipments, usuarios, clientes |
| Reportes | Entregas, performance de drivers, analytics por cliente |
| Background jobs | Ingesta GPS, purge de datos, imports CSV bulk |

### Decisiones del equipo

- **Equipo**: Pequeño (2-4 personas), formable en nuevo lenguaje
- **Frontend**: SPA separada (React/Vue) — el backend será pura API
- **Tracking**: Abstracción con adaptadores — Traccar hoy, GPS directo mañana
- **Aprendizaje**: Abiertos a un lenguaje nuevo si es la mejor herramienta

---

## 2. Análisis del stack actual (PHP/Symfony)

### Fortalezas

- **Productividad CRUD**: Symfony Forms, Doctrine ORM, Twig — excelente para admin panels
- **Ecosystem maduro**: Bundles para todo (security, validation, serialization)
- **Doctrine ORM**: SQL filters para multi-tenancy, migrations, fixtures
- **Seguridad**: Firewall, voters, CSRF, rate limiting out-of-the-box

### Fricciones para este negocio

| Problema | Impacto |
|----------|---------|
| **Real-time requiere Mercure externo** | Un servicio extra que mantener, configurar JWT keys, CORS, cookies — complejidad operacional |
| **PHP no mantiene conexiones persistentes** | Cada request bootstraps el framework completo (~100-200MB RAM por worker) |
| **`traccar:stream` es un proceso CLI bloqueante** | PHP no está diseñado para procesos long-running; memory leaks, no graceful shutdown nativo |
| **Sin WebSockets nativos** | Imposible sin extensiones (Swoole/RoadRunner) que cambian el modelo de ejecución |
| **Concurrencia limitada** | Para 500 vehículos a 1 posición/s = 500 writes/s + broadcasts — PHP necesita múltiples workers |
| **Deploy pesado** | PHP + extensiones + Composer + PHP-FPM/Apache + Mercure = stack complejo |
| **Twig será abandonado** | Al ir a SPA, todo el frontend Twig (41 templates) queda obsoleto |

### Veredicto

Symfony es excelente para aplicaciones web tradicionales (request-response + templates). Para un sistema de tracking con real-time intensivo y APIs puras, **no es la herramienta óptima**.

---

## 3. Candidatos evaluados

### 3.1 Go (Chi/Echo)

| Criterio | Evaluación |
|----------|-----------|
| Real-time (WebSockets) | **Excelente** — gorilla/websocket o stdlib, sin servicio externo |
| Concurrencia | **Excelente** — goroutines manejan miles de conexiones con ~2KB RAM cada una |
| APIs REST | **Muy bueno** — Chi/Echo son minimalistas y performantes |
| Ingesta GPS | **Excelente** — goroutine que corre dentro del mismo server |
| Multi-tenancy | **Bueno** — middleware + context, requiere implementación manual |
| CRUD/Admin | **Regular** — más boilerplate que frameworks full-stack |
| Deploy | **Excelente** — binario estático único, ~20MB, sin runtime |
| Performance | **Excelente** — ~10-50MB RAM para el server completo |
| Curva aprendizaje | **Moderada** — lenguaje simple pero paradigma diferente a PHP |
| Talento disponible | **Bueno** — creciendo rápido, especialmente en infraestructura/backend |

### 3.2 TypeScript/NestJS

| Criterio | Evaluación |
|----------|-----------|
| Real-time (WebSockets) | **Muy bueno** — Socket.io integrado en NestJS |
| Concurrencia | **Bueno** — event loop async, pero single-threaded |
| APIs REST | **Excelente** — decorators similares a Symfony, muy productivo |
| Ingesta GPS | **Bueno** — async/await, pero event loop puede bloquearse con CPU-intensive |
| Multi-tenancy | **Bueno** — similar a Go, middleware + context |
| CRUD/Admin | **Bueno** — TypeORM/Prisma, validation pipes |
| Deploy | **Bueno** — Node.js runtime necesario, más pesado que Go |
| Performance | **Regular** — ~100-150MB RAM, GC pauses posibles |
| Curva aprendizaje | **Baja** — familiar si conoces PHP/Symfony (mismos patrones) |
| Talento disponible | **Excelente** — el pool más grande |

### 3.3 Elixir/Phoenix

| Criterio | Evaluación |
|----------|-----------|
| Real-time (WebSockets) | **El mejor** — Phoenix Channels, 2M+ conexiones en un server |
| Concurrencia | **El mejor** — BEAM VM, procesos ligeros, fault-tolerant |
| APIs REST | **Bueno** — funcional pero menos convenciones que NestJS |
| Ingesta GPS | **Excelente** — GenServers para procesos long-running |
| Multi-tenancy | **Bueno** — Ecto repos con prefix |
| CRUD/Admin | **Regular** — menos ecosystem para admin UI |
| Deploy | **Bueno** — releases OTP, pero BEAM VM necesaria |
| Performance | **Muy bueno** — excelente para I/O, no para CPU |
| Curva aprendizaje | **Alta** — paradigma funcional, OTP, pattern matching |
| Talento disponible | **Bajo** — comunidad pequeña, difícil contratar |

### 3.4 Python/FastAPI

| Criterio | Evaluación |
|----------|-----------|
| Real-time (WebSockets) | **Regular** — soportado pero no es su fuerte |
| Concurrencia | **Regular** — GIL limita, async ayuda pero es más complejo |
| APIs REST | **Excelente** — FastAPI con auto-docs, validación, async |
| Ingesta GPS | **Regular** — asyncio funciona pero no tan robusto como Go/Elixir |
| Multi-tenancy | **Bueno** — SQLAlchemy events/filters |
| CRUD/Admin | **Muy bueno** — Django Admin es el mejor, FastAPI menos |
| Deploy | **Bueno** — runtime Python necesario |
| Performance | **Regular** — el más lento de los candidatos para I/O intensivo |
| Curva aprendizaje | **Baja** — muy accesible |
| Talento disponible | **Excelente** — enorme pool |

---

## 4. Decisión: Go + React/Next.js

### ¿Por qué Go gana para MXO Track?

1. **WebSockets nativos** — Elimina Mercure como dependencia. El server Go mantiene un hub de conexiones WebSocket para el fleet map, notificaciones y actualizaciones de estado. Todo en un solo proceso.

2. **Goroutines para ingesta GPS** — El stream de Traccar corre como goroutine dentro del mismo server. Sin procesos CLI separados, sin memory leaks, con graceful shutdown.

3. **Performance para 500 vehículos** — 500 posiciones/s + broadcast a N clientes conectados = trivial para Go. PHP necesitaría múltiples workers + Mercure + Redis pub/sub.

4. **Deploy mínimo** — Un binario de ~20MB. Sin PHP, sin extensions, sin Composer, sin FPM. Railway lo sirve directo.

5. **Abstracción de tracking con interfaces** — Go interfaces son perfectas para el patrón adaptador (Traccar hoy, GPS directo mañana):

```go
type PositionProvider interface {
    StreamPositions(ctx context.Context, ch chan<- VehiclePosition) error
    GetDevices() ([]Device, error)
    CreateDevice(name, uniqueID string) (*Device, error)
}

type TraccarProvider struct {
    baseURL  string
    username string
    password string
}

type DirectGPSProvider struct {
    listenPort int // recibe OsmAnd/GPS protocol directamente
}
```

6. **Concurrencia real sin trucos** — Cada conexión WebSocket, cada ingesta de posición, cada webhook es una goroutine. No hay event loop que bloquear ni workers que escalar.

### ¿Por qué no TypeScript/NestJS?

- Sería la opción más **fácil** por familiaridad, pero para real-time intensivo con 500 vehículos, el event loop single-threaded de Node.js puede ser limitante.
- La ventaja de "mismo lenguaje front+back" pierde peso con un equipo pequeño que puede manejar dos lenguajes.
- Go será más valioso a largo plazo si el negocio escala.

### ¿Por qué no Elixir/Phoenix?

- Es técnicamente superior para real-time, pero el pool de talento es demasiado pequeño para un equipo de 2-4.
- La curva de aprendizaje del paradigma funcional + OTP es significativa.
- Overkill para 500 vehículos — Go maneja esa escala sin problemas.

### ¿Por qué React/Next.js para frontend?

- **Mapas**: `react-leaflet` y `react-map-gl` tienen el mejor soporte para mapas interactivos en tiempo real.
- **Ecosystem**: Más librerías para dashboards logísticos (tablas, gráficos, drag-and-drop de rutas).
- **Talento**: El pool de React devs es el más grande.
- **Next.js**: SSR para tracking público (SEO del link de seguimiento), App Router para organización, API routes si se necesitan.
- **shadcn/ui + Tailwind**: Ya conoces Tailwind. shadcn/ui da componentes profesionales sin vendor lock-in.

---

## 5. Stack técnico propuesto

### Backend (Go)

| Componente | Herramienta | Propósito |
|-----------|-------------|-----------|
| HTTP Router | **Chi** | Ligero, compatible con stdlib, middleware composable |
| Database | **sqlc** | Genera código Go type-safe desde queries SQL — sin ORM magic |
| Migrations | **golang-migrate** | Migraciones SQL versionadas |
| WebSockets | **gorilla/websocket** | Hub de conexiones para real-time |
| Auth | **JWT** (golang-jwt) | Stateless auth para APIs, compatible con SPA |
| Validation | **go-playground/validator** | Validación de structs con tags |
| DI | **Wire** (Google) | Inyección de dependencias compile-time |
| Config | **envconfig** o **viper** | Variables de entorno |
| Logging | **slog** (stdlib) | Structured logging (Go 1.21+) |
| Testing | **stdlib + testify** | Tests + assertions |

### Frontend (React/Next.js)

| Componente | Herramienta | Propósito |
|-----------|-------------|-----------|
| Framework | **Next.js 15** (App Router) | SSR, routing, API routes |
| Mapas | **react-leaflet** | Fleet map interactivo |
| State/Fetch | **TanStack Query** | Server state, cache, refetching |
| UI Components | **shadcn/ui** | Componentes accesibles, sin vendor lock-in |
| Styling | **Tailwind CSS** | Ya conocido por el equipo |
| Forms | **React Hook Form + Zod** | Validación type-safe |
| WebSocket | **Native WebSocket API** | Conexión al hub Go |
| Auth | **NextAuth.js** o cookies | Sesiones/JWT |
| Tables | **TanStack Table** | Tablas con sorting, filtering, pagination |

### Infraestructura

| Componente | Herramienta | Notas |
|-----------|-------------|-------|
| Database | **PostgreSQL 16** | Sin cambios |
| Cache/Sessions | **Redis 7** | Sin cambios |
| GPS Tracking | **Traccar** (adaptable) | Fase 1 con Traccar, fase 2 GPS directo |
| Deploy | **Railway** | Go binary + Next.js, sin Mercure |
| CI/CD | **GitHub Actions** | Build Go + Next.js, run tests |

---

## 6. Arquitectura

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENTES                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐ │
│  │ Admin    │  │ Customer │  │ Driver   │  │ Public  │ │
│  │ Dashboard│  │ Portal   │  │ PWA      │  │Tracking │ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬────┘ │
│       └──────────────┴──────────────┴─────────────┘      │
│                    Next.js (React)                        │
│              HTTP REST + WebSocket                        │
└─────────────────────┬───────────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────────┐
│                   GO API SERVER                          │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │  HTTP Router  │  │  WebSocket   │  │  Background   │  │
│  │  (Chi)        │  │  Hub         │  │  Workers      │  │
│  │              │  │              │  │  (goroutines) │  │
│  │  - REST APIs │  │  - Fleet Map │  │  - GPS Ingest │  │
│  │  - Auth      │  │  - Notifs    │  │  - Webhooks   │  │
│  │  - CRUD      │  │  - Status    │  │  - CSV Import │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬────────┘  │
│         └──────────────────┴─────────────────┘           │
│                     Service Layer                         │
│  ┌──────────────────────────────────────────────────┐    │
│  │  PositionProvider (interface)                     │    │
│  │  ├── TraccarProvider (fase 1)                     │    │
│  │  └── DirectGPSProvider (fase 2)                   │    │
│  ├──────────────────────────────────────────────────┤    │
│  │  RouteService | ShipmentService | AuthService     │    │
│  │  NotificationService | AuditService | ...         │    │
│  └──────────────────────────────────────────────────┘    │
│                          │                                │
│              ┌───────────┴───────────┐                   │
│              │                       │                    │
│         ┌────┴────┐           ┌──────┴──┐                │
│         │ PostgreSQL│         │  Redis   │                │
│         │   16     │          │    7     │                │
│         └─────────┘           └─────────┘                │
└──────────────────────────────────────────────────────────┘
                      │
          ┌───────────┴──────────┐
          │  Traccar (fase 1)    │
          │  Puerto 8082 API     │
          │  Puerto 5055 GPS     │
          └──────────────────────┘
```

---

## 7. Mapeo de funcionalidades: Symfony → Go + Next.js

### Entidades / Modelos

| Symfony (Doctrine Entity) | Go (sqlc) |
|--------------------------|-----------|
| `#[ORM\Entity]` + attributes | SQL schema + sqlc generated structs |
| Doctrine Migrations | golang-migrate SQL files |
| `PublicIdTrait` (ULID) | Campo `public_id` ULID en schema, helper function |
| `SoftDeleteTrait` | `deleted_at` en schema, `WHERE deleted_at IS NULL` en queries |
| `CustomerTenantFilter` (Doctrine SQL filter) | Middleware Go que inyecta `customer_id` en context, queries filtran |
| Repositories | sqlc generated query functions |

### Controllers → HTTP Handlers

| Symfony | Go |
|---------|-----|
| `#[Route('/api/driver/routes')]` | `r.Get("/api/driver/routes", handler)` |
| `$this->json(...)` | `json.NewEncoder(w).Encode(...)` |
| ParamConverter (`{publicId}`) | Chi URL params: `chi.URLParam(r, "publicId")` |
| `#[IsGranted('ROLE_ADMIN')]` | Middleware: `RequireRole("admin")` |
| Form validation | `validator.Struct(input)` |

### Servicios

| Symfony Service | Go equivalent |
|----------------|---------------|
| `TraccarApiClient` | `TraccarProvider` (implementa `PositionProvider`) |
| `TraccarIngestionService` | Goroutine con `PositionProvider.StreamPositions()` |
| `MercureJwtFactory` + Mercure | `WebSocketHub` — broadcast directo |
| `NotificationService` | `NotificationService` + broadcast via WebSocket hub |
| `RouteOptimizationService` | Mismo algoritmo nearest-neighbor en Go |
| `EtaService` | Mismo cálculo haversine en Go |
| `AuditLogger` | Middleware + `AuditService` |
| `ShipmentCsvImporter` | `CSVImporter` con goroutine para async |

### Frontend

| Symfony/Twig | Next.js/React |
|-------------|---------------|
| `base.html.twig` (sidebar layout) | `app/layout.tsx` + sidebar component |
| `tracking/map.html.twig` (Leaflet + Alpine) | `FleetMap` component con `react-leaflet` |
| Turbo Frames | React components con TanStack Query |
| Alpine.js interactividad | React state + hooks |
| Twig forms | React Hook Form + Zod |
| EventSource (Mercure SSE) | Native WebSocket |
| Tailwind CDN | Tailwind via PostCSS (build-time) |

---

## 8. Roadmap de migración

### Fase 0: Setup y fundación (2-3 semanas)

- [ ] Inicializar proyecto Go con estructura de carpetas
- [ ] Configurar sqlc + golang-migrate con el schema PostgreSQL existente
- [ ] Setup CI/CD (GitHub Actions: lint, test, build)
- [ ] Implementar auth (JWT login, middleware de roles)
- [ ] Implementar multi-tenancy (middleware customer_id en context)
- [ ] ULID helpers para public_id
- [ ] Inicializar proyecto Next.js con Tailwind + shadcn/ui
- [ ] Layout base con sidebar y navegación

### Fase 1: APIs core + Fleet Map (3-4 semanas)

- [ ] API REST de vehículos (CRUD + posiciones)
- [ ] `TraccarProvider` — adapter para Traccar API
- [ ] Goroutine de ingesta GPS (reemplaza `traccar:stream`)
- [ ] WebSocket Hub para broadcast de posiciones
- [ ] Fleet Map en React con react-leaflet + WebSocket
- [ ] API REST de rutas (CRUD + stops)
- [ ] API REST de shipments (CRUD + eventos)

### Fase 2: Driver API + Delivery flow (2-3 semanas)

- [ ] Driver API REST (start/finish route, deliver/exception stop)
- [ ] Proof of Delivery (POD) con idempotencia (DriverAction)
- [ ] Notificaciones en WebSocket (reemplaza Mercure SSE)
- [ ] Public tracking page (Next.js SSR)
- [ ] CSV import de shipments

### Fase 3: Admin + Customer portals (2-3 semanas)

- [ ] Admin dashboard con métricas
- [ ] CRUD completo: customers, users, vehicles, drivers
- [ ] Customer portal: rutas, shipments, reportes
- [ ] Reportes con export CSV
- [ ] Search global

### Fase 4: Producción + limpieza (1-2 semanas)

- [ ] Deploy en Railway (Go binary + Next.js)
- [ ] Migración de datos desde DB actual
- [ ] Audit logging
- [ ] Security headers middleware
- [ ] Rate limiting
- [ ] Monitoring y health checks
- [ ] Documentación API (OpenAPI/Swagger)

### Fase 5: GPS directo (futuro)

- [ ] `DirectGPSProvider` — recibir OsmAnd protocol directamente
- [ ] Eliminar dependencia de Traccar
- [ ] App móvil driver con GPS nativo

---

## 9. Estructura de proyecto Go propuesta

```
mxo-track-go/
├── cmd/
│   └── server/
│       └── main.go              # Entrypoint
├── internal/
│   ├── config/                  # Environment config
│   ├── server/                  # HTTP server setup, routes
│   ├── middleware/               # Auth, tenant, logging, CORS
│   ├── handler/                 # HTTP handlers (controllers)
│   │   ├── vehicle.go
│   │   ├── route.go
│   │   ├── shipment.go
│   │   ├── driver.go
│   │   ├── auth.go
│   │   └── ...
│   ├── service/                 # Business logic
│   │   ├── route.go
│   │   ├── shipment.go
│   │   ├── notification.go
│   │   └── ...
│   ├── tracking/                # GPS abstraction
│   │   ├── provider.go          # PositionProvider interface
│   │   ├── traccar.go           # TraccarProvider
│   │   ├── direct.go            # DirectGPSProvider (futuro)
│   │   └── hub.go               # WebSocket hub + broadcast
│   ├── model/                   # Domain structs
│   ├── tenant/                  # Multi-tenancy logic
│   └── audit/                   # Audit logging
├── db/
│   ├── migrations/              # SQL migration files
│   ├── queries/                 # SQL queries para sqlc
│   └── sqlc.yaml                # sqlc config
├── web/                         # Next.js frontend (monorepo)
│   ├── app/
│   ├── components/
│   └── ...
├── docker-compose.yml
├── Dockerfile
├── Makefile
└── go.mod
```

---

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|-----------|
| Curva de aprendizaje de Go | Go es intencionalmente simple. Un dev PHP productivo en Go en 2-3 semanas. |
| Más boilerplate para CRUD que Symfony | sqlc genera 80% del código de DB. Handlers son directos sin magia. |
| Sin ORM "mágico" como Doctrine | sqlc es explícito — más código pero cero sorpresas. Las queries son SQL puro. |
| Perder Doctrine migrations | golang-migrate usa SQL puro — mismo resultado, más control. |
| Multi-tenancy manual | Middleware simple (~30 líneas) que inyecta customer_id. Más transparente que Doctrine SQL Filter. |
| Dos repos/lenguajes | Monorepo con Go + Next.js. Makefile unifica comandos. |

---

## 11. Conclusión

La migración a **Go + React/Next.js** elimina las fricciones fundamentales del stack PHP para un negocio de tracking logístico:

- **Real-time nativo** sin servicios externos (adiós Mercure)
- **Concurrencia real** para ingesta GPS y WebSockets (adiós procesos CLI bloqueantes)
- **Deploy simple** con binario único (adiós PHP extensions + Composer + FPM)
- **Frontend moderno** desacoplado con el mejor ecosystem para mapas
- **Abstracción de tracking** preparada para evolucionar más allá de Traccar

El roadmap de ~10-14 semanas permite una migración incremental manteniendo el sistema actual en producción hasta que el nuevo esté listo.

---

## 12. Interacción con el Plan de IA (`PLAN_AI_INTEGRATION.md`)

> Actualizado 2026-03-06 tras análisis socrático.

El plan de IA se ha diseñado para ser **compatible con ambos stacks** (ver decisión D7 en PLAN_AI_INTEGRATION.md):

| Componente IA | PHP/Symfony | Go |
|---------------|------------|-----|
| Tablas ML, feature store | PostgreSQL (idéntico) | PostgreSQL (idéntico) |
| Python sidecar | HTTP client → FastAPI | HTTP client → FastAPI |
| Claude/OpenAI API | HTTP JSON simple | HTTP JSON simple |
| Messenger async | Doctrine transport | goroutines + river/pgq |
| pgvector | Doctrine type | sqlc + raw SQL |

**Recomendación**: Implementar Tracks A y B del plan IA en PHP actual (valor inmediato, semanas 1-7). Track C (ML real) puede iniciarse en Go si la migración ya comenzó para entonces. El sidecar Python es stack-agnostic.
