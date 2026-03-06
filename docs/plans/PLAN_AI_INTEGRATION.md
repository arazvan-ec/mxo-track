# Plan: Implementación IA en mxo-track (v3 — post-análisis socrático)

> **Última actualización**: 2026-03-06
> **Origen**: Brainstorming socrático de 8 rondas sobre el plan v2. Se identificaron bugs, quick wins ignorados, casos de uso nuevos, y decisiones arquitectónicas pendientes.

---

## Progreso

| Track | ID | Descripción | Estado | Semana |
|-------|----|-------------|--------|--------|
| A | A0 | Fix bugs conocidos (ETA 120s, service time inconsistencia) | **DONE** | 1 |
| A | A1 | Geocodificación dual (coordenadas + calle) | Pendiente | 1-2 |
| A | A2 | Window Violation Detection (ETA vs deliveryWindow) | **DONE** | 2 |
| A | A3 | CSV Quality Score (reglas, integrado en CsvImportRun) | **DONE** | 2-3 |
| A | A4 | Address Risk desde historial (SQL puro) | **DONE** | 3 |
| A | A5 | Predictive Dashboard (media móvil del trend data) | **DONE** | 3-4 |
| A | A6 | Driver Feedback Endpoint (captura conocimiento) | **DONE** | 4 |
| — | I0 | Infraestructura Base (Messenger Doctrine, tablas ML, clientes API) | **DONE** (falta `composer require symfony/doctrine-messenger`) | 1 |
| B | B1 | NLP Exception Classification (Claude API) | **DONE** | 4-5 |
| B | B2 | AI Delivery Notes (historial → notas sintetizadas) | **DONE** | 5 |
| B | B3 | Skill Detection from Shipment Description | **DONE** | 5 |
| B | B4 | Driver Briefing al inicio de ruta | **DONE** | 5-6 |
| B | B5 | Smart Loading Manifest con notas ML | **DONE** | 6 |
| B | B6 | Post-Route Analysis | **DONE** | 6 |
| B | B7 | Webhook Enrichment (mensajes contextuales) | **DONE** | 6 |
| B | B8 | Asistente Conversacional (MVP: 3-5 tools) | **DONE** | 7 |
| C | C0 | Python FastAPI Sidecar setup | Pendiente | 6 |
| C | C1 | Service Time Prediction (LightGBM) | Pendiente | 7-8 |
| C | C2 | ETA Predictivo (reemplaza +120s) | Pendiente | 8 |
| C | C3 | Delivery Risk Score | Pendiente | 9 |
| C | C4 | Demand Forecast (Prophet) | Pendiente | 10 |
| C | C5 | Zone Clustering (K-means) | Pendiente | 10 |
| C | C6 | Driver-Zone Affinity (como VROOM skills) | Pendiente | 11 |
| C | C7 | Fleet Anomaly Detection | Pendiente | 12 |
| C | C8 | Búsqueda Semántica (pgvector) | Pendiente | 13 |
| D | D1 | Pre-delivery SMS/WhatsApp (30 min antes) | Pendiente | — |
| D | D2 | Delivery slot selection en tracking page | Pendiente | — |
| D | D3 | Post-delivery rating por destinatario | Pendiente | — |

---

## Decisiones Tomadas (análisis socrático 2026-03-06)

### D1. No hay North Star único → Balanced Scorecard

El negocio necesita ser rentable Y tener buena satisfacción. Se adopta un **Balanced Scorecard** con 4 dimensiones que se revisan periódicamente:

| Dimensión | Métricas clave | Fases que impactan |
|-----------|---------------|-------------------|
| **Rentabilidad** | Coste/entrega, entregas/hora/vehículo | C1 (service time), C6 (affinity), A5 (forecast) |
| **Éxito primera entrega** | % entregas OK sin reintento | C3 (risk), D1 (pre-delivery), B2 (AI notes) |
| **Satisfacción cliente** | Feedback destinatario, on-time % | D3 (rating), B7 (webhook), A2 (window) |
| **Capacidad operativa** | Entregas/día, utilización flota | C4 (demand), C5 (zones), A3 (CSV quality) |

**Acción**: Crear un dashboard ejecutivo con las 4 dimensiones. En cada sprint, priorizar las fases que más impacten la dimensión más débil.

### D2. Geocodificación dual: coordenadas + calle

- Si hay coordenadas → usarlas directamente
- Si hay calle sin coordenadas → geocodificar
- Si hay ambas → validar coherencia (reverse geocoding, alertar si discrepancia > 200m)
- Nunca descartar shipments por falta de uno u otro

### D3. Distribución de excepciones: se aprende con el uso

No se asume a priori qué tipo de excepción domina (ABSENT vs REFUSED vs OTHER). El sistema debe:
- Registrar distribución desde el día 1 (Track A6: driver feedback)
- Adaptar prioridades dinámicamente (ej: si 70% son ABSENT → Track D1 es urgente)
- Dashboard de distribución de excepciones visible desde el primer mes

