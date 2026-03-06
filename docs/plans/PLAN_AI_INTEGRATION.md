# Plan: Implementación Completa de IA en mxo-track

## Progreso

| Fase | Descripción | Estado |
|------|-------------|--------|
| 0 | Infraestructura Base (messenger async, pgvector, tablas ML, clientes API, sidecar) | Pendiente |
| 1 | Geocodificación y Validación de Direcciones (quick win, sin ML) | Pendiente |
| 2 | CSV Quality Score + Direcciones Problemáticas (quick wins) | Pendiente |
| 3 | NLP Clasificación de Excepciones (solo Claude API) | Pendiente |
| 4 | Service Time + ETA Predictivo (ML real) | Pendiente |
| 5 | Predicción de Fallos de Entrega | Pendiente |
| 6 | Búsqueda Semántica (pgvector + OpenAI) | Pendiente |
| 7 | Demand Forecasting | Pendiente |
| 8 | Driver-Zone Affinity + Clustering de Zonas | Pendiente |
| 9 | Comunicación Proactiva + Auto-generación de Delivery Notes | Pendiente |
| 10 | Detección de Anomalías en Flota | Pendiente |
| 11 | Asistente Conversacional para Operadores | Pendiente |

---

## Contexto

El sistema tiene datos ricos (eventos de envío, posiciones GPS, excepciones con notas, tiempos de servicio, métricas de ruta) pero cero IA. Este plan implementa los puntos de integración IA identificados, con ML completo (feature store, training pipelines, model serving, A/B testing, drift monitoring) y proveedores mixtos (Claude API para NLP, OpenAI para embeddings, modelos custom para predicción).

### Principios de Diseño (revisión v2)

1. **Quick wins primero**: Fases que no requieren ML van antes (geocodificación, quality score, NLP).
2. **Separación clara PHP vs Python**: PHP hace llamadas directas a LLM (Claude/OpenAI = HTTP JSON simple). Python sidecar solo para ML real (training, feature computation, model serving).
3. **Cold-start explícito**: Cada fase ML define mínimo de datos, fallbacks, y plan de bootstrapping.
4. **Observabilidad desde el día 1**: Latencia de predicción, tasa de fallback, y salud ML integrados en admin dashboard.
5. **Rate limiting para APIs externas**: Throttling obligatorio para Claude y OpenAI.
6. **Caché de predicciones**: Evitar llamadas repetidas al sidecar para los mismos inputs.
7. **UI/UX para resultados de IA**: Cada fase define dónde y cómo se visualizan los resultados.

## Decisión Arquitectónica: Python Sidecar

PHP orquesta la lógica de dominio y hace llamadas a LLM (Claude/OpenAI son HTTP JSON simples). Un **Python FastAPI sidecar** (`ml-service`) entrena modelos, computa features pesados, y sirve predicciones. Sigue el mismo patrón que VROOM/OSRM/Traccar: PHP thin-client via `HttpClientInterface`.

**Importante**: El sidecar solo se usa para Fases que requieren ML real (4, 5, 7, 8, 10). Las Fases 1, 2, 3, 6, 9, 11 son llamadas HTTP directas desde PHP a APIs externas o lógica PHP pura — no pasan por el sidecar.

---

## Fase 0: Infraestructura Base

### 0.1 Messenger Async (PRIORITARIO — prerrequisito para todo)

**Problema actual**: `messenger.yaml` usa transporte sync. Cualquier llamada a Claude/OpenAI (2-5s) bloquearía requests HTTP.

Modificar `messenger.yaml` — usar **Redis** (ya está en docker-compose, `redis:7-alpine`):
```yaml
transports:
    async:
        dsn: 'redis://redis:6379/messages/async'
        retry_strategy:
            max_retries: 3
            delay: 1000
            multiplier: 2
    ml:
        dsn: 'redis://redis:6379/messages/ml'
        retry_strategy:
            max_retries: 2
            delay: 5000
routing:
    'App\Message\MlTrainingMessage': ml
    'App\Message\BatchPredictionMessage': ml
    'App\Message\EmbeddingMessage': async
    'App\Message\NlpClassificationMessage': async
    'App\Message\FleetAnomalyCheckMessage': async
```

