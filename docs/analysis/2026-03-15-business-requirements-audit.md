# Auditoría de Requisitos de Negocio y Visión de Dominio

**Fecha:** 2026-03-15
**Software:** mxo-track (transporte-tracking) — Symfony 7.4 LTS
**Alcance:** Estado actual vs requisitos + visión de dominio validada + decisiones pendientes

---

## Punto Central del Negocio

El valor del software no está en el CRUD (cualquier sistema gestiona vehículos, clientes, envíos) sino en **convertir un conjunto desordenado de entregas en la secuencia óptima de paradas**, ejecutarla con visibilidad total, y aprender de los resultados.

**El negocio vende kilómetros y tiempo ahorrados.**

Todo lo demás — gestión de flota, multi-tenancy, portales, tracking — es infraestructura al servicio de ese punto central.

---

## Resumen de Requisitos

| # | Requisito | Estado |
|---|-----------|--------|
| 1 | Gestión de transporte y rutas optimizadas con datos y mapas | **CUMPLE** |
| 2 | Gestión de vehículos, clientes, transportistas, rutas, envíos | **CUMPLE** |
| 3 | Crear rutas con envíos asignados a vehículo y transportista | **CUMPLE** |
| 4 | Optimización de la ruta | **CUMPLE** |
| 5 | Ruta incluye envíos programados + historial de eventos | **CUMPLE** |
| 6 | Ruta planificada inmutable + eventos que impactan sin modificarla | **PARCIAL** |
| 7 | Frontales diferenciados (admin, cliente, transportista) + realtime | **CUMPLE** |

---

## Detalle por Requisito

### 1. Gestión de transporte y rutas optimizadas con datos y mapas

**Estado: CUMPLE**

#### Evidencia

| Componente | Archivo | Descripción |
|-----------|---------|-------------|
| Optimizador VROOM (VRP solver) | `src/RouteOptimization/VroomRouteOptimizer.php` | Resuelve Vehicle Routing Problem con constraints |
| API Client VROOM | `src/Service/VroomApiClient.php` | Comunicación HTTP con servidor VROOM |
| Routing OSRM | `src/Routing/OsrmRoutingEngine.php` | Distancias reales por carretera (mapa Madrid) |
| Client OSRM | `src/Service/OsrmClient.php` | HTTP client para OSRM |
| Google Directions | `src/Provider/Routing/GoogleDirectionsEngine.php` | Alternativa a OSRM via Google Maps API |
| Greedy fallback | `src/Provider/RouteOptimizer/GreedyOptimizer.php` | Nearest-neighbor PHP puro (haversine), sin infra externa |
| Capacidad 3D | `src/Service/RouteCapacityValidator.php` | Valida peso (kg), volumen (m³) y número de bultos |
| Time windows | `RouteStop.deliveryWindowStart/End` | Ventanas de entrega en cada parada |
| Skills de vehículo | `App\Enum\VehicleSkill` | REFRIGERATED, HEAVY_LOAD, PEDESTRIAN_ACCESS, HAZMAT, FRAGILE → mapeados a VROOM |
| Prioridades | `App\Enum\ShipmentPriority` | LOW(0), NORMAL(25), HIGH(50), URGENT(75), CRITICAL(100) → mapeados a VROOM |
| Route builder | `src/Service/RouteBuilder.php` | Orquesta creación de rutas con optimización integrada |

#### Gaps

- **GAP-1.1:** No hay UI interactiva para que el admin lance la optimización (actualmente es via servicio/API)
- **GAP-1.2:** No hay preview visual de la ruta optimizada en mapa antes de confirmarla

---

### 2. Gestión de vehículos, clientes, transportistas, rutas, envíos

**Estado: CUMPLE**

#### Evidencia

