# Plan Maestro de Fases — MXO-Track Agent-Native

> Fecha: 2026-02-27
> Este es el plan COMPLETO del proyecto, no solo SGA

---

## Vista General

```
FASE 1: Modelo de Datos (entidades base que faltan)
FASE 2: Motor de Reglas Core (tools + services)
FASE 3: Optimizador de Rutas Avanzado
FASE 4: SGA Fase 0-1 (almacén básico)
FASE 5: Notificaciones + Portal B2B
FASE 6: Dashboard + Analytics + Costes
FASE 7: Importación CSV Avanzada + Demo
FASE 8: SGA Fase 2-3 (recepción, picking, packing)
FASE 9: Capa LLM (agente inteligente)
FASE 10: Documentos (albaranes, etiquetas)
FASE 11: SGA Fase 4-5 (devoluciones, multi-almacén)
FASE 12: Isócronas + RGU + Productividad
```

---

## FASE 1: Modelo de Datos Extendido

### Objetivo
Añadir las entidades y campos que faltan para soportar todo lo que viene después.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 1.1 | Crear entidad `Parcel` | peso, volumen, EAN, descripción, sequence |
| 1.2 | Crear enum `ServiceType` | DELIVERY, PICKUP, RETURN, DELIVERY_AND_PICKUP, EXCHANGE |
| 1.3 | Crear enum `PriorityLevel` | STANDARD, SAME_DAY, NEXT_DAY, NEXT_DAY_AM, SCHEDULED |
| 1.4 | Crear enum `DeliveryLocation` | DOOR, PICKUP_POINT, LOCKER, DOCK |
| 1.5 | Crear enum `ParcelStatus` | PENDING, LOADED, IN_ROUTE, DELIVERED, RETURNED, ABSENT, EXCEPTION |
| 1.6 | Crear enum `ShipmentStatus` | REGISTERED, PICKED_UP, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, ATTEMPT_FAILED, RETURNED, CANCELLED |
| 1.7 | Extender `Shipment` | +serviceType, +priority, +deliveryLocation, +handlingFlags, +cashOnDeliveryAmount, +signatureRequired, +idVerificationRequired, +status, +totalParcels, +estimatedDeliveryDate, +preferredTimeWindowStart/End |
| 1.8 | Extender `Vehicle` | +maxWeightKg, +maxVolumeM3, +licensePlate, +vehicleType |
| 1.9 | Crear enum `VehicleType` | VAN, TRUCK, MOTORCYCLE, CAR |
| 1.10 | Extender `Customer` | +email, +frequencyCategory, +preferredDeliveryMorning/Afternoon, +notificationPreferences (JSON) |
| 1.11 | Crear enum `CustomerFrequency` | INFREQUENT, FREQUENT, VERY_FREQUENT, SUPER_FREQUENT |
| 1.12 | Ampliar `ShipmentEventType` | +DELIVERY_ATTEMPTED, +RETURNED, +CANCELLED |
| 1.13 | Ampliar `ExceptionCode` | +INCOMPLETE_ADDRESS, +ACCESS_RESTRICTED, +NO_TIME |
| 1.14 | Crear migraciones Doctrine | Todo lo anterior |
| 1.15 | Actualizar fixtures | Datos de ejemplo con nuevos campos |
| 1.16 | Verificar schema | `doctrine:schema:validate` |

### Entregable
Modelo de datos completo, migraciones funcionando, fixtures cargadas.

---

## FASE 2: Motor de Reglas Core (Services/Tools)

### Objetivo
Crear los services que representan los "tools atómicos" del sistema. Estos services son consumidos tanto por los controladores (UI) como por el futuro agente LLM.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 2.1 | `ShipmentService` | create, addParcel, updateStatus, getStatus, search |
| 2.2 | `ShipmentStatusMachine` | Validar transiciones de estado (tabla de transiciones) |
| 2.3 | `ParcelService` | create, updateStatus, getByShipment |
| 2.4 | `VehicleCapacityService` | checkCapacity(vehicle, parcels[]), getRemainingCapacity |
| 2.5 | `RouteCreationService` | create, addStop, removeStop, assignVehicle, assignDriver |
| 2.6 | `CustomerProfileService` | getProfile, updateFrequency, setPreferences |
| 2.7 | Endpoints REST para cada service | JSON in/out, consistente con ApiErrorResponder |
| 2.8 | Tests unitarios para cada service | |
| 2.9 | Tool Schema definitions (JSON Schema) | Input/output de cada tool documentado |

