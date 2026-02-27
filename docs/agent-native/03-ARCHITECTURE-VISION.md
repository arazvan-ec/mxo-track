# Visión Arquitectónica: MXO-Track Agent-Native

> Fecha: 2026-02-27
> Estado: Borrador v1

---

## Concepto

Transformar MXO-Track de una aplicación web tradicional de logística a un **sistema agent-native** donde los agentes IA son ciudadanos de primera clase. Los agentes pueden ejecutar cualquier operación que un operador humano haría, usando **tools atómicos** compuestos por prompts.

---

## Diagrama de Alto Nivel

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLIENTES B2B                                 │
│  ┌─────────┐  ┌──────┐  ┌─────┐  ┌──────────┐                  │
│  │   API    │  │  CSV │  │ SMS │  │  Portal  │                  │
│  └────┬─────┘  └──┬───┘  └──┬──┘  └────┬─────┘                  │
│       └───────────┴─────────┴───────────┘                        │
└───────────────────────┬──────────────────────────────────────────┘
                        │
┌───────────────────────▼──────────────────────────────────────────┐
│                   AGENT LAYER                                     │
│                                                                   │
│  ┌──────────────────────────────────────────────────────┐        │
│  │              AGENT ORCHESTRATOR                        │        │
│  │  (Recibe peticiones → descompone → ejecuta tools)     │        │
│  └──────────────────────┬────────────────────────────────┘        │
│                         │                                         │
│  ┌──────────────────────▼────────────────────────────────┐        │
│  │              ATOMIC TOOLS LAYER                        │        │
│  │                                                        │        │
│  │  ┌─────────────┐ ┌──────────────┐ ┌───────────────┐  │        │
│  │  │ Shipment    │ │ Route        │ │ Vehicle       │  │        │
│  │  │ Tools       │ │ Tools        │ │ Tools         │  │        │
│  │  ├─────────────┤ ├──────────────┤ ├───────────────┤  │        │
│  │  │create       │ │create        │ │list_available │  │        │
│  │  │add_parcel   │ │add_stop      │ │check_capacity │  │        │
│  │  │update_status│ │optimize      │ │assign_to_route│  │        │
│  │  │get_status   │ │validate_load │ │get_position   │  │        │
│  │  │import_csv   │ │start/finish  │ │get_isochrone  │  │        │
│  │  └─────────────┘ └──────────────┘ └───────────────┘  │        │
│  │                                                        │        │
│  │  ┌─────────────┐ ┌──────────────┐ ┌───────────────┐  │        │
│  │  │ Customer    │ │ Notification │ │ Analytics     │  │        │
│  │  │ Tools       │ │ Tools        │ │ Tools         │  │        │
│  │  ├─────────────┤ ├──────────────┤ ├───────────────┤  │        │
│  │  │get_profile  │ │notify_status │ │cost_per_route │  │        │
│  │  │get_frequency│ │notify_eta    │ │cost_per_parcel│  │        │
│  │  │get_prefs    │ │notify_bulk   │ │driver_perf    │  │        │
│  │  │set_prefs    │ │send_webhook  │ │zone_analysis  │  │        │
│  │  └─────────────┘ └──────────────┘ └───────────────┘  │        │
│  │                                                        │        │
│  │  ┌─────────────┐ ┌──────────────┐                     │        │
│  │  │ Document    │ │ Geo/Iso      │                     │        │
│  │  │ Tools       │ │ Tools        │                     │        │
│  │  ├─────────────┤ ├──────────────┤                     │        │
│  │  │gen_albaran  │ │calc_isochrone│                     │        │
│  │  │gen_report   │ │calc_distance │                     │        │
│  │  │gen_label    │ │geocode_addr  │                     │        │
│  │  └─────────────┘ └──────────────┘                     │        │
│  └────────────────────────────────────────────────────────┘        │
│                                                                   │
│  ┌──────────────────────────────────────────────────────┐        │
│  │              CONTEXT SYSTEM                            │        │
│  │  - Context.md por agente (memoria de trabajo)         │        │
│  │  - Historial de acciones                              │        │
│  │  - Preferencias de cliente acumuladas                 │        │
│  │  - Métricas de rendimiento                            │        │
│  └──────────────────────────────────────────────────────┘        │
└───────────────────────────────────────────────────────────────────┘
                        │