| Entidad | Archivo | CRUD Admin | Multi-tenant |
|---------|---------|-----------|-------------|
| Customer | `src/Entity/Customer.php` | Si | Root entity |
| Vehicle | `src/Entity/Vehicle.php` | Si | Global (bridge via CustomerVehicle) |
| CustomerVehicle | `src/Entity/CustomerVehicle.php` | Si | Scoped |
| User (Driver/Customer) | `src/Entity/User.php` | Si | Scoped por customer (nullable) |
| Route | `src/Entity/Route.php` | Si | Scoped por customer |
| Shipment | `src/Entity/Shipment.php` | Si | Scoped por customer |
| Parcel | `src/Entity/Parcel.php` | Si (via Shipment) | Indirecto |
| CustomerLocation (depots) | `src/Entity/CustomerLocation.php` | Si | Scoped |
| Pod (prueba de entrega) | `src/Entity/Pod.php` | Via RouteStop | Indirecto |
| DriverAvailability | `src/Entity/DriverAvailability.php` | Si | Via User |
| VehicleInspection | `src/Entity/VehicleInspection.php` | Via Route | Indirecto |
| DeliveryZone | `src/Entity/DeliveryZone.php` | Si | Scoped |
| AddressRisk | `src/Entity/AddressRisk.php` | Analytics | Global |

**Multi-tenancy:** `CustomerTenantFilter` (Doctrine SQL filter) + `CustomerScopedEntityInterface`. Admin/Operator bypass, ROLE_CUSTOMER y ROLE_DRIVER scoped.

