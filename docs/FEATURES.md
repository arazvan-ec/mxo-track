# Características del Software — mxo-track

> **Última actualización:** 2026-03-11
> **Versión del documento:** 1.0.0
>
> Este documento describe todas las características funcionales y técnicas del sistema.
> Debe actualizarse cada vez que se añada, modifique o elimine una funcionalidad.

---

## Índice

1. [Visión General](#1-visión-general)
2. [Gestión de Rutas](#2-gestión-de-rutas)
3. [Optimización de Rutas (VROOM + OSRM)](#3-optimización-de-rutas-vroom--osrm)
4. [Planificador de Rutas Interactivo](#4-planificador-de-rutas-interactivo)
5. [Gestión de Envíos (Shipments)](#5-gestión-de-envíos-shipments)
6. [Tracking GPS en Tiempo Real](#6-tracking-gps-en-tiempo-real)
7. [Mapa de Flota](#7-mapa-de-flota)
8. [Portal del Conductor](#8-portal-del-conductor)
9. [Portal del Cliente](#9-portal-del-cliente)
10. [Tracking Público](#10-tracking-público)
11. [Proof of Delivery (POD)](#11-proof-of-delivery-pod)
12. [Notificaciones y Webhooks](#12-notificaciones-y-webhooks)
13. [Reportes y Analítica](#13-reportes-y-analítica)
14. [Inteligencia Artificial](#14-inteligencia-artificial)
15. [Servicio ML (Machine Learning)](#15-servicio-ml-machine-learning)
16. [Multi-Tenant](#16-multi-tenant)
17. [Gestión de Usuarios y Seguridad](#17-gestión-de-usuarios-y-seguridad)
18. [API REST v1](#18-api-rest-v1)
19. [API del Conductor](#19-api-del-conductor)
20. [Importación CSV](#20-importación-csv)
21. [Administración del Sistema](#21-administración-del-sistema)
22. [Infraestructura y Despliegue](#22-infraestructura-y-despliegue)
23. [Modelo de Datos](#23-modelo-de-datos)
24. [Búsqueda](#24-búsqueda)
25. [Providers Configurables por Tenant](#25-providers-configurables-por-tenant)
26. [Sistema de Feedback y Aprendizaje](#26-sistema-de-feedback-y-aprendizaje)
27. [Historial de Cambios](#27-historial-de-cambios)

---

## 1. Visión General

**mxo-track** es un portal de logística y tracking que permite:

- Gestionar flotas de vehículos con tracking GPS en tiempo real
- Planificar y optimizar rutas de entrega usando algoritmos VRP
- Gestionar envíos con ciclo de vida completo (creación → entrega/excepción)
- Proporcionar prueba de entrega (POD) digital
- Ofrecer tracking público a destinatarios
- Análisis e inteligencia artificial aplicada a operaciones logísticas

**Stack tecnológico principal:**
- Backend: Symfony 7.4 LTS, PHP 8.4, PostgreSQL 16, Redis 7
- Tiempo real: Mercure (SSE)
- GPS: Traccar
- Optimización: VROOM (VRP solver) + OSRM (routing engine)
- ML: Python FastAPI con scikit-learn, LightGBM, Prophet
- IA: Claude API, OpenAI API
- Frontend: Twig + Turbo (Hotwire)

---

## 2. Gestión de Rutas

### Funcionalidades

| Característica | Descripción | Ruta |
|---|---|---|
| Listado de rutas | Paginación, filtros por estado/fecha/conductor/cliente | `/admin/routes` |
| Detalle de ruta (en vivo) | Mapa con paradas + lista reactiva via Mercure, métricas de optimización, tracking de vehículo | `/admin/routes/{publicId}/show` |
| Crear ruta | Formulario con asignación de conductor, vehículo, cliente y origen | `/admin/routes/new` |
| Editar ruta | Modificar detalles, gestionar paradas, calcular distancias | `/admin/routes/{publicId}/edit` |
| Cancelar ruta | Cambia estado a CANCELLED | `/admin/routes/{publicId}/delete` |
| Añadir paradas | AJAX para agregar paradas a una ruta | POST en edición |
| Eliminar paradas | Eliminar paradas individuales | POST en edición |
| Reordenar paradas | Drag-and-drop con persistencia JSON | POST `/admin/routes/{publicId}/stops/reorder` |
| Optimizar paradas | Optimización via VROOM con preview o aplicación directa | POST `/admin/routes/{publicId}/optimize` |

### Estados de Ruta (`RouteStatus`)

```
PLANNED → ACTIVE → DONE
    ↘ CANCELLED
```

### Estados de Parada (`RouteStopStatus`)

```
PENDING → DELIVERED
    ↘ EXCEPTION
    ↘ SKIPPED
```

### Campos de Ruta

- Nombre, estado, conductor, vehículo, cliente
- Ubicación de origen (`CustomerLocation`)
- Ventana temporal (startAt, endAt)
- Totales: peso (kg), volumen (m³), bultos, distancia (km), duración estimada (min)
- Análisis IA (JSON almacenado)
- Auto-reoptimización (flag booleano)

---

## 3. Optimización de Rutas (VROOM + OSRM)

### Componentes

| Servicio | Responsabilidad |
|---|---|
| `RouteBuilder` | Construye rutas optimizadas distribuyendo envíos entre vehículos |
| `RouteOptimizationService` | Re-optimiza orden de paradas en rutas existentes |
| `RouteCapacityValidator` | Valida restricciones de capacidad (peso, volumen, bultos) |
| `VroomRouteOptimizer` | Adaptador del solver VRP VROOM |
| `OsrmRoutingEngine` | Motor de routing con distancias reales por carretera |

### Capacidades de Optimización

- **3 dimensiones de capacidad**: peso (gramos), volumen (cm³), bultos
- **Ventanas temporales**: respeta horarios de entrega preferidos
- **Skills/habilidades de vehículo**: refrigerado, carga pesada, acceso peatonal, hazmat, frágil
- **Prioridades de envío**: LOW, NORMAL, HIGH, URGENT, CRITICAL (mapeadas a VROOM)
- **Optimización por duración**: minimiza tiempo total, no distancia
- **Re-optimización en ruta**: optimiza paradas pendientes con posición actual del conductor
- **Clustering k-means++**: agrupación geográfica de envíos antes de asignar a vehículos

### Endpoints de Optimización

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/routes/{publicId}/optimize` | Optimizar ruta existente |
| GET | `/api/routes/{publicId}/validate-capacity` | Validar capacidad del vehículo |
| POST | `/api/routes/build` | Construir rutas óptimas desde envíos + vehículos |
| GET | `/api/routes/{publicId}/timing` | Estimar tiempos de ruta |
| POST | `/api/routes/{publicId}/reoptimize` | Re-optimizar paradas pendientes en ruta activa |

---

## 4. Planificador de Rutas Interactivo

Interfaz visual para crear rutas optimizadas desde cero.

| Funcionalidad | Endpoint | Descripción |
|---|---|---|
| Vista principal | `/admin/route-planner` | UI interactiva con mapa |
| Envíos sin asignar | `/admin/route-planner/shipments` | Filtrados por cliente, con coordenadas |
| Vehículos disponibles | `/admin/route-planner/vehicles` | Con info de capacidad |
| Ubicaciones de origen | `/admin/route-planner/locations` | Depósitos/almacenes del cliente |
| Clustering | `/admin/route-planner/cluster` | Agrupación geográfica k-means++ |
| Preview de rutas | `/admin/route-planner/preview` | Genera rutas sin persistir |
| Sugerir conductores | `/admin/route-planner/suggest-drivers` | Ranking multi-criterio |
| Confirmar rutas | `/admin/route-planner/confirm` | Persiste rutas y asigna conductores |

---

## 5. Gestión de Envíos (Shipments)

### Ciclo de Vida

```
CREATED → PICKED_UP → IN_HUB → IN_TRANSIT → OUT_FOR_DELIVERY → DELIVERED
                                                        ↘ EXCEPTION
                                                        ↘ RESCHEDULE_REQUESTED
```

Cada transición genera un `ShipmentEvent` (event sourcing).

### Campos del Envío

- Referencia (única), cliente, destinatario (nombre, teléfono, dirección)
- Coordenadas (lat, lng), notas, descripción
- Tipo de servicio: Entrega, Entrega y Recogida, Devolución
- Peso total (kg), volumen total (m³), total bultos
- Fecha estimada de entrega, ventana horaria preferida
- Tiempo de servicio estimado (segundos)
- Prioridad: Baja, Normal, Alta, Urgente, Crítica
- Skills requeridos (refrigerado, carga pesada, etc.)
- Token de tracking público (auto-generado, único)

### Bultos (Parcels)

Cada envío contiene uno o más bultos con:
- Número de secuencia, peso, volumen, EAN, descripción
- Estado individual: REGISTERED → IN_WAREHOUSE → LOADED → IN_TRANSIT → DELIVERED/ABSENT/RETURNED/DAMAGED/LOST

---

## 6. Tracking GPS en Tiempo Real

### Flujo de Ingesta

```
Traccar (GPS) → TraccarStreamCommand (polling/WebSocket)
    → TraccarIngestionService
        → VehiclePosition (historial)
        → VehicleLastPosition (snapshot)
        → VehicleCheckpoint (progreso)
        → Mercure SSE (publicación en tiempo real)
        → Evento VehiclePositionReceived
```

### Modos de Ingesta

| Modo | Comando | Descripción |
|---|---|---|
| Polling | `app:traccar:stream --mode=poll --sleep=5` | Consulta periódica a Traccar API |
| WebSocket | `app:traccar:stream --mode=ws` | Streaming en tiempo real con backfill |
| Una vez | `app:traccar:stream --once` | Una sola consulta (para cron/testing) |

### Simulación GPS (Desarrollo)

```bash
php bin/console app:dev:simulate-gps --points=120 --interval=1 --ingest
```
- Circuito por el centro de Madrid (Sol → Gran Vía → Palacio Real → Atocha → Retiro)
- Soporte para rutas personalizadas via `--route-file` (JSON)
- Crea dispositivos en Traccar automáticamente

### Mercure Topics

| Topic | Contenido | Publisher |
|---|---|---|
| `/vehicles/{publicId}/position` | Lat, lng, speed, course, accuracy | MercurePositionListener |
| `/operator/fleet` | Resumen de flota | MercurePositionListener |
| `/customers/{customerId}/routes` | Eventos ligeros: `stop_delivered`, `stop_exception`, `route_started`, `route_completed` | MercureRouteProgressListener |
| `/customers/{customerId}/shipments` | Eventos de envío | MercureRouteProgressListener |
| `/routes/{publicId}/view/admin` | MapViewData completa (con métricas de optimización) | RouteSnapshotListener |
| `/routes/{publicId}/view/customer` | MapViewData completa (sin métricas admin) | RouteSnapshotListener |
| `/routes/{publicId}/view/driver` | MapViewData completa (sin métricas admin) | RouteSnapshotListener |

### Actualización en tiempo real de vistas de ruta

Cuando un conductor marca una entrega o reporta una excepción, `RouteSnapshotListener` publica la vista completa (`MapViewData`) a los 3 topics por rol. En el frontend:

1. **`MxoRouteMap`** (componente compartido) recibe el update via EventSource y re-renderiza el mapa (marcadores cambian color según status)
2. **`mxo:route-updated`** evento DOM se emite con los datos actualizados
3. **Lista de paradas reactiva** (`_stop_list.html.twig` o listener inline) escucha el evento y actualiza badges de estado, contadores, y info de entrega/excepción

| Vista | Mapa en tiempo real | Lista de paradas reactiva |
|---|---|---|
| Admin route show | Sí (Mercure) | Sí (Alpine.js `_stop_list.html.twig`) |
| Customer route show | Sí (Mercure) | Sí (Alpine.js `_stop_list.html.twig`) |
| Driver route show | Sí (Mercure) | Sí (listener DOM inline) |
| Operator dashboard | Sí (posiciones vehiculos) | N/A (refresca KPIs) |

---

## 7. Mapa de Flota

| Ruta | Descripción |
|---|---|
| `/fleet/map` | Mapa interactivo con posiciones de vehículos y rutas en tiempo real |
| `/api/fleet/summary` | Resumen JSON: conteos de vehículos, rutas activas |

Usa Mercure SSE para actualización en tiempo real sin polling. Las posiciones de los vehículos se mueven en el mapa conforme llegan nuevas coordenadas.

---

## 8. Portal del Conductor

### Interfaz Web

| Ruta | Descripción |
|---|---|
| `/driver/routes` | Lista de rutas asignadas con conteo de paradas |
| `/driver/routes/{publicId}` | Detalle de ruta con mapa, paradas, ETAs y posición del vehículo en tiempo real |

### API del Conductor (ver sección 19)

Operaciones móviles: iniciar/finalizar ruta, entregar paradas, reportar excepciones, inspección de vehículo, feedback, POD.

---

## 9. Portal del Cliente

| Ruta | Descripción |
|---|---|
| `/customer/dashboard` | KPIs, últimas 5 rutas activas, vista de flota |
| `/customer/dashboard/kpis` | Datos KPI en JSON |
| `/customer/routes` | Listado de rutas con paginación y filtro de estado |
| `/customer/routes/{publicId}` | Detalle con mapa de paradas y posición del vehículo |
| `/customer/shipments` | Listado de envíos con búsqueda, rango de fechas, filtro de estado |
| `/customer/shipments/{publicId}` | Detalle con timeline completo de eventos |

Aislamiento multi-tenant automático: los clientes solo ven sus propios datos.

---

## 10. Tracking Público

Accesible sin autenticación mediante token único por envío.

| Ruta | Descripción |
|---|---|
| `/track/{trackingToken}` | Página pública de seguimiento del envío |
| `/track/{trackingToken}/rate` | Formulario de calificación de entrega (1-5 estrellas, comentario, tags) |
| `/track/{trackingToken}/reschedule` | Reprogramar entrega (propone slots, envía SMS al destinatario) |

### Delivery Rating (`DeliveryRating`)
- Puntuación 1-5, comentario opcional, tags JSON
- Vinculado 1:1 con Shipment

### Delivery Slots (`DeliverySlot`)
- Estados: proposed → selected → confirmed → expired
- Ventanas horarias seleccionables por el destinatario

---

## 11. Proof of Delivery (POD)

| Campo | Descripción |
|---|---|
| `signedByName` | Nombre de quien firma |
| `recipientIdEncoded` | Identificación del destinatario codificada (6-512 chars) |
| `confirmedByDriver` | Confirmación del conductor |
| `createdByUser` | Usuario que creó el POD |

- Vinculado 1:1 con RouteStop
- Almacenamiento abstracto via `PodStorageInterface` (DB, S3, filesystem)
- Descargable via API del conductor

---

## 12. Notificaciones y Webhooks

### Notificaciones In-App

- Notificaciones por usuario con tipos, títulos, mensajes, payload JSON
- Canal predeterminado: `in_app`
- Estado de lectura y timestamp
- Publicación Mercure para actualizaciones en tiempo real (`/users/{userId}/notifications`)

### Web Push

- Suscripciones W3C Push API (endpoint, authKey, p256dhKey)
- Servicio `WebPushService` para envío a dispositivos suscritos

### Webhooks

| Endpoint API | Método | Descripción |
|---|---|---|
| `/api/v1/webhooks` | POST | Registrar endpoint webhook (devuelve signing secret) |
| `/api/v1/webhooks` | GET | Listar endpoints |
| `/api/v1/webhooks/{publicId}` | DELETE | Eliminar endpoint |

- Multi-endpoint por cliente
- Firma HMAC-SHA256 en cada petición
- Eventos: `shipment.delivered`, `shipment.exception`
- Filtro configurable: array vacío = todos los eventos

### Notificaciones al Destinatario (`RecipientNotification`)

- Canal (SMS, email), template, destinatario
- Estados: sent → delivered / failed
- Tracking de errores

---

## 13. Reportes y Analítica

### Portal de Reportes

| Ruta | Descripción |
|---|---|
| `/admin/reports` | Índice de reportes |
| `/admin/reports/deliveries` | Estadísticas de entregas por rango de fechas, tendencias, distribución por estado |
| `/admin/reports/drivers` | Ranking de conductores (rutas, entregas, excepciones, tasa de éxito) |
| `/admin/reports/customers` | Reportes a nivel de cliente |
| `/admin/reports/export/deliveries.csv` | Exportación CSV de entregas |
| `/admin/reports/export/drivers.csv` | Exportación CSV de ranking de conductores |

### Métricas del Dashboard Admin

- KPIs operacionales, métricas de salud
- Entregas diarias, top conductores
- Import runs, posiciones ingestadas, rutas activas, paradas pendientes

### Dashboard en Vivo del Operador

| Ruta | Descripción |
|---|---|
| `/operator` | Dashboard de métricas del operador |
| `/operator/dashboard/live` | Dashboard en tiempo real con conteos de rutas/paradas via Mercure |

#### `OperatorKpiService`

Servicio dedicado que calcula KPIs operacionales usando DQL QueryBuilder (Doctrine ORM). Consultas tipadas contra entidades `Route`, `RouteStop` y `VehicleLastPosition` con enums `RouteStatus` y `RouteStopStatus`. El método `getTopDrivers()` usa DBAL nativo para funciones PostgreSQL-específicas (`FILTER (WHERE ...)`).

KPIs calculados:
- **activeRoutes**: Rutas con estado ACTIVE o PLANNED
- **deliveriesToday**: Paradas entregadas desde medianoche
- **exceptionsToday**: Excepciones en rutas activas/planificadas/completadas
- **completionRate**: % de paradas entregadas en rutas activas
- **successRate7d**: % de entregas exitosas vs excepciones (últimos 7 días)
- **vehiclesWithPosition**: Vehículos con posición GPS registrada
- **topDrivers**: Top 3 conductores por entregas (últimos 7 días)

---

## 14. Inteligencia Artificial

### Servicios IA Integrados

| Servicio | Descripción | Provider | Estado |
|---|---|---|---|
| `ExceptionClassifierService` | Clasifica excepciones de entrega en 9 subcategorías con confianza | Claude API | Activo |
| `PostRouteAnalyzer` | Analiza rutas completadas (eficiencia, planned vs actual, insights, recomendaciones) | Claude API | Activo |
| `DeliveryNoteAiEnricher` | Genera notas de entrega basadas en historial de excepciones y feedback de conductores | Claude API | Activo |
| `AiAssistantService` | Asistente conversacional con 5 herramientas (buscar envíos, reportes, rutas, alertas, patrones) | Claude API | Activo |
| `EmbeddingService` | Embeddings vectoriales para búsqueda semántica de envíos (pgvector) | OpenAI API | Activo |
| `DeliveryRiskService` | Predicción de riesgo de fallo en entrega (LOW/MEDIUM/HIGH) | ML Service | Activo |
| `AddressRiskService` | Evaluación de riesgo por dirección basada en historial de excepciones | SQL Analytics | Activo |

### Clasificación de Excepciones

Cuando un conductor reporta una excepción, `NlpClassificationHandler` clasifica automáticamente el texto en una de 9 subcategorías:

| Subcategoría | Descripción |
|---|---|
| `AUSENTE_REPETIDO` | Ausencias frecuentes en la misma dirección |
| `HORARIO_INADECUADO` | Entrega fuera del horario preferido |
| `ACCESO_DIFICIL` | Problemas de acceso al edificio/zona |
| `DIRECCION_INCORRECTA` | Dirección errónea o incompleta |
| `RECHAZO_CLIENTE` | El destinatario rechaza el paquete |
| `DANO_PAQUETE` | Paquete dañado durante el transporte |
| `VEHICULO_INADECUADO` | Vehículo no apto para la entrega |
| `ZONA_PELIGROSA` | Zona con problemas de seguridad |
| `OTRO` | No clasificable en las anteriores |

La clasificación incluye: subcategoría, confianza (0.0-1.0), insight accionable y acción sugerida. Se almacena en `ShipmentEvent.payload['ai_classification']`.

### Análisis Post-Ruta

Al completarse una ruta (`RouteCompleted` event), `PostRouteAnalysisHandler` genera un análisis que incluye:
- **Summary**: Resumen ejecutivo de la ruta
- **Planned vs Actual**: Comparación de métricas planificadas vs reales
- **Insights**: Observaciones sobre el rendimiento
- **Recommendations**: Sugerencias para futuras rutas similares

Almacenado en `Route.aiAnalysis` (JSON). Incluye fallback estadístico cuando Claude API no está disponible.

### Predicción de Riesgo de Entrega

`DeliveryRiskService` calcula un score de riesgo (0.0-1.0) para cada envío:
- **LOW** (< 0.3): Entrega sin problemas esperados
- **MEDIUM** (0.3 - 0.7): Riesgo moderado, precaución recomendada
- **HIGH** (> 0.7): Alto riesgo de fallo

Factores: historial de excepciones en la dirección (+0.15 boost), predicción ML, y datos del envío. Se muestra como badge de color en el planificador de rutas.

### Notas de Entrega con IA

`DeliveryNoteAiEnricher` genera notas para conductores (máximo 200 caracteres) basándose en:
- Excepciones previas en la misma dirección
- Feedback de conductores (notas de acceso, coordenadas corregidas, comentarios)

Se activa al iniciar ruta (`RouteStarted` → `EnrichRouteNotesMessage`). Almacena en `RouteStop.aiNotes`.

### Asistente IA para Operadores

Interfaz de chat (`/admin/ai-assistant`) con 5 herramientas integradas:

| Herramienta | Descripción |
|---|---|
| `search_shipments` | Buscar envíos por referencia, nombre o dirección |
| `get_delivery_report` | Reporte de entregas con tasas de éxito y desglose |
| `get_route_details` | Detalles de ruta con paradas, progreso y conductor |
| `get_active_alerts` | Vehículos offline y rutas con excepciones excesivas |
| `get_exception_patterns` | Análisis de patrones por código, conductor y dirección |

Rate limiting: 20 mensajes/minuto por usuario.

### Procesamiento Asíncrono (Messenger)

| Mensaje | Handler | Trigger |
|---|---|---|
| `NlpClassificationMessage` | Clasifica excepción con Claude, persiste en `ShipmentEvent.payload` | `DeliveryService` al reportar excepción |
| `PostRouteAnalysisMessage` | Analiza ruta completada, guarda en `Route.aiAnalysis` | Evento `RouteCompleted` |
| `EnrichRouteNotesMessage` | Enriquece notas de paradas con IA | Evento `RouteStarted` |
| `FleetAnomalyCheckMessage` | Detecta anomalías de flota via ML | Evento `VehiclePositionReceived` |

---

## 15. Servicio ML (Machine Learning)

Microservicio Python FastAPI independiente (`ml-service/`, puerto 5200).

| Modelo | Descripción |
|---|---|
| `anomaly_detector` | Detección de anomalías en flota (velocidad, desviación de ruta, tiempos idle) |
| `delivery_risk` | Predicción de riesgo de fallo en entrega |
| `demand_forecast` | Pronóstico de demanda de envíos (Prophet time series) |
| `driver_affinity` | Clustering de preferencias conductor-zona |
| `service_time` | Estimación de tiempo de servicio por entrega |
| `zone_clustering` | Clustering geográfico de zonas de entrega |

Stack: Python 3.12, FastAPI, scikit-learn, LightGBM, Prophet.

---

## 16. Multi-Tenant

### Mecanismo

- **Doctrine SQL Filter** (`CustomerTenantFilter`): filtra automáticamente por `customer_id`
- **Interface**: entidades que implementan `CustomerScopedEntityInterface` son filtradas
- **Activación**: `DoctrineCustomerFilterSubscriber` habilita el filtro para `ROLE_CUSTOMER` y `ROLE_DRIVER`
- **Bypass**: usuarios ADMIN y OPERATOR no son filtrados

### Entidades Multi-Tenant

`CustomerVehicle`, `Shipment`, `ApiKey`, `CustomerLocation`, `RoutePlanTemplate`, `WebhookEndpoint`, `CsvImportRun`

---

## 17. Gestión de Usuarios y Seguridad

### Roles

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

### Autenticación

| Mecanismo | Ámbito | Detalles |
|---|---|---|
| `form_login` | Web | CSRF, rate limiting (5 intentos), sesiones en Redis (`sess:transporte:`) |
| API Key | API v1 | Hash almacenado, rate limit por minuto, último uso tracked |
| `UserChecker` | Global | Valida que el usuario esté activo antes de autenticar |

### Seguridad HTTP

- `SecurityHeadersSubscriber`: X-Frame-Options, CSP, X-Content-Type-Options
- CSRF en todos los formularios POST/DELETE

### Auditoría

- `AuditLog`: registro estructurado de operaciones sensibles
- Actor, acción, tipo/ID de entidad, payload, IP, cambios
- Índices por (entity_type, entity_id), (action), (created_at)

### CRUD de Usuarios (Admin)

| Ruta | Descripción |
|---|---|
| `/admin/users` | Listado con paginación |
| `/admin/users/new` | Crear usuario (ADMIN, CUSTOMER, DRIVER) |
| `/admin/users/{publicId}/edit` | Editar rol, reset password |
| `/admin/users/{publicId}/delete` | Desactivar usuario |

---

## 18. API REST v1

Autenticación: API Key.

### Envíos

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/v1/shipments` | Crear envío(s) — soporta batch (`shipments` array) |
| GET | `/api/v1/shipments` | Listar envíos (paginación) |
| GET | `/api/v1/shipments/{publicId}` | Detalle del envío |
| GET | `/api/v1/shipments/{publicId}/tracking` | Timeline completo de eventos |

### Rutas

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/routes` | Listar rutas (paginación, filtro de estado) |
| GET | `/api/v1/routes/{publicId}` | Detalle con paradas, capacidades, distancias |

### Webhooks

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/v1/webhooks` | Registrar endpoint (devuelve secret) |
| GET | `/api/v1/webhooks` | Listar endpoints |
| DELETE | `/api/v1/webhooks/{publicId}` | Eliminar endpoint |

---

## 19. API del Conductor

Autenticación: sesión con `ROLE_DRIVER`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/driver/routes` | Rutas asignadas |
| POST | `/api/driver/routes/{id}/start` | Iniciar ruta (requiere inspección) |
| POST | `/api/driver/routes/{id}/finish` | Finalizar ruta |
| GET | `/api/driver/routes/{id}/stops` | Paradas con URLs de navegación |
| POST | `/api/driver/stops/{id}/deliver` | Marcar como entregado + POD |
| POST | `/api/driver/stops/{id}/exception` | Reportar excepción |
| GET | `/api/driver/stops/{id}/pod` | Metadatos del POD |
| GET | `/api/driver/stops/{id}/pod/download` | Descargar POD |
| GET | `/api/driver/routes/{id}/etas` | ETAs de paradas pendientes |
| POST | `/api/driver/routes/{id}/stops/{id}/feedback` | Feedback (coords corregidas, notas acceso) |
| GET | `/api/driver/routes/{id}/briefing` | Briefing pre-ruta |
| GET | `/api/driver/routes/{id}/inspection` | Checklist de inspección |
| POST | `/api/driver/routes/{id}/inspection` | Enviar inspección del vehículo |

### Idempotencia

Cada acción de conductor usa `clientActionId` (UUID) para prevenir duplicados. Entidad `DriverAction` con constraint unique (driver_id, client_action_id).

---

## 20. Importación CSV

### Servicio `ShipmentCsvImporter`

Columnas soportadas:

| Columna | Obligatoria | Descripción |
|---|---|---|
| `reference` | Sí | Referencia única del envío |
| `recipient_name` | Sí | Nombre del destinatario |
| `address` | Sí | Dirección de entrega |
| `lat`, `lng` | No | Coordenadas GPS |
| `phone` | No | Teléfono del destinatario |
| `notes` | No | Notas de entrega |
| `service_type` | No | delivery, delivery_and_pickup, return |
| `weight_kg` | No | Peso en kg |
| `volume_m3` | No | Volumen en m³ |
| `num_parcels` | No | Número de bultos |
| `ean` | No | Código EAN del bulto |
| `description` | No | Descripción del envío |
| `service_time_seconds` | No | Tiempo estimado de servicio |
| `priority` | No | low, normal, high, urgent, critical |

### Calidad de Datos (`CsvQualityAnalyzer`)

- Score de calidad 0-100
- Warnings: coordenadas faltantes, tipos inválidos, referencias vacías, distribuciones inusuales
- Tracking de importaciones por cliente (`CsvImportRun`)

---

## 21. Administración del Sistema

### CRUDs Admin

| Entidad | Ruta base | Operaciones |
|---|---|---|
| Rutas | `/admin/routes` | CRUD + optimización + gestión de paradas |
| Vehículos | `/admin/vehicles` | CRUD + capacidades + Traccar device ID |
| Clientes | `/admin/customers` | CRUD + asignación de vehículos |
| Conductores | `/admin/drivers` | CRUD + reset password |
| Usuarios | `/admin/users` | CRUD + asignación de roles |

### Comandos CLI

| Comando | Descripción |
|---|---|
| `app:create-admin` | Crear usuario admin por defecto |
| `app:traccar:stream` | Ingestar posiciones GPS (polling/WebSocket) |
| `app:traccar:sync-devices` | Sincronizar dispositivos Traccar → Vehicle |
| `app:dev:simulate-gps` | Simular posiciones GPS para desarrollo |
| `app:positions:purge` | Purgar posiciones antiguas (>N días) |
| `app:positions:downsample` | Reducir tabla de posiciones |
| `app:update:address-risk` | Recalcular scores de riesgo por dirección |
| `app:database:maintenance` | Vacuum, analyze, reindex |
| `app:system:status` | Estado del sistema (DB, Redis, Traccar, Mercure) |
| `app:dev:smoke:csv-import` | Smoke test importación CSV |
| `app:dev:smoke:traccar-once` | Smoke test polling Traccar |

### Health Checks

| Ruta | Descripción |
|---|---|
| `/admin/health` | Health check JSON del sistema |
| `/admin/health/live` | Liveness probe para Kubernetes |

---

## 22. Infraestructura y Despliegue

### Servicios Docker (Desarrollo Local)

| Servicio | Imagen | Puerto | Función |
|---|---|---|---|
| `app` | php:8.4-cli-bookworm | 8000 | Backend Symfony |
| `db` | postgres:16 | 5432 | Base de datos principal |
| `redis` | redis:7 | 6379 | Sesiones y caché |
| `mercure` | dunglas/mercure | 3000 | Hub SSE tiempo real |
| `traccar` | traccar/traccar | 8082 + 5055 | Tracking GPS |
| `traccar_db` | postgres:16 | 5433 | BD dedicada Traccar |
| `osrm` | osrm/osrm-backend | 5000 | Motor de routing |
| `vroom` | vroom-docker | 5100 | Optimizador VRP |
| `worker` | php:8.4 | — | Consumidor Messenger (async + ml) |
| `ml` | python:3.12 | 5200 | Servicio ML FastAPI |

### Despliegue en Railway

- 8 servicios containerizados
- Auto-migraciones al inicio
- Health checks
- Configuración Nginx dinámica con PORT de Railway
- OPcache (256MB, 20K archivos)
- PHP-FPM para producción

### CI/CD (GitHub Actions)

- PHP 8.4 + extensiones (pdo_pgsql, redis, zip, intl)
- Lint de sintaxis PHP
- Composer install
- Verificación Symfony (`symfony console about`)
- PostgreSQL 16 + Redis 7 como servicios

---

## 23. Modelo de Datos

### Patrones de Identidad

| Patrón | Descripción |
|---|---|
| `PublicIdTrait` | Todas las entidades (excepto `CustomerVehicle`, `AddressRisk`) tienen BIGINT `id` (interno) + ULID `publicId` (público) |
| `SoftDeleteTrait` | 7 entidades con borrado lógico: User, Customer, Vehicle, Route, Shipment |
| `CustomerScopedEntityInterface` | 9 entidades con aislamiento multi-tenant |

### Entidades Principales (39 entidades en `src/Entity/` + modelos de dominio en `src/Domain/`)

| Categoría | Entidades |
|---|---|
| **Core** | User, Customer, Vehicle, CustomerVehicle |
| **Rutas** | Route, RouteStop, RouteEvent, RoutePlanTemplate |
| **Envíos** | Shipment, Parcel, ShipmentEvent |
| **Tracking GPS** | VehiclePosition, VehicleLastPosition, VehicleCheckpoint |
| **Entrega** | Pod, DriverAction, DriverFeedback, VehicleInspection |
| **Ubicaciones** | CustomerLocation, DeliveryZone |
| **Programación** | DeliverySlot, DeliveryRating, DriverAvailability |
| **Notificaciones** | Notification, NotificationLog, NotificationPreference, PushSubscription, RecipientNotification, RecipientAction |
| **Integraciones** | ApiKey, WebhookEndpoint, CsvImportRun, CustomerIntegration |
| **Realtime** | RealtimeEvent |
| **Analytics** | AuditLog, AddressRisk, RoutePerformanceMetric, OptimizationStrategyComparison, RouteOptimizationLog |
| **Domain Models** **[PARTIAL]** | RouteSnapshot, RouteMapView, StopMapView, MapUpdate, MapUpdateType, RouteMapMetrics, RouteMapOptions, RouteMapTiming, VehiclePosition (Domain) — POPOs en `src/Domain/`, sin ORM |

> **[PARTIAL]** La migración a DDD está en progreso. Route Planning tiene modelos de dominio (RouteSnapshot, MapView), pero las entidades principales (Route, RouteStop, Shipment) permanecen en `src/Entity/` con ORM attributes. Ver `docs/knowledge/architecture-ddd.md`.

### Enums (17 total)

| Enum | Valores |
|---|---|
| `UserRole` | ADMIN, CUSTOMER, DRIVER |
| `RouteStatus` | PLANNED, ACTIVE, DONE, CANCELLED |
| `RouteStopStatus` | PENDING, DELIVERED, EXCEPTION, SKIPPED |
| `RouteEventType` | Tipos de eventos de ruta |
| `ShipmentEventType` | CREATED, PICKED_UP, IN_HUB, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, EXCEPTION, RESCHEDULE_REQUESTED |
| `ExceptionCode` | ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER |
| `ServiceType` | DELIVERY, DELIVERY_AND_PICKUP, RETURN |
| `ParcelStatus` | REGISTERED, IN_WAREHOUSE, LOADED, IN_TRANSIT, DELIVERED, ABSENT, RETURNED, DAMAGED, LOST |
| `VehicleSkill` | REFRIGERATED, HEAVY_LOAD, PEDESTRIAN_ACCESS, HAZMAT, FRAGILE |
| `ShipmentPriority` | LOW, NORMAL, HIGH, URGENT, CRITICAL |
| `ClientFrequency` | NOT_FREQUENT, FREQUENT, VERY_FREQUENT, SUPER_FREQUENT |
| `NotificationChannel` | Canales de notificación |
| `NotificationLogStatus` | Estados de log de notificaciones |
| `NotificationTriggerType` | Tipos de trigger de notificaciones |
| `OptimizationOperation` | Operaciones de optimización |
| `OptimizationStepCategory` | Categorías de pasos de optimización |
| `RecipientActionType` | Tipos de acción de destinatario |

---

## 24. Búsqueda

### Búsqueda Global

| Ruta | Descripción |
|---|---|
| `/search` | Búsqueda web con resultados HTML |
| `/api/search` | Búsqueda API con respuesta JSON |

### Modalidades

- **Keyword**: SQL LIKE en referencias, nombres, direcciones (rutas, envíos, vehículos)
- **Semántica**: Embeddings vectoriales (OpenAI) con similitud coseno para envíos (pgvector)
- **Híbrida**: Combina keyword + semántica, hasta 10 resultados por tipo
- **Fallback**: Si embeddings no disponibles (sin API key), solo búsqueda keyword. Si keyword devuelve <3 resultados, intenta búsqueda semántica automáticamente.

---

## 25. Providers Configurables por Tenant

### Visión General

Cada Customer (tenant) puede configurar qué proveedores externos usar para routing, optimización de rutas, GPS y actualizaciones en tiempo real. El sistema usa un patrón **Transparent Proxy + Provider Factory** que mantiene compatibilidad total hacia atrás: los servicios de dominio existentes no cambian.

### Arquitectura

| Componente | Responsabilidad |
|---|---|
| `TenantContext` | Resuelve el Customer actual desde el token de seguridad |
| `ProviderResolver` | Lee `CustomerIntegration` de DB, aplica fallbacks a defaults globales |
| `CachedProviderResolver` | Decorator con caché Redis (TTL 5 min) sobre el resolver |
| `ProviderFactoryRegistry` | Mapea tipo de provider → factory, autodiscovery via tags |
| `FallbackChain` | Ejecuta providers en orden de prioridad, captura `ProviderUnavailableException` |
| Transparent Proxies | Implementan la misma interfaz del puerto, resuelven provider en runtime |

### Entidad `CustomerIntegration`

Configuración per-tenant almacenada en DB:

| Campo | Tipo | Descripción |
|---|---|---|
| `customer` | ManyToOne | Tenant propietario |
| `serviceType` | `ServiceType` enum | routing, route_optimizer, gps, realtime |
| `providerType` | string | Identificador del provider (ej. `osrm`, `google_directions`) |
| `config` | JSON | Configuración específica del provider (API keys, URLs, etc.) |
| `enabled` | boolean | Activar/desactivar sin eliminar |
| `priority` | integer | Orden en fallback chain (menor = mayor prioridad) |

### Providers Disponibles

#### Routing (`RoutingEngineInterface`)

| Provider | Tipo | Infraestructura | Descripción |
|---|---|---|---|
| OSRM | `osrm` | Requiere servidor OSRM | Routing con distancias reales por carretera |
| Google Directions | `google_directions` | API key de Google | Routing via Google Directions API (default) |

#### Optimización de Rutas (`RouteOptimizerInterface`)

| Provider | Tipo | Infraestructura | Descripción |
|---|---|---|---|
| VROOM | `vroom` | Requiere servidor VROOM + OSRM | Solver VRP completo (existente) |
| Greedy | `greedy` | Ninguna | Algoritmo nearest-neighbor, sin dependencias externas |

#### GPS (`GpsDeviceProviderInterface`)

| Provider | Tipo | Infraestructura | Descripción |
|---|---|---|---|
| Traccar | `traccar` | Requiere servidor Traccar | Tracking GPS via Traccar API (existente) |
| Webhook | `webhook` | Ninguna | Recibe posiciones via webhook, sin servidor GPS externo |

#### Realtime (`RealtimePublisherInterface`)

| Provider | Tipo | Infraestructura | Descripción |
|---|---|---|---|
| Mercure | `mercure` | Requiere hub Mercure | SSE en tiempo real (existente) |
| HTTP Polling | `http_polling` | Ninguna | Persiste eventos en DB, clientes consultan via API |

### API de Polling

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/events` | Eventos desde `?since=ISO8601`, filtro opcional `?topic=` |

### Admin CRUD

| Ruta | Descripción |
|---|---|
| `/admin/customer-integrations` | Listado de integraciones por tenant |
| `/admin/customer-integrations/new` | Crear nueva integración |
| `/admin/customer-integrations/{publicId}/edit` | Editar integración |
| `/admin/customer-integrations/{publicId}/delete` | Eliminar integración |

### Defaults Globales (`.env`)

```
DEFAULT_ROUTE_OPTIMIZER=vroom
DEFAULT_ROUTING_ENGINE=osrm
DEFAULT_GPS_PROVIDER=traccar
DEFAULT_REALTIME_PUBLISHER=mercure
```

Cuando un tenant no tiene `CustomerIntegration` configurada, se usan estos defaults.

### Entidades Nuevas

| Entidad | Multi-tenant | Descripción |
|---|---|---|
| `CustomerIntegration` | Sí | Configuración de providers por tenant |
| `RealtimeEvent` | Sí | Eventos almacenados para HTTP polling |

### Enums Nuevos

| Enum | Valores |
|---|---|
| `ServiceType` | ROUTING, ROUTE_OPTIMIZER, GPS, REALTIME |
| `RoutingProvider` | OSRM, GOOGLE_DIRECTIONS |
| `RouteOptimizerProvider` | VROOM, GREEDY |
| `GpsProviderType` | TRACCAR, WEBHOOK |
| `RealtimeProviderType` | MERCURE, HTTP_POLLING |

---

## 26. Sistema de Feedback y Aprendizaje

Sistema de captura de datos y aprendizaje continuo que opera en dos niveles para mejorar decisiones de diseño y resultados de negocio.

### 26.1 Workflow Feedback (docs/)

- **Execution Logs** — Captura estructurada de datos en cada fase del desarrollo (brainstorming, planning, implementation, verification, retrospective)
- **Retrospective Reviews** — Análisis mensual/trimestral de patrones, accuracy de estimaciones, blockers recurrentes
- **Decision Log** — Registro de decisiones de diseño no-triviales con outcomes

### 26.2 Business Feedback (Doctrine)

- **RoutePerformanceMetric** — KPIs inmutables por ruta completada: km saved, delivery success rate, plan accuracy, tiempo ahorrado
  - Creado automáticamente cuando una ruta finaliza (via PostRouteAnalysisHandler)
  - Queryable por optimizer, customer, periodo
- **OptimizationStrategyComparison** — Comparación A/B de estrategias de optimización sobre los mismos shipments
  - Almacena resultados de ambas estrategias + outcome real de la elegida
  - Permite aprender qué estrategia funciona mejor bajo qué condiciones

### 26.3 Learning Loop

- **Inmediato** — Antes de cada brainstorming, consulta de decisiones pasadas, execution logs, y métricas de negocio
- **Periódico** — Review mensual con `app:learning:metrics` para analizar patrones y actualizar guías

### 26.4 Console Command

```bash
php bin/console app:learning:metrics --period=30d --context=route-optimization
```

Output: resumen agregado de performance por optimizer, delivery rates, km saved, comparaciones A/B.

---

## 27. Historial de Cambios

| Fecha | Versión | Cambios |
|---|---|---|
| 2026-03-11 | 1.0.0 | Documento inicial con todas las características del sistema |
| 2026-03-11 | 1.1.0 | Fase 3: +41 unit tests (92→133). Fase 4: fix APP_BASE_URL, deprecar legacy ShipmentApiController, archivar docs completados, añadir metadata a composer.json |
| 2026-03-11 | 1.2.0 | Providers configurables por tenant: framework de proxy transparente + factory, 4 providers nuevos (Greedy, Google Directions, Webhook GPS, HTTP Polling), entidad CustomerIntegration, API de polling, admin CRUD, fallback chains, caché Redis. 255 tests (122 nuevos). |
| 2026-03-11 | 1.3.0 | Fase 2 — IA Activa: 55 tests nuevos para servicios AI/ML (304 total). Tests para ExceptionClassifier, PostRouteAnalyzer, DeliveryRisk, AddressRisk, EmbeddingService, SearchService, AiAssistant, DeliveryNoteAiEnricher. Fix bug DeliveryRiskService (array vs entity). Fix bug AiAssistantService (customerId int→string). UI: clasificación AI en excepciones (badge + insight + acción sugerida). UI: badge de riesgo en planificador de rutas. |
| 2026-03-12 | 1.3.1 | Refactor: OperatorKpiService migrado de DBAL (SQL crudo) a DQL QueryBuilder (Doctrine ORM). Consultas ahora tipadas con entidades y enums. `getTopDrivers()` mantiene DBAL nativo por funciones PostgreSQL-específicas. Tests actualizados con mocks de EntityManager/QueryBuilder. |
| 2026-03-13 | 1.4.0 | Mapas unificados: planificador y test-routing usan `MxoRouteMap` compartido (colores, estilos, decoradores consistentes). Actualización en tiempo real via Mercure en las 3 vistas de ruta (admin, customer, driver): mapa se re-renderiza y lista de paradas reactiva actualiza estados, badges y contadores al instante. Nueva vista admin route show con mapa en vivo, métricas de optimización y lista de paradas reactiva. Documentación actualizada: realtime.md, api-surface.md, FEATURES.md. |
| 2026-03-18 | 1.5.0 | Sistema de feedback y aprendizaje: flujo obligatorio para toda interacción (4 niveles), captura de datos en cada fase, doble learning loop (inmediato + periódico). Nuevas entidades RoutePerformanceMetric y OptimizationStrategyComparison. Console command `app:learning:metrics`. Skill 15: Learning Review. Knowledge module `feedback-learning.md`. |

---

> **Nota para mantenedores:** Este documento debe actualizarse con cada PR que añada, modifique o elimine funcionalidad. Incluir en el PR una actualización de la sección correspondiente y añadir una entrada en el [Historial de Cambios](#25-historial-de-cambios).