**Nota**: Redis es más performante que Doctrine para colas. Ya está corriendo en el stack.

### 0.2 pgvector

**Cambio requerido en docker-compose**: La imagen `postgres:16-alpine` NO incluye pgvector. Cambiar a `ankane/pgvector:v0.7.0-pg16` o `pgvector/pgvector:pg16`.

Migración: `CREATE EXTENSION IF NOT EXISTS vector;`

### 0.3 Tablas ML (migración Doctrine)

| Tabla | Propósito |
|-------|-----------|
| `ml_feature_store` | Features materializados (feature_set, entity_type, entity_id, features JSONB, computed_at) |
| `ml_model` | Registro de modelos (name, type, version, metrics JSONB, artifact_path, is_active) |
| `ml_prediction_log` | Log de predicciones para drift/A/B (model_name, version, input, prediction, actual_outcome, ab_group, latency_ms) |
| `ml_embedding` | Embeddings pgvector (entity_type, entity_id, embedding vector(1536), text_content) |
| `ml_ab_test` | Config A/B tests (name, model, control_version, treatment_version, traffic_pct) |
| `address_risk` | Direcciones problemáticas (address_hash, address, total_deliveries, exception_count, exception_rate, last_updated) |

### 0.4 Clientes PHP API (patrón VroomApiClient)

| Archivo nuevo | Constructor | Métodos |
|---------------|-------------|---------|
| `src/Service/MlApiClient.php` | `HttpClientInterface, $mlServiceUrl` | `predict(model, features)`, `train(model, params)`, `health()` |
| `src/Service/ClaudeApiClient.php` | `HttpClientInterface, $claudeApiKey` | `complete(system, user, model)` |
| `src/Service/OpenAiApiClient.php` | `HttpClientInterface, $openaiApiKey` | `embed(text)`, `embedBatch(texts)` |
| `src/Service/RateLimitedApiClient.php` | Decorator | `call(callable, $maxPerMinute, $retryAfterMs)` — throttling para APIs externas |

Env vars: `ML_SERVICE_URL`, `CLAUDE_API_KEY`, `OPENAI_API_KEY`

### 0.5 Python ML Sidecar — Docker (diferido hasta Fase 4)

- Nuevo servicio `ml` en `docker-compose.local.yml` (FastAPI, python:3.12-slim, puerto 5200)
- Deps: scikit-learn, lightgbm, prophet, numpy, psycopg2, sqlalchemy
- Env: `DATABASE_URL` (mismo PostgreSQL), `ML_SERVICE_URL=http://ml:5200` en servicio `app`
- Estructura: `ml-service/app/{main.py, routers/, models/, feature_store.py, model_registry.py}`
- **No se necesita hasta Fase 4** — las fases 1-3 son PHP puro + APIs externas

### 0.6 A/B Testing
- Nuevo: `src/Service/AbTestService.php` — hash determinístico para asignar 'control'|'treatment', consulta `ml_ab_test`

### 0.7 Drift Monitoring
- Nuevo: `src/Command/MlDriftCheckCommand.php` — compara predicciones vs actuals en `ml_prediction_log`, alerta si MAE > 1.5× training MAE

### 0.8 Observabilidad ML (NUEVO)
- Modificar: `src/Service/SystemHealthService.php` — nuevo check `mlServiceHealth()`:
  - Latencia de predicción (p50/p95/p99) desde `ml_prediction_log.latency_ms`
  - Tasa de fallback (predicciones con `ab_group = 'fallback'` / total)
  - Último drift check y resultado
- Modificar: `templates/admin/dashboard.html.twig` — nuevo panel "ML Health" con métricas

### 0.9 Caché de Predicciones (NUEVO)
- Nuevo: `src/Service/PredictionCacheService.php`
  - Usa Redis con TTL configurable (default 15min)
  - Key: `ml:pred:{model}:{hash(features)}`
  - Evita llamadas repetidas al sidecar para los mismos inputs
  - Métricas: cache hit/miss ratio

---