**Roles:**
```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

---

### 3. Crear rutas con envíos asignados a vehículo y transportista

**Estado: CUMPLE**

#### Evidencia

- `RouteBuilder.buildRoutes()` (`src/Service/RouteBuilder.php`):
  - Acepta `Shipment[]` + `Vehicle` + `CustomerLocation` (origen)
  - Crea `Route` con `RouteStop[]` (uno por shipment + parada origen)
  - Asigna `Vehicle` y `Driver` a la ruta
  - `RouteStop.shipment` (ManyToOne) vincula cada parada con su envío
- Multi-vehículo: VROOM puede generar N rutas a partir de una lista grande de shipments
- Cada `RouteStop` tiene: sequence, address, lat/lng, recipientName, recipientPhone, deliveryWindowStart/End, notes

#### Gaps

- **GAP-3.1:** No hay flujo UI completo de "seleccionar shipments → preview → configurar → confirmar ruta"

---

### 4. Optimización de la ruta

**Estado: CUMPLE**

#### Evidencia

| Fase | Servicio | Descripción |
|------|---------|-------------|
| Build-time | `RouteBuilder` | Optimiza al crear via VROOM (o fallback greedy) |
| Re-optimización | `RouteOptimizationService` (`src/Service/RouteOptimizationService.php`) | Re-optimiza rutas existentes (solo paradas PENDING) |
| Métricas | `RouteSnapshot` (`src/Entity/RouteSnapshot.php`) | `distanceBeforeKm`, `distanceAfterKm`, `savingsPercent`, tiempos |
| Audit trail | `RouteOptimizationLog` | Request/response VROOM completo por cada operación |
| Comparación | `RouteComparisonService` (`src/Service/RouteComparisonService.php`) | Compara ruta planificada vs ruta real ejecutada |
| Snapshot manager | `RouteSnapshotManager` (`src/Service/RouteSnapshotManager.php`) | Gestiona creación y actualización de snapshots |
| Post-análisis | `PostRouteAnalyzer` (`src/Service/PostRouteAnalyzer.php`) | Análisis posterior con IA de la ruta completada |

**Constraints soportados por el optimizador:**
- Capacidad 3D (peso, volumen, bultos)
- Ventanas de entrega (time windows)
- Skills de vehículo (refrigerado, carga pesada, etc.)
- Prioridades de envío (0-100, mapeadas a VROOM)
- Tiempo de servicio por parada (`Shipment.serviceTimeSeconds`)

---

### 5. La ruta incluye envíos programados + historial de eventos

**Estado: CUMPLE**

#### Evidencia — Envíos programados

- `RouteStop[]` (OneToMany desde Route):
  - `sequence` (int): orden de visita
  - `status`: PENDING → DELIVERED / EXCEPTION / SKIPPED
  - `shipment` (ManyToOne): vinculo con el envío
  - `isOrigin` (bool): marca la parada de origen/depot
  - `deliveryWindowStart/End`: ventanas de entrega
  - `recipientName`, `recipientPhone`, `address`, `lat/lng`

#### Evidencia — Historial de eventos

- `RouteEvent` (`src/Entity/RouteEvent.php`) — **append-only, inmutable** (sin setters, sin updates):
  - **15 tipos de evento** (`RouteEventType` enum):
    - Lifecycle: `CREATED`, `OPTIMIZED`, `ASSIGNED`, `STARTED`, `COMPLETED`, `CANCELLED`
    - Paradas: `STOP_DELIVERED`, `STOP_EXCEPTION`, `STOP_SKIPPED`
    - Optimización: `REOPTIMIZED`, `STOPS_REORDERED`
    - Desviaciones: `DEVIATION_DETECTED`, `DEVIATION_ENDED`, `ETA_CHANGED`
    - Externo: `NOTE_ADDED`
  - Campos: `actorType`, `actorUser` (ManyToOne User), `payload` (JSON), `snapshotMetrics` (JSON), `occurredAt`
  - Indices: `(route_id, occurred_at)`, `(event_type, occurred_at)`

- `ShipmentEvent` (`src/Entity/ShipmentEvent.php`) — lifecycle paralelo del envío:
  - Tipos: `CREATED`, `PICKED_UP`, `IN_HUB`, `IN_TRANSIT`, `OUT_FOR_DELIVERY`, `DELIVERED`, `EXCEPTION`, `RESCHEDULE_REQUESTED`

---

### 6. Ruta planificada inmutable + eventos que impactan sin modificarla

**Estado: PARCIAL — modelo híbrido pragmático**

#### Lo que SÍ es inmutable

| Dato | Dónde | Inmutable? |
|------|-------|-----------|
| Orden original de paradas | `RouteSnapshot.originalStopOrder` (JSON) | **Sí** — se escribe una vez, nunca se modifica |
| Polyline original | `RouteSnapshot.originalPolyline` (encoded) | **Sí** — se escribe una vez |
| Métricas de optimización | `RouteSnapshot.distanceBeforeKm/AfterKm/savingsPercent` | **Sí** — se escriben al optimizar |
| Validación de capacidad | `RouteSnapshot.capacityValidation` (JSON) | **Sí** — se escribe al crear |
| Historial de eventos | `RouteEvent[]` | **Sí** — append-only, sin setters |

#### Lo que SÍ muta (estado operativo actual)

| Dato | Dónde | Muta? |
|------|-------|-------|
| Status de ruta | `Route.status` (PLANNED → ACTIVE → DONE) | **Sí** |
| Status de parada | `RouteStop.status` (PENDING → DELIVERED/EXCEPTION/SKIPPED) | **Sí** |
| Secuencia de parada | `RouteStop.sequence` | **Sí** (en re-optimización) |
| Datos de entrega | `RouteStop.deliveredAt`, `exceptionCode`, `exceptionNotes` | **Sí** |
| ETAs actuales | `RouteSnapshot.etas` (JSON) | **Sí** (se recalculan) |
| Estados actuales de paradas | `RouteSnapshot.stopStates` (JSON) | **Sí** (se actualizan) |
| Polyline actual | `RouteSnapshot.actualPolyline` | **Sí** (se actualiza con GPS real) |

#### Análisis

El sistema usa un **modelo híbrido** que no es event sourcing puro:

1. **Capa inmutable:** `RouteSnapshot.originalStopOrder` + `originalPolyline` + métricas = foto de la planificación original
2. **Capa mutable:** `Route.status` + `RouteStop.status/sequence` = estado operativo actual necesario para operar
3. **Log inmutable:** `RouteEvent[]` = registro completo y auditable de todo cambio

La "inmutabilidad de la ruta planificada" se logra preservando el plan original en el snapshot, mientras que las entidades operativas reflejan la realidad actual. Los eventos son el puente: registran qué cambió, cuándo y por quién, sin modificar el plan original.

#### Gap conceptual

- **GAP-6.1:** No es event sourcing puro — el estado actual se muta directamente en las entidades en lugar de derivarse de la secuencia de eventos. Esto es una decisión de diseño pragmática (no un defecto), pero limita la capacidad de "rebobinar" el estado a cualquier punto en el tiempo.

---

### 7. Frontales diferenciados (admin, cliente, transportista) + realtime

**Estado: CUMPLE**

#### Evidencia — Frontales

| Frontend | Existe | Tecnología | Scope |
|----------|--------|-----------|-------|
| Admin portal | Sí | Twig + Turbo (UX Turbo) | Full access |
| Customer portal | Sí | Twig + Turbo | Scoped por tenant (CustomerTenantFilter) |
| Driver API | Sí | JSON API (mobile) | Solo rutas/paradas asignadas |
| Public tracking | Sí | Sin auth, via `Shipment.trackingToken` | Solo lectura del envío |

#### Evidencia — Realtime

| Componente | Archivo | Descripción |
|-----------|---------|-------------|
| Mercure Publisher | `src/Realtime/MercurePublisher.php` | Publica SSE a topics por entidad (Route, Vehicle, etc.) |
| HTTP Polling fallback | `RealtimeEvent` entity | Persiste eventos para clientes sin SSE |
| ETA reactivo | `src/EventListener/Domain/EtaRecalculationListener.php` | Recalcula ETAs cuando cambian posiciones o estados |
| Provider configurable | `CustomerIntegration` entity | Per-tenant: Mercure o HTTP Polling |

---

## Arquitectura del Dominio de Rutas

### Las 4 capas

```
┌─────────────────────────────────────────────┐
│          RUTA PLANIFICADA (inmutable)         │
│  RouteSnapshot                               │
│  ├── originalStopOrder (JSON)                │
│  ├── originalPolyline (encoded)              │
│  ├── distanceBeforeKm / distanceAfterKm      │
│  ├── savingsPercent                          │
│  └── capacityValidation (JSON)               │
└──────────────────┬──────────────────────────┘
                   │ 1:1
