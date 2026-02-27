# 14 — Agentic Native Delivery System (Enum + Flags)

## Principio fundamental

El sistema NO es una app donde un humano mira pantallas y decide.
Es un **entorno donde agentes operan**, con estado estructurado, espacio de acciones y políticas de decisión.

```
Sistema tradicional:
  Operador humano → ve pantalla → decide → click → Controller → Service → DB

Agentic native:
  Evento ocurre → Agente observa estado → razona con contexto → decide → actúa → evento nuevo
```

---

## 1. Enums como vocabulario del agente

Los enums no son "columnas de base de datos". Son el **vocabulario estructurado** que los agentes usan para percibir, decidir y actuar.

### ShipmentStatus — Espacio de estados (lo que el agente ve)

```php
enum ShipmentStatus: string {
    case REGISTERED       = 'registered';        // Registrado en el sistema
    case IN_WAREHOUSE     = 'in_warehouse';       // En almacén/hub
    case IN_TRANSIT       = 'in_transit';          // En tránsito
    case OUT_FOR_DELIVERY = 'out_for_delivery';    // En reparto
    case DELIVERED        = 'delivered';            // Entregado
    case EXCEPTION        = 'exception';            // Incidencia (intento fallido)
    case CANCELLED        = 'cancelled';            // Cancelado
}
```

### ShipmentEventType — Tipos de evento (audit trail)

```php
enum ShipmentEventType: string {
    // Ya existentes
    case CREATED          = 'created';
    case PICKED_UP        = 'picked_up';
    case IN_HUB           = 'in_hub';
    case IN_TRANSIT       = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED        = 'delivered';
    case EXCEPTION        = 'exception';

    // Nuevos
    case ASSIGNED_TO_ROUTE = 'assigned_to_route';
    case CANCELLED         = 'cancelled';
    case STATUS_CHANGED    = 'status_changed';    // Cambio manual por operador
    case NOTE_ADDED        = 'note_added';         // Nota/comentario añadido
}
```

### ExceptionCode — Descripción estructurada del problema

```php
enum ExceptionCode: string {
    // Ya existentes
    case ABSENT             = 'absent';
    case WRONG_ADDRESS      = 'wrong_address';
    case REFUSED            = 'refused';
    case DAMAGED            = 'damaged';
    case OTHER              = 'other';

    // Nuevos (operativa real española)
    case ACCESS_BLOCKED     = 'access_blocked';      // Portero no abre, urbanización cerrada
    case BUSINESS_CLOSED    = 'business_closed';      // Negocio cerrado (B2B)
    case INCOMPLETE_ADDRESS = 'incomplete_address';    // Falta piso/puerta
}
```

---

## 2. Flags como POLÍTICAS DE DECISIÓN

**Cada flag es una instrucción que el agente entiende y ejecuta automáticamente.**

Los flags NO son "metadata del paquete". Son **instrucciones para el agente**:

```php
enum ShipmentFlag: int {
    // Instrucciones de handling
    case FRAGILE            = 1;    // Agente: priorizar acceso fácil en furgoneta
    case REQUIRES_SIGNATURE = 2;    // Agente: NO permitir LEAVE_AT_DOOR
    case CASH_ON_DELIVERY   = 4;    // Agente: driver debe cobrar, no dejar en puerta

    // Políticas ante ausencia
    case LEAVE_AT_DOOR      = 8;    // Agente: si ABSENT → instruir dejar en puerta
    case RETURN_IF_ABSENT   = 16;   // Agente: si ABSENT → devolver, no reintentar
    case RETRY_NEXT_DAY     = 32;   // Agente: si ABSENT → meter en ruta de mañana

    // Prioridad
    case PRIORITY           = 64;   // Agente: asignar a primera posición de ruta
    case SAME_DAY           = 128;  // Agente: si exception → re-agendar HOY

    // Verificación
    case AGE_VERIFICATION   = 256;  // Agente: REQUIRES_SIGNATURE implícito + verificar edad
}
```

Se almacenan como un solo `INT` en la columna `shipment.flags` usando operaciones bitwise:

```php
// Combinar flags
$flags = ShipmentFlag::FRAGILE->value | ShipmentFlag::PRIORITY->value; // = 65

// Verificar flag
$hasFlag = ($flags & ShipmentFlag::FRAGILE->value) !== 0; // true

// Helpers estáticos en el enum
ShipmentFlag::has($flags, ShipmentFlag::FRAGILE);       // true
ShipmentFlag::add($flags, ShipmentFlag::SAME_DAY);      // 193
ShipmentFlag::remove($flags, ShipmentFlag::PRIORITY);   // 1
ShipmentFlag::toArray($flags);                           // [FRAGILE, PRIORITY]
```

