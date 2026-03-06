# Plan: Implementación Completa de IA en mxo-track

## Progreso

| Fase | Descripción | Estado |
|------|-------------|--------|
| 0 | Infraestructura Base (sidecar, pgvector, tablas ML, clientes API, messenger) | Pendiente |
| 1 | Service Time + ETA Predictivo (Puntos 1 y 3) | Pendiente |
| 2 | Predicción de Fallos de Entrega (Punto 2) | Pendiente |
| 3 | NLP Clasificación de Excepciones (Punto 4) | Pendiente |
| 4 | Geocodificación y Validación (Punto 5) | Pendiente |
| 5 | Búsqueda Semántica (Punto 6) | Pendiente |
| 6 | Demand Forecasting (Punto 7) | Pendiente |
| 7 | Driver-Zone Affinity (Punto 8) | Pendiente |
| 8 | Comunicación Proactiva (Punto 9) | Pendiente |
| 9 | Detección de Anomalías en Flota (Punto 10) | Pendiente |

---

## Contexto

El sistema tiene datos ricos (eventos de envío, posiciones GPS, excepciones con notas, tiempos de servicio, métricas de ruta) pero cero IA. Este plan implementa los 10 puntos de integración IA identificados, con ML completo (feature store, training pipelines, model serving, A/B testing, drift monitoring) y proveedores mixtos (Claude API para NLP, OpenAI para embeddings, modelos custom para predicción).

## Decisión Arquitectónica: Python Sidecar

PHP orquesta la lógica de dominio y hace llamadas a LLM (Claude/OpenAI son HTTP JSON simples). Un **Python FastAPI sidecar** (`ml-service`) entrena modelos, computa features pesados, y sirve predicciones. Sigue el mismo patrón que VROOM/OSRM/Traccar: PHP thin-client via `HttpClientInterface`.

---

## Fase 0: Infraestructura Base

### 0.1 Python ML Sidecar — Docker
- Nuevo servicio `ml` en `docker-compose.local.yml` (FastAPI, python:3.12-slim, puerto 5200)
- Deps: scikit-learn, lightgbm, prophet, numpy, psycopg2, sqlalchemy
- Env: `DATABASE_URL` (mismo PostgreSQL), `ML_SERVICE_URL=http://ml:5200` en servicio `app`
- Estructura: `ml-service/app/{main.py, routers/, models/, feature_store.py, model_registry.py}`

### 0.2 pgvector
- Migración: `CREATE EXTENSION IF NOT EXISTS vector;`

### 0.3 Tablas ML (migración Doctrine)

| Tabla | Propósito |
|-------|-----------|
| `ml_feature_store` | Features materializados (feature_set, entity_type, entity_id, features JSONB, computed_at) |
| `ml_model` | Registro de modelos (name, type, version, metrics JSONB, artifact_path, is_active) |
| `ml_prediction_log` | Log de predicciones para drift/A/B (model_name, version, input, prediction, actual_outcome, ab_group) |
| `ml_embedding` | Embeddings pgvector (entity_type, entity_id, embedding vector(1536), text_content) |
| `ml_ab_test` | Config A/B tests (name, model, control_version, treatment_version, traffic_pct) |

### 0.4 Clientes PHP API (patrón VroomApiClient)

| Archivo nuevo | Constructor | Métodos |
|---------------|-------------|---------|
| `src/Service/MlApiClient.php` | `HttpClientInterface, $mlServiceUrl` | `predict(model, features)`, `train(model, params)`, `health()` |
| `src/Service/ClaudeApiClient.php` | `HttpClientInterface, $claudeApiKey` | `complete(system, user, model)` |
| `src/Service/OpenAiApiClient.php` | `HttpClientInterface, $openaiApiKey` | `embed(text)`, `embedBatch(texts)` |

Env vars: `ML_SERVICE_URL`, `CLAUDE_API_KEY`, `OPENAI_API_KEY`