┌──────────────────▼──────────────────────────┐
│          RUTA OPERATIVA (mutable)            │
│  Route + RouteStop[]                         │
│  ├── Route.status: PLANNED → ACTIVE → DONE   │
│  ├── RouteStop.sequence + status             │
│  ├── Vehicle asignado                        │
│  └── Driver asignado                         │
└──────────────────┬──────────────────────────┘
                   │ 1:N
┌──────────────────▼──────────────────────────┐
│       HISTORIAL DE EVENTOS (append-only)     │
│  RouteEvent[]                                │
│  ├── CREATED, OPTIMIZED, STARTED             │
│  ├── STOP_DELIVERED, STOP_EXCEPTION          │
│  ├── DEVIATION_DETECTED, ETA_CHANGED         │
│  └── REOPTIMIZED, STOPS_REORDERED            │
└──────────────────┬──────────────────────────┘
                   │ impacta
┌──────────────────▼──────────────────────────┐
│       ESTADO EN TIEMPO REAL                  │
│  RouteSnapshot (campos mutables)             │
│  ├── stopStates (JSON)                       │
│  ├── etas (JSON)                             │
│  ├── actualPolyline (encoded)                │
│  └── Mercure SSE → frontales                 │
└─────────────────────────────────────────────┘
```

### Flujo end-to-end

1. **Optimizador crea ruta** → `RouteBuilder` usa VROOM con shipments, vehículos, ventanas, prioridades, skills
2. **Se captura snapshot inmutable** → `RouteSnapshot` con orden original, polyline, métricas de ahorro
3. **Ruta se activa** → Driver inicia ruta, GPS tracking comienza via Traccar
4. **Eventos ocurren** → `RouteEvent` registra entregas, excepciones, desviaciones (append-only)
5. **Estado se actualiza** → `RouteStop.status` cambia, ETAs se recalculan (`EtaRecalculationListener`)
6. **Frontales se actualizan** → Mercure publica cambios en realtime a admin, customer, driver, tracking público
7. **Post-análisis** → `PostRouteAnalyzer` + AI comparan planificado vs real

---

## Los Dos Ejes del Dominio

### Eje 1: Planificación con Múltiples Estrategias

La ruta no se optimiza con "un algoritmo". Se planifica con **estrategias** que compiten o se combinan según el contexto.

#### Estado actual

| Estrategia | Archivo | Tipo | Cuándo se usa |
|-----------|---------|------|--------------|
| VROOM (VRP solver) | `src/RouteOptimization/VroomRouteOptimizer.php` | Exacto / metaheurístico | Default — resuelve Vehicle Routing Problem con constraints |
| Greedy (nearest-neighbor) | `src/Provider/RouteOptimizer/GreedyOptimizer.php` | Heurístico simple | Fallback — sin infraestructura VROOM disponible |
| Google Directions | `src/Provider/Routing/GoogleDirectionsEngine.php` | Routing engine | Cálculo de distancias/tiempos reales |
| OSRM | `src/Routing/OsrmRoutingEngine.php` | Routing engine | Cálculo de distancias/tiempos reales (self-hosted) |

#### Constraints ya soportados

- Capacidad 3D: peso (kg), volumen (m³), bultos — `RouteCapacityValidator`
- Ventanas de entrega (time windows) — `RouteStop.deliveryWindowStart/End`
- Skills de vehículo — `VehicleSkill` enum (refrigerado, carga pesada, acceso peatonal, hazmat, frágil)
- Prioridades de envío — `ShipmentPriority` (0-100) mapeado a VROOM
- Tiempo de servicio por parada — `Shipment.serviceTimeSeconds`

#### Visión

No solo "un optimizador" sino un sistema donde:
- Múltiples estrategias pueden ejecutarse sobre los mismos datos
- Los resultados se pueden comparar antes de confirmar
- El sistema puede recomendar la mejor estrategia según el tipo de ruta
- Datos históricos (excepciones, tiempos reales, feedback de drivers) alimentan futuras planificaciones

---

### Eje 2: Reactividad a Eventos

La ruta no muere cuando se planifica. **Vive.** Los eventos impactan el estado en tiempo real sin destruir el plan original.

#### Estado actual

| Componente | Archivo | Función |
|-----------|---------|---------|
| RouteEvent (15 tipos) | `src/Entity/RouteEvent.php` | Log inmutable append-only de todo lo que ocurre |
| RouteSnapshot | `src/Entity/RouteSnapshot.php` | Preserva plan original + refleja estado actual |
| EtaRecalculationListener | `src/EventListener/Domain/EtaRecalculationListener.php` | Recalcula ETAs cuando cambian posiciones o estados |
| RouteOptimizationService | `src/Service/RouteOptimizationService.php` | Re-optimiza paradas PENDING en ruta activa |
| Mercure SSE | `src/Realtime/MercurePublisher.php` | Publica cambios a frontales en tiempo real |
| HTTP Polling fallback | `RealtimeEvent` entity | Para clientes sin soporte SSE |

#### Tipos de evento y su impacto

| Evento | Impacto |
|--------|---------|
| `STOP_DELIVERED` | Actualiza estado parada, recalcula ETAs restantes |
| `STOP_EXCEPTION` | Marca excepción, puede disparar re-optimización |
| `STOP_SKIPPED` | Salta parada, re-optimiza restantes |
| `DEVIATION_DETECTED` | Alerta, recalcula ETAs |
| `ETA_CHANGED` | Notifica frontales |
| `REOPTIMIZED` | Nueva secuencia de paradas PENDING |
| `STOPS_REORDERED` | Cambio manual de orden |

#### Visión

Los eventos no solo registran lo que pasó — **disparan reacciones**:
- Un evento puede provocar re-planificación usando una estrategia diferente a la original
- El histórico de eventos alimenta el aprendizaje del sistema
- Los frontales reaccionan en tiempo real a cada cambio

---

### Cruce de los Dos Ejes

Los ejes se cruzan: **un evento puede disparar una re-planificación con una estrategia diferente a la original.**

```
Eje 1 (Planificación)          Eje 2 (Reactividad)
         │                              │
         │    ┌──────────────────┐      │
         ├───→│  RUTA PLANIFICADA │←────┤  Evento dispara
         │    │  (snapshot)       │      │  re-planificación
         │    └────────┬─────────┘      │
         │             │                │
         │    ┌────────▼─────────┐      │
         │    │  RUTA OPERATIVA  │      │
         │    │  (estado actual)  │←────┤  Eventos actualizan
         │    └────────┬─────────┘      │  estado
         │             │                │
         │    ┌────────▼─────────┐      │
         └───→│  DATOS HISTÓRICOS │←────┘  Eventos alimentan
              │  (aprendizaje)    │         futuras planificaciones
              └──────────────────┘