---

## 3. State machine como ESPACIO DE ACCIONES

Las transiciones válidas definen **qué puede hacer el agente** dado un estado:

```
REGISTERED       → [IN_WAREHOUSE, IN_TRANSIT, OUT_FOR_DELIVERY, CANCELLED]
IN_WAREHOUSE     → [IN_TRANSIT, OUT_FOR_DELIVERY, CANCELLED]
IN_TRANSIT       → [IN_WAREHOUSE, OUT_FOR_DELIVERY, CANCELLED]
OUT_FOR_DELIVERY → [DELIVERED, EXCEPTION, IN_WAREHOUSE]
EXCEPTION        → [OUT_FOR_DELIVERY, IN_WAREHOUSE, CANCELLED]  ← re-intento
DELIVERED        → []  (estado terminal)
CANCELLED        → []  (estado terminal)
```

El método `canTransitionTo(ShipmentStatus $target): bool` vive en el propio enum — sin librería externa.

---

## 4. Matriz de decisión: ExceptionCode × Flags → Acción automática

Esta es la tabla central del sistema agentic. **La decisión NO la toma un humano mirando una pantalla.**

| Exception | + Flag | → Acción del agente |
|---|---|---|
| `ABSENT` | `LEAVE_AT_DOOR` | Instruir driver: dejar en puerta, marcar DELIVERED |
| `ABSENT` | `RETURN_IF_ABSENT` | Marcar para devolución, no reintentar |
| `ABSENT` | `RETRY_NEXT_DAY` | Crear nuevo intento para mañana |
| `ABSENT` | `PRIORITY + SAME_DAY` | Re-asignar a otra ruta activa de hoy |
| `ABSENT` | (ninguno) | Default: reintentar siguiente día laborable |
| `REFUSED` | cualquiera | Marcar para devolución, notificar al cliente |
| `DAMAGED` | cualquiera | Marcar para devolución, crear incidencia de calidad |
| `WRONG_ADDRESS` | cualquiera | Escalar a operador para corrección manual |
| `INCOMPLETE_ADDRESS` | cualquiera | Escalar a operador para completar datos |
| `ACCESS_BLOCKED` | `PRIORITY` | Re-agendar mismo día franja diferente |
| `ACCESS_BLOCKED` | (ninguno) | Re-agendar siguiente día laborable |
| `BUSINESS_CLOSED` | cualquiera | Re-agendar siguiente día laborable en horario comercial |

---

## 5. Decision Points (dónde interviene el agente)

En vez de "endpoints para que un humano haga click", el sistema tiene puntos donde un agente evalúa y actúa:

| Decision Point | Trigger | El agente decide |
|---|---|---|
| **Shipment ingress** | CSV importado / API recibe envío | ¿Es válido? ¿Geocodificar? ¿Asignar prioridad? |
| **Route building** | Pool de shipments pendientes | ¿Cuántas rutas? ¿Qué vehículo? ¿Qué driver? ¿Qué orden? |
| **Exception handling** | Driver reporta incidencia | ¿Reintentar? ¿Reschedulear? ¿Devolver? ¿Notificar? |
| **Route monitoring** | Ruta activa, posiciones GPS llegan | ¿Va retrasado? ¿Recalcular ETAs? ¿Avisar al cliente? |
| **End of day** | Ruta finalizada con entregas pendientes | ¿Qué hacer con lo no entregado? |
| **Capacity check** | Se asigna envío a ruta | ¿Cabe en el vehículo? ¿Hay alternativa? |

---

## 6. Arquitectura del flujo agentic

```
                    ┌─────────────────┐
  Eventos           │   EVENT BUS     │         Acciones
  (input)           │  (Messenger)    │         (output)
                    └────────┬────────┘
                             │
           ┌─────────────────┼─────────────────┐
           ▼                 ▼                  ▼
   ┌───────────────┐ ┌──────────────┐  ┌───────────────┐
   │  Exception     │ │  Route       │  │  Notification  │
   │  Resolution    │ │  Assignment  │  │  Agent         │
   │  Agent         │ │  Agent       │  │                │
   └───────┬───────┘ └──────┬───────┘  └───────┬───────┘
           │                │                   │
           ▼                ▼                   ▼
   ┌─────────────────────────────────────────────────┐
   │              STRUCTURED STATE                    │
   │  ShipmentStatus + Flags + ExceptionCode          │
   │  (enums = vocabulario compartido)                │
   └─────────────────────────────────────────────────┘
           │                │                   │
           ▼                ▼                   ▼
     Reschedule       Assign to Route      SMS/Mercure
     Return           Optimize order       Email
     Escalate         Swap vehicle         Push notification
```

