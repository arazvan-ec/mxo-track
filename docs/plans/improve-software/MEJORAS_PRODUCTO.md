# Analisis y Sugerencias de Mejora para mxo-track

## Contexto

mxo-track es una plataforma de logistica de ultima milla construida con Symfony 7.4 que incluye: tracking GPS en tiempo real (Traccar + Mercure), optimizacion de rutas (VROOM + OSRM), gestion de entregas con POD, portal de cliente, y servicios de AI/ML. El objetivo es identificar funcionalidades nuevas que aporten valor real al producto y lo diferencien en el mercado.

---

## CATEGORIA 1: Experiencia del Destinatario Final

### 1.1 Notificacion ETA al Destinatario (Prioridad: ALTA)
- **Que:** SMS/WhatsApp automatico al destinatario con ventana de entrega estimada ("Su paquete llega entre 14:00-14:30") y link de tracking publico
- **Por que:** El 70% de excepciones de entrega (ABSENT) se evitan si el destinatario sabe cuando llega el paquete. Reduce costes de re-entrega
- **Sobre que se construye:** `EtaService`, `WebhookNotificationService`, entity `Shipment` (ya tiene `recipientPhone`), pagina publica `/track/{token}`
- **Implementacion:**
  - Nuevo servicio `RecipientNotificationService` que calcula ETA via `EtaService` y envia SMS via proveedor (Twilio/MessageBird)
  - Trigger cuando `RouteStatus` cambia a ACTIVE y cuando el vehiculo esta a N paradas del destino
  - Template configurable por cliente (`Customer.smsTemplate`)
  - Entidad `RecipientNotification` para tracking de envios SMS

### 1.2 Reprogramacion por el Destinatario (Prioridad: MEDIA)
- **Que:** Desde el link de tracking, el destinatario puede solicitar cambio de hora/dia o indicar "Dejar en porteria"
- **Por que:** Reduce tasa de ABSENT y mejora NPS del servicio
- **Sobre que se construye:** Pagina `/track/{token}`, entity `DeliverySlot`, `ShipmentEvent`
- **Implementacion:**
  - Ampliar `TrackingController` con formulario de reprogramacion
  - Nuevo evento `RESCHEDULE_REQUESTED` en `ShipmentEventType`
  - Notificacion al operador via `NotificationService`

### 1.3 Rating de Entrega por Destinatario (Prioridad: BAJA)
- **Que:** Tras la entrega, el destinatario puede valorar (1-5 estrellas) la experiencia
- **Por que:** Datos de calidad para ranking de conductores y mejora continua
- **Sobre que se construye:** Entity `DeliveryRating` (ya existe), pagina `/track/{token}`
- **Implementacion:** Ampliar la pagina de tracking post-entrega con formulario de rating

---

## CATEGORIA 2: Optimizacion Operativa

### 2.1 Planificador de Rutas con Preview (Prioridad: ALTA)
- **Que:** Interfaz visual donde el operador importa CSV, ve los envios en mapa, configura vehiculos/capacidades, y genera rutas optimizadas con un click. Preview antes de confirmar
- **Por que:** Es el flujo core del producto (mencionado en CLAUDE.md como requisito de demo). Actualmente el RouteBuilder existe pero falta la UI de planificacion
- **Sobre que se construye:** `RouteBuilder`, `VroomApiClient`, `ShipmentCsvImporter`, `RouteCapacityValidator`
- **Plan detallado:** Ver `PLAN_PLANIFICADOR_RUTAS.md`

### 2.2 Reoptimizacion Dinamica en Ruta (Prioridad: MEDIA)
- **Que:** Si un conductor reporta una excepcion o hay trafico, recalcular automaticamente el orden de las paradas restantes
- **Por que:** Ahorra tiempo al conductor y reduce km recorridos
- **Sobre que se construye:** `RouteOptimizationService`, `EtaService`, `OsrmClient`
- **Implementacion:**
  - Endpoint `POST /api/routes/{id}/reoptimize` que re-optimiza solo paradas PENDING
  - Trigger automatico tras cada excepcion (configurable)
  - Notificacion al conductor via Mercure con nuevo orden

### 2.3 Agrupacion de Envios por Zona (Clustering) (Prioridad: MEDIA)
- **Que:** Antes de crear rutas, agrupar automaticamente envios por zona geografica para distribuir eficientemente entre vehiculos
- **Por que:** Mejora la eficiencia de las rutas un 15-25% vs asignacion manual
- **Sobre que se construye:** `DeliveryZoneService` (ya existe), `VroomRequestMapper`
- **Implementacion:**
  - Servicio `ShipmentClusteringService` con algoritmo k-means o DBSCAN basado en coordenadas
  - Visualizacion de clusters en mapa con colores por zona
  - Integracion con el planificador (2.1) como paso previo