### D4. Messenger transport: Doctrine (no Redis)

**Razón**: Durabilidad + análisis histórico de jobs > velocidad.
- Redis se queda para sesiones (`sess:transporte:`) y caché de predicciones
- Doctrine transport guarda mensajes en tabla `messenger_messages` en PostgreSQL
- Permite queries analíticas: volumen de jobs/hora, tiempos de procesamiento, tasa de fallos
- No se pierden mensajes si Redis se reinicia

```yaml
# messenger.yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: 'doctrine://default?queue_name=async'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
            ml:
                dsn: 'doctrine://default?queue_name=ml'
                retry_strategy:
                    max_retries: 2
                    delay: 5000
            failed:
                dsn: 'doctrine://default?queue_name=failed'
```

### D5. ML Serving: Python FastAPI sidecar

**Razón**: Ecosistema ML de Python es incomparablemente superior a PHP.
- Mismo patrón que VROOM/OSRM/Traccar: servicio HTTP independiente, PHP thin-client
- Railway lo soporta como servicio separado
- Se empieza simple (1 endpoint, 1 modelo) y se escala

**Decisión futura documentada**: Si la latencia del sidecar HTTP es problema (>200ms p95), evaluar ONNX Runtime en PHP como optimización. Esto no debería ser necesario en las primeras fases porque los modelos son pequeños (LightGBM < 5MB).

### D6. Driver-Zone Affinity como VROOM skills (no post-proceso)

El plan v2 proponía asignar drivers después de VROOM, deshaciendo parte de su trabajo. Mejor approach:
- Modelar afinidad de zona como **skill virtual en VROOM**: el conductor que conoce la zona "Centro" tiene skill `ZONE_CENTRO`
- Los shipments de esa zona requieren ese skill con `priority` (VROOM skills)
- Así VROOM hace la asignación completa (vehículos + drivers + zonas) en un solo paso
- Requiere que `VroomRequestMapper` genere skills virtuales desde el affinity score

### D7. Compatibilidad con migración a Go

El `TECH_STACK_EVALUATION.md` propone migrar de PHP/Symfony a Go + React/Next.js. El plan de IA se diseña para ser **compatible con ambos stacks**:
- **Tablas ML, feature store, prediction log**: son PostgreSQL puro — funcionan igual en Go
- **Python sidecar**: es HTTP independiente — funciona igual desde Go
- **Clientes API (Claude, OpenAI)**: son HTTP JSON — trivial en Go
- **Messenger async**: en Go se reemplaza por goroutines + job queue (pgq, river, etc.)
- **Lógica de negocio (quality analyzer, address risk, etc.)**: se documenta como algoritmos, no solo como código PHP

**Recomendación**: Implementar Tracks A y B en PHP actual (valor inmediato). Track C puede iniciarse en Go si la migración ya comenzó. El sidecar Python es stack-agnostic.

---

## Hallazgos del Análisis Socrático

### Bugs encontrados (fix inmediato)

| # | Bug | Ubicación | Fix |
|---|-----|-----------|-----|
| BUG-1 | ETA usa +120s hardcoded en vez de `serviceTimeSeconds` del Shipment (que puede ser != 120) | `EtaService.php` | Usar `$stop->getShipment()?->getServiceTimeSeconds() ?? DEFAULT_SERVICE_TIME_SECONDS` |
| BUG-2 | `VroomRequestMapper` tiene `DEFAULT_SERVICE_TIME_SECONDS = 300` pero ETA usa 120 | `EtaService.php` vs `VroomRequestMapper.php` | Unificar: ambos deben usar el mismo valor (preferiblemente del Shipment, fallback a 300) |

### Infraestructura ignorada

| # | Problema | Descripción |
|---|----------|-------------|
| INFRA-1 | Messenger es sync | `messenger.yaml` solo tiene `sync://`. Prerequisito para TODA la IA async |
| INFRA-2 | No hay worker process | No hay `messenger:consume` en ningún Dockerfile/supervisor. Sin esto, Doctrine transport acumula mensajes sin procesar |
| INFRA-3 | Redis solo para sesiones | Configurado para sesiones, no se usa para caché. Hay que añadir pool de caché |

### Sistemas existentes infrautilizados

| # | Sistema | Oportunidad |
|---|---------|-------------|
| USE-1 | `LoadingManifestGenerator` (LIFO) | Enriquecer con predicciones de service time y notas operativas por paquete |
| USE-2 | `Notification` entity (in_app) | Usar para notificaciones proactivas de IA, no crear sistema paralelo |
| USE-3 | `CsvImportRun` tracking | CSV Quality Score debe extender esto, guardando quality_score en CsvImportRun |
| USE-4 | `deliveryWindowStart/End` en RouteStop | Existen pero NADIE verifica si el ETA cae dentro de la ventana |
| USE-5 | `ReportingService.getTrendData()` | Ya es un predictor decente (media móvil por día de semana) — usar como fallback y baseline |
| USE-6 | `Shipment.serviceTimeSeconds` | Existe pero EtaService lo ignora (usa 120s hardcoded) |