## Fase 1: Geocodificación y Validación (antes Fase 4 — adelantada por alto impacto)

**Por qué primero**: Si el CSV no trae coordenadas, hoy el shipment es inútil. No requiere ML ni sidecar — es una llamada HTTP. Bajo riesgo, alto impacto inmediato.

### Geocoding
- Nuevo: `src/Service/GeocodingService.php`
  - `geocode(address): ?{lat, lng, confidence}`
  - Usa Nominatim (self-hosted o API pública con rate limit 1 req/s) o Google Geocoding
  - Cache en Redis por hash de dirección (TTL: 30 días)

### Validación
- Nuevo: `src/Service/AddressValidationService.php`
  - Cross-valida dirección↔coordenadas via reverse geocoding
  - Flags inconsistencias (ej: coordenadas en el mar, país diferente)

### Modificaciones
- **`src/Service/ShipmentCsvImporter.php`**: inyectar `GeocodingService`
  - Si lat/lng vacíos pero address presente → geocodificar
  - Detección fuzzy de duplicados (Levenshtein sobre dirección normalizada)

### UI
- Indicador en import results: "X shipments geocodificados automáticamente"
- Warning visual si confidence < 0.7 en la geocodificación

---

## Fase 2: CSV Quality Score + Direcciones Problemáticas (NUEVO)

Dos quick wins que mejoran la calidad de datos antes de entrar al pipeline.

### 2.1 CSV Quality Score
- Nuevo: `src/Service/CsvQualityAnalyzer.php`
  - `analyze(array $rows): QualityReport`
  - Checks (sin ML, reglas + estadísticas):
    - Coordenadas que caen en el mar o fuera de España
    - Teléfonos con formato inválido (regex)
    - Direcciones incompletas (sin número, sin ciudad)
    - Pesos/volúmenes outliers (>3σ del dataset)
    - Referencias duplicadas
    - Campos obligatorios vacíos
  - Output: score 0-100, lista de warnings por fila
- Modificar: **`src/Service/ShipmentCsvImporter.php`** — ejecutar análisis antes de importar, mostrar resumen
- Nuevo: `templates/admin/shipment/_csv_quality_report.html.twig` — tabla de warnings con opción de continuar/cancelar

### 2.2 Direcciones Problemáticas
- Nuevo: `src/Service/AddressRiskService.php`
  - `checkAddress(string $address): ?AddressRisk` — busca en `address_risk` tabla
  - `updateRiskScores()` — recalcula periódicamente desde historial de entregas
  - Marca como "dirección de riesgo" si exception_rate > 30% y total_deliveries > 5
- Nuevo: `src/Command/UpdateAddressRiskCommand.php` — cron diario
- Modificar: **`src/Service/ShipmentCsvImporter.php`** — warning "Dirección X tiene 45% de excepciones históricas"
- **UI**: Badge de warning en lista de paradas de ruta si dirección es de riesgo

---

## Fase 3: NLP Clasificación de Excepciones

**No requiere sidecar Python** — solo `ClaudeApiClient` + Messenger async.

### Clasificador
- Nuevo: `src/Service/ExceptionClassifierService.php`
  - `classify(exceptionNotes, ExceptionCode)`: llama `ClaudeApiClient` con prompt estructurado
  - Retorna: `{subcategory, actionable_insight, suggested_action}`
  - Rate limiting: max 30 requests/min a Claude API via `RateLimitedApiClient`

### Async
- Nuevo: `src/Message/NlpClassificationMessage.php` + handler
  - Dispatch al crear ShipmentEvent con excepción
  - Almacena resultado en nueva columna `shipment_event.ai_classification` (JSON, migración)

### Patrones
- Nuevo: `src/Service/ExceptionPatternService.php` — agrega por subcategoría/zona/hora para dashboard

### UI (NUEVO)
- Modificar: `templates/admin/route/show.html.twig` — mostrar subcategoría y suggested_action junto a cada excepción
- Nuevo: `templates/admin/report/_exception_patterns.html.twig` — dashboard de patrones (top subcategorías, heatmap por zona/hora)

---

## Fase 4: Service Time + ETA Predictivo (Puntos 1 y 3)