### 0.5 Messenger Async
Modificar `messenger.yaml`: habilitar transporte `async` y `ml` (Doctrine):
```yaml
transports:
    async: 'doctrine://default?queue_name=async'
    ml: 'doctrine://default?queue_name=ml'
routing:
    'App\Message\MlTrainingMessage': ml
    'App\Message\BatchPredictionMessage': ml
    'App\Message\EmbeddingMessage': async
    'App\Message\NlpClassificationMessage': async
```

### 0.6 A/B Testing
- Nuevo: `src/Service/AbTestService.php` — hash determinístico para asignar 'control'|'treatment', consulta `ml_ab_test`

### 0.7 Drift Monitoring
- Nuevo: `src/Command/MlDriftCheckCommand.php` — compara predicciones vs actuals en `ml_prediction_log`, alerta si MAE > 1.5× training MAE

---

## Fase 1: Service Time + ETA Predictivo (Puntos 1 y 3)

Acoplados: el mismo modelo predice service time → alimenta VROOM (punto 3) y ETA (punto 1).

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
  - Log en `ml_prediction_log`

### Modificaciones
- **`src/Service/VroomRequestMapper.php`**: inyectar `ServiceTimePredictionService`, reemplazar `DEFAULT_SERVICE_TIME_SECONDS` con predicción
- **`src/Service/EtaService.php`**: inyectar predicción, reemplazar `+= 120` hardcoded con service time predicho

### Backfill Outcomes
- Nuevo: `src/EventSubscriber/PredictionOutcomeSubscriber.php` — cuando ruta → DONE, computa service times reales, actualiza `ml_prediction_log.actual_outcome`

---

## Fase 2: Predicción de Fallos de Entrega (Punto 2)

### Feature Extraction
- Nuevo: `src/Service/FeatureExtractor/DeliveryRiskFeatureExtractor.php`
  - Features: `hour_of_day`, `day_of_week`, `zone_geohash`, `has_phone`, `has_notes`, `prior_exceptions_at_address`, `prior_exceptions_for_recipient`, `delivery_attempt_number`, `parcel_count`
  - Label: 1 si excepción, 0 si entregado

### Training (Python)
- Endpoint: `POST /train/delivery-risk` — LightGBM clasificador binario, output probabilidad 0.0-1.0

### Integration
- Nuevo: `src/Service/DeliveryRiskService.php`
  - `predictRisk(RouteStop)`: score 0-1, fallback a heurística simple
  - `getRiskScoresForRoute(Route)`: scores por parada
- Modificar: `src/Service/AlertService.php` — nuevo método `predictDeliveryRisk()` que usa `DeliveryRiskService`

---

## Fase 3: NLP Clasificación de Excepciones (Punto 4)

### Clasificador
- Nuevo: `src/Service/ExceptionClassifierService.php`
  - `classify(exceptionNotes, ExceptionCode)`: llama `ClaudeApiClient` con prompt estructurado
  - Retorna: `{subcategory, actionable_insight, suggested_action}`

### Async
- Nuevo: `src/Message/NlpClassificationMessage.php` + handler
  - Dispatch al crear ShipmentEvent con excepción
  - Almacena resultado en nueva columna `shipment_event.ai_classification` (JSON, migración)

### Patrones
- Nuevo: `src/Service/ExceptionPatternService.php` — agrega por subcategoría/zona/hora para dashboard

---

## Fase 4: Geocodificación y Validación (Punto 5)

### Geocoding
- Nuevo: `src/Service/GeocodingService.php`
  - `geocode(address): ?{lat, lng, confidence}`
  - Usa Nominatim (self-hosted o API) o Google Geocoding
  - Cache en Redis por hash de dirección

### Validación
- Nuevo: `src/Service/AddressValidationService.php`
  - Cross-valida dirección↔coordenadas via reverse geocoding
  - Flags inconsistencias

### Modificaciones
- **`src/Service/ShipmentCsvImporter.php`**: inyectar `GeocodingService`
  - Si lat/lng vacíos pero address presente → geocodificar
  - Detección fuzzy de duplicados (Levenshtein sobre dirección normalizada)

---

## Fase 5: Búsqueda Semántica (Punto 6)

