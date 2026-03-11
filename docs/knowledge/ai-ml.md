# AI/ML

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Servicios AI

| Servicio | Propósito | Modelo/API | Tests |
|----------|-----------|-----------|-------|
| `ExceptionClassifierService` | Clasificación NLP de excepciones en 9 subcategorías | Claude API | 8 unit |
| `PostRouteAnalyzer` | Análisis de rutas completadas (summary, insights, recommendations) | Claude API | 6 unit |
| `DeliveryNoteAiEnricher` | Notas de entrega basadas en historial (≤200 chars) | Claude API | 5 unit |
| `AiAssistantService` | Asistente conversacional con 5 herramientas (tool loop) | Claude API | 4 unit |
| `EmbeddingService` | Embeddings vectoriales + búsqueda semántica (pgvector) | OpenAI API | 5 unit |
| `DeliveryRiskService` | Predicción de riesgo de fallo (LOW/MEDIUM/HIGH) | ML Service | 6 unit |
| `AddressRiskService` | Riesgo por dirección (historial de excepciones) | SQL Analytics | 6 unit |
| `SearchService` | Búsqueda híbrida keyword + semántica | OpenAI + SQL | 5 unit |
| `ShipmentClusteringService` | Clustering ML de envíos por características | PHP puro | — |

## Subcategorías de Clasificación

`ExceptionClassifierService` clasifica excepciones en 9 subcategorías:
`AUSENTE_REPETIDO`, `HORARIO_INADECUADO`, `ACCESO_DIFICIL`, `DIRECCION_INCORRECTA`, `RECHAZO_CLIENTE`, `DANO_PAQUETE`, `VEHICULO_INADECUADO`, `ZONA_PELIGROSA`, `OTRO`

Cada clasificación incluye: subcategoría, confianza (0.0-1.0), insight accionable, acción sugerida.
Almacenada en `ShipmentEvent.payload['ai_classification']`.

## Async Processing (Symfony Messenger)

| Message | Handler | Queue | Propósito | Tests |
|---------|---------|-------|-----------|-------|
| `NlpClassificationMessage` | `NlpClassificationHandler` | ml | Clasificar excepciones | 3 unit |
| `PostRouteAnalysisMessage` | `PostRouteAnalysisHandler` | async | Análisis post-ruta | 2 unit |
| `EnrichRouteNotesMessage` | `EnrichRouteNotesHandler` | async | AI notes para paradas | — |
| `FleetAnomalyCheckMessage` | `FleetAnomalyCheckHandler` | async | Detección anomalías | — |

## Event-Driven Triggers

| Listener | Evento | Acción |
|----------|--------|--------|
| `AiEnrichmentListener` | `RouteStarted` | Dispatch `EnrichRouteNotesMessage` |
| `PostRouteAnalysisListener` | `RouteCompleted` | Dispatch `PostRouteAnalysisMessage` |
| `FleetAnomalyCheckListener` | `VehiclePositionReceived` | Dispatch `FleetAnomalyCheckMessage` |
| `ShipmentEmbeddingListener` | `ShipmentsImported` | Dispatch embedding generation |
| (inline en `DeliveryService:147`) | Excepción reportada | Dispatch `NlpClassificationMessage` |

## Client Interfaces

| Interface | Default Implementation | Null Implementation |
|-----------|----------------------|---------------------|
| `LlmClientInterface` | `ClaudeLlmClient` (services.yaml:116) | `NullLlmClient` |
| `EmbeddingClientInterface` | `OpenAiEmbeddingClient` (services.yaml:123) | `NullEmbeddingClient` |
| `MlApiClient` | Calls ML sidecar (port 5200) | Returns empty arrays on failure |

## UI Elements

| Ubicación | Elemento | Datos |
|-----------|----------|-------|
| Detalle de excepción (shipment/show) | Badge subcategoría + confianza + insight + acción | `ShipmentEvent.payload['ai_classification']` |
| Detalle de ruta completada (route/analysis) | Summary, planned vs actual, insights, recommendations | `Route.aiAnalysis` |
| Planificador de rutas (route_planner) | Badge de riesgo (Alto/Bajo) con tooltip | `AddressRiskService.checkAddress()` |
| Dashboard operador (`/admin/ai-assistant`) | Chat widget con 5 herramientas | `AiAssistantService.chat()` |

## Analytics Services

| Servicio | Propósito |
|----------|-----------|
| `AdminMetricsService` | KPIs del dashboard admin |
| `SlaMetricsService` | Tracking de cumplimiento SLA |
| `RouteAnalysisService` | Análisis post-ruta |
| `PostRouteAnalyzer` | Eficiencia de ruta completada |
| `RouteComparisonService` | Comparación planificado vs real |
| `ExceptionPatternService` | Patrones en entregas fallidas |
| `FleetAnomalyService` | Detección de anomalías en flota |
| `DriverScoringService` | Scoring de rendimiento de conductores |
| `DriverAffinityService` | Afinidad conductor-cliente |

## Bugs Corregidos (Phase 2)

1. **`DeliveryRiskService`**: `AddressRiskService.checkAddress()` retorna `array`, no entidad. Llamaba `isHighRisk()` en array → corregido a `$result['is_risky'] ?? false`
2. **`AiAssistantService.chat()`**: Parámetro `$customerId` era `?int` pero recibía `?string` (Doctrine BIGINT) → corregido tipos

## Historial

- 2026-03-11: Creación inicial
- 2026-03-11: Phase 2 — 55 tests AI/ML, 2 bug fixes, UI elements documentados