### Casos de uso nuevos descubiertos

| # | Caso | Descripción | Track |
|---|------|-------------|-------|
| NEW-1 | Window Violation Detection | Al crear ruta, verificar si alguna parada violará su ventana de entrega según ETA | A |
| NEW-2 | Driver Knowledge Capture | Endpoint para que conductores reporten coordenadas corregidas, notas de acceso, feedback | A |
| NEW-3 | Smart Loading Manifest | Enriquecer manifiesto LIFO con predicciones de service time y notas | B |
| NEW-4 | Predictive Dashboard | En admin, junto a "Entregas hoy: 142", mostrar "Estimado mañana: ~165" | A |
| NEW-5 | Driver Briefing | Al iniciar ruta, resumen AI: "8 paradas, 2 alto riesgo, nota en C/Gran Vía 45" | B |
| NEW-6 | Pre-delivery Contact | SMS/WhatsApp 30 min antes con ETA + opción "reprogramar" | D |
| NEW-7 | Delivery Slot Selection | Destinatario elige ventana desde tracking page → llena deliveryWindow | D |
| NEW-8 | Post-delivery Rating | Rating + comentarios del destinatario → alimenta driver performance | D |

---

## Track A: Zero-ML Quick Wins (Semanas 1-4)

Valor inmediato **sin ML, sin sidecar, sin APIs externas**. Solo PHP + SQL + lógica de negocio.

### A0. Fix Bugs Conocidos

**ETA Service Time Inconsistency**:
- `EtaService.php`: Cambiar `+= 120` por `+= $stop->getShipment()?->getServiceTimeSeconds() ?? 300`
- Unificar constante `DEFAULT_SERVICE_TIME_SECONDS = 300` en un solo lugar (config parameter)
- Asegurar que `VroomRequestMapper` y `EtaService` usan la misma fuente

**Messenger Async**:
- Activar Doctrine transport en `messenger.yaml` (ver D4 arriba)
- Añadir worker process: `php bin/console messenger:consume async ml --time-limit=3600`
- Añadir a `docker-compose.local.yml` como servicio `worker`:
  ```yaml
  worker:
      build: .
      command: php bin/console messenger:consume async ml failed --time-limit=3600 --memory-limit=128M
      depends_on: [db, redis]
      restart: unless-stopped
  ```

### A1. Geocodificación Dual

**Archivo principal**: `src/Service/GeocodingService.php` (nuevo)
**Modifica**: `src/Service/ShipmentCsvImporter.php`

Lógica:
1. Si hay lat/lng Y address → validar coherencia (reverse geocode, alertar si > 200m)
2. Si solo hay address → geocodificar (Nominatim, cache Redis 30 días)
3. Si solo hay lat/lng → aceptar, opcionalmente enriquecer con reverse geocode
4. Si no hay ninguno → marcar como error, no importar

**Nuevo**: `src/Service/AddressValidationService.php`
- Cross-validación dirección ↔ coordenadas
- Detección de coordenadas en el mar o fuera de zona de servicio
- Warning si confidence < 0.7

**UI**: Indicador en import results con geocodificaciones automáticas y warnings.

### A2. Window Violation Detection

**Archivo principal**: `src/Service/WindowViolationDetector.php` (nuevo)
**Modifica**: UI de creación/edición de ruta

Al crear/confirmar una ruta, ejecutar:
```
Para cada parada con deliveryWindowStart/End:
    Calcular ETA acumulado (OSRM durations + service times)
    Si ETA > deliveryWindowEnd:
        Warning: "Parada #12 (C/Gran Vía 45) tiene ventana 09:00-10:00 pero ETA es 10:35"
    Si ETA < deliveryWindowStart - 30min:
        Info: "Parada #3 llegará 30 min antes de la ventana — conductor esperará"
```

**Nota**: Esto es lógica pura con datos que ya existen. Cero ML. Alto impacto para clientes con ventanas de entrega.

### A3. CSV Quality Score

**Archivo principal**: `src/Service/CsvQualityAnalyzer.php` (nuevo)
**Modifica**: `src/Service/ShipmentCsvImporter.php`, `CsvImportRun` entity

- `analyze(array $rows): QualityReport` — score 0-100 + warnings por fila
- Checks: coordenadas fuera de zona, teléfonos inválidos, direcciones incompletas, pesos outliers (>3σ), referencias duplicadas
- **Guardar quality_score en CsvImportRun** (nueva columna, migración) — no crear tabla nueva
- UI: tabla de warnings con opción continuar/cancelar antes de importar

### A4. Address Risk desde Historial