### Entregable
API REST funcional con todos los tools atómicos. Cada tool tiene schema, validación y tests.

---

## FASE 3: Optimizador de Rutas Avanzado

### Objetivo
Mejorar el optimizador existente con capacidad, ventanas de tiempo y creación automática de rutas desde lista de pedidos.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 3.1 | Estrategia "farthest-first" | Empezar desde el punto más alejado del depósito |
| 3.2 | Integrar capacidad en optimización | No añadir más paradas si peso/volumen excede |
| 3.3 | Integrar ventanas de tiempo | Priorizar paradas con ventana estrecha |
| 3.4 | `AutoRouteCreationService` | De N shipments → M rutas automáticas |
| 3.5 | Algoritmo de clustering geográfico | Agrupar shipments por zona (k-means o grid) |
| 3.6 | Bin-packing por vehículo | Asignar shipments a vehículos respetando capacidad |
| 3.7 | Validación pre-ruta completa | ¿cabe todo? ¿ventanas posibles? ¿conductor disponible? |
| 3.8 | Endpoint: `POST /api/routes/auto-create` | Body: shipment_ids[], vehicle_ids[], constraints |
| 3.9 | Tests con volumen (800 pedidos) | Verificar < 30s, 0 pedidos perdidos |

### Entregable
"Dame 800 pedidos y 5 furgonetas" → sistema genera N rutas optimizadas en < 30s.

---

## FASE 4: SGA Básico (Fases 0-1 del doc SGA)

### Objetivo
Registro de entrada/salida + estructura física del almacén.

### Tareas
Ver documento `12-SGA-PHASES-COMPLETE.md`, Fases 0 y 1. Resumen:

| # | Tarea |
|---|-------|
| 4.1 | Entidad `WarehouseMovement` + enum |
| 4.2 | Service de registro de movimientos |
| 4.3 | API de escaneo |
| 4.4 | Integración con ShipmentEvent (IN_HUB) |
| 4.5 | Entidades `Warehouse`, `WarehouseZone`, `StorageLocation` |
| 4.6 | Service de configuración de almacén |
| 4.7 | Vista admin de estructura del almacén |
| 4.8 | Búsqueda "¿dónde está este paquete?" |
| 4.9 | Tests y fixtures |

### Entregable
Operario escanea paquetes entrando/saliendo. Se ve en timeline del envío.

---

## FASE 5: Notificaciones + Portal B2B

### Objetivo
El cliente B2B ve sus envíos, recibe notificaciones por cada cambio de estado.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 5.1 | Notificaciones por email por cambio de estado | Extender NotificationService existente |
| 5.2 | Notificaciones webhook por cambio de estado | Extender WebhookNotificationService existente |
| 5.3 | Configuración de preferencias de notificación | Por cliente: qué estados notificar, qué canales |
| 5.4 | Portal B2B: vista de envíos con estados | Lista con filtros y búsqueda |
| 5.5 | Portal B2B: detalle de envío con timeline | Todos los eventos ordenados |
| 5.6 | Portal B2B: tracking público con mapa | Ya existe PublicTrackingController, extender |
| 5.7 | Portal B2B: propuesta de fecha/franja horaria | Sugerir ventana de entrega al crear envío |
| 5.8 | Mercure SSE para actualizaciones en vivo en portal | Ya existe infraestructura, nuevos topics |
| 5.9 | Tests |

### Entregable
Cliente entra a su portal, ve envíos en vivo, recibe notificaciones automáticas.

---

## FASE 6: Dashboard + Analytics + Costes

### Objetivo
Dashboard operativo con métricas de negocio. Los costes son composables (agent-native).

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 6.1 | Dashboard KPIs principales | Envíos hoy, en ruta, entregados, pendientes |
| 6.2 | Tools atómicos de datos de coste | getRouteDistanceKm, getDriverHours, countParcels, etc. |
| 6.3 | Widget €/ruta | Coste medio por ruta completada |
| 6.4 | Widget €/bulto | Coste medio por bulto entregado |
| 6.5 | Métricas por transportista | Entregas/día, ratio éxito, tiempo medio |
| 6.6 | Métricas por zona | Eficiencia por área geográfica |
| 6.7 | Categorización automática de clientes | Calcular frecuencia y actualizar CustomerFrequency |
| 6.8 | Exportar reportes CSV | |
| 6.9 | Tests |