### 2.4 Turnos y Jornada del Conductor (Prioridad: BAJA)
- **Que:** Configurar horarios de trabajo de conductores, calcular horas trabajadas, alertar si exceden jornada legal
- **Por que:** Cumplimiento normativo (tiempos de conduccion) y planificacion de recursos
- **Implementacion:**
  - Entity `DriverShift` (start, end, breakMinutes, vehicle)
  - Calculo automatico de horas desde Route start/finish timestamps
  - Alerta cuando el conductor lleva mas de X horas activo

---

## CATEGORIA 3: Visibilidad y Analytics

### 3.1 Dashboard de SLA y Cumplimiento (Prioridad: ALTA)
- **Que:** Panel con metricas de cumplimiento de ventanas de entrega, tiempo medio de entrega, tasa de primer intento exitoso, comparativa entre conductores
- **Por que:** Los clientes de logistica necesitan ver KPIs de SLA para justificar el servicio
- **Sobre que se construye:** `ReportingService`, `AdminMetricsService`, entities `Route`/`RouteStop`/`ShipmentEvent`
- **Implementacion:**
  - Nuevo `SlaMetricsService` que calcula: % entregas dentro de ventana, tiempo medio puerta-a-puerta, tasa OTIF (On Time In Full)
  - Vista `/admin/reports/sla` con graficos de tendencia
  - Exportable a PDF para presentar a clientes
  - Filtros por cliente, periodo, conductor

### 3.2 Mapa de Calor de Excepciones (Prioridad: MEDIA)
- **Que:** Visualizacion geografica de donde se concentran las excepciones (ABSENT, WRONG_ADDRESS, etc.)
- **Por que:** Identifica zonas problematicas para tomar acciones (ej: cambiar horario en zona residencial)
- **Sobre que se construye:** `AddressRiskService` (ya calcula risk scores), `ExceptionPatternService`, fleet map
- **Implementacion:**
  - Capa de heatmap sobre el mapa Leaflet existente
  - Filtros por tipo de excepcion, periodo, cliente
  - Drill-down a direcciones especificas con historial

### 3.3 Comparativa de Rutas Planificadas vs Ejecutadas (Prioridad: MEDIA)
- **Que:** Overlay en mapa mostrando la ruta planificada (OSRM) vs la ruta real (GPS positions), con desvios y tiempos
- **Por que:** Identifica ineficiencias, paradas no autorizadas, y mejora la planificacion futura
- **Sobre que se construye:** `PostRouteAnalyzer` (ya guarda `ai_analysis`), `VehiclePosition`, `RouteStop`
- **Implementacion:**
  - Vista `/admin/routes/{id}/analysis` con dos polylines (planificada vs real)
  - Metricas: km extra, tiempo extra, paradas no previstas
  - Se alimenta del `PostRouteAnalysisMessage` existente

---

## CATEGORIA 4: Integraciones

### 4.1 API Publica para Clientes (Prioridad: ALTA)
- **Que:** API REST documentada con autenticacion API Key para que los clientes integren sus sistemas (ERP, eCommerce) directamente
- **Por que:** Elimina la dependencia del CSV manual. Permite integracion con Shopify, WooCommerce, SAP, etc.
- **Sobre que se construye:** Controladores API existentes, multi-tenant filter
- **Implementacion:**
  - Autenticacion via API Key (header `X-Api-Key`) con entity `ApiKey` ligada a Customer
  - Endpoints: crear envios, consultar estado, listar rutas, webhooks de eventos
  - Rate limiting por API key
  - Documentacion OpenAPI/Swagger autogenerada

### 4.2 Integracion con eCommerce (Prioridad: MEDIA)
- **Que:** Plugins/conectores para Shopify, WooCommerce, PrestaShop que crean envios automaticamente al confirmar pedidos
- **Por que:** Automatiza la cadena pedido-envio-entrega para tiendas online
- **Sobre que se construye:** API publica (4.1), `ShipmentCsvImporter` (patron de creacion)
- **Implementacion:**
  - Webhook receiver generico `POST /api/webhooks/orders`
  - Mappers por plataforma (Shopify, WooCommerce)
  - Creacion automatica de Shipment + Parcel

### 4.3 Exportacion Contable (Prioridad: BAJA)
- **Que:** Exportar datos de facturacion en formatos compatibles con software contable (CSV para A3, formato SEPA)
- **Por que:** Ahorra tiempo administrativo
- **Sobre que se construye:** `BillingService`

---

## CATEGORIA 5: Experiencia del Conductor

### 5.1 Navegacion Integrada en App (Prioridad: ALTA)
- **Que:** Boton "Navegar" en cada parada que abre Google Maps/Waze con las coordenadas exactas
- **Por que:** Ahorra tiempo al conductor y reduce errores de direccion
- **Sobre que se construye:** Driver API, `RouteStop` (ya tiene lat/lng)
- **Implementacion:**
  - Deep links a Google Maps (`google.navigation:q=lat,lng`) y Waze
  - Endpoint API que devuelve el deep link formateado
  - Deteccion de plataforma (Android/iOS) para el link correcto

