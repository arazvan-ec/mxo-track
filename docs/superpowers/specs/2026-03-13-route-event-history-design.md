# Fase 1: Route Event History

**Fecha:** 2026-03-13
**Estado:** Pendiente de aprobación
**Objetivo:** Registrar un histórico inmutable de todos los cambios que ocurren sobre una ruta, desde su creación hasta su finalización/cancelación.

---

## Problema

`RouteSnapshot` almacena **solo el estado actual** (se sobreescribe en cada cambio). No existe un histórico que responda:
- "¿Qué pasó con esta ruta y cuándo?"
- "¿Cuántas veces se re-optimizó?"
- "¿A qué hora se entregó cada parada?"
- "¿Quién hizo qué cambio?"

Sin este histórico no se puede alimentar analytics, detectar patrones, ni auditar operaciones.

## Diseño

### 1. Nueva entidad: `RouteEvent`

Tabla de append-only (nunca se actualiza ni elimina). Un registro por cada cambio relevante en la ruta.

```
route_event
├── id (BIGINT PK auto-increment)
├── route_id (FK Route, ON DELETE CASCADE, INDEX)
├── event_type (VARCHAR 40, enum RouteEventType)
├── actor_type (VARCHAR 20, enum: 'driver', 'admin', 'system')
├── actor_user_id (FK User, nullable, ON DELETE SET NULL)
├── payload (JSON) — datos contextuales del evento
├── snapshot_metrics (JSON, nullable) — métricas en el momento del evento
├── occurred_at (TIMESTAMP) — cuándo ocurrió el evento
├── created_at (TIMESTAMP) — cuándo se persistió
└── INDEX (route_id, occurred_at)
    INDEX (event_type, occurred_at)
```

**Patrón:** Sigue la misma convención que `ShipmentEvent` y `AuditLog`.
**No usa PublicIdTrait** — es un log interno, no se expone en APIs públicas directamente.

### 2. Nuevo enum: `RouteEventType`

```php
enum RouteEventType: string
{
    // Lifecycle
    case CREATED = 'CREATED';
    case OPTIMIZED = 'OPTIMIZED';
    case ASSIGNED = 'ASSIGNED';           // Vehicle/driver asignado
    case STARTED = 'STARTED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    // Stop changes
    case STOP_DELIVERED = 'STOP_DELIVERED';
    case STOP_EXCEPTION = 'STOP_EXCEPTION';
    case STOP_SKIPPED = 'STOP_SKIPPED';

    // Optimization
    case REOPTIMIZED = 'REOPTIMIZED';     // Auto o manual
    case STOPS_REORDERED = 'STOPS_REORDERED';

    // Deviations (preparado para Fase 3)
    case DEVIATION_DETECTED = 'DEVIATION_DETECTED';
    case ETA_CHANGED = 'ETA_CHANGED';

    // External
    case NOTE_ADDED = 'NOTE_ADDED';       // Admin añade nota
}
```

### 3. Nuevo listener: `RouteEventLogListener`

Un solo listener que escucha **todos** los domain events existentes y crea `RouteEvent` entries:

| Domain Event | → RouteEventType | Actor | Payload |
|---|---|---|---|
| `RoutesBuilt` | `CREATED` (×N) | `system` | `{shipment_count, vehicle_count}` |
| `RouteOptimized` | `OPTIMIZED` | `admin` | `{improvement_percent, distance_before_km, distance_after_km}` |
| `RouteStarted` | `STARTED` | `driver` | `{driver_user_id}` |
| `StopDelivered` | `STOP_DELIVERED` | `driver` | `{stop_public_id, shipment_public_id, pod_public_id}` |
| `StopExceptionReported` | `STOP_EXCEPTION` | `driver` | `{stop_public_id, exception_code, notes}` |
| `RouteCompleted` | `COMPLETED` | `driver` | `{driver_user_id}` |

**snapshot_metrics** captura el estado de la ruta en el momento del evento:
```json
{
  "total_stops": 12,
  "delivered": 5,
  "exceptions": 1,
  "pending": 6,
  "elapsed_minutes": 45
}
```

### 4. Nuevos domain events necesarios (solo los que faltan)

Se crean estos eventos para que `RouteEventLogListener` pueda capturarlos:

- **`RouteCancelled`**: `routePublicId`, `cancelledByUserId`, `reason`, `occurredAt`
- **`RouteAssigned`**: `routePublicId`, `vehiclePublicId`, `driverUserId`, `assignedByUserId`, `occurredAt`

**No se crean** `StopSkipped`, `RouteDeviation`, `EtaChanged` en esta fase — se añaden en fases posteriores.

### 5. Dispatch de eventos faltantes

| Evento | Dónde se dispatcha |
|---|---|
| `RouteCancelled` | `RouteAdminController::delete()` (ya cancela rutas pero sin evento) |
| `RouteAssigned` | `RouteAdminController::edit()` cuando cambia driver/vehicle |