**Archivo principal**: `src/Service/AddressRiskService.php` (nuevo)
**Nueva tabla**: `address_risk` (address_hash, address, total_deliveries, exception_count, exception_rate, last_codes, last_updated)

- `updateRiskScores()`: SQL puro, recalcula desde historial de RouteStop
- `checkAddress(address)`: busca por hash, devuelve risk info
- Dirección "de riesgo" si exception_rate > 30% y total_deliveries > 5
- **Comando cron**: `app:address-risk:update` (diario)
- **UI**: Badge de warning en paradas de ruta + en CSV import

### A5. Predictive Dashboard

**Modifica**: `src/Service/ReportingService.php`, dashboard admin template

Usar `getTrendData()` existente para calcular:
- Media móvil 4 semanas por día de la semana → predicción "mañana"
- Recomendación de flota: `ceil(predicted_shipments / avg_stops_per_route)`
- Mostrar en dashboard: "Estimado mañana: ~165 entregas, recomendado: 8 vehículos"

**Sin ML** — es estadística básica con datos existentes. Sirve como fallback/baseline para Track C (Demand Forecast).

### A6. Driver Feedback Endpoint

**Nuevo endpoint**: `POST /api/driver/stops/{stopPublicId}/feedback`
**Nuevo DTO**: `src/Dto/Driver/StopFeedbackInput.php`
**Nueva tabla**: `driver_feedback` (stop_id, driver_id, corrected_lat, corrected_lng, access_notes, actual_service_time_seconds, created_at)

Captura:
- Coordenadas corregidas (del GPS del móvil del conductor)
- Notas de acceso para futuras visitas ("portería cerrada 14-16h")
- Tiempo real de servicio (auto-calculado o manual)

**Este endpoint alimenta directamente**:
- Track C1 (service time training data)
- Track C3 (delivery risk features)
- Track B2 (AI delivery notes)
- Track A4 (address risk — corrected coordinates)

---

## Track B: LLM-Powered Features (Semanas 4-7)

Requiere: Track I0 (Messenger async) + Claude API key. **No requiere sidecar Python.**

### B1. NLP Exception Classification

**Archivo principal**: `src/Service/ExceptionClassifierService.php` (nuevo)
**Modifica**: `ShipmentEvent` entity (nueva columna `ai_classification` JSONB)

- `classify(exceptionNotes, ExceptionCode)` → Claude API con prompt estructurado
- Retorna: `{subcategory, actionable_insight, suggested_action, confidence}`
- Subcategorías: ACCESO_EDIFICIO, DIRECCION_INCOMPLETA, AUSENCIA_RECURRENTE, RECHAZO_ESTADO, HORARIO_INCOMPATIBLE, ...
- Async via Messenger al crear ShipmentEvent con excepción
- Rate limiting: 30 req/min a Claude API

**Nuevo**: `src/Service/ExceptionPatternService.php` — agrega por subcategoría/zona/hora
**UI**: Subcategoría + suggested_action junto a cada excepción en vista de ruta. Dashboard de patrones.

### B2. AI Delivery Notes

**Archivo principal**: `src/Service/DeliveryNoteAiEnricher.php` (nuevo)
**Modifica**: `RouteStop` entity (nueva columna `ai_notes` TEXT), `DeliveryNoteGenerator.php`

Al crear ruta (async):
1. Para cada parada, consultar historial de la misma dirección (ShipmentEvent.payload.notes + driver_feedback.access_notes)
2. Si hay historial, llamar a Claude para sintetizar: "Portería cerrada 14-16h. Llamar timbre 3B. Último intento fallido: 2026-02-28 (ausente)."
3. Guardar en `route_stop.ai_notes`

**UI**: Badge "AI" en vista de parada del conductor para distinguir notas generadas vs manuales.

### B3. Skill Detection from Description

**Archivo principal**: `src/Service/ShipmentSkillDetector.php` (nuevo)
**Modifica**: `ShipmentCsvImporter.php`

Al importar shipments, si `requiredSkills` está vacío pero hay `description`:
- Claude API analiza descripción para detectar skills: "Nevera médica" → REFRIGERATED, "Mueble 120kg" → HEAVY_LOAD
- Se asignan automáticamente con flag `ai_detected: true`
- Operador puede override

### B4. Driver Briefing

**Archivo principal**: `src/Service/DriverBriefingService.php` (nuevo)
**Modifica**: Driver web/API — endpoint `GET /api/driver/routes/{publicId}/briefing`

Al inicio de ruta, generar resumen con Claude:
- "8 paradas, duración estimada 3h45m"
- "2 paradas alto riesgo: #4 (C/Gran Vía 45 — 40% excepciones) y #7 (ausente en últimos 2 intentos)"
- "Nota en parada #3: portería cerrada 14-16h"
- "Carga total: 245 kg, 1.2 m³ — 78% capacidad"