```

---

## Consolidación de Gaps

| ID | Requisito | Gap | Descripción |
|----|-----------|-----|-------------|
| GAP-1.1 | 1 | UI de optimización | No hay UI interactiva para que el admin lance la optimización |
| GAP-1.2 | 1 | Preview visual | No hay preview de la ruta optimizada en mapa antes de confirmar |
| GAP-3.1 | 3 | Flujo de creación | No hay flujo UI "seleccionar shipments → preview → configurar → confirmar ruta" |
| GAP-6.1 | 6 | Event sourcing puro | Estado actual se muta directamente (pragmático, no defecto — limita "rebobinar" en el tiempo) |

**Nota:** GAP-1.1, GAP-1.2 y GAP-3.1 son esencialmente facetas del mismo gap: falta un flujo UI completo para la creación y configuración interactiva de rutas. El backend ya soporta toda la funcionalidad.

**Nota sobre GAP-6.1:** No es necesariamente un gap que requiera solución. El modelo híbrido actual es pragmático, eficiente y cubre el caso de uso real. Event sourcing puro añadiría complejidad significativa sin beneficio claro a menos que se necesite literalmente "rebobinar" el estado de la ruta a un punto arbitrario del pasado.

---

## Decisiones Pendientes (para futuro brainstorming)

### Decisión 1: ¿Cómo se selecciona la estrategia de optimización?

**Contexto:** Actualmente la estrategia se selecciona por provider configuration (`CustomerIntegration`). El admin no tiene visibilidad de "qué estrategia se usó" ni puede comparar.

**Opciones a evaluar (cuando se diseñe):**
- A) El admin elige manualmente la estrategia
- B) El sistema recomienda basándose en el tipo de ruta
- C) Se ejecutan varias en paralelo y se presentan para comparar
- D) Combinación: recomendación + override manual

**Trigger:** Cuando se diseñe el flujo UI de creación de rutas (GAP-3.1).

---

### Decisión 2: ¿Qué eventos disparan re-optimización automática vs manual?

**Contexto:** `RouteOptimizationService` puede re-optimizar paradas PENDING, pero actualmente no hay política definida de cuándo hacerlo automáticamente.

**Opciones a evaluar:**
- A) Siempre manual (admin o driver solicita)
- B) Automática por defecto, con override para desactivar (`Route.autoReoptimize` ya existe)
- C) Reglas configurables por customer (ej: "re-optimizar si >2 excepciones consecutivas")
- D) Recomendación IA: el sistema sugiere re-optimizar pero no lo hace sin confirmación

**Trigger:** Cuando se defina la política de negocio de re-optimización.

---

### Decisión 3: ¿Qué datos históricos alimentan futuras planificaciones?

**Contexto:** Ya existen datos históricos que podrían mejorar la planificación:

| Dato | Entidad | Uso potencial |
|------|---------|--------------|
| Tasa de excepciones por dirección | `AddressRisk` | Ajustar prioridad/orden de paradas en zonas problemáticas |
| Feedback de drivers | `DriverFeedback` | Coordenadas corregidas, notas de acceso, tiempos reales |
| Tiempos reales vs estimados | `RouteSnapshot` + `RouteComparison` | Calibrar estimaciones del optimizador |
| Análisis post-ruta | `PostRouteAnalyzer` + `Route.aiAnalysis` | Patrones de desviación, causas de retraso |

**Opciones a evaluar:**
- A) Solo analytics/reporting (no impactan planificación)
- B) Alimentan automáticamente el optimizador (service times ajustados, risk scoring)
- C) Sistema de recomendaciones: sugiere ajustes pero no los aplica automáticamente

**Trigger:** Cuando se diseñe el módulo de aprendizaje/mejora continua.

---

## Referencia

- Modelo de dominio: `docs/knowledge/domain-model.md`
- Optimización de rutas: `docs/knowledge/route-optimization.md`
- Framework de providers: `docs/knowledge/provider-framework.md`