Acoplados: el mismo modelo predice service time → alimenta VROOM (punto 3) y ETA (punto 1).

**Requiere sidecar Python** (primera fase que lo necesita).

### Cold-Start (NUEVO)
- **Mínimo de datos**: 500 rutas completadas (con `deliveredAt` en RouteStop)
- **Fallback mientras no hay modelo**: 300s default (actual) — no cambia nada hasta tener datos
- **Bootstrapping**: Si existen datos históricos en CSV/Excel, crear comando de import: `app:ml:import-historical-routes`

### Feature Extraction
- Nuevo: `src/Service/FeatureExtractor/ServiceTimeFeatureExtractor.php`
  - Features: `hour_of_day`, `day_of_week`, `zone_geohash_6`, `driver_id`, `parcel_count`, `total_weight_kg`, `has_time_window`, `stop_sequence_position`
  - Label: service time real (diff entre `deliveredAt` consecutivos - tiempo OSRM de viaje)
- Nuevo: `src/Command/MlExtractFeaturesCommand.php` — `app:ml:extract-features --set=service_time`

### Training (Python)
- Endpoint: `POST /train/service-time` — LightGBM regressor, lee `ml_feature_store`, guarda en `ml_model`

### Prediction Service
- Nuevo: `src/Service/ServiceTimePredictionService.php`
  - `predictServiceTime(Shipment, ?User driver, DateTimeImmutable)`: llama ML sidecar, fallback a 300s
  - **Caché**: via `PredictionCacheService`, key por (shipment_id, driver_id, hour), TTL 15min
  - Log en `ml_prediction_log` (incluye `latency_ms`)

### Modificaciones
- **`src/Service/VroomRequestMapper.php`**: inyectar `ServiceTimePredictionService`, reemplazar `DEFAULT_SERVICE_TIME_SECONDS` con predicción
- **`src/Service/EtaService.php`**: inyectar predicción, reemplazar `+= 120` hardcoded con service time predicho

### Backfill Outcomes
- Nuevo: `src/EventSubscriber/PredictionOutcomeSubscriber.php` — cuando ruta → DONE, computa service times reales, actualiza `ml_prediction_log.actual_outcome`

### UI (NUEVO)
- Modificar: ETA display en route show — indicador "(predicción ML)" vs "(estimación base)" según origen

---

## Fase 5: Predicción de Fallos de Entrega (Punto 2)

### Cold-Start (NUEVO)
- **Mínimo de datos**: 1000 entregas completadas (mix de exitosas y excepciones)
- **Fallback**: heurística simple (exception_rate histórica de la dirección, de `address_risk` tabla de Fase 2)
- **Bootstrapping**: Usa datos de `address_risk` como feature adicional

### Feature Extraction
- Nuevo: `src/Service/FeatureExtractor/DeliveryRiskFeatureExtractor.php`
  - Features: `hour_of_day`, `day_of_week`, `zone_geohash`, `has_phone`, `has_notes`, `prior_exceptions_at_address` (de Fase 2), `prior_exceptions_for_recipient`, `delivery_attempt_number`, `parcel_count`, `address_risk_score`
  - Label: 1 si excepción, 0 si entregado

### Training (Python)
- Endpoint: `POST /train/delivery-risk` — LightGBM clasificador binario, output probabilidad 0.0-1.0

### Integration
- Nuevo: `src/Service/DeliveryRiskService.php`
  - `predictRisk(RouteStop)`: score 0-1, fallback a heurística simple
  - `getRiskScoresForRoute(Route)`: scores por parada, con caché
- Modificar: `src/Service/AlertService.php` — nuevo método `predictDeliveryRisk()` que usa `DeliveryRiskService`

### UI (NUEVO)
- Modificar: `templates/admin/route/show.html.twig` — indicador visual por parada:
  - Verde (score < 0.2): "Bajo riesgo"
  - Amarillo (0.2-0.5): "Riesgo moderado"
  - Rojo (> 0.5): "Alto riesgo — considerar contacto previo"
- Modificar: `templates/admin/route/index.html.twig` — columna "Riesgo" con el max score de la ruta