### Pipeline de Embeddings
- Nuevo: `src/Service/EmbeddingService.php` — llama `OpenAiApiClient::embed()`, almacena en `ml_embedding`
- Nuevo: `src/Message/EmbeddingMessage.php` + handler — async al crear/actualizar shipments/routes
- Nuevo: `src/Command/MlIndexEmbeddingsCommand.php` — backfill batch

### Search
- Modificar: **`src/Service/SearchService.php`**
  - Nuevo método `semanticSearch(query, user)`: embed query → pgvector `<=>` nearest neighbor
  - Merge con búsqueda LIKE existente, deduplicar
  - Respetar tenant filtering (JOIN con tablas customer_id)

---

## Fase 6: Demand Forecasting (Punto 7)

### Features
- Nuevo: `src/Service/FeatureExtractor/DemandFeatureExtractor.php` — volúmenes diarios por cliente/zona/day_of_week

### Forecasting (Python)
- Endpoint: `POST /predict/demand-forecast` — Prophet/ARIMA, series temporales por zona
- Output: `[{date, predicted_shipments, lower, upper}]` para 7/14/30 días

### Integration
- Nuevo: `src/Service/DemandForecastService.php` — `forecast(zone, days)`
- Modificar: **`src/Service/ReportingService.php`** — nuevo `getVolumeForecasts()`, recomendación de flota

---

## Fase 7: Driver-Zone Affinity (Punto 8)

### Features
- Nuevo: `src/Service/FeatureExtractor/DriverZoneFeatureExtractor.php`
  - Por par (driver, zone_geohash): deliveries, exception_rate, avg_service_time, adherence

### Model (Python)
- Endpoint: `POST /predict/driver-zone-affinity` — scoring ponderado de métricas históricas

### Integration
- Nuevo: `src/Service/DriverAffinityService.php` — `getRecommendedDrivers(shipments)`, `getDriverScore(driver, zone)`
- Modificar: **`src/Service/RouteBuilder.php`** — después de VROOM asignar paradas, sugerir conductor óptimo por zona

---

## Fase 8: Comunicación Proactiva (Punto 9)

### Delay Prediction
- Nuevo: `src/Service/DelayPredictionService.php` — compara ETA predicho vs ventana de entrega

### Message Generation
- Nuevo: `src/Service/NotificationMessageGenerator.php`
  - `generateDelayMessage(RouteStop, predictedEta)`: llama `ClaudeApiClient` con contexto
  - Genera mensajes en español: "Su paquete TRK-A4F2-BC31 llegará con ~25 min de retraso"

### Integration
- Modificar: **`src/Service/WebhookNotificationService.php`** — nuevo `sendProactiveNotification()`, evento `shipment.delay_predicted`
- Nuevo: `src/Command/ProactiveNotificationCommand.php` — cada 5 min durante rutas activas, detecta retrasos, envía una vez por parada

---

## Fase 9: Detección de Anomalías en Flota (Punto 10)

### Anomaly Detection (Python)
- Endpoint: `POST /predict/fleet-anomaly`
  - Input: posiciones recientes + paradas planificadas
  - Detecta: desvío de ruta, velocidad anómala (z-score), paradas largas, violaciones de geofence

### Integration
- Nuevo: `src/Service/FleetAnomalyService.php` — `checkAnomaly(Vehicle, Route)`: llama ML sidecar
- Nuevo: `src/Message/FleetAnomalyCheckMessage.php` + handler — async tras ingesta de posiciones
- Modificar: **`src/Service/TraccarIngestionService.php`** — dispatch anomaly check si vehículo tiene ruta activa
- Nueva tabla `geofence` (migración): `customer_id, name, center_lat, center_lng, radius_meters`

---

## Secuenciación