┌───────────────────────▼──────────────────────────────────────────┐
│                   DOMAIN LAYER (Symfony)                           │
│                                                                   │
│  Entities: Customer, Shipment, Parcel, Route, RouteStop,         │
│            Vehicle, Driver, ShipmentEvent, Pod, AuditLog          │
│                                                                   │
│  Services: RouteOptimization, IsochroneCalculation,              │
│            CapacityValidation, CsvImport, Notifications,          │
│            Traccar, Mercure, Billing, ETA                         │
│                                                                   │
│  Infrastructure: PostgreSQL, Redis, Mercure, Traccar, ORS        │
└───────────────────────────────────────────────────────────────────┘
```

---

## Tools Atómicos — Catálogo Completo

### Shipment Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `create_shipment` | customer_id, reference, service_type, recipient | shipment_public_id | Crea un envío |
| `add_parcel` | shipment_id, weight_kg, volume_m3, ean?, description? | parcel_public_id | Añade bulto a envío |
| `update_shipment_status` | shipment_id, new_status, notes? | event_id | Cambia estado del envío |
| `get_shipment_status` | shipment_id | status, events[], parcels[] | Consulta estado completo |
| `import_csv` | file_path, customer_id | {created, skipped, errors, shipment_ids[]} | Importa envíos desde CSV |
| `search_shipments` | filters (customer, status, date_range, reference) | shipments[] | Busca envíos |

### Route Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `create_route` | name, customer_id, vehicle_id?, driver_id?, date? | route_public_id | Crea ruta |
| `add_stop_to_route` | route_id, address, lat, lng, shipment_id?, delivery_window? | stop_public_id | Añade parada |
| `optimize_route` | route_id, strategy? | {optimized_stops[], distance_before, distance_after} | Optimiza orden |
| `validate_route_capacity` | route_id | {valid: bool, total_weight, total_volume, vehicle_capacity} | Valida carga vs vehículo |
| `start_route` | route_id | status | Inicia ruta |
| `finish_route` | route_id | status, metrics | Finaliza ruta |
| `auto_create_routes` | shipment_ids[], vehicle_ids[], constraints? | routes[] | Crea rutas automáticamente |

### Vehicle Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `list_vehicles` | filters? (active, available, capacity_min) | vehicles[] | Lista vehículos |
| `check_vehicle_capacity` | vehicle_id, parcels[] | {fits: bool, remaining_weight, remaining_volume} | Verifica capacidad |
| `get_vehicle_position` | vehicle_id | {lat, lng, speed, updated_at} | Posición actual |
| `assign_vehicle_to_route` | vehicle_id, route_id | confirmation | Asigna vehículo a ruta |

### Customer Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `get_customer_profile` | customer_id | {name, frequency_category, preferences, stats} | Perfil completo |
| `get_customer_frequency` | customer_id | {category, total_shipments, avg_per_week} | Frecuencia |
| `set_delivery_preferences` | customer_id, time_window?, priority? | confirmation | Configura preferencias |
| `get_customer_shipments` | customer_id, filters? | shipments[] | Envíos del cliente |

### Notification Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `notify_status_change` | shipment_id, channel (email/sms/webhook/push) | notification_id | Notifica cambio estado |
| `notify_eta` | shipment_id, eta, channel | notification_id | Informa ETA |
| `bulk_notify` | shipment_ids[], message, channel | notification_ids[] | Notificación masiva |

### Geo/Isochrone Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `calculate_isochrone` | lat, lng, time_minutes, profile? | {polygon, area_km2} | Isócrona desde punto |
| `calculate_distance` | origin, destination | {distance_km, duration_minutes} | Distancia real |
| `geocode_address` | address_string | {lat, lng, formatted_address} | Geocodifica dirección |
| `reverse_geocode` | lat, lng | {address, city, postal_code} | Dirección desde coords |

### Analytics Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `cost_per_route` | route_id | {total_cost, per_stop, per_parcel, distance_cost} | Coste por ruta |
| `cost_per_parcel` | filters? | {avg_cost, by_service_type, by_zone} | Coste por bulto |
| `driver_performance` | driver_id, period? | {deliveries, success_rate, avg_time, rgu_compliance} | Rendimiento |
| `zone_analysis` | zone_polygon?, period? | {deliveries, success_rate, avg_cost} | Análisis por zona |

### Document Tools
| Tool | Input | Output | Descripción |
|------|-------|--------|-------------|
| `generate_delivery_note` | shipment_id | {pdf_url, albaran_number} | Genera albarán |
| `generate_route_report` | route_id | {pdf_url, summary} | Informe de ruta |
| `generate_labels` | parcel_ids[] | {pdf_url, label_count} | Etiquetas de bultos |

---

## Modelo de Datos Extendido (nuevas entidades)

### Parcel (Bulto) — NUEVA
```
Parcel
├── id (BIGINT)
├── public_id (ULID)
├── shipment_id (FK → Shipment)
├── sequence (INT) — "1/5", "2/5"
├── weight_kg (DECIMAL, obligatorio)
├── volume_m3 (DECIMAL, obligatorio)
├── ean (VARCHAR, nullable)
├── description (TEXT, nullable)
├── status (ENUM: PENDING, LOADED, IN_ROUTE, DELIVERED, RETURNED, ABSENT)
├── created_at
└── updated_at
```

### ServiceType — NUEVO (Enum)
```
DELIVERY          — Paquetería entrega
DELIVERY_PICKUP   — Entrega y recogida
RETURN            — Devolución
```

### Vehicle (extendido)
```
+ max_weight_kg (DECIMAL)
+ max_volume_m3 (DECIMAL)
+ license_plate (VARCHAR)
+ vehicle_type (ENUM: VAN, TRUCK, MOTORCYCLE)
```

### Shipment (extendido)
```
+ service_type (ServiceType enum)
+ total_parcels (INT)
+ estimated_delivery_date (DATE)
+ preferred_time_window_start (TIME)
+ preferred_time_window_end (TIME)
```

### Customer (extendido)
```
+ email (VARCHAR)
+ frequency_category (ENUM: INFREQUENT, FREQUENT, VERY_FREQUENT, SUPER_FREQUENT)
+ preferred_delivery_morning (BOOL)
+ preferred_delivery_afternoon (BOOL)
+ notification_preferences (JSON)
```

### ParcelStatus — NUEVO (Enum)
```
PENDING     — Pendiente de carga
LOADED      — Cargado en vehículo
IN_ROUTE    — En ruta
DELIVERED   — Entregado
RETURNED    — Devuelto
ABSENT      — Ausencia del destinatario
EXCEPTION   — Excepción
```

---

## Flujos Agent-Native Clave

### Flujo 1: "Cliente Raúl crea 800 pedidos"

```
INPUT: CSV con 800 pedidos