Usa datos de: RouteCapacityValidator, AddressRiskService, DeliveryNoteAiEnricher, EtaService.

### B5. Smart Loading Manifest

**Modifica**: `src/Service/LoadingManifestGenerator.php`

Enriquecer el manifiesto LIFO existente con:
- Service time estimado por paquete (del shipment o predicción C1 cuando disponible)
- Warning si paquete requiere skill especial
- Notas operativas: "Paquete frágil", "Requiere firma", "80kg — usar carretilla"
- Información generada por Claude si hay descripción rica

### B6. Post-Route Analysis

**Archivo principal**: `src/Service/PostRouteAnalyzer.php` (nuevo)

Cuando ruta → DONE, generar análisis con Claude:
- Resumen: "7/8 entregas exitosas, 1 excepción (ABSENT en parada #4)"
- Comparación planned vs actual: "Ruta duró 4h10m vs 3h45m estimadas (+11%)"
- Insights: "Paradas 5-7 fueron más rápidas de lo estimado. Parada #4 ha fallado 3 veces seguidas — considerar contacto previo."
- Guardar en `route.ai_analysis` (nueva columna JSONB)

### B7. Webhook Enrichment

**Modifica**: `src/Service/WebhookNotificationService.php`

Enriquecer webhooks con mensajes contextuales generados por Claude:
- `shipment.out_for_delivery` → "Tu paquete sale ahora. ETA: 15:30 ± 10 min"
- `shipment.delivered` → "Entregado a las 15:25. Firmado por: María García"
- `shipment.exception` → "No pudimos entregar hoy (destinatario ausente). Nuevo intento mañana 09:00-12:00"
- `shipment.delay_predicted` → "Tu entrega podría retrasarse ~25 min. Nuevo ETA: 16:15"

### B8. Asistente Conversacional (MVP)

**Archivos**: `src/Controller/Admin/AiAssistantController.php`, `src/Service/AiAssistantService.php`

MVP con 5 tools (wrappers de servicios existentes):
1. `search_shipments(query)` → `SearchService`
2. `get_delivery_report(date_range)` → `ReportingService::getDeliveryReport()`
3. `get_route_details(route_id)` → Route entity + stops
4. `get_active_alerts()` → `AlertService`
5. `get_exception_patterns(date_range)` → `ExceptionPatternService`

System prompt en español. Respeta tenant isolation. Rate limiting: 20 msg/min/user.
**UI**: Chat en admin con Turbo Streams. Floating action button en toda la sección admin.

---

## Track I0: Infraestructura Base (Semana 1, paralelo a Track A)

### Messenger Doctrine Transport
Ver decisión D4 arriba. Incluye:
- Modificar `messenger.yaml` (Doctrine transport con 3 colas: async, ml, failed)
- Añadir servicio `worker` en docker-compose
- `messenger_messages` table (auto-creada por Doctrine transport)

### Tablas ML (migración)

| Tabla | Propósito | Fase que la necesita |
|-------|-----------|---------------------|
| `address_risk` | Direcciones problemáticas | A4 |
| `driver_feedback` | Feedback de conductores | A6 |
| `ml_prediction_log` | Log de predicciones para drift/A/B | C1+ |
| `ml_model` | Registro de modelos | C1+ |
| `ml_feature_store` | Features materializados | C1+ |
| `ml_embedding` | Embeddings pgvector | C8 |

**Nota**: Solo crear tablas cuando la fase que las necesita se implementa. No crear todo de golpe.

### Clientes API (patrón VroomApiClient)

| Archivo | Cuándo | Para qué |
|---------|--------|----------|
| `ClaudeApiClient.php` | Track B (semana 4) | NLP, generation, assistant |
| `MlApiClient.php` | Track C (semana 6) | Predicciones ML sidecar |
| `OpenAiApiClient.php` | Track C8 (semana 13) | Embeddings pgvector |
| `RateLimitedApiClient.php` | Track B (semana 4) | Throttling APIs externas |

### pgvector (diferido hasta C8)
- Cambiar imagen postgres a `pgvector/pgvector:pg16`
- Migración: `CREATE EXTENSION IF NOT EXISTS vector;`
- **No se necesita hasta Fase C8** (Búsqueda Semántica)

### Caché de Predicciones
- `PredictionCacheService.php` — Redis con TTL configurable (15min default)
- Key: `ml:pred:{model}:{hash(features)}`
- Se implementa junto con Track C0 (sidecar setup)

---

## Track C: ML Real (Semanas 6-13)

Requiere: Python FastAPI sidecar + datos suficientes para training.

### C0. Python FastAPI Sidecar Setup

Nuevo servicio `ml` en `docker-compose.local.yml`:
```yaml
ml:
    build: ./ml-service
    ports: ["5200:5200"]
    environment:
        DATABASE_URL: postgresql://mxo:mxo@db:5432/mxo_track
    depends_on: [db]
```

