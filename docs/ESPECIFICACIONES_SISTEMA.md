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
20. [Auditoría y seguridad](#20-auditoría-y-seguridad)
21. [Infraestructura y servicios](#21-infraestructura-y-servicios)

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

### 16.1 Webhooks

Si el cliente tiene configurado `webhookUrl`, el sistema envía notificaciones HTTP POST automáticas cuando hay eventos relevantes (entrega, excepción).

**Servicio:** WebhookNotificationService
**Seguridad:** Las llamadas se firman con `WEBHOOK_SECRET` para que el receptor pueda verificar la autenticidad.

### 16.2 Email

**Servicio:** EmailNotificationService
Envía emails para eventos configurados.

### 16.3 Notificaciones in-app

**Servicio:** NotificationService → Notification entity
**Endpoint:** `/api/notifications` (lectura), `/api/notifications/{id}/read` (marcar leída)

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

## 20. Auditoría y seguridad

### 20.1 Audit Log

**Servicios:** AuditLogger → AuditLog entity, AuditSubscriber

Registra automáticamente operaciones sensibles:
- Entregas (quién, cuándo, qué parada)
- Excepciones (motivo, comentario)
- Cambios de estado de ruta
- Login/logout

Cada registro incluye: usuario, acción, entidad afectada, IP, timestamp, datos adicionales (JSON).

### 20.2 Seguridad

- **Sessions:** Redis con prefijo `sess:transporte:` y TTL configurable
- **Login:** form_login con CSRF + rate limiting (máximo 10 intentos por hora)
- **UserChecker:** Verifica que el usuario esté activo antes de autenticar
- **Headers de seguridad:** X-Frame-Options, Content-Security-Policy, etc. vía SecurityHeadersSubscriber
- **Soft delete:** Las entidades no se borran físicamente, se marcan con `deleted_at`

---

## 21. Infraestructura y servicios

### 21.1 Stack de servicios (desarrollo local)

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

### 21.2 Flujo de datos entre servicios

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

### 21.3 Preparación del entorno

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
