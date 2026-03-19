# Spec: Eliminación Completa de Deuda Técnica

**Fecha:** 2026-03-19
**Approach:** C — Por impacto de negocio (pragmático)
**Alcance:** Todo el backlog arquitectónico + violaciones SOLID + migración DDD + Event Sourcing puro para Route Planning

---

## Problema

El codebase tiene ~25 items de deuda técnica documentados en CLAUDE.md y descubiertos via auditoría:

| Tipo | Count | Severidad |
|------|-------|-----------|
| DDD migration (entidades críticas en src/Entity/) | 7 entidades | High |
| Repository interfaces faltantes | 7 interfaces | High |
| DIP violations (servicios con EntityManager/repos concretos) | ~10 servicios | High |
| Event sourcing incompleto (state-first, 6/15 events sin dispatch) | Route context | High |
| SOLID: SRP — User.php (5 responsabilidades) | 1 | Medium |
| SOLID: ISP/LSP — GpsDeviceProviderInterface | 1 interface | Medium |
| SOLID: DIP — DeliveryService | 1 servicio | High |
| Mercure listeners sin abstracción | ~6 listeners | Medium |
| Seguridad: credenciales sin encriptar | 1 entidad | Critical |
| Business features pendientes | 3 decisiones | Business |
| TODO en código | 1 (PDF export) | Low |

**Impacto actual:** Los servicios no son testeables sin base de datos, las entidades críticas mezclan dominio con infraestructura, el estado de las rutas no es reconstruible desde eventos, y hay una violación de seguridad bloqueante para producción.

---

## Decisiones de Diseño

### 1. Event Sourcing puro para Route Planning

El contexto de Route Planning migrará a event sourcing puro. RouteEvent[] pasa de ser un log after-the-fact a ser la **fuente de verdad** del estado.

**Estado actual (auditoría):**
- RouteEventType tiene 15 tipos definidos, pero solo 9 se dispatchen realmente
- Patrón actual: state-first, events-second (`$route->start()` → luego `dispatch(RouteStarted)`)
- 6 event types sin dispatch: ASSIGNED, CANCELLED, REOPTIMIZED, STOPS_REORDERED, STOP_SKIPPED, NOTE_ADDED
- State mutations directas sin evento: name, originLocation, autoReoptimize, aiAnalysis, capacity metrics, stop address/notes

**Estado objetivo:**
- Events-first: dispatch evento → listener aplica cambio de estado
- Route Aggregate reconstruye estado desde stream de eventos
- Projections/Read Models para queries (reemplazan queries directas al ORM)
- Capacidad de "rebobinar" estado a cualquier punto en el tiempo

**Enfoque de implementación:** Evolucionar la infraestructura existente (no adoptar library externa). RouteEvent ya tiene la estructura correcta — solo falta invertir el flujo y crear projections.

**Nuevos eventos necesarios** (para cubrir todos los state mutations):
- `ROUTE_RENAMED` — captura cambio de name
- `ORIGIN_CHANGED` — captura cambio de originLocation
- `CONFIG_CHANGED` — captura cambio de autoReoptimize y otros flags
- `METRICS_CALCULATED` — captura totalDistanceKm, totalVolumeM3, etc.
- `AI_ANALYSIS_COMPLETED` — captura aiAnalysis updates

**Projections necesarias:**
| Projection | Propósito | Derivada de |
|-----------|----------|-------------|
| RouteCurrentState | Status, driver, vehicle, métricas actuales | CREATED, ASSIGNED, STARTED, COMPLETED, CANCELLED, OPTIMIZED, METRICS_CALCULATED |
| StopCurrentStatus | Status de cada parada (PENDING/DELIVERED/EXCEPTION/SKIPPED) | STOP_DELIVERED, STOP_EXCEPTION, STOP_SKIPPED |
| RouteTimeline | Audit trail completo | Todos los eventos (query directa, sin materialized view) |

### 2. Entidades DDD: POPOs + Doctrine XML mapping

Las entidades de contextos críticos migrarán a POPOs en `src/Domain/{Context}/Model/` con mapping externo en `config/doctrine/`.

**Formato de mapping:** XML (no PHP attributes en clase separada) porque:
- Separación total entre dominio y persistencia
- Symfony/Doctrine soporta XML mapping nativo
- No requiere cargar clases de mapping en runtime

### 3. Repository interfaces: métodos basados en uso real

Cada interface solo expondrá los métodos que los servicios actuales necesitan. No "por si acaso".

### 4. GpsDeviceProviderInterface: split en 2 interfaces

- `GpsPositionProviderInterface`: `getPositions()`, `isAvailable()` — ambos providers lo necesitan
- `GpsDeviceManagerInterface`: `login()`, `getSessionCookie()`, `getDevices()`, `createDevice()` — solo pull-based (Traccar)

### 5. User.php: no migrar a DDD, solo documentar

User.php está en contexto pragmático (Identity/Auth). La SRP violation es real pero el costo de refactorizarla (rompe Symfony Security integration) supera el beneficio. Se documenta como decisión consciente.

### 6. Credential encryption: sodium

`CustomerIntegration.credentials` se encriptará con `sodium_crypto_secretbox` usando `APP_SECRET` como key derivation base.