| Semana | Fase | Dependencias |
|--------|------|-------------|
| 1-2 | Fase 0: Infraestructura | — |
| 3-4 | Fase 1: Service Time + ETA | Fase 0 |
| 5 | Fase 2: Delivery Risk | Fase 0 |
| 6 | Fase 3: NLP Excepciones | Fase 0 (solo ClaudeApiClient) |
| 7 | Fase 4: Geocodificación | Fase 0 |
| 8 | Fase 5: Búsqueda Semántica | Fase 0 (pgvector + OpenAI) |
| 9 | Fase 6: Demand Forecasting | Fases 0+1 (datos históricos) |
| 10 | Fase 7: Driver-Zone Affinity | Fases 0+1 |
| 11 | Fase 8: Comunicación Proactiva | Fases 0+1 (ETA predictivo) |
| 12 | Fase 9: Anomalías Flota | Fase 0 |

---

## Métricas de Verificación

| Punto | Métrica | Objetivo |
|-------|---------|----------|
| 1+3 Service Time/ETA | MAE predicho vs real | < 60 segundos |
| 2 Delivery Risk | AUC-ROC | > 0.70 |
| 4 NLP Classification | Precisión subcategoría (review manual 50) | > 85% |
| 5 Geocodificación | Error < 100m | 95% de direcciones |
| 6 Búsqueda Semántica | Recall@10 | > 80% |
| 7 Demand Forecast | MAPE semana held-out | < 25% |
| 8 Driver Affinity | Mejora success rate vs random | > 5% |
| 9 Comunicación Proactiva | Retrasos predichos >10min antes | > 60% |
| 10 Anomalías Flota | False positive rate / True detection | < 10% / > 80% |

A/B testing: `AbTestService` split tráfico, compara control (heurística actual) vs treatment (ML). Drift monitoring diario via `MlDriftCheckCommand`.

---

## Resumen de Archivos

### Nuevos (PHP)
- `src/Service/MlApiClient.php`, `ClaudeApiClient.php`, `OpenAiApiClient.php`
- `src/Service/AbTestService.php`
- `src/Service/ServiceTimePredictionService.php`
- `src/Service/DeliveryRiskService.php`
- `src/Service/ExceptionClassifierService.php`, `ExceptionPatternService.php`
- `src/Service/GeocodingService.php`, `AddressValidationService.php`
- `src/Service/EmbeddingService.php`
- `src/Service/DemandForecastService.php`
- `src/Service/DriverAffinityService.php`
- `src/Service/DelayPredictionService.php`, `NotificationMessageGenerator.php`
- `src/Service/FleetAnomalyService.php`
- `src/Service/FeatureExtractor/{ServiceTime,DeliveryRisk,Demand,DriverZone}FeatureExtractor.php`
- `src/Command/Ml{ExtractFeatures,DriftCheck,IndexEmbeddings}Command.php`
- `src/Command/ProactiveNotificationCommand.php`
- `src/Message/{MlTraining,BatchPrediction,Embedding,NlpClassification,FleetAnomalyCheck}Message.php` + handlers
- `src/EventSubscriber/PredictionOutcomeSubscriber.php`

### Nuevos (Python sidecar)
- `ml-service/app/main.py` (FastAPI)
- `ml-service/app/routers/{training,prediction,anomaly,demand}.py`
- `ml-service/app/models/{service_time,delivery_risk,driver_affinity,demand_forecast,anomaly_detector}.py`
- `ml-service/Dockerfile`, `requirements.txt`

### Modificados
- `docker-compose.local.yml` — servicio `ml`, pgvector
- `backend/.env` — `ML_SERVICE_URL`, `CLAUDE_API_KEY`, `OPENAI_API_KEY`
- `config/services.yaml` — registrar nuevos servicios
- `config/packages/messenger.yaml` — transportes async/ml
- `src/Service/EtaService.php` — inyectar predicción service time
- `src/Service/VroomRequestMapper.php` — inyectar predicción service time
- `src/Service/AlertService.php` — añadir risk prediction
- `src/Service/SearchService.php` — añadir `semanticSearch()`
- `src/Service/ShipmentCsvImporter.php` — inyectar geocoding
- `src/Service/ReportingService.php` — añadir forecasts
- `src/Service/RouteBuilder.php` — añadir driver affinity
- `src/Service/WebhookNotificationService.php` — notificaciones proactivas
- `src/Service/TraccarIngestionService.php` — dispatch anomaly check