---

## Fase 6: Búsqueda Semántica (Punto 6)

### Pipeline de Embeddings
- Nuevo: `src/Service/EmbeddingService.php` — llama `OpenAiApiClient::embed()`, almacena en `ml_embedding`
  - Rate limiting: max 500 embeddings/min via `RateLimitedApiClient`
- Nuevo: `src/Message/EmbeddingMessage.php` + handler — async al crear/actualizar shipments/routes
- Nuevo: `src/Command/MlIndexEmbeddingsCommand.php` — backfill batch (con progress bar, batch de 100)

### Search
- Modificar: **`src/Service/SearchService.php`**
  - Nuevo método `semanticSearch(query, user)`: embed query → pgvector `<=>` nearest neighbor
  - Merge con búsqueda LIKE existente, deduplicar
  - Respetar tenant filtering (JOIN con tablas customer_id)

### UI (NUEVO)
- Modificar: `templates/search/results.html.twig` — tab "Resultados semánticos" junto a "Resultados exactos"
- Indicar relevance score por resultado

---

## Fase 7: Demand Forecasting (Punto 7)

### Cold-Start (NUEVO)
- **Mínimo de datos**: 90 días de historial de shipments para series temporales
- **Fallback**: promedio móvil simple (últimos 4 semanas, mismo día de la semana)

### Features
- Nuevo: `src/Service/FeatureExtractor/DemandFeatureExtractor.php` — volúmenes diarios por cliente/zona/day_of_week

### Forecasting (Python)
- Endpoint: `POST /predict/demand-forecast` — Prophet/ARIMA, series temporales por zona
- Output: `[{date, predicted_shipments, lower, upper}]` para 7/14/30 días

### Integration
- Nuevo: `src/Service/DemandForecastService.php` — `forecast(zone, days)`
- Modificar: **`src/Service/ReportingService.php`** — nuevo `getVolumeForecasts()`, recomendación de flota

### UI (NUEVO)
- Nuevo: `templates/admin/report/_demand_forecast.html.twig` — chart con predicción + bandas de confianza (Chart.js)
- Integrar en `ReportController` como nueva pestaña

---

## Fase 8: Driver-Zone Affinity + Clustering de Zonas (Punto 8)

### 8.1 Clustering Automático de Zonas (NUEVO)

Hoy las zonas son por geohash fijo. Con clustering se crean zonas naturales que reflejan la realidad operativa.

- Endpoint Python: `POST /cluster/delivery-zones` — K-means sobre coordenadas históricas de entrega
  - Input: coordenadas de todas las entregas de los últimos 90 días
  - Output: clusters con centroide, radio, y nombre sugerido (basado en reverse geocoding del centroide)
- Nuevo: `src/Service/DeliveryZoneClusterService.php` — `computeZones(Customer)`
- Nueva tabla `delivery_zone` (migración): `customer_id, name, center_lat, center_lng, radius_km, shipment_count`
- Alimenta mejor las Fases 7 y 8.2

### 8.2 Driver-Zone Affinity

### Features
- Nuevo: `src/Service/FeatureExtractor/DriverZoneFeatureExtractor.php`
  - Por par (driver, delivery_zone): deliveries, exception_rate, avg_service_time, adherence

### Model (Python)
- Endpoint: `POST /predict/driver-zone-affinity` — scoring ponderado de métricas históricas

### Integration
- Nuevo: `src/Service/DriverAffinityService.php` — `getRecommendedDrivers(shipments)`, `getDriverScore(driver, zone)`
- Modificar: **`src/Service/RouteBuilder.php`** — después de VROOM asignar paradas, sugerir conductor óptimo por zona

### UI (NUEVO)
- Modificar: form de creación de ruta — mostrar "Conductores recomendados" con score al seleccionar driver
- Nuevo: `templates/admin/driver/_zone_affinity.html.twig` — mapa de heatmap de afinidad por zona para cada driver

---

## Fase 9: Comunicación Proactiva + Auto-generación de Delivery Notes (Punto 9)

### Delay Prediction
- Nuevo: `src/Service/DelayPredictionService.php` — compara ETA predicho vs ventana de entrega