Estructura:
```
ml-service/
├── app/
│   ├── main.py          # FastAPI app
│   ├── routers/         # Endpoints por dominio
│   ├── models/          # Modelos ML
│   ├── feature_store.py # Lee features de PostgreSQL
│   └── model_registry.py
├── Dockerfile           # python:3.12-slim
└── requirements.txt     # fastapi, scikit-learn, lightgbm, prophet, psycopg2, sqlalchemy
```

`MlApiClient.php` en PHP: `predict(model, features)`, `train(model, params)`, `health()`

### C1. Service Time Prediction

**Mínimo de datos**: 500 rutas completadas con `deliveredAt`
**Fallback**: `Shipment.serviceTimeSeconds` o 300s default (fix de BUG-1)

**Features**: hour_of_day, day_of_week, zone_geohash_6, driver_id, parcel_count, total_weight_kg, has_time_window, stop_sequence, address_risk_score (de A4), driver_feedback_service_time (de A6)
**Label**: service time real (diff deliveredAt consecutivos - tiempo OSRM)
**Modelo**: LightGBM regressor
**Endpoint Python**: `POST /train/service-time`, `POST /predict/service-time`

**Modifica**: `VroomRequestMapper.php` — inyectar predicción, reemplazar DEFAULT_SERVICE_TIME_SECONDS

### C2. ETA Predictivo

Usa modelo C1 para reemplazar el +120s hardcoded en `EtaService.php`.
- `predictServiceTime(stop)` → ML sidecar si hay modelo, else fallback a C1 heurística
- Log en `ml_prediction_log` con latency_ms
- Backfill outcomes cuando ruta → DONE

**UI**: Indicador "(ML)" vs "(estimación)" según origen de la predicción.

### C3. Delivery Risk Score

**Mínimo de datos**: 1000 entregas (mix éxito/excepción)
**Fallback**: heurística de address_risk (A4)

**Features**: hour_of_day, day_of_week, zone_geohash, has_phone, has_notes, prior_exceptions_at_address, prior_exceptions_for_recipient, delivery_attempt_number, parcel_count, address_risk_score, ai_classification_subcategory (de B1)
**Label**: 1=excepción, 0=entregado
**Modelo**: LightGBM clasificador binario → probabilidad 0.0-1.0

**UI**: Semáforo por parada (verde < 0.2, amarillo 0.2-0.5, rojo > 0.5). Columna "Riesgo" en lista de rutas.

### C4. Demand Forecast

**Mínimo de datos**: 90 días de historial
**Fallback**: media móvil 4 semanas (A5 ya lo implementa)

**Modelo**: Prophet series temporales por zona/cliente
**Endpoint**: `POST /predict/demand-forecast` → [{date, predicted, lower, upper}] para 7/14/30 días
**UI**: Chart con predicción + bandas de confianza en reportes admin.

### C5. Zone Clustering

**Mínimo de datos**: 90 días de entregas
**Modelo**: K-means sobre coordenadas históricas

Endpoint: `POST /cluster/delivery-zones`
- Input: coordenadas de entregas últimos 90 días
- Output: clusters con centroide, radio, nombre sugerido (reverse geocoding)
- Nueva tabla `delivery_zone`: customer_id, name, center_lat, center_lng, radius_km, shipment_count

Alimenta C4 (forecast por zona) y C6 (affinity por zona).

### C6. Driver-Zone Affinity (como VROOM skills)

**Mínimo de datos**: 200 rutas/conductor
**Fallback**: asignación manual

Per decisión D6, se modela como skills virtuales para VROOM:
1. Calcular affinity score por par (driver, zone) desde historial
2. Si score > umbral → driver tiene skill virtual `ZONE_{zone_name}`
3. Shipments en esa zona requieren skill con `priority` (soft constraint en VROOM)
4. **Modifica `VroomRequestMapper.php`** — generar skills virtuales, no post-procesar

### C7. Fleet Anomaly Detection

**Mínimo de datos**: 30 días de posiciones GPS
**Fallback**: umbrales fijos (velocidad >120km/h, parada >45min, offline >30min — los que ya tiene `AlertService`)

Endpoint: `POST /predict/fleet-anomaly`
- Detecta: desvío de ruta, velocidad anómala (z-score), paradas largas no planificadas, patrones sospechosos
- Async via Messenger tras ingesta de posiciones
- **UI**: Alerta en fleet map via Mercure (o WebSocket en Go)

### C8. Búsqueda Semántica (pgvector)

**Mínimo de datos**: 1000 shipments
**Requiere**: pgvector extension, OpenAI API key

- Embeddings via OpenAI `text-embedding-3-small` (1536 dims)
- Almacenaje en `ml_embedding` con pgvector
- Merge búsqueda LIKE existente + pgvector nearest neighbor
- Respeta tenant filtering

---

## Track D: Destinatario-Centric (en paralelo, requiere decisión de negocio)