### 5.2 Chat Conductor-Operador (Prioridad: MEDIA)
- **Que:** Canal de mensajeria en tiempo real entre conductor y centro de operaciones
- **Por que:** Resuelve incidencias rapido sin necesidad de llamada telefonica
- **Sobre que se construye:** Mercure (ya configurado para SSE), `NotificationService`
- **Implementacion:**
  - Entity `ChatMessage` (sender, route, content, timestamp)
  - Topic Mercure `/routes/{id}/chat`
  - Endpoint API para enviar/recibir mensajes
  - Vista en panel de operador junto a la ruta activa

### 5.3 Checklist Pre-Ruta del Vehiculo (Prioridad: BAJA)
- **Que:** El conductor completa una checklist (neumaticos, luces, carga asegurada) antes de iniciar ruta
- **Por que:** Cumplimiento normativo y prevencion de incidentes
- **Sobre que se construye:** Driver API, entity `Route`
- **Implementacion:**
  - Entity `VehicleInspection` con items configurables
  - Bloqueo de inicio de ruta hasta completar checklist

---

## CATEGORIA 6: Inteligencia y Prediccion (builds on existing AI/ML)

### 6.1 Prediccion de Volumen por Cliente (Prioridad: MEDIA)
- **Que:** Predecir cuantos envios tendra cada cliente la proxima semana basado en historico
- **Por que:** Permite planificar recursos (vehiculos, conductores) con antelacion
- **Sobre que se construye:** `DemandForecastService`, `DemandPredictionService` (ya existen), datos historicos en `Shipment`
- **Implementacion:**
  - Ampliar `DemandForecastService` con modelo de series temporales
  - Dashboard de forecast en `/admin/forecast` con graficos
  - Alertas cuando el volumen predicho supera la capacidad disponible

### 6.2 Asignacion Inteligente de Conductores (Prioridad: MEDIA)
- **Que:** Sugerir automaticamente que conductor asignar a cada ruta basado en: experiencia en la zona, rating, skill match, carga de trabajo
- **Por que:** Mejora tasa de entrega exitosa y satisfaccion del conductor
- **Sobre que se construye:** `DriverAffinityService` (ya existe), `DeliveryRating`, `DriverFeedback`, `VehicleSkill`
- **Implementacion:**
  - Scoring de conductor por zona (basado en entregas exitosas previas)
  - Matching de skills requeridos vs skills del vehiculo
  - Sugerencia en UI del planificador (2.1) con justificacion

### 6.3 Deteccion de Fraude en POD (Prioridad: BAJA)
- **Que:** Verificar que la foto de POD fue tomada en la ubicacion correcta (GPS de la foto vs coordenadas de la parada)
- **Por que:** Previene marcado falso de entregas
- **Sobre que se construye:** `Pod`, `DeliveryEvidenceFactory`, `RouteStop`
- **Implementacion:**
  - Extraer EXIF GPS de la foto y comparar con `RouteStop.latitude/longitude`
  - Flag automatico si distancia > umbral configurable
  - Vista de alertas en panel de operador

---

## Resumen de Prioridades

| # | Feature | Prioridad | Esfuerzo |
|---|---------|-----------|----------|
| 2.1 | Planificador de Rutas con Preview | ALTA | Grande |
| 1.1 | Notificacion ETA al Destinatario | ALTA | Medio |
| 4.1 | API Publica para Clientes | ALTA | Grande |
| 3.1 | Dashboard de SLA | ALTA | Medio |
| 5.1 | Navegacion Integrada | ALTA | Pequeno |
| 2.2 | Reoptimizacion Dinamica | MEDIA | Medio |
| 2.3 | Clustering de Envios | MEDIA | Medio |
| 3.2 | Mapa de Calor de Excepciones | MEDIA | Medio |
| 3.3 | Rutas Planificadas vs Ejecutadas | MEDIA | Medio |
| 5.2 | Chat Conductor-Operador | MEDIA | Medio |
| 1.2 | Reprogramacion por Destinatario | MEDIA | Medio |
| 6.1 | Prediccion de Volumen | MEDIA | Medio |
| 6.2 | Asignacion Inteligente Conductores | MEDIA | Medio |
| 4.2 | Integracion eCommerce | MEDIA | Grande |
| 2.4 | Turnos y Jornada | BAJA | Medio |
| 1.3 | Rating por Destinatario | BAJA | Pequeno |
| 5.3 | Checklist Pre-Ruta | BAJA | Pequeno |
| 6.3 | Deteccion Fraude POD | BAJA | Medio |
| 4.3 | Exportacion Contable | BAJA | Pequeno |

## Recomendacion de Implementacion

**Fase inmediata (maximo impacto):**
1. **Planificador de Rutas con Preview** (2.1) — Es el flujo core para la demo
2. **Navegacion Integrada** (5.1) — Esfuerzo minimo, valor inmediato para conductores
3. **Dashboard SLA** (3.1) — Diferenciador para captar clientes

**Segunda iteracion:**
4. **Notificacion ETA** (1.1) — Reduce excepciones ABSENT
5. **API Publica** (4.1) — Habilita integraciones y escala

**Tercera iteracion:**
6. Features de analytics (3.2, 3.3) y AI (6.1, 6.2)