### Message Generation
- Nuevo: `src/Service/NotificationMessageGenerator.php`
  - `generateDelayMessage(RouteStop, predictedEta)`: llama `ClaudeApiClient` con contexto
  - Genera mensajes en español: "Su paquete TRK-A4F2-BC31 llegará con ~25 min de retraso"
  - Rate limiting via `RateLimitedApiClient`

### Auto-generación de Delivery Notes (NUEVO)
- Nuevo: `src/Service/DeliveryNoteAiEnricher.php`
  - `enrichWithHistoricalNotes(RouteStop): ?string`
  - Consulta historial de `ShipmentEvent.payload.notes` de la misma dirección
  - Llama a Claude para sintetizar instrucciones útiles: "Portería cerrada después de 14h, llamar timbre 3B"
  - Se ejecuta async al crear ruta, resultado en `route_stop.ai_notes` (nueva columna)
- Modifica el `DeliveryNoteGenerator` existente para incluir AI notes junto a notes manuales

### Integration
- Modificar: **`src/Service/WebhookNotificationService.php`** — nuevo `sendProactiveNotification()`, evento `shipment.delay_predicted`
- Nuevo: `src/Command/ProactiveNotificationCommand.php` — cada 5 min durante rutas activas, detecta retrasos, envía una vez por parada

### UI (NUEVO)
- Mostrar `ai_notes` en la vista de detalle de parada del conductor (driver app)
- Badge "AI" para distinguir notas generadas vs manuales

---

## Fase 10: Detección de Anomalías en Flota (Punto 10)

### Anomaly Detection (Python)
- Endpoint: `POST /predict/fleet-anomaly`
  - Input: posiciones recientes + paradas planificadas
  - Detecta: desvío de ruta, velocidad anómala (z-score), paradas largas, violaciones de geofence

### Integration
- Nuevo: `src/Service/FleetAnomalyService.php` — `checkAnomaly(Vehicle, Route)`: llama ML sidecar
- Nuevo: `src/Message/FleetAnomalyCheckMessage.php` + handler — async tras ingesta de posiciones
- Modificar: **`src/Service/TraccarIngestionService.php`** — dispatch anomaly check si vehículo tiene ruta activa
- Nueva tabla `geofence` (migración): `customer_id, name, center_lat, center_lng, radius_meters`

### UI (NUEVO)
- Alerta en tiempo real via Mercure en fleet map: ícono de warning sobre vehículo anómalo
- Panel de anomalías en admin dashboard con tipo, severidad, y hora

---

## Fase 11: Asistente Conversacional para Operadores (NUEVO)

### Motivación
Los operadores buscan info dispersa ("qué rutas tienen más excepciones esta semana", "cuántos paquetes tiene pendiente el cliente X") y navegan entre 5+ pantallas. Un chat con Claude que tenga acceso a los servicios existentes como tools les ahorra minutos por consulta.

### Implementación
- Nuevo: `src/Controller/Admin/AiAssistantController.php`
  - `GET /admin/ai-assistant` — vista del chat
  - `POST /admin/ai-assistant/message` — envía mensaje, retorna respuesta streamed
- Nuevo: `src/Service/AiAssistantService.php`
  - Usa `ClaudeApiClient` con **tool_use** (function calling)
  - Tools disponibles (wrappers de servicios existentes):
    - `search_shipments(query)` → `SearchService`
    - `get_delivery_report(date_range)` → `ReportingService::getDeliveryReport()`
    - `get_driver_performance(driver_id)` → `ReportingService::getDriverPerformance()`
    - `get_customer_report(customer_id)` → `ReportingService::getCustomerReport()`
    - `get_route_details(route_id)` → Route entity + stops
    - `get_active_alerts()` → `AlertService`
    - `get_demand_forecast(zone, days)` → `DemandForecastService` (si Fase 7 activa)
  - System prompt en español con contexto del negocio
  - Respeta tenant isolation (tools filtran por customer_id del operador)