### Entregable
Dashboard con métricas en vivo: €/ruta, €/bulto, productividad por conductor.

---

## FASE 7: Importación CSV Avanzada + Demo

### Objetivo
El CSV del cliente crea envíos con toda la info nueva (peso, volumen, tipo, prioridad) y opcionalmente genera rutas automáticas.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 7.1 | Actualizar ShipmentCsvImporter | Nuevas columnas: service_type, priority, weight, volume, parcels_count, time_window |
| 7.2 | Validación avanzada del CSV | Peso obligatorio, volumen obligatorio, geocodificación de direcciones |
| 7.3 | Flujo "importar + generar rutas" | CSV → shipments → auto-create routes → preview → confirmar |
| 7.4 | Pantalla de preview de rutas | Mapa con rutas propuestas, poder ajustar antes de aceptar |
| 7.5 | CSV template descargable | Para que el cliente sepa el formato |
| 7.6 | Demo data: 800 pedidos de "Raúl" | CSV de ejemplo con datos realistas |
| 7.7 | Tests con CSV grande |

### Entregable
Demo completa: subir CSV → ver rutas propuestas en mapa → ajustar → aceptar → empieza la logística.

---

## FASE 8: SGA Avanzado (Fases 2-3 del doc SGA)

### Objetivo
Recepción formal, expedición verificada, picking optimizado, packing con etiquetado.

### Tareas
Ver documento `12-SGA-PHASES-COMPLETE.md`, Fases 2 y 3. Resumen:

| # | Tarea |
|---|-------|
| 8.1 | Entidades ReceivingOrder + Lines |
| 8.2 | Entidades ShippingOrder + Lines |
| 8.3 | Entidades PickingOrder + Lines |
| 8.4 | Entidad PackingRecord |
| 8.5 | Services: Receiving, Shipping, Picking, Packing |
| 8.6 | Auto-generar Picking/Shipping al planificar ruta |
| 8.7 | API para PDA/móvil |
| 8.8 | Generador de etiquetas |
| 8.9 | Vistas admin para cada proceso |
| 8.10 | Tests y fixtures |

### Entregable
Flujo completo de almacén: recibir → ubicar → picking → packing → cargar → verificar.

---

## FASE 9: Capa LLM (Agente Inteligente)

### Objetivo
Añadir la capa de agente IA que interpreta lenguaje natural y ejecuta tools.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 9.1 | Integrar Claude API como servicio Symfony | HttpClient, config, retry, logging |
| 9.2 | Definir tool schemas para Claude | JSON Schema de cada tool atómico |
| 9.3 | `AgentOrchestrator` | Recibe texto → envía a Claude con tools → ejecuta resultado |
| 9.4 | Capa de validación post-LLM | Toda acción del LLM pasa por validación de reglas |
| 9.5 | Chat del operador | UI de chat en el dashboard |
| 9.6 | Prompts por dominio | Rutas, envíos, almacén, analytics |
| 9.7 | Contexto acumulado | Memoria de trabajo por cliente, zona, conductor |
| 9.8 | Parseo de emails | Recibir email → extraer datos → crear envío |
| 9.9 | Audit log de acciones del agente | Trazabilidad completa |
| 9.10 | Tests end-to-end | "Crea rutas para 800 pedidos de Raúl" → funciona |

### Entregable
Operador escribe en chat: instrucciones en lenguaje natural → sistema ejecuta.

---

## FASE 10: Documentos (Albaranes, Etiquetas)

### Objetivo
Generar documentos PDF: albaranes de entrega, etiquetas de bultos, informes de ruta.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 10.1 | Integrar librería PDF (TCPDF o Dompdf) | |
| 10.2 | Template de albarán | Datos del envío, bultos, receptor, firma |
| 10.3 | Numeración secuencial de albaranes | ALB-2026-00001 |
| 10.4 | Template de etiqueta de bulto | Código barras, dirección, peso |
| 10.5 | Template de informe de ruta | Paradas, entregas, excepciones |
| 10.6 | Generación batch (todas las etiquetas de una ruta) | |
| 10.7 | Endpoint API para descargar PDFs | |
| 10.8 | Tests |