### 6. API de consulta del histórico

**GET** `/api/routes/{publicId}/events` (ROLE_ADMIN, ROLE_OPERATOR)

Response:
```json
{
  "events": [
    {
      "type": "CREATED",
      "actor_type": "system",
      "payload": {"shipment_count": 12},
      "snapshot_metrics": {"total_stops": 12, "delivered": 0, "pending": 12},
      "occurred_at": "2026-03-13T08:00:00Z"
    },
    {
      "type": "OPTIMIZED",
      "actor_type": "admin",
      "actor_email": "admin@company.com",
      "payload": {"improvement_percent": 14.9},
      "occurred_at": "2026-03-13T08:01:30Z"
    }
  ]
}
```

### 7. Publicación Mercure del histórico

Topic: `/routes/{publicId}/events`

Cada vez que se crea un `RouteEvent`, se publica en este topic para que las UIs puedan mostrar un feed en vivo de cambios. El payload es el mismo JSON del evento individual.

Esto permite que la vista de detalle de ruta (admin) muestre un timeline reactivo de eventos.

### 8. Vista timeline en admin route show

Añadir una sección "Historial" debajo del mapa en `/admin/routes/{publicId}/show` que:
- Lista todos los `RouteEvent` ordenados por `occurred_at DESC`
- Se actualiza en tiempo real via Mercure (topic `/routes/{publicId}/events`)
- Muestra icono + texto + timestamp para cada evento
- Incluye métricas snapshot cuando están disponibles

---

## Archivos a crear/modificar

### Nuevos archivos
1. `src/Entity/RouteEvent.php` — Entidad
2. `src/Enum/RouteEventType.php` — Enum
3. `src/Domain/Event/RouteCancelled.php` — Domain event
4. `src/Domain/Event/RouteAssigned.php` — Domain event
5. `src/EventListener/Domain/RouteEventLogListener.php` — Listener principal
6. `src/Repository/RouteEventRepository.php` — Repository con métodos de consulta
7. `src/Controller/Api/RouteEventApiController.php` — API endpoint
8. `migrations/VersionXXX.php` — Migration para tabla `route_event`

### Archivos a modificar
1. `src/Controller/Admin/RouteAdminController.php` — Dispatch `RouteCancelled` en `delete()`, dispatch `RouteAssigned` en `edit()` cuando cambia driver/vehicle
2. `src/EventListener/Domain/RouteSnapshotListener.php` — NO se modifica, RouteEventLogListener es independiente
3. `templates/admin/route/show.html.twig` — Añadir sección timeline
4. `docs/knowledge/domain-model.md` — Añadir RouteEvent, RouteEventType, nuevos domain events

---

## Decisiones de diseño

### ¿Por qué una entidad separada y no reutilizar AuditLog?

`AuditLog` es genérico (cualquier entidad, cualquier acción). `RouteEvent` es específico de dominio:
- Tiene `snapshot_metrics` (métricas en el momento)
- Tiene `event_type` tipado como enum
- Permite queries eficientes por ruta + tipo
- Es la base para analytics de rutas

### ¿Por qué append-only?

Los eventos son hechos inmutables. Una entrega no se "des-entrega". Esto simplifica la lógica y garantiza auditabilidad.

### ¿Por qué snapshot_metrics en cada evento?

Permite reconstruir el estado de la ruta en cualquier punto del tiempo sin tener que recalcular. "¿Cuántas entregas tenía la ruta cuando se reportó la excepción #3?" → consulta directa.

### ¿Por qué un solo listener?

Un solo `RouteEventLogListener` que escucha todos los domain events evita duplicación de lógica de persistencia y garantiza que no se pierda ningún evento. Es la "caja negra" de la ruta.

---

## Orden de implementación (TDD)

1. Crear enum `RouteEventType`
2. Crear entidad `RouteEvent` + migration
3. Crear `RouteEventRepository` con `findByRoute()`
4. Crear domain events faltantes (`RouteCancelled`, `RouteAssigned`)
5. Crear `RouteEventLogListener` — test que cada domain event genera un RouteEvent
6. Dispatch `RouteCancelled` desde controller
7. Dispatch `RouteAssigned` desde controller
8. Crear API endpoint `/api/routes/{publicId}/events`
9. Añadir publicación Mercure del evento
10. Añadir timeline en admin route show template
11. Actualizar documentación

---

## Fuera de alcance (fases posteriores)

- `DEVIATION_DETECTED` events (Fase 3: Detección de desvío)
- `ETA_CHANGED` events (Fase 2: ETAs reactivas)
- `STOP_SKIPPED` events (Fase 4: Eventos/notificaciones faltantes)
- Timeline en customer/driver views
- Analytics/aggregaciones sobre RouteEvent