AGENTE ejecuta secuencialmente:
1. import_csv(file, customer_id="raul")
   → 800 shipments creados

2. Para cada shipment, valida parcels:
   validate_parcels() → peso/volumen OK

3. list_vehicles(available=true)
   → 5 furgonetas disponibles

4. calculate_isochrones(depot, [30min, 60min, 90min])
   → Zonas de entrega agrupadas

5. auto_create_routes(shipments, vehicles, strategy="farthest_first")
   → 12 rutas creadas, cada una validada contra capacidad

6. Para cada ruta:
   validate_route_capacity(route_id)
   → ¿Cabe todo? Si no, redistribuir

7. bulk_notify(customer="raul", "12 rutas creadas, X entregas/ruta")
   → Cliente informado

OUTPUT: 12 rutas optimizadas, validadas, cliente notificado
```

### Flujo 2: "Camión averiado, reorganizar rutas de mañana"

```
INPUT: "El camión TRK-003 se ha averiado"

AGENTE razona y ejecuta:
1. get_vehicle_routes(vehicle="TRK-003", date=tomorrow)
   → Ruta R-045 con 23 paradas

2. list_vehicles(available=true, date=tomorrow)
   → 3 vehículos libres

3. Para cada parada de R-045:
   check_vehicle_capacity(vehicle_id, parcels)
   → ¿En qué vehículo cabe?

4. Redistribuye por isócrona/proximidad:
   auto_create_routes(stops, available_vehicles)
   → 2 rutas nuevas

5. notify_drivers(affected_drivers, changes)
6. notify_customers(affected_shipments, new_etas)

OUTPUT: Rutas redistribuidas, todos informados
```

### Flujo 3: Dashboard — "¿Cuánto cuesta cada ruta hoy?"

```
INPUT: "Dame los costes de hoy"

AGENTE:
1. search_routes(date=today, status=DONE)
2. Para cada ruta: cost_per_route(route_id)
3. Agrega: total, promedio, por km, por bulto

OUTPUT: Tabla de costes con métricas
```

---

## Principios de Implementación

### 1. Paridad
- **Cada acción del panel admin tiene su tool equivalente**
- Los agentes pueden hacer TODO lo que un operador hace en UI
- El UI consume los mismos services que los tools

### 2. Granularidad
- Tools atómicos: `create_shipment`, `add_parcel`, `update_status`
- NO tools monolíticos: `process_all_orders`
- El agente decide la secuencia y la estrategia

### 3. Composabilidad
- "Crear rutas para 800 pedidos" = composición de 10+ tools
- "Reorganizar por avería" = composición diferente de los mismos tools
- Nuevos flujos sin código nuevo

### 4. Capacidad Emergente
- El agente puede descubrir patrones que no programamos
- "Los clientes de zona norte siempre piden por la mañana"
- "El transportista Juan es 20% más rápido en zona centro"

### 5. Mejora Continua
- Context acumulado por cliente, transportista, zona
- Prompts refinados con datos reales
- Sin deploys para mejorar comportamiento