### Entregable
Un click → albarán PDF. Un click → todas las etiquetas de la ruta.

---

## FASE 11: SGA Avanzado (Fases 4-5 del doc SGA)

### Objetivo
Devoluciones formalizadas, inventario cíclico, multi-almacén.

### Tareas
Ver documento `12-SGA-PHASES-COMPLETE.md`, Fases 4 y 5. Resumen:

| # | Tarea |
|---|-------|
| 11.1 | Entidad ReturnOrder + service |
| 11.2 | Auto-crear devolución desde excepción |
| 11.3 | Flujo inspección + resolución |
| 11.4 | Entidades CycleCount + Lines |
| 11.5 | Planificación y ejecución de conteos |
| 11.6 | Informe de discrepancias |
| 11.7 | Multi-almacén (TransferOrder) |
| 11.8 | Wave picking |
| 11.9 | Reservas de stock |

### Entregable
SGA completo: devoluciones cerradas, inventario verificado, múltiples almacenes.

---

## FASE 12: Isócronas + RGU + Productividad

### Objetivo
Optimización geográfica avanzada con isócronas y métricas de productividad por zona.

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 12.1 | Integrar OpenRouteService Isochrones API | Endpoint ya existe, crear client |
| 12.2 | Calcular isócrona desde depósito | Polígonos de 15, 30, 45, 60 min |
| 12.3 | Crear entidad `DeliveryZone` (RGU) | Zona definida por polígono de isocrona |
| 12.4 | Agrupar entregas por RGU | Asignar shipments a su zona |
| 12.5 | Optimizar rutas por RGU | Una ruta por zona |
| 12.6 | Productividad por transportista por RGU | Entregas/hora, ratio éxito por zona |
| 12.7 | Dashboard de productividad | Mapa con zonas coloreadas por rendimiento |
| 12.8 | Visualización de isócronas en mapa | Polígonos sobre el mapa de flota |
| 12.9 | Tests |

### Entregable
Mapa con zonas de entrega, métricas de productividad por zona y conductor.

---

## Dependencias entre Fases

```
Fase 1 ──→ Fase 2 ──→ Fase 3 ──→ Fase 7 (demo CSV)
  │          │                       │
  │          ├──→ Fase 5 (notif)     │
  │          │                       │
  │          ├──→ Fase 6 (dashboard) │
  │          │                       │
  └──→ Fase 4 (SGA básico)          │
       │                             │
       └──→ Fase 8 (SGA avanzado)   │
            │                        │
            └──→ Fase 11 (SGA full)  │
                                     │
Fase 2 ──→ Fase 9 (LLM) ←──────────┘
  │
  └──→ Fase 10 (documentos)
  │
  └──→ Fase 12 (isócronas)
```

**Fases paralelizables:**
- 4 y 5 pueden avanzar en paralelo (SGA y notificaciones son independientes)
- 6 y 7 pueden avanzar en paralelo (dashboard y CSV import son independientes)
- 10 y 12 pueden avanzar en paralelo (documentos e isócronas son independientes)

---

## Resumen Ejecutivo

| Fase | Nombre | Entidades nuevas | Servicios nuevos | Dependencias |
|:---:|--------|:---:|:---:|---|
| 1 | Modelo de datos | 1 (Parcel) + 6 enums + extensiones | 0 | Ninguna |
| 2 | Tools Core | 0 | 6 services | Fase 1 |
| 3 | Optimizador | 0 | 2 services | Fase 2 |
| 4 | SGA Básico | 4 (Warehouse, Zone, Location, Movement) | 2 | Fase 1 |
| 5 | Notificaciones | 0 | 0 (extiende existentes) | Fase 2 |
| 6 | Dashboard | 0 | 2 | Fase 2 |
| 7 | CSV + Demo | 0 | 1 (extiende existente) | Fase 3 |
| 8 | SGA Avanzado | 6 (Receiving, Shipping, Picking, Packing) | 4 | Fase 4 |
| 9 | LLM Agent | 0 | 2 (Orchestrator, Claude client) | Fase 2 |
| 10 | Documentos | 0 | 3 (PDF generators) | Fase 2 |
| 11 | SGA Full | 3 (Return, CycleCount, Transfer) | 3 | Fase 8 |
| 12 | Isócronas | 1 (DeliveryZone) | 2 | Fase 2 |