### 7. Mercure: verificar que RealtimePublisherInterface ya se usa

El codebase ya tiene `RealtimePublisherInterface`. Verificar qué listeners la usan y cuáles van directamente a HubInterface.

### 8. Shipment Event Sourcing: evaluación diferida

ShipmentEvent tiene estructura similar a RouteEvent. La decisión de implementar event sourcing para Shipment/Delivery se tomará al llegar a Fase 5, con la experiencia adquirida en Route Planning.

---

## Fases

### Fase 1: Foundation — Repository Interfaces + DIP Fixes
**Objetivo:** Desbloquear testabilidad sin cambiar entidades
- Extraer repository interfaces para Route Planning context
- Crear implementaciones Doctrine
- Migrar servicios críticos (RouteOptimizationService, RouteBuilder, DeliveryService) a interfaces
- TDD: test unitario por cada servicio migrado

### Fase 2: Event Sourcing — Route Planning
**Objetivo:** RouteEvent como fuente de verdad del estado de rutas

**Sub-fases:**

**2A: Event Dispatch Completeness** (LOW risk)
- Dispatch los 6 event types faltantes (ASSIGNED, CANCELLED, REOPTIMIZED, STOPS_REORDERED, STOP_SKIPPED, NOTE_ADDED)
- Crear nuevos event types para state mutations no cubiertas

**2B: Event-First State Transitions** (MEDIUM risk)
- Invertir el patrón: eventos primero, state mutations en listeners
- Refactorizar RouteLifecycleService, DeliveryService, RouteOptimizationService
- Route Aggregate con método `apply(RouteEvent)` que aplica el cambio

**2C: Projections + Read Models** (HIGH risk)
- Crear RouteCurrentState projection (status, driver, vehicle, métricas)
- Crear StopCurrentStatus projection
- Refactorizar queries para usar projections en vez de ORM directo

**2D: Route Entity POPO Migration** (MEDIUM risk)
- Route, RouteStop, RouteEvent → POPOs en src/Domain/Route/Model/
- Doctrine XML mapping
- Route Aggregate con `rebuildFromEventStream()` para time-travel

### Fase 3: Security — Credential Encryption
**Objetivo:** Producción-ready para clientes con API keys
- Encriptar CustomerIntegration.credentials
- Migration para encriptar datos existentes
- Tests de encrypt/decrypt

### Fase 4: SOLID — GpsProvider ISP/LSP
**Objetivo:** Contratos limpios para providers GPS
- Split GpsDeviceProviderInterface
- Actualizar TraccarProvider y WebhookGpsProvider
- Actualizar factories y proxies

### Fase 5: Delivery DDD — Shipment/Delivery Entities
**Objetivo:** Segundo contexto crítico en DDD puro
- Repository interfaces para Shipment context
- Shipment, Parcel, Pod, ShipmentEvent → POPOs
- Doctrine XML mapping
- Migrar DeliveryService y servicios dependientes
- **Decisión:** ¿Event sourcing para ShipmentEvent? Evaluar con experiencia de Fase 2

### Fase 6: Infrastructure — Mercure + Provider Cleanup
**Objetivo:** Consistencia en abstracciones
- Audit de Mercure listeners → migrar a RealtimePublisherInterface
- Provider framework: evaluar boilerplate actual

### Fase 7: Business Features — Decisiones Pendientes
**Objetivo:** Completar gaps de negocio
- Requiere brainstorming separado para cada decisión (strategy selection, re-optimization policy, historical data)
- Cada una es un proyecto en sí mismo — esta fase es un placeholder

---

## Dependencias entre fases

```
Fase 1 (Foundation) ──→ Fase 2 (Event Sourcing + Route DDD) ──→ Fase 5 (Delivery DDD)
                                                                        │
Fase 3 (Security)     [independiente]                                   │
                                                                        │
Fase 4 (SOLID GPS)    [independiente]                                   │
                                                                        │
Fase 6 (Mercure)      [independiente]                                   ▼
                                                               Fase 7 (Business)
```

Dentro de Fase 2: 2A → 2B → 2C → 2D (estrictamente secuencial)
Fases 3, 4 y 6 son independientes entre sí y de las demás.

---

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Romper tests existentes al mover entidades | TDD: tests antes del cambio, verificar green después |
| Doctrine mapping XML incorrecto | Migration + fixture load como smoke test |
| Imports rotos al mover clases | `grep -r "use App\\Entity\\Route"` antes/después |
| Constructor changes sin actualizar factories | Checklist mandatory de CLAUDE.md |
| Projections out-of-sync con event store | Tests de consistencia: rebuild vs projection comparison |
| Event ordering issues en transitions concurrentes | Optimistic locking en Route aggregate (version field) |
| Performance de event replay en rutas con muchos eventos | Periodic snapshots (RouteSnapshot ya existe, evolucionar) |

---

## Exclusiones

- **User.php SRP**: Decisión consciente de no refactorizar (contexto pragmático)
- **SlaReportController PDF**: Baja prioridad, no bloquea arquitectura
- **Provider framework codegen**: Trigger no alcanzado (<6 servicios)