### UI
- Nuevo: `templates/admin/ai_assistant/index.html.twig` — chat UI con Turbo Streams para respuestas en tiempo real
- Floating action button en toda la sección admin para acceso rápido
- Historial de conversación por sesión

### Seguridad
- Solo `ROLE_ADMIN` y `ROLE_OPERATOR`
- Rate limiting: 20 mensajes/min por usuario
- Audit log de cada consulta

---

## Secuenciación Revisada

| Semana | Fase | Dependencias | Complejidad |
|--------|------|-------------|-------------|
| 1 | Fase 0: Infraestructura (messenger async, clientes API, tablas) | — | Media |
| 2 | Fase 1: Geocodificación | Fase 0 (messenger async) | Baja |
| 3 | Fase 2: CSV Quality + Direcciones Problemáticas | Fase 0 (tabla address_risk) | Baja |
| 4 | Fase 3: NLP Excepciones | Fase 0 (ClaudeApiClient + async) | Media |
| 5-6 | Fase 4: Service Time + ETA (incluye sidecar Python) | Fase 0 completa | Alta |
| 7 | Fase 5: Delivery Risk | Fases 0+2 (address_risk) + 4 (sidecar) | Alta |
| 8 | Fase 6: Búsqueda Semántica | Fase 0 (pgvector + OpenAI) | Media |
| 9 | Fase 7: Demand Forecasting | Fases 0+4 (sidecar + datos históricos) | Alta |
| 10 | Fase 8: Driver-Zone Affinity + Clustering | Fases 0+4 (sidecar) | Alta |
| 11 | Fase 9: Comunicación Proactiva + AI Notes | Fases 0+4 (ETA predictivo) | Media |
| 12 | Fase 10: Anomalías Flota | Fase 0+4 (sidecar) | Alta |
| 13 | Fase 11: Asistente Conversacional | Fase 0 (ClaudeApiClient) | Media |

**Nota**: Las semanas 1-4 no requieren el sidecar Python, lo que permite arrancar rápido con valor visible. El sidecar se introduce en semana 5 con Fase 4.

---

## Estrategia Cold-Start (NUEVO — transversal)

| Fase | Mínimo de datos | Fallback | Bootstrapping |
|------|----------------|----------|---------------|
| 4: Service Time | 500 rutas completadas | 300s default | Import histórico CSV |
| 5: Delivery Risk | 1000 entregas (mix éxito/excepción) | Heurística de address_risk (Fase 2) | Datos de Fase 2 como features |
| 7: Demand Forecast | 90 días de historial | Promedio móvil 4 semanas | Datos de CsvImportRun existentes |
| 8: Driver Affinity | 200 rutas por conductor | Asignación manual | Métricas de ReportingService |
| 10: Anomalías | 30 días de posiciones GPS | Umbrales fijos (velocidad >120km/h, parada >45min) | Datos de VehiclePosition |

---

## Métricas de Verificación

| Punto | Métrica | Objetivo |
|-------|---------|----------|
| 1 Geocodificación | Error < 100m | 95% de direcciones |
| 2 CSV Quality | Detección de errores vs revisión manual | > 90% recall |
| 2 Direcciones Problemáticas | Predicción de excepción por dirección | > 70% precision |
| 3 NLP Classification | Precisión subcategoría (review manual 50) | > 85% |
| 4 Service Time/ETA | MAE predicho vs real | < 60 segundos |
| 5 Delivery Risk | AUC-ROC | > 0.70 |
| 6 Búsqueda Semántica | Recall@10 | > 80% |
| 7 Demand Forecast | MAPE semana held-out | < 25% |
| 8 Driver Affinity | Mejora success rate vs random | > 5% |
| 9 Comunicación Proactiva | Retrasos predichos >10min antes | > 60% |
| 10 Anomalías Flota | False positive rate / True detection | < 10% / > 80% |
| 11 Asistente | Satisfacción operador (survey) | > 4/5 |

### Métricas Operacionales (NUEVO)

| Métrica | Umbral de alerta |
|---------|-----------------|
| Latencia predicción p95 | > 500ms |
| Tasa de fallback | > 30% en 24h |
| Rate limit hits (Claude/OpenAI) | > 10/hora |
| Cache hit ratio (predicciones) | < 50% |
| Messenger queue depth | > 100 mensajes pendientes |