El destinatario es quien determina ABSENT/REFUSED. Si toda la IA se enfoca en backoffice, optimizamos el 20% del problema.

### D1. Pre-delivery Contact (SMS/WhatsApp)

**Requiere**: Proveedor SMS (Twilio, MessageBird, etc.) o WhatsApp Business API
**Trigger**: 30 min antes de ETA estimado
**Mensaje**: "Tu entrega llega en ~30 min. ¿Estarás? [Sí] [Reprogramar]"
**Impacto esperado**: Reducción significativa de ABSENT (la excepción más común en última milla)

Si responde "Reprogramar" → marcar parada como SKIPPED, mover a siguiente ruta. Alimenta `deliveryWindowStart/End` para el siguiente intento.

### D2. Delivery Slot Selection

**Modifica**: Tracking page pública (`/tracking/{token}`)
**Añade**: Selector de ventana horaria para próximo intento

Si shipment tiene tracking token activo, el destinatario puede:
- Ver ETA en tiempo real
- Seleccionar ventana preferida → actualiza `Shipment.preferredWindowStart/End`
- Añadir notas de acceso → alimenta driver_feedback/ai_notes

### D3. Post-delivery Rating

**Nuevo endpoint**: `POST /api/public/tracking/{token}/rating`
**Nueva tabla**: `delivery_rating` (shipment_id, score 1-5, comment, created_at)

Disponible 24h después de entrega. Alimenta:
- Driver performance metrics (ReportingService)
- Balanced Scorecard dimensión "Satisfacción"

---

## Observabilidad y A/B Testing (transversal)

### Observabilidad ML
- `SystemHealthService.php` → nuevo check `mlServiceHealth()`:
  - Latencia predicción p50/p95/p99 desde `ml_prediction_log`
  - Tasa de fallback / total
  - Cache hit/miss ratio
  - Queue depth de Messenger
- Dashboard admin panel "ML Health"

### A/B Testing
- `AbTestService.php` — hash determinístico para split control/treatment
- Tabla `ml_ab_test`: name, model, control_version, treatment_version, traffic_pct
- Cada predicción registra `ab_group` en `ml_prediction_log`

### Drift Monitoring
- `MlDriftCheckCommand.php` — cron diario
- Compara predicciones vs actuals
- Alerta si MAE > 1.5× training MAE

---

## Estrategia Cold-Start (transversal)

| Fase | Mínimo datos | Fallback | Bootstrapping |
|------|-------------|----------|---------------|
| C1: Service Time | 500 rutas | serviceTimeSeconds del Shipment o 300s | driver_feedback (A6), import histórico |
| C2: ETA | = C1 | OSRM duration + service time fijo | = C1 |
| C3: Delivery Risk | 1000 entregas | address_risk heurística (A4) | Datos A4 como features |
| C4: Demand | 90 días historial | Media móvil 4 semanas (A5) | CsvImportRun existentes |
| C5: Zones | 90 días entregas | Geohash fijo | RouteStop coordinates |
| C6: Affinity | 200 rutas/driver | Asignación manual | ReportingService metrics |
| C7: Anomalías | 30 días GPS | Umbrales fijos (AlertService actual) | VehiclePosition existentes |
| C8: Búsqueda | 1000 shipments | Búsqueda LIKE actual | Backfill batch |

---

## Métricas de Verificación

### Por Feature

| ID | Métrica | Objetivo |
|----|---------|----------|
| A1 | Geocodificación error < 100m | 95% |
| A2 | Window violations detectadas vs manual | > 95% recall |
| A3 | CSV quality detección errores | > 90% recall |
| A4 | Address risk precision | > 70% |
| B1 | NLP subcategoría precision (review 50) | > 85% |
| B8 | Asistente satisfacción (survey) | > 4/5 |
| C1 | Service time MAE | < 60s |
| C3 | Delivery risk AUC-ROC | > 0.70 |
| C4 | Demand MAPE semana held-out | < 25% |
| C6 | Mejora success rate vs random | > 5% |
| C7 | Anomalías FPR / True detection | < 10% / > 80% |

### Balanced Scorecard

| Dimensión | Baseline (medir antes) | Objetivo 6 meses |
|-----------|----------------------|-------------------|
| Rentabilidad (coste/entrega) | TBD | -10% |
| Éxito primera entrega | TBD | +8% |
| Satisfacción (rating promedio) | TBD (requiere D3) | > 4.2/5 |
| Capacidad (entregas/día) | TBD | +15% |

### Métricas Operacionales

| Métrica | Umbral alerta |
|---------|--------------|
| Latencia predicción p95 | > 500ms |
| Tasa fallback | > 30% en 24h |
| Rate limit hits (Claude/OpenAI) | > 10/hora |
| Cache hit ratio | < 50% |
| Messenger queue depth | > 100 pendientes |
| Worker uptime | < 99% |

---

## Secuenciación Global

