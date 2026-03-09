# Especificaciones del Sistema — mxo-track

Documento de referencia que describe todos los flujos del sistema, las decisiones de diseño, y los servicios que intervienen en cada operación. Dirigido tanto a perfiles técnicos como de negocio.

---

## Índice

1. [Visión general](#1-visión-general)
2. [Modelo de datos](#2-modelo-de-datos)
3. [Roles y permisos](#3-roles-y-permisos)
4. [Aislamiento multi-tenant](#4-aislamiento-multi-tenant)
5. [Flujo: Gestión de clientes](#5-flujo-gestión-de-clientes)
6. [Flujo: Gestión de vehículos](#6-flujo-gestión-de-vehículos)
7. [Flujo: Creación de envíos (Shipments)](#7-flujo-creación-de-envíos-shipments)
8. [Flujo: Construcción de rutas](#8-flujo-construcción-de-rutas)
9. [Flujo: Optimización de rutas (VROOM + OSRM)](#9-flujo-optimización-de-rutas-vroom--osrm)
10. [Flujo: Validación de capacidad](#10-flujo-validación-de-capacidad)
11. [Flujo: Ciclo de vida de la ruta](#11-flujo-ciclo-de-vida-de-la-ruta)
12. [Flujo: Entrega y Proof of Delivery](#12-flujo-entrega-y-proof-of-delivery)
13. [Flujo: Excepciones en entrega](#13-flujo-excepciones-en-entrega)
14. [Flujo: Cálculo de ETAs](#14-flujo-cálculo-de-etas)
15. [Flujo: Tracking GPS en tiempo real](#15-flujo-tracking-gps-en-tiempo-real)
16. [Flujo: Notificaciones](#16-flujo-notificaciones)
17. [Flujo: Albarán de entrega](#17-flujo-albarán-de-entrega)
18. [Flujo: Vista del cliente](#18-flujo-vista-del-cliente)
19. [Flujo: Tracking público](#19-flujo-tracking-público)
20. [Flujo: Reporting y dashboards](#20-flujo-reporting-y-dashboards)
21. [Flujo: Búsqueda global](#21-flujo-búsqueda-global)
22. [Flujo: Facturación](#22-flujo-facturación)
23. [Auditoría y seguridad](#23-auditoría-y-seguridad)
24. [Infraestructura y servicios](#24-infraestructura-y-servicios)

---

## 1. Visión general

mxo-track es un portal de logística que gestiona el ciclo completo de entrega: desde la importación de envíos, la planificación y optimización de rutas, el seguimiento GPS en tiempo real de los vehículos, hasta la confirmación de entrega (POD) por parte del conductor.

**Flujo principal simplificado:**

```
Cliente importa envíos (CSV)
        ↓
Operador selecciona envíos + vehículos → Construir rutas
        ↓
VROOM optimiza: asigna envíos a vehículos + ordena paradas (OSRM calcula distancias reales)
        ↓
Conductor recibe ruta en su app → Inicia ruta
        ↓
Por cada parada: Entrega (POD) o Excepción
        ↓
Conductor finaliza ruta → Cliente ve estado en tiempo real
```

---

## 2. Modelo de datos

### Entidades principales

| Entidad | Descripción | Campos clave |
|---------|-------------|--------------|
| **Customer** | Empresa cliente que contrata el servicio | name, address, contactPhone, webhookUrl, frequency, isActive |
| **CustomerLocation** | Almacén/depósito del cliente (punto de origen) | customer, name, address, latitude, longitude, isDefault |
| **Vehicle** | Vehículo de la flota | name, maxWeightKg, maxVolumeM3, maxParcels, traccarDeviceId, isActive |
| **Shipment** | Envío individual a entregar | reference, customer, recipientName, address, lat/lng, totalWeightKg, totalVolumeM3, totalParcels, preferredWindowStart/End, trackingToken |
| **Parcel** | Bulto dentro de un envío | shipment, sequence, weightKg, volumeM3, ean, description |
| **Route** | Ruta planificada/activa | name, status, driver, vehicle, customer, originLocation, totalWeightKg, totalVolumeM3, totalParcels, totalDistanceKm, estimatedDurationMinutes |
| **RouteStop** | Parada dentro de una ruta | route, sequence, address, lat/lng, recipientName, status, shipment, isOrigin, deliveryWindowStart/End |
| **Pod** | Proof of Delivery | routeStop, shipment, signedByName, recipientIdEncoded, confirmedByDriver |
| **ShipmentEvent** | Evento en la línea de tiempo del envío | shipment, eventType, payload, createdAt |
| **DriverAction** | Idempotencia de acciones del conductor | driver, clientActionId, actionType |
| **User** | Usuario del sistema | email, name, role (ADMIN/CUSTOMER/DRIVER), customer |
| **CustomerVehicle** | Asociación cliente ↔ vehículo (multi-tenant) | customer, vehicle (unique pair, sin public_id) |
| **VehiclePosition** | Histórico completo de posiciones GPS | vehicle, route, lat, lng, speed, course, accuracy, deviceTime |
| **VehicleLastPosition** | Última posición conocida (desnormalizada) | vehicle (1:1), lat, lng, speed, course, deviceTime |
| **VehicleCheckpoint** | Progreso de ingesta desde Traccar | vehicle (1:1), lastDeviceTime, lastTraccarPositionId |
| **AuditLog** | Registro de auditoría | user, action, entityType, entityId, ip, payload, createdAt |
| **Notification** | Notificación in-app | user, title, body, isRead, createdAt |

### Patrón de identidad

Todas las entidades (excepto CustomerVehicle) tienen dos identificadores:

- **`id`** (BIGINT auto-increment): Para joins internos y rendimiento. **Nunca se expone en APIs.**
- **`public_id`** (ULID): Se usa en URLs, APIs, y topics de Mercure. Es el identificador público.

---

## 3. Roles y permisos

| Rol | Descripción | Acceso |
|-----|-------------|--------|
| **ROLE_ADMIN** | Administrador del sistema | Todo: gestión de clientes, vehículos, usuarios, rutas, reportes, configuración |
| **ROLE_OPERATOR** | Operador logístico (heredado de ADMIN) | Construir rutas, optimizar, gestionar envíos, ver mapa de flota |
| **ROLE_CUSTOMER** | Cliente externo | Ver sus propios envíos y rutas (filtrado por tenant) |
| **ROLE_DRIVER** | Conductor | Gestionar sus rutas asignadas, entregar paradas, reportar excepciones |

**Jerarquía:**
```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

---

## 4. Aislamiento multi-tenant

**Objetivo de negocio:** Cada cliente solo ve sus propios datos. Un cliente nunca puede ver envíos, rutas o vehículos de otro cliente.

**Implementación técnica:**

1. Las entidades que pertenecen a un cliente implementan `CustomerScopedEntityInterface`
2. `CustomerTenantFilter` es un filtro SQL de Doctrine que añade `WHERE customer_id = :id` automáticamente a todas las consultas
3. `DoctrineCustomerFilterSubscriber` activa el filtro para usuarios con ROLE_CUSTOMER o ROLE_DRIVER que tienen un customer asociado
4. Usuarios ADMIN/OPERATOR no tienen filtro → ven todos los datos

**Entidades con scope de cliente:** Shipment, CustomerLocation, Route (vía customer), RouteStop (vía route→customer)

---

## 5. Flujo: Gestión de clientes

### 5.1 Crear cliente

**Quién:** ROLE_ADMIN
**Dónde:** `/admin/customers/new`
**Servicios:** CustomerAdminController → Formulario Symfony → Doctrine persist

**Datos del cliente:**
- Nombre (obligatorio)
- Dirección
- Teléfono de contacto
- URL de webhook (para notificaciones automáticas)
- Frecuencia de envío (DAILY, WEEKLY, BIWEEKLY, MONTHLY)
- Franja horaria preferida de entrega

### 5.2 Crear ubicación (almacén/depósito)

**Quién:** ROLE_ADMIN
**Dónde:** `/admin/customer-locations/new`
**Servicios:** CustomerLocationAdminController

Las ubicaciones se usan como **punto de origen** de las rutas. El vehículo sale de aquí, entrega, y vuelve aquí.

**Datos:**
- Cliente (obligatorio)
- Nombre (ej: "Almacén Madrid Centro")
- Dirección
- Latitud / Longitud (necesarias para el cálculo de rutas)
- ¿Es la ubicación por defecto?

---

## 6. Flujo: Gestión de vehículos

### 6.1 Crear vehículo

**Quién:** ROLE_ADMIN
**Dónde:** `/admin/vehicles/new`
**Servicios:** VehicleAdminController → VehicleType form → Doctrine persist

**Datos del vehículo:**
- Nombre (obligatorio, ej: "Furgoneta Madrid-01")
- Peso máximo (kg) — capacidad de carga
- Volumen máximo (m³) — capacidad volumétrica
- Máximo de bultos — cantidad máxima de paquetes
- Activo (sí/no)

**Decisión de diseño:** Las tres dimensiones de capacidad (peso, volumen, bultos) son independientes. Un envío debe caber en las tres para ser asignado al vehículo. Si un vehículo no tiene configurada alguna dimensión (null), esa restricción no se aplica.

### 6.2 Visibilidad de vehículos (multi-tenant)

La visibilidad de vehículos se controla por `VisibilityScopeService` según el rol:

| Rol | Vehículos visibles |
|-----|-------------------|
| ROLE_ADMIN | Todos |
| ROLE_CUSTOMER | Solo los asociados vía `CustomerVehicle` |
| ROLE_DRIVER | Solo los asignados a sus rutas activas |

La tabla `CustomerVehicle` es una asociación N:M entre Customer y Vehicle. No tiene `public_id` (solo uso interno). Constraint único: `(customer_id, vehicle_id)`.

### 6.3 Vincular vehículo con Traccar (GPS)

**Quién:** Sistema automático o admin
**Servicios:** TraccarSyncDevicesCommand, TraccarApiClient

El vehículo se vincula a un dispositivo GPS en Traccar mediante `traccarDeviceId`. Esto permite:
- Recibir posiciones GPS del vehículo en tiempo real
- Mostrar el vehículo en el mapa de flota
- Calcular ETAs basadas en la posición real

**Método A — Sincronización por nombre:**
```bash
php bin/console app:traccar:sync-devices --apply
```
Busca coincidencias por nombre (case-insensitive) entre vehículos locales y dispositivos Traccar. Los que coinciden se vinculan automáticamente.

**Método B — Simulación GPS (desarrollo):**
```bash
php bin/console app:dev:simulate-gps --points=20 --interval=2 --ingest
```
Crea un dispositivo en Traccar con `uniqueId=sim-{nombre}`, vincula el vehículo, y envía posiciones simuladas por el centro de Madrid.

### 6.4 API de vehículos

| Endpoint | Método | Rol | Descripción |
|----------|--------|-----|-------------|
| `/api/vehicles` | GET | Autenticado | Lista vehículos (filtrados por visibilidad) |
| `/api/vehicles/{publicId}/last-position` | GET | Autenticado | Última posición GPS |
| `/api/vehicles/{publicId}/positions` | GET | Autenticado | Histórico de posiciones (paginado, filtro por fecha) |
| `/api/vehicles/{publicId}/positions.csv` | GET | Autenticado | Exportar posiciones a CSV |

---

## 7. Flujo: Creación de envíos (Shipments)

### 7.1 Importación por CSV

**Quién:** ROLE_ADMIN
**Dónde:** `/admin/shipments/import`
**Servicios:** AdminShipmentController → ShipmentCsvImporter → ImportRunTracker

**Formato CSV (13 columnas):**
```
reference,recipient_name,address,latitude,longitude,phone,notes,service_type,weight_kg,volume_m3,num_parcels,ean,description
```

**Proceso paso a paso:**

1. El admin sube un fichero CSV y selecciona el cliente
2. Por cada fila del CSV:
   - Valida que `reference` no está vacío y no existe ya
   - Crea entidad `Shipment` con todos los datos
   - Valida coordenadas (lat: -90..90, lng: -180..180)
   - Crea N entidades `Parcel` (dividiendo peso/volumen entre bultos)
   - Genera `trackingToken` único (formato: TRK-XXXX-XXXX)
   - Registra evento `ShipmentEvent(CREATED, source='csv_import')`
3. Resultado: contadores de {creados, saltados, errores}

**Tipos de servicio:**
- `DELIVERY` — Entrega estándar
- `DELIVERY_AND_PICKUP` — Entrega con recogida
- `RETURN` — Devolución

### 7.2 Línea de tiempo del envío (ShipmentEvent)

Cada envío tiene un historial de eventos que describe su ciclo de vida:

```
CREATED            → Envío registrado en el sistema
PICKED_UP          → Recogido del remitente
IN_HUB             → En centro de distribución
IN_TRANSIT         → En tránsito
OUT_FOR_DELIVERY   → Asignado a ruta/conductor
DELIVERED          → Entregado con éxito (incluye datos del POD)
EXCEPTION          → Incidencia en la entrega (motivo + comentario)
```

---

## 8. Flujo: Construcción de rutas

### 8.1 Descripción del problema

Dado un conjunto de envíos y una flota de vehículos, el sistema debe:
1. **Asignar** cada envío al vehículo más adecuado (respetando capacidad)
2. **Ordenar** las paradas de cada vehículo para minimizar la distancia total recorrida
3. **Respetar** ventanas horarias de entrega si existen
4. Todo usando **distancias reales por carretera** (no línea recta)

Este es un problema de optimización combinatoria conocido como **VRP** (Vehicle Routing Problem).

### 8.2 Endpoint

**Quién:** ROLE_OPERATOR
**Endpoint:** `POST /api/routes/build`
**Servicios:** RouteOptimizationApiController → RouteBuilder → VroomRequestMapper → VroomApiClient → VroomResponseMapper → RouteCapacityValidator

**Request:**
```json
{
  "shipment_ids": ["01HXYZ...", "01HABC..."],
  "vehicle_ids": ["01HVEH1...", "01HVEH2..."],
  "origin_id": "01HLOC...",
  "max_stops_per_route": 30
}
```

### 8.3 Flujo interno detallado

```
                    ┌─────────────────────┐
                    │  RouteBuilder        │
                    │  buildRoutes()       │
                    └──────┬──────────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
   VroomRequestMapper            VroomRequestMapper
   mapVehicles()                 mapJobs()
              │                         │
              │ Convierte Vehicle →      │ Convierte Shipment →
              │ {id, start/end,          │ {id, location,
              │  capacity:[g,cm³,uds]}   │  service:300s,
              │                         │  amount:[g,cm³,uds],
              │ Coordenadas: [lon,lat]  │  time_windows:[]}
              └────────────┬────────────┘
                           │
                           ▼
                   ┌───────────────┐
                   │ VroomApiClient │
                   │ optimize()    │───── POST JSON ────▶ VROOM API
                   └───────┬───────┘                        │
                           │                                │
                           │◀────── Respuesta ──────────────┘
                           │  {routes: [{vehicle, steps,
                           │   distance, duration}],
                           │   unassigned: [...]}
                           ▼
                  ┌─────────────────────┐
                  │ VroomResponseMapper  │
                  │ mapToRoutes()        │
                  └──────┬──────────────┘
                         │
                         │ Por cada ruta VROOM:
                         │  1. Crea entidad Route
                         │  2. Crea RouteStop origen (seq 0)
                         │  3. Por cada step tipo "job":
                         │     Crea RouteStop con shipment
                         │  4. distanceKm = distance / 1000
                         │  5. durationMinutes = duration / 60
                         │  6. Valida capacidad
                         ▼
                  ┌─────────────────────┐
                  │ RouteCapacityValidator│
                  │ validate()          │
                  └─────────────────────┘
```

### 8.4 Decisiones de optimización VROOM

| Aspecto | Decisión |
|---------|----------|
| **Motor de routing** | OSRM con mapa OpenStreetMap de la Comunidad de Madrid |
| **Distancias** | Kilómetros reales por carretera (no línea recta) |
| **Duraciones** | Tiempos reales de conducción según la red vial |
| **Capacidad** | 3 dimensiones: peso (gramos), volumen (cm³), bultos |
| **Tiempo de servicio** | 5 minutos (300 seg) por parada de entrega |
| **Punto de origen** | El vehículo sale y vuelve al almacén (CustomerLocation) |
| **Ventanas horarias** | Se respetan si el envío tiene `preferredWindowStart/End` |
| **Envíos sin coordenadas** | Se excluyen de la optimización (no se pueden rutear) |
| **Envíos no asignables** | VROOM los reporta como "unassigned" (sin capacidad o sin ruta viable) |
| **Algoritmo** | VROOM usa metaheurísticas: local search + perturbaciones |

### 8.5 Conversión de unidades

VROOM requiere capacidades como **enteros**. La conversión es:

| Dominio | VROOM | Fórmula |
|---------|-------|---------|
| Peso: 5.5 kg | 5500 g | `kg × 1000` |
| Volumen: 0.25 m³ | 250000 cm³ | `m³ × 1,000,000` |
| Bultos: 3 | 3 | Sin conversión |

Si un campo es `null` (sin configurar), se usa un valor muy alto (999999) para no restringir.

### 8.6 Response

```json
{
  "routesCreated": 2,
  "routes": [
    {
      "route": {
        "publicId": "01HXYZ...",
        "name": "Ruta 1 - 06/03/2026",
        "vehicle": "Furgoneta Madrid-01",
        "totalDistanceKm": 45.3,
        "estimatedDurationMinutes": 185
      },
      "stopsCount": 12,
      "validation": {
        "valid": true,
        "errors": [],
        "totalWeightKg": 450.0,
        "weightUtilization": 75.0
      }
    }
  ]
}
```

---

## 9. Flujo: Optimización de rutas (VROOM + OSRM)

### 9.1 Re-optimizar una ruta existente

**Quién:** ROLE_OPERATOR
**Endpoint:** `POST /api/routes/{publicId}/optimize`
**Servicios:** RouteOptimizationApiController → RouteOptimizationService → VroomApiClient + OsrmClient

Cuando se añaden o eliminan paradas manualmente, el orden puede dejar de ser óptimo. Este endpoint recalcula el orden usando VROOM.

**Proceso:**

1. Carga todas las paradas de la ruta ordenadas por secuencia
2. Calcula la **distancia antes** usando OSRM (ruta real por las paradas actuales)
3. Envía las paradas a VROOM como problema de un solo vehículo
4. VROOM devuelve el orden óptimo + distancia + duración reales
5. Aplica el nuevo orden de secuencias a las paradas
6. Actualiza `totalDistanceKm` y `estimatedDurationMinutes` en la ruta

**Response:**
```json
{
  "distanceBefore": 52.3,
  "distanceAfter": 41.7,
  "improvement": 20.3,
  "estimatedDurationMinutes": 165,
  "stops": [
    {"publicId": "...", "sequence": 0, "address": "Almacén Central"},
    {"publicId": "...", "sequence": 1, "address": "C/ Gran Vía 45"},
    {"publicId": "...", "sequence": 2, "address": "C/ Alcalá 12"}
  ]
}
```

### 9.2 Arquitectura de optimización

```
┌────────────┐      ┌──────────┐      ┌──────────────────┐
│ Symfony App │─────▶│  VROOM   │─────▶│  OSRM            │
│ (PHP 8.4)  │ HTTP │ (VRP     │ HTTP │ (Road routing     │
│            │◀─────│  solver) │◀─────│  engine)          │
└────────────┘      └──────────┘      └──────────────────┘
                                              │
                                      ┌───────┴───────┐
                                      │ Mapa OSM      │
                                      │ Comunidad de  │
                                      │ Madrid (~75MB)│
                                      └───────────────┘
```

- **OSRM** (Open Source Routing Machine): Calcula la distancia y el tiempo por carretera entre dos puntos. Usa datos de OpenStreetMap pre-procesados.
- **VROOM** (Vehicle Routing Open-source Optimization Machine): Resuelve el VRP. Consulta OSRM para obtener la matriz de distancias entre todos los puntos, y luego aplica algoritmos de optimización para encontrar la mejor distribución y orden.

### 9.3 Diferencia con el sistema anterior

| Aspecto | Antes (Haversine) | Ahora (VROOM + OSRM) |
|---------|-------------------|----------------------|
| Distancias | Línea recta (Haversine) | Carreteras reales (OSRM) |
| Asignación a vehículos | Greedy: farthest-first, llenar secuencialmente | Óptima: VROOM distribuye minimizando distancia total |
| Orden de paradas | Nearest-neighbor heuristic | Metaheurísticas de VROOM (local search + perturbaciones) |
| Duración estimada | `distancia / 40 km/h + 5 min/parada` | Tiempo real de conducción de OSRM + 5 min/parada |
| Ventanas horarias | No se consideraban | VROOM las respeta como restricciones |
| Calidad de solución | Aproximada (puede ser 20-40% peor) | Cercana al óptimo (<5% del óptimo teórico) |

---

## 10. Flujo: Validación de capacidad

**Quién:** ROLE_OPERATOR
**Endpoint:** `GET /api/routes/{publicId}/validate-capacity`
**Servicios:** RouteOptimizationApiController → RouteCapacityValidator

**Proceso:**

1. Carga el vehículo asignado a la ruta
2. Suma peso, volumen y bultos de todos los envíos en la ruta
3. Compara contra los límites del vehículo
4. Calcula porcentajes de utilización
5. Actualiza los totales en la ruta

**Validaciones:**
- Si peso total > maxWeightKg → Error: "Peso total (X kg) excede la capacidad del vehículo (Y kg)"
- Si volumen total > maxVolumeM3 → Error análogo
- Si bultos totales > maxParcels → Error análogo
- Si un envío no tiene peso NI volumen → Warning: "Envío X no tiene peso ni volumen configurado"
- Si la ruta no tiene vehículo → Error: "La ruta no tiene vehículo asignado"

**Response:**
```json
{
  "valid": true,
  "errors": [],
  "totalWeightKg": 450.0,
  "totalVolumeM3": 2.5,
  "totalParcels": 35,
  "weightUtilization": 75.0,
  "volumeUtilization": 50.0,
  "parcelUtilization": 87.5
}
```

---

## 11. Flujo: Ciclo de vida de la ruta

### 11.1 Estados

```
┌──────────┐     start()     ┌──────────┐    finish()    ┌──────────┐
│ PLANNED  │────────────────▶│  ACTIVE  │───────────────▶│   DONE   │
└──────────┘                 └──────────┘                └──────────┘
      │
      │ (cancelar manualmente)
      ▼
┌──────────┐
│CANCELLED │
└──────────┘
```

| Transición | Quién | Endpoint | Efecto |
|------------|-------|----------|--------|
| PLANNED → ACTIVE | Conductor | `POST /api/driver/routes/{id}/start` | Setea `startAt = ahora` |
| ACTIVE → DONE | Conductor | `POST /api/driver/routes/{id}/finish` | Setea `endAt = ahora` |
| PLANNED → CANCELLED | Operador | Admin UI | Manual |

### 11.2 Ciclo de vida de una parada (RouteStop)

```
┌─────────┐    markDelivered()    ┌───────────┐
│ PENDING │──────────────────────▶│ DELIVERED │
└─────────┘                       └───────────┘
      │
      │ markException(code, notes)
      ▼
┌───────────┐
│ EXCEPTION │
└───────────┘
```

**Códigos de excepción:**
- `ABSENT` — Destinatario ausente
- `WRONG_ADDRESS` — Dirección incorrecta
- `REFUSED` — Entrega rechazada
- `DAMAGED` — Mercancía dañada
- `OTHER` — Otro motivo (requiere comentario)

---

## 12. Flujo: Entrega y Proof of Delivery

**Quién:** ROLE_DRIVER
**Endpoint:** `POST /api/driver/stops/{stopPublicId}/deliver`
**Servicios:** DriverApiController → DriverActionService → Pod entity → ShipmentEvent → AuditLogger

### 12.1 Request

```json
{
  "client_action_id": "550e8400-e29b-41d4-a716-446655440000",
  "signed_by_name": "María García",
  "recipient_id_encoded": "base64_encoded_id_document",
  "confirmed_by_driver": true,
  "shipment_public_id": "01HSHIP..."
}
```

### 12.2 Proceso paso a paso

1. **Validar DTO:** DeliverStopInput con constraints (NotBlank, Uuid, Length)
2. **Buscar parada:** por `public_id`, verificar que pertenece a una ruta del conductor
3. **Idempotencia:** DriverActionService comprueba si `(driver, client_action_id)` ya existe
   - Si ya existe → retorna `{ok: true, idempotent: true}` sin reprocesar
   - Si es nuevo → crea DriverAction y continúa
4. **Verificar confirmación:** `confirmed_by_driver` debe ser `true`
5. **Marcar entregado:** `stop.markDelivered()` → status=DELIVERED, deliveredAt=ahora
6. **Crear POD:** entidad Pod con signedByName, recipientIdEncoded, driver
7. **Registrar evento:** ShipmentEvent(DELIVERED) con metadata de confirmación
8. **Auditoría:** AuditLogger registra la acción
9. **Response:** `{ok: true, idempotent: false}` con HTTP 201

### 12.3 Idempotencia

La app del conductor puede tener problemas de red. Si envía la misma acción dos veces (mismo `client_action_id`), el servidor detecta el duplicado y responde sin reprocesar. Esto evita entregas dobles.

---

## 13. Flujo: Excepciones en entrega

**Quién:** ROLE_DRIVER
**Endpoint:** `POST /api/driver/stops/{stopPublicId}/exception`

### 13.1 Request

```json
{
  "client_action_id": "550e8400-...",
  "reason": "ABSENT",
  "comment": "No contesta al timbre después de 3 intentos",
  "shipment_public_id": "01HSHIP..."
}
```

### 13.2 Proceso

1. Validar ExceptionStopInput DTO
2. Verificar que la parada pertenece a una ruta del conductor
3. Comprobar idempotencia
4. `stop.markException(ExceptionCode::ABSENT, comment)`
5. Crear ShipmentEvent(EXCEPTION) con reason y comment
6. Registrar auditoría

---

## 14. Flujo: Cálculo de ETAs

**Quién:** ROLE_DRIVER
**Endpoint:** `GET /api/driver/routes/{routePublicId}/etas`
**Servicios:** DriverApiController → EtaService → OsrmClient

### 14.1 Proceso

1. Obtener la **última posición GPS** del vehículo (VehicleLastPosition)
2. Si no hay posición GPS → usar la parada origen como referencia
3. Para cada parada pendiente (en orden de secuencia):
   - Consultar OSRM: distancia real y duración desde la posición actual
   - Acumular tiempo
   - ETA = hora actual + tiempo acumulado
   - Sumar 2 minutos de tiempo de parada
   - Mover posición actual a esta parada (para la siguiente)

### 14.2 Response

```json
{
  "items": {
    "01HSTOP1...": {
      "eta": "2026-03-06T14:35:00+01:00",
      "eta_formatted": "14:35",
      "remaining_minutes": 25,
      "distance_km": 8.3
    },
    "01HSTOP2...": {
      "eta": "2026-03-06T14:57:00+01:00",
      "eta_formatted": "14:57",
      "remaining_minutes": 47,
      "distance_km": 14.1
    }
  }
}
```

**Nota:** Las ETAs usan duraciones reales de OSRM (tráfico estático basado en la red vial), no velocidades estimadas.

---

## 15. Flujo: Tracking GPS en tiempo real

### 15.1 Ingesta de posiciones

```
Dispositivo GPS → Protocolo OsmAnd (puerto 5055) → Traccar → API REST
                                                                 │
TraccarStreamCommand (polling cada 2-5 seg) ◀────────────────────┘
        │
        ▼
TraccarIngestionService
        │
        ├── Crea VehiclePosition (histórico append-only)
        ├── Actualiza VehicleLastPosition (1:1 por vehículo)
        ├── Actualiza VehicleCheckpoint (progreso de ingesta)
        └── Publica a Mercure → /vehicles/{public_id}/position
                                  /operator/fleet
```

**Servicios:**
- `TraccarStreamCommand` — Proceso de larga duración que consulta Traccar periódicamente
- `TraccarApiClient` — Cliente HTTP para la API REST de Traccar
- `TraccarIngestionService` — Procesa posiciones y las almacena en PostgreSQL

**Modos de operación del stream:**

| Modo | Comando | Descripción |
|------|---------|-------------|
| Polling continuo | `app:traccar:stream --sleep=2` | Consulta Traccar cada N segundos (por defecto) |
| WebSocket | `app:traccar:stream --mode=ws` | Recibe posiciones en near real-time vía WS |
| Una sola vez | `app:traccar:stream --once` | Ejecuta un ciclo de polling y termina |

**Proceso de ingesta por cada posición:**

1. **Deduplicación:** Constraint único `(vehicle_id, device_time)` previene duplicados
2. **VehiclePosition:** Se crea una entrada de histórico (append-only). Si el vehículo tiene una ruta activa, se asocia la posición a la ruta
3. **VehicleLastPosition:** Se crea (primera vez) o actualiza (refresh) la posición desnormalizada. Es un registro 1:1 por vehículo para consultas rápidas del mapa de flota
4. **VehicleCheckpoint:** Registra `lastDeviceTime` y `lastTraccarPositionId` para saber desde dónde reanudar el polling tras un reinicio (evita re-ingestar posiciones antiguas)
5. **Mercure:** Publica JSON con lat, lng, speed, course, accuracy, deviceTime al topic `/vehicles/{publicId}/position`
6. **Errores:** Si falla la inserción por constraint violation (race condition), se limpia el EntityManager y se continúa. Si falla Mercure, se loguea y se continúa (no se pierde la posición)

**Datos de cada posición publicada por Mercure:**
```json
{
  "vehicleId": "01HVEH...",
  "lat": 40.4168,
  "lng": -3.7038,
  "speed": 25.5,
  "course": 180.0,
  "accuracy": 5.0,
  "deviceTime": "2026-03-06T14:30:00+01:00"
}
```

### 15.2 Mapa de flota

**Quién:** ROLE_ADMIN
**Endpoint:** `/fleet/map`
**Servicios:** FleetMapController → VehicleLastPosition → Mercure SSE

El mapa de flota muestra todos los vehículos activos con su última posición. Se actualiza en tiempo real vía Mercure SSE (Server-Sent Events).

**Datos mostrados por vehículo:**
- Posición actual (lat, lng), velocidad, dirección, hora del último GPS fix
- Rutas activas asignadas con progreso de entregas (entregadas / total)
- Paradas pendientes con ubicación

### 15.3 Mercure (realtime SSE)

El frontend recibe actualizaciones en tiempo real vía Server-Sent Events:

| Topic | Descripción | Suscriptor |
|-------|-------------|------------|
| `/vehicles/{public_id}/position` | Posición de un vehículo específico | Vista de detalle de vehículo |
| `/operator/fleet` | Todas las posiciones de flota | Mapa de flota (`/fleet/map`) |
| `/customers/{id}/routes` | Cambios en rutas del cliente | Portal del cliente |
| `/customers/{id}/shipments` | Cambios en envíos del cliente | Portal del cliente |

**Autenticación Mercure:** `MercureTokenController` genera JWTs de suscriptor via `MercureJwtFactory`. Se envían como cookie `mercureAuthorization` (sin prefijo "Bearer"). CORS configurado para orígenes específicos (no wildcard).

---

## 16. Flujo: Notificaciones

El sistema tiene tres canales de notificación independientes. Los tres se disparan ante los mismos eventos (entrega, excepción, asignación de ruta) pero llegan por vías distintas.

### 16.1 Webhooks (integración externa)

**Servicio:** `WebhookNotificationService`
**Cuándo se envía:** Cuando un evento relevante ocurre y el cliente tiene `webhookUrl` configurado.

**Proceso:**

1. Se construye el payload JSON con `event` (tipo), `timestamp` (ISO 8601) y `data` (detalles del evento)
2. Se calcula la firma HMAC-SHA256 del body completo usando `WEBHOOK_SECRET`
3. Se envía POST HTTP al `webhookUrl` del cliente con headers:
   - `Content-Type: application/json`
   - `X-MxoTrack-Signature: sha256={hmac_hex}` — Para verificación de autenticidad
   - `X-MxoTrack-Event: {tipo_evento}` — Para routing en el receptor
4. Timeout: 10 segundos. Si falla, se loguea el error pero no se reintenta (fire-and-forget)

**Verificación en el receptor:**

```python
# Ejemplo: el cliente puede verificar la firma así
import hmac, hashlib
expected = hmac.new(webhook_secret.encode(), body, hashlib.sha256).hexdigest()
assert request.headers['X-MxoTrack-Signature'] == f'sha256={expected}'
```

**Decisión de diseño:** Si `WEBHOOK_SECRET` no está configurado, se usa un valor por defecto (`mxo-track-webhook-default`). Esto permite funcionar en desarrollo sin configuración adicional, pero en producción debe configurarse un secreto real.

### 16.2 Email

**Servicio:** `EmailNotificationService`
**Dependencias:** Symfony Mailer (`MailerInterface`) + Twig para plantillas HTML

**Emails disponibles:**

| Evento | Template Twig | Destinatario | Asunto |
|--------|--------------|-------------|--------|
| Entrega exitosa | `email/delivery_notification.html.twig` | Cliente del envío | "Su envío {ref} ha sido entregado" |
| Excepción/incidencia | `email/exception_notification.html.twig` | Cliente del envío | "Incidencia en su envío {ref}" |
| Ruta asignada | `email/route_assigned.html.twig` | Conductor asignado | "Nueva ruta asignada: {nombre}" |

**Decisión de diseño:** Los emails se envían de forma síncrona con manejo de errores silencioso (`safeSend`). Si falla el envío (SMTP caído, etc.), se loguea el error y la operación principal continúa sin interrumpirse. Esto evita que un fallo de email bloquee una entrega.

### 16.3 Notificaciones in-app (Mercure realtime)

**Servicio:** `NotificationService`
**Entidad:** `Notification` (user, type, title, message, channel, payload, isRead, createdAt)
**Endpoint lectura:** `/api/notifications`
**Endpoint marcar leída:** `/api/notifications/{id}/read`

**Proceso:**

1. Se crea la entidad `Notification` asociada al usuario destino
2. Se persiste en base de datos (PostgreSQL)
3. Se publica una actualización Mercure al topic `/users/{userId}/notifications` con:
   ```json
   {
     "type": "notification_count",
     "unread_count": 5
   }
   ```
4. El frontend recibe el SSE y actualiza el contador de notificaciones sin recargar la página

**Notificación a todos los usuarios de un cliente:**

`notifyCustomerUsers()` busca todos los User con el customer dado, crea una Notification para cada uno, y publica actualizaciones Mercure individuales. Útil para eventos que afectan al cliente completo (ej: ruta completada).

**Decisión de diseño:** Si Mercure falla al publicar, la notificación ya está guardada en BD. El usuario la verá al recargar la página, aunque no reciba el push en tiempo real. La excepción se silencia intencionalmente.

---

## 17. Flujo: Albarán de entrega

**Quién:** ROLE_OPERATOR
**Endpoint:** `GET /api/routes/{publicId}/delivery-note`
**Servicios:** RouteOptimizationApiController → DeliveryNoteGenerator

Genera un albarán digital con:
- Número: `ALB-{8_char_ulid}-{yymmdd}`
- Datos de ruta: nombre, conductor, vehículo
- Datos del cliente: nombre, dirección
- Lista de paradas con: dirección, destinatario, referencia del envío, bultos (peso, volumen, EAN, descripción)
- Totales: peso, volumen, bultos, paradas

---

## 18. Flujo: Vista del cliente

### 18.1 Rutas del cliente

**Quién:** ROLE_CUSTOMER
**Endpoint:** `/customer/routes`

El cliente ve sus rutas filtradas automáticamente por tenant. Para cada ruta: nombre, estado, progreso de entregas (entregadas / total), vehículo asignado.

El detalle de ruta (`/customer/routes/{publicId}`) muestra un mapa con todas las paradas y la posición del vehículo en tiempo real vía Mercure.

### 18.2 Envíos del cliente

**Quién:** ROLE_CUSTOMER
**Endpoint:** `/customer/shipments`

Lista de envíos con búsqueda por referencia o destinatario, filtro por estado y rango de fechas. Cada envío muestra su último evento.

El detalle (`/customer/shipments/{publicId}`) muestra la línea de tiempo completa de eventos.

---

## 19. Flujo: Tracking público

**Quién:** Cualquiera (sin autenticación)
**Endpoint:** `/track/{trackingToken}`
**Servicios:** PublicTrackingController

Permite a destinatarios consultar el estado de su envío usando el token de tracking (formato TRK-XXXX-XXXX) que reciben por SMS o email. Muestra el último estado sin requerir login.

---

## 20. Flujo: Reporting y dashboards

**Quién:** ROLE_ADMIN
**Servicio:** `ReportingService`

El sistema de reporting proporciona métricas operativas para toma de decisiones. Todas las consultas se hacen directamente a PostgreSQL con Doctrine QueryBuilder.

### 20.1 Informe de entregas (`getDeliveryReport`)

**Filtros disponibles:** Rango de fechas (`from`/`to`), cliente específico, conductor específico.

**Métricas calculadas:**

| Métrica | Descripción | Fórmula |
|---------|-------------|---------|
| `total_deliveries` | Paradas entregadas exitosamente | COUNT(RouteStop.status = DELIVERED) |
| `total_exceptions` | Paradas con incidencia | COUNT(RouteStop.status = EXCEPTION) |
| `success_rate` | Porcentaje de éxito | deliveries / (deliveries + exceptions) × 100 |
| `avg_deliveries_per_route` | Entregas promedio por ruta | deliveries / rutas_completadas |

**Desgloses incluidos:**

- **Por conductor:** driver_name, deliveries, exceptions, routes — ordenados por entregas descendente
- **Por cliente:** customer_name, deliveries, exceptions, routes — ordenados por entregas descendente

### 20.2 Rendimiento de conductor (`getDriverPerformance`)

**Parámetros:** Conductor, rango de fechas.

| Métrica | Descripción |
|---------|-------------|
| `routes_completed` | Rutas finalizadas (status = DONE) en el período |
| `stops_delivered` | Paradas entregadas con éxito |
| `stops_exception` | Paradas con incidencia |
| `avg_stops_per_hour` | Velocidad de entrega — se calcula con la duración real de las rutas (endAt - startAt) |
| `exception_rate` | Porcentaje de incidencias |

**Decisión de diseño:** `avg_stops_per_hour` usa tiempos reales de inicio/fin de ruta, no estimaciones. Esto mide la eficiencia real del conductor incluyendo paradas, tráfico, y descansos.

### 20.3 Informe de cliente (`getCustomerReport`)

**Parámetros:** Cliente, rango de fechas.

Proporciona una vista desde la perspectiva del cliente:
- Total de envíos creados en el período
- Envíos entregados, con excepción, y pendientes
- Tasa de completitud (`completion_rate`)

### 20.4 Tendencias temporales (`getTrendData`)

**Parámetros:** Período (`week` o `month`), número de períodos (por defecto 12).

Genera una serie temporal con entregas, excepciones y rutas completadas por período. Útil para gráficos de evolución en el dashboard.

**Ejemplo de salida (últimas 4 semanas):**
```json
[
  {"period_label": "10 Feb", "deliveries": 145, "exceptions": 12, "routes_completed": 8},
  {"period_label": "17 Feb", "deliveries": 162, "exceptions": 9, "routes_completed": 10},
  {"period_label": "24 Feb", "deliveries": 138, "exceptions": 15, "routes_completed": 7},
  {"period_label": "03 Mar", "deliveries": 171, "exceptions": 8, "routes_completed": 11}
]
```

### 20.5 Métricas de dashboard

| Método | Uso | Datos |
|--------|-----|-------|
| `getDailyDeliveries(days)` | Mini gráfico de barras (últimos 7 días por defecto) | fecha + entregas por día |
| `getTopDrivers(limit, days)` | Ranking de conductores más productivos | nombre, email, entregas |
| `getDriverRanking(from, to)` | Tabla completa de rendimiento de todos los conductores | deliveries, exceptions, success_rate, routes |
| `getStopStatusDistribution(from, to)` | Gráfico de tarta de estados de paradas | delivered, exception, pending, skipped |

---

## 21. Flujo: Búsqueda global

**Servicio:** `SearchService`
**Dependencias:** Doctrine EntityManager, UrlGenerator

La búsqueda global permite encontrar envíos, rutas y vehículos desde una barra de búsqueda unificada. Los resultados se filtran según el rol del usuario.

### 21.1 Alcance por rol

| Rol | Busca en |
|-----|----------|
| ROLE_ADMIN | Envíos + Rutas + Vehículos (todos) |
| ROLE_CUSTOMER | Envíos propios + Rutas propias (filtrado por customer) |
| ROLE_DRIVER | No tiene acceso a búsqueda global |

### 21.2 Proceso de búsqueda

1. Se valida que el query tenga al menos 2 caracteres
2. Se busca por coincidencia parcial (LIKE) en campos clave:
   - **Envíos:** `reference`, `recipientName` — URL de resultado: detalle del envío
   - **Rutas:** `name` — URL de resultado: edición (admin) o detalle (customer)
   - **Vehículos:** `name` — URL de resultado: edición del vehículo (solo admin)
3. Máximo 10 resultados por tipo (30 resultados totales máximo)
4. Ordenados por fecha de creación descendente (más recientes primero)

**Formato de resultado:**
```json
[
  {"type": "shipment", "label": "REF-2026-001", "url": "/customer/shipments/01H...", "extra": "María García"},
  {"type": "route", "label": "Ruta Madrid Centro", "url": "/admin/routes/01H.../edit", "extra": "ACTIVE"},
  {"type": "vehicle", "label": "Furgoneta-01", "url": "/admin/vehicles/01H.../edit", "extra": "Activo"}
]
```

**Decisión de diseño:** La búsqueda es case-insensitive (LOWER en ambos lados). No usa índices full-text de PostgreSQL — para el volumen actual, LIKE con índices B-tree es suficiente. Si el volumen crece, se puede migrar a `pg_trgm` o Elasticsearch.

---

## 22. Flujo: Facturación

**Servicio:** `BillingService`

Proporciona resúmenes de facturación por cliente y período. Actualmente genera datos para facturación manual — no emite facturas automáticamente.

### 22.1 Resumen de cliente (`getCustomerSummary`)

**Parámetros:** Cliente, rango de fechas (`from`, `to`).

**Métricas:**

| Campo | Descripción | Fuente |
|-------|-------------|--------|
| `total_shipments` | Envíos creados en el período | Tabla `shipment` (por `created_at`) |
| `total_delivered` | Envíos entregados | Tabla `shipment_event` (event_type = DELIVERED) |
| `total_exceptions` | Envíos con incidencia | Tabla `shipment_event` (event_type = EXCEPTION) |
| `billable_deliveries` | Entregas facturables | Actualmente = `total_delivered` |

**Decisión de diseño:** `billable_deliveries` es igual a `total_delivered` por ahora. El campo existe separado para permitir en el futuro reglas de facturación más complejas (ej: no facturar reenvíos, descuentos por volumen, etc.).

**Implementación técnica:** A diferencia del ReportingService que usa Doctrine QueryBuilder (DQL), BillingService usa SQL nativo vía `$em->getConnection()` para consultas más directas y eficientes contra `shipment_event`.

---

## 23. Auditoría y seguridad

### 23.1 Audit Log automático (Doctrine Listener)

**Servicio:** `AuditSubscriber` (Doctrine event listener)
**Eventos escuchados:** `preUpdate`, `postPersist`, `postUpdate`, `postRemove`

El sistema registra automáticamente cada CREATE, UPDATE y DELETE en entidades auditadas, sin intervención del código de negocio.

**Entidades auditadas:**
- `User` — Creación/modificación de usuarios
- `Route` — Cambios de estado, asignación de conductor/vehículo
- `Shipment` — Modificaciones de envíos
- `Customer` — Cambios en datos de clientes

**Tracking de cambios a nivel de campo:**

En operaciones UPDATE, el sistema captura el changeset completo de Doctrine: qué campos cambiaron, valor anterior y valor nuevo.

```json
{
  "action": "UPDATE",
  "entityType": "Route",
  "entityId": "01HXYZ...",
  "changes": {
    "status": {"old": "PLANNED", "new": "ACTIVE"},
    "startAt": {"old": null, "new": "2026-03-06T09:00:00+01:00"}
  }
}
```

**Normalización de valores:** El subscriber normaliza automáticamente:
- `DateTimeInterface` → formato ISO 8601 (ATOM)
- `BackedEnum` → su valor string/int
- Entidades con `getPublicIdString()` → su public_id
- Campo `passwordHash` → se enmascara como `***` (nunca se registra el hash real)

**Protección anti-recursión:** Los registros `AuditLog` no se auditan a sí mismos (evita loop infinito).

### 23.2 Audit Log manual (AuditLogger service)

**Servicio:** `AuditLogger`

Para operaciones que no son simples CRUD de Doctrine (entregas, excepciones, acciones de conductor), los servicios llaman manualmente a `AuditLogger::log()`:

```php
$auditLogger->log(
    actor: $driver,
    action: 'DELIVER_STOP',
    entityType: 'RouteStop',
    entityId: $stop->getPublicIdString(),
    payload: ['shipment_ref' => $shipment->getReference()],
    changes: null,
);
```

**Datos registrados por cada entrada:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `actor` | User (nullable) | Usuario que realizó la acción (null para acciones del sistema) |
| `action` | string | Tipo: CREATE, UPDATE, DELETE, DELIVER_STOP, EXCEPTION_STOP, etc. |
| `entityType` | string | Nombre corto de la entidad (sin namespace) |
| `entityId` | string | Public ID de la entidad afectada |
| `payload` | JSON | Datos adicionales específicos de la acción |
| `changes` | JSON (nullable) | Changeset campo a campo (solo en UPDATEs) |
| `ipAddress` | string (nullable) | IP del cliente (extraída de RequestStack) |
| `createdAt` | DateTimeImmutable | Timestamp del evento |

**Índices para consulta eficiente:**
- `idx_audit_log_entity` → `(entity_type, entity_id)` — buscar historial de una entidad
- `idx_audit_log_action` → `(action)` — filtrar por tipo de acción
- `idx_audit_log_created_at` → `(created_at)` — ordenar cronológicamente

### 23.3 Seguridad de sesiones

| Aspecto | Configuración |
|---------|--------------|
| **Storage** | Redis (`RedisSessionHandler`) |
| **Prefijo** | `sess:transporte:` |
| **Cookie secure** | `auto` (HTTPS en producción, HTTP en desarrollo) |
| **Cookie httponly** | `true` (no accesible desde JavaScript) |
| **Cookie samesite** | `lax` (protección CSRF parcial) |
| **TTL** | 43200 segundos (12 horas) |
| **GC maxlifetime** | 43200 segundos |

### 23.4 Protección de login

**Mecanismo:** Symfony `login_throttling` + rate limiter con sliding window.

| Parámetro | Valor |
|-----------|-------|
| Máximo intentos | 5 por ventana |
| Ventana | Sliding window de 1 minuto |
| CSRF | Habilitado (`enable_csrf: true`) |

**Flujo de login:**
1. El usuario envía email + contraseña + token CSRF a `POST /login`
2. `UserChecker` verifica que la cuenta esté activa (`isActive = true`)
3. Si el usuario ha excedido 5 intentos en el último minuto → HTTP 429 (Too Many Requests)
4. Si las credenciales son correctas → sesión creada en Redis
5. Si falla → se incrementa el contador del rate limiter

### 23.5 Headers de seguridad

**Servicio:** `SecurityHeadersSubscriber` (kernel.response event)

Se añaden automáticamente a cada respuesta HTTP:

| Header | Valor | Protección |
|--------|-------|------------|
| `X-Frame-Options` | `DENY` | Previene clickjacking (iframes) |
| `X-Content-Type-Options` | `nosniff` | Previene MIME sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Controla qué info se envía en Referer |
| `Content-Security-Policy` | Ver detalle abajo | Previene XSS e inyección de recursos |

**Detalle de Content-Security-Policy:**

```
default-src 'self';
script-src 'self' 'unsafe-inline' 'unsafe-eval' unpkg.com cdn.tailwindcss.com cdn.jsdelivr.net;
style-src 'self' 'unsafe-inline' unpkg.com cdn.tailwindcss.com;
font-src 'self' data:;
img-src 'self' *.tile.openstreetmap.org *.basemaps.cartocdn.com unpkg.com data:;
connect-src 'self' unpkg.com nominatim.openstreetmap.org {mercure_origin};
frame-ancestors 'self';
object-src 'none';
base-uri 'self';
```

**Decisión de diseño:** `script-src` permite `unsafe-inline` y `unsafe-eval` porque Twig y Turbo generan scripts inline. `img-src` permite tiles de OpenStreetMap y CartoDB para los mapas de Leaflet. `connect-src` incluye el origen de Mercure para SSE y Nominatim para geocodificación inversa.

---

## 24. Infraestructura y servicios

### 24.1 Stack de servicios (desarrollo local)

| Servicio | Imagen Docker | Puerto host | Función |
|----------|--------------|-------------|---------|
| app | php:8.4-cli-bookworm | 8000 | Symfony 7.4 + PHP built-in server |
| db | postgres:16-alpine | 5432 | Base de datos principal |
| redis | redis:7-alpine | 6379 | Sesiones |
| mercure | dunglas/mercure | 3000 | SSE realtime |
| traccar | traccar/traccar | 8082 (API) + 5055 (GPS) | Tracking GPS |
| traccar_db | postgres:16-alpine | 5433 | BD dedicada para Traccar |
| osrm | osrm/osrm-backend | 5000 | Motor de rutas por carretera |
| vroom | ghcr.io/vroom-project/vroom-docker | 5100 | Optimizador VRP |

### 24.2 Flujo de datos entre servicios

```
                    ┌──────────────────────────────────────────┐
                    │             Symfony App (PHP 8.4)         │
                    │                                          │
                    │  Controllers → Services → Doctrine ORM   │
                    └──┬──────┬───────┬───────┬──────┬────────┘
                       │      │       │       │      │
                  ┌────┘  ┌───┘   ┌───┘   ┌───┘  ┌──┘
                  ▼       ▼       ▼       ▼      ▼
             PostgreSQL  Redis  Mercure  VROOM  Traccar
               (datos)  (sess) (SSE)     │     (GPS)
                                         │
                                         ▼
                                       OSRM
                                    (carreteras)
```

### 24.3 Preparación del entorno

```bash
# 1. Preparar mapa OSRM (solo primera vez, ~5 min)
./docker/osrm/prepare-map.sh

# 2. Levantar todos los servicios
docker compose -f docker-compose.local.yml up -d --build

# 3. Instalar dependencias y preparar BD
docker compose -f docker-compose.local.yml exec app bash -c \
  "composer install && php bin/console doctrine:migrations:migrate -n && \
   php bin/console doctrine:fixtures:load -n && php -S 0.0.0.0:8000 -t public"
```