A/B testing: `AbTestService` split tráfico, compara control (heurística actual) vs treatment (ML). Drift monitoring diario via `MlDriftCheckCommand`.

---

## Resumen de Archivos

### Nuevos (PHP)
- `src/Service/MlApiClient.php`, `ClaudeApiClient.php`, `OpenAiApiClient.php`, `RateLimitedApiClient.php`
- `src/Service/AbTestService.php`, `PredictionCacheService.php`
- `src/Service/GeocodingService.php`, `AddressValidationService.php`
- `src/Service/CsvQualityAnalyzer.php`, `AddressRiskService.php`
- `src/Service/ExceptionClassifierService.php`, `ExceptionPatternService.php`
- `src/Service/ServiceTimePredictionService.php`
- `src/Service/DeliveryRiskService.php`
- `src/Service/EmbeddingService.php`
- `src/Service/DemandForecastService.php`
- `src/Service/DeliveryZoneClusterService.php`
- `src/Service/DriverAffinityService.php`
- `src/Service/DelayPredictionService.php`, `NotificationMessageGenerator.php`, `DeliveryNoteAiEnricher.php`
- `src/Service/FleetAnomalyService.php`
- `src/Service/AiAssistantService.php`
- `src/Service/FeatureExtractor/{ServiceTime,DeliveryRisk,Demand,DriverZone}FeatureExtractor.php`
- `src/Controller/Admin/AiAssistantController.php`
- `src/Command/Ml{ExtractFeatures,DriftCheck,IndexEmbeddings}Command.php`
- `src/Command/{ProactiveNotification,UpdateAddressRisk}Command.php`
- `src/Message/{MlTraining,BatchPrediction,Embedding,NlpClassification,FleetAnomalyCheck}Message.php` + handlers
- `src/EventSubscriber/PredictionOutcomeSubscriber.php`

### Nuevos (Python sidecar)
- `ml-service/app/main.py` (FastAPI)
- `ml-service/app/routers/{training,prediction,anomaly,demand,clustering}.py`
- `ml-service/app/models/{service_time,delivery_risk,driver_affinity,demand_forecast,anomaly_detector,zone_clustering}.py`
- `ml-service/Dockerfile`, `requirements.txt`

### Nuevos (Templates)
- `templates/admin/ai_assistant/index.html.twig`
- `templates/admin/shipment/_csv_quality_report.html.twig`
- `templates/admin/report/_exception_patterns.html.twig`
- `templates/admin/report/_demand_forecast.html.twig`
- `templates/admin/driver/_zone_affinity.html.twig`

### Modificados
- `docker-compose.local.yml` — imagen pgvector, servicio `ml`, Redis como transporte Messenger
- `backend/.env` — `ML_SERVICE_URL`, `CLAUDE_API_KEY`, `OPENAI_API_KEY`
- `config/services.yaml` — registrar nuevos servicios
- `config/packages/messenger.yaml` — transportes async/ml via Redis
- `src/Service/SystemHealthService.php` — panel ML health
- `src/Service/EtaService.php` — inyectar predicción service time
- `src/Service/VroomRequestMapper.php` — inyectar predicción service time
- `src/Service/AlertService.php` — añadir risk prediction
- `src/Service/SearchService.php` — añadir `semanticSearch()`
- `src/Service/ShipmentCsvImporter.php` — inyectar geocoding + quality analyzer + address risk
- `src/Service/ReportingService.php` — añadir forecasts
- `src/Service/RouteBuilder.php` — añadir driver affinity
- `src/Service/WebhookNotificationService.php` — notificaciones proactivas
- `src/Service/TraccarIngestionService.php` — dispatch anomaly check
- `src/Service/DeliveryNoteGenerator.php` — incluir AI notes
- `templates/admin/dashboard.html.twig` — panel ML health
- `templates/admin/route/show.html.twig` — risk scores, exception classifications, AI notes
- `templates/admin/route/index.html.twig` — columna de riesgo
- `templates/search/results.html.twig` — tab semántica