---

## 7. Campos necesarios en entidades

### Shipment (añadir)

| Campo | Tipo | Descripción |
|---|---|---|
| `status` | `VARCHAR` (ShipmentStatus enum) | Estado actual desnormalizado |
| `flags` | `INT` default 0 | Bitfield de ShipmentFlag |
| `weight_kg` | `NUMERIC(8,2)` nullable | Peso en kg |
| `volume_m3` | `NUMERIC(8,4)` nullable | Volumen en m³ |
| `parcels` | `SMALLINT` default 1 | Número de bultos |

### Vehicle (añadir)

| Campo | Tipo | Descripción |
|---|---|---|
| `max_weight_kg` | `NUMERIC(8,2)` nullable | Carga máxima en kg |
| `max_volume_m3` | `NUMERIC(8,4)` nullable | Volumen máximo en m³ |
| `max_stops` | `SMALLINT` nullable | Paradas máximas por ruta |

---

## 8. Implementación priorizada

### P0 — Los cimientos para que los agentes operen

1. **Enums como vocabulario** — `ShipmentStatus`, `ShipmentFlag`, `ExceptionCode` expandido
2. **State machine como action space** — transiciones válidas = lo que el agente puede hacer
3. **Flags como policies** — instrucciones codificadas para decisión automática
4. **Event bus** — cada cambio de estado genera un evento (Symfony Messenger async)
5. **`ExceptionResolutionHandler`** — primer agente: recibe EXCEPTION, lee flags, ejecuta política automáticamente
6. **Escalation path** — si el agente no puede decidir (flags contradictorios, caso no cubierto) → escala a operador humano
7. **Campos de capacidad** — peso/volumen en Shipment, capacidad en Vehicle
8. **Validación pre-ruta** — verificar capacidad al asignar envíos a ruta

### P1 — Agentes de routing

9. **`RouteAssignmentHandler`** — pool de shipments pendientes → genera rutas automáticamente respetando capacidad
10. **`RouteMonitorHandler`** — ruta activa + GPS → recalcula ETAs, detecta retrasos, notifica
11. **OSRM self-hosted** — distancias reales por carretera en vez de Haversine
12. **2-opt improvement** — post-proceso sobre el resultado del NN

### P2 — Agentes de comunicación y multi-bulto

13. **`NotificationHandler`** — cada transición de estado → decide a quién notificar y por qué canal
14. **Webhooks a cliente** — callbacks automáticos sin polling
15. **Entidad Parcel separada** — para envíos multi-bulto con status independiente por paquete
16. **`PARTIALLY_DELIVERED` / `RETURNING` / `RETURNED`** — estados adicionales

---

## 9. Shipment vs Parcel (decisión arquitectónica)

### MVP (P0): 1 Shipment = 1 bulto lógico

El `status` vive directamente en `Shipment`. Simple, suficiente para la mayoría de operaciones last-mile.

### Post-MVP (P2): Shipment → N Parcels

Si un cliente envía 3 cajas:
- Se crea 1 `Shipment` con 3 `Parcel` entities
- Cada `Parcel` tiene su propio `status`
- El `Shipment` deriva su status del "peor" parcel:
  - 2 delivered + 1 exception → `PARTIALLY_DELIVERED`
  - 3 delivered → `DELIVERED`
  - 1 returned → `RETURNING`

No requiere cambios estructurales ahora — solo añadir la entidad `Parcel` después y mover el status granular ahí.

---

## 10. Relación optimización de rutas + delivery system

Los P0 de ambos sistemas se complementan:

```
CSV Import  ──→  Shipment (+ weight, volume, flags, status)
                      │
                      ▼
             Asignar a Route  ──→  Validar capacidad Vehicle
                      │                    (agente verifica)
                      ▼
              Optimizar orden  ──→  NN con constraint de capacidad
                      │                    (agente optimiza)
                      ▼
              Driver ejecuta   ──→  ShipmentLifecycleService
                      │                    (status transitions)
                      ▼
              Exception?  ──→  ExceptionResolutionHandler
                                   (agente decide automáticamente)
```
