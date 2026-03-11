# AI/ML

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Servicios AI

| Servicio | Propósito | Modelo/API |
|----------|-----------|-----------|
| `AiAssistantService` | Asistente conversacional con tool loops | Claude API |
| `DeliveryNoteAiEnricher` | Enriquecimiento AI de notas de entrega | Claude API |
| `ExceptionClassifierService` | Clasificación NLP de excepciones | Claude API |
| `ShipmentClusteringService` | Clustering ML de envíos por características | PHP puro |

## Async Processing (Symfony Messenger)

| Message | Handler | Queue | Propósito |
|---------|---------|-------|-----------|
| `EnrichRouteNotesMessage` | `EnrichRouteNotesHandler` | async | AI notes para paradas |
| `NlpClassificationMessage` | `NlpClassificationHandler` | ml | Clasificar excepciones |
| `PostRouteAnalysisMessage` | `PostRouteAnalysisHandler` | async | Análisis post-ruta |
| `FleetAnomalyCheckMessage` | `FleetAnomalyCheckHandler` | async | Detección anomalías |

## Event-Driven Triggers

| Listener | Evento | Acción |
|----------|--------|--------|
| `AiEnrichmentListener` | `RouteStarted` | Dispatch async `EnrichRouteNotesMessage` |
| `PostRouteAnalysisListener` | `RouteCompleted` | Dispatch async `PostRouteAnalysisMessage` |
| `FleetAnomalyCheckListener` | `VehiclePositionReceived` | Dispatch async `FleetAnomalyCheckMessage` |
| `ShipmentEmbeddingListener` | `ShipmentCreated` | Dispatch async embedding generation |

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

## Historial

- 2026-03-11: Creación inicial