```
Semana 1-2:  Track I0 (infra) + Track A0-A2 (bugs + geocoding + windows)
Semana 2-3:  Track A3-A4 (CSV quality + address risk)
Semana 3-4:  Track A5-A6 (predictive dashboard + driver feedback)
Semana 4-5:  Track B1-B2 (NLP exceptions + AI notes) [necesita Claude API key]
Semana 5-6:  Track B3-B5 (skill detection + briefing + loading manifest)
Semana 6-7:  Track B6-B8 (post-route + webhooks + assistant MVP)
Semana 6:    Track C0 (sidecar setup) [en paralelo con B]
Semana 7-8:  Track C1-C2 (service time + ETA) [si hay 500 rutas]
Semana 9:    Track C3 (delivery risk) [si hay 1000 entregas]
Semana 10:   Track C4-C5 (demand + zones) [si hay 90 días]
Semana 11:   Track C6 (driver affinity como VROOM skills)
Semana 12:   Track C7 (fleet anomaly)
Semana 13:   Track C8 (búsqueda semántica)
Track D:     En paralelo cuando haya decisión de proveedor SMS/WhatsApp
```

**Nota clave**: Tracks A y B no requieren datos mínimos — se pueden implementar desde el día 1. Track C requiere datos reales de operaciones. Track D requiere decisión de negocio sobre proveedor de mensajería.

---

## Resumen de Archivos

### Nuevos (PHP) — Track A
- `src/Service/GeocodingService.php`, `AddressValidationService.php`
- `src/Service/WindowViolationDetector.php`
- `src/Service/CsvQualityAnalyzer.php`, `AddressRiskService.php`
- `src/Dto/Driver/StopFeedbackInput.php`
- `src/Command/UpdateAddressRiskCommand.php`

### Nuevos (PHP) — Track B
- `src/Service/ClaudeApiClient.php`, `RateLimitedApiClient.php`
- `src/Service/ExceptionClassifierService.php`, `ExceptionPatternService.php`
- `src/Service/DeliveryNoteAiEnricher.php`
- `src/Service/ShipmentSkillDetector.php`
- `src/Service/DriverBriefingService.php`
- `src/Service/PostRouteAnalyzer.php`
- `src/Service/AiAssistantService.php`
- `src/Controller/Admin/AiAssistantController.php`
- `src/Message/NlpClassificationMessage.php` + handler

### Nuevos (PHP) — Track C
- `src/Service/MlApiClient.php`, `PredictionCacheService.php`
- `src/Service/ServiceTimePredictionService.php`
- `src/Service/DeliveryRiskService.php`
- `src/Service/DemandForecastService.php`
- `src/Service/DeliveryZoneClusterService.php`
- `src/Service/DriverAffinityService.php`
- `src/Service/FleetAnomalyService.php`
- `src/Service/EmbeddingService.php`, `OpenAiApiClient.php`
- `src/Service/AbTestService.php`
- `src/Service/FeatureExtractor/{ServiceTime,DeliveryRisk,Demand,DriverZone}FeatureExtractor.php`
- `src/Command/Ml{ExtractFeatures,DriftCheck,IndexEmbeddings}Command.php`
- `src/EventSubscriber/PredictionOutcomeSubscriber.php`

### Nuevos (Python sidecar) — Track C
- `ml-service/` completo (FastAPI + modelos + Dockerfile)

### Nuevos (Templates)
- `templates/admin/ai_assistant/index.html.twig`
- `templates/admin/shipment/_csv_quality_report.html.twig`
- `templates/admin/report/_exception_patterns.html.twig`
- `templates/admin/report/_demand_forecast.html.twig`

### Modificados
- `config/packages/messenger.yaml` — Doctrine transport (D4)
- `docker-compose.local.yml` — servicio worker, servicio ml (C0), pgvector (C8)
- `src/Service/EtaService.php` — fix BUG-1/BUG-2, inyectar predicción C2
- `src/Service/VroomRequestMapper.php` — inyectar service time C1, skills virtuales C6
- `src/Service/ShipmentCsvImporter.php` — geocoding A1, quality A3, risk A4, skills B3
- `src/Service/LoadingManifestGenerator.php` — enriquecer B5
- `src/Service/DeliveryNoteGenerator.php` — AI notes B2
- `src/Service/WebhookNotificationService.php` — enrichment B7
- `src/Service/ReportingService.php` — predictive dashboard A5, forecasts C4
- `src/Service/AlertService.php` — risk prediction C3
- `src/Service/SearchService.php` — semantic search C8
- `src/Service/SystemHealthService.php` — ML health observability
- `src/Service/TraccarIngestionService.php` — anomaly dispatch C7
- `src/Service/RouteBuilder.php` — driver affinity C6 (via VROOM skills)
- Driver API controller — feedback endpoint A6, briefing B4
- Admin templates — risk badges, ML indicators, quality reports, AI assistant
