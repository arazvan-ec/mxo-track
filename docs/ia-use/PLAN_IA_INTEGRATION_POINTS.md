# Plan: Puntos de Integración IA en mxo-track

## Contexto

El sistema tiene una base de datos rica (eventos de envío, posiciones GPS, excepciones con notas, tiempos de servicio, métricas de ruta) pero cero inteligencia artificial. Todo es rule-based o manual. Este plan identifica los puntos concretos donde IA añade valor real, priorizados por impacto y viabilidad.

---

## Puntos de Integración IA (ordenados por impacto)

### 1. ETA Predictivo — `EtaService.php`
**Problema**: ETA actual usa OSRM + 2 min fijos por parada. No considera tráfico, hora del día, ni historial del conductor.
**Datos disponibles**: VehiclePosition (GPS trail), RouteStop.deliveredAt (tiempos reales), RouteAnalysisService (planned vs actual).
**IA**: Modelo que predice tiempo real por parada usando: zona, hora, conductor, tipo de dirección, historial. Alimenta ETAs dinámicos para clientes y operadores.
**Archivos**: `backend/src/Service/EtaService.php`, `backend/src/Service/OsrmClient.php`

### 2. Predicción de Fallos de Entrega — `AlertService.php`
**Problema**: Alertas son reactivas (3+ excepciones). No previene fallos.
**Datos disponibles**: ExceptionCode (ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER), exceptionNotes (texto libre), ShipmentEvent timeline, hora/día/zona.
**IA**: Scoring de riesgo por parada antes de asignar la ruta. Alta probabilidad de ABSENT → reordenar, contactar antes, o mover a otra franja.
**Archivos**: `backend/src/Service/AlertService.php`, `backend/src/Enum/ExceptionCode.php`, `backend/src/Entity/RouteStop.php`

### 3. Tiempos de Servicio Adaptativos — `VroomRequestMapper.php`
**Problema**: Service time es 300s fijo o manual por envío. VROOM optimiza con datos imprecisos.
**Datos disponibles**: RouteAnalysisService calcula service time real entre paradas consecutivas. Historial por dirección/zona.
**IA**: Modelo que aprende service time real por zona/tipo-dirección/conductor. Alimenta VROOM con datos más precisos → rutas mejor optimizadas.
**Archivos**: `backend/src/Service/VroomRequestMapper.php`, `backend/src/Service/RouteAnalysisService.php`

### 4. Análisis de Notas de Excepción (NLP) — `ShipmentEvent`
**Problema**: exceptionNotes es texto libre sin estructura. Solo 5 códigos de excepción. Mucha información perdida.
**Datos disponibles**: Todas las notas históricas de excepción.
**IA**: LLM clasifica notas en subcategorías accionables ("portero no abre" → ACCESO_EDIFICIO, "dirección incorrecta piso 3" → DIRECCIÓN_INCOMPLETA). Detecta patrones por zona/dirección.
**Archivos**: `backend/src/Entity/ShipmentEvent.php`, `backend/src/Dto/Driver/ExceptionStopInput.php`

### 5. Geocodificación y Validación de Direcciones — `ShipmentCsvImporter.php`
**Problema**: CSV import requiere lat/lng. Direcciones son texto libre sin normalizar. No hay geocoding.
**Datos disponibles**: address (texto), lat/lng (cuando existen), historial de direcciones exitosas.
**IA**: Auto-geocoding en import (API externa + cache). Detección de duplicados fuzzy. Validación de coherencia dirección↔coordenadas. Enriquecimiento (zona, tipo edificio).
**Archivos**: `backend/src/Service/ShipmentCsvImporter.php`, `backend/src/Entity/Shipment.php`

### 6. Búsqueda Semántica — `SearchService.php`
**Problema**: Búsqueda LIKE básica. No entiende intención ("entregas fallidas en Madrid esta semana").
**Datos disponibles**: Shipment.reference, recipientName, address. Route.name. Vehicle.name.
**IA**: Embeddings (pgvector/Neon) para búsqueda semántica. Fuzzy matching para typos. Filtros inteligentes por contexto del usuario.
**Archivos**: `backend/src/Service/SearchService.php`

### 7. Demand Forecasting — `ReportingService.php`
**Problema**: Sin previsión de demanda. Asignación de vehículos y conductores es manual.
**Datos disponibles**: Historial de envíos por cliente/zona/día/mes. Métricas de ReportingService.
**IA**: Predicción de volumen por zona/día. Recomendación de flota necesaria. Detección de tendencias estacionales.
**Archivos**: `backend/src/Service/ReportingService.php`, `backend/src/Service/BillingService.php`

### 8. Driver-Zone Affinity — `RouteBuilder.php`
**Problema**: Asignación conductor-ruta no considera rendimiento histórico del conductor en cada zona.
**Datos disponibles**: RouteAnalysisService (adherencia, tiempos, excepciones por ruta). ShipmentEvent por conductor.
**IA**: Perfil de rendimiento conductor×zona. Recomendar asignaciones óptimas. Detectar fatiga (degradación de tiempos).
**Archivos**: `backend/src/Service/RouteBuilder.php`, `backend/src/Service/RouteAnalysisService.php`

### 9. Comunicación Proactiva al Cliente — `WebhookNotificationService.php`
**Problema**: Webhooks son eventos crudos. No hay mensajes inteligentes al destinatario.
**Datos disponibles**: ETA, posición vehículo, estado envío, historial de excepciones.
**IA**: Generar mensajes contextuales ("Tu entrega llega en ~15 min"). Predecir retraso y notificar antes. Sugerir acciones post-excepción.
**Archivos**: `backend/src/Service/WebhookNotificationService.php`, `backend/src/Service/NotificationService.php`

### 10. Detección de Anomalías en Flota — `TraccarIngestionService`
**Problema**: Solo alerta "vehículo offline 30 min". No detecta desvíos, paradas largas, o patrones sospechosos.
**Datos disponibles**: VehiclePosition (lat, lng, speed, course, accuracy). VehicleLastPosition.
**IA**: Detección de desvíos de ruta en tiempo real. Paradas anormalmente largas. Velocidad excesiva. Geofencing inteligente.
**Archivos**: `backend/src/Service/TraccarIngestionService.php`, `backend/src/Entity/VehiclePosition.php`

---

## Matriz de Priorización

| # | Punto | Impacto | Complejidad | Datos Listos | Quick Win |
|---|-------|---------|-------------|-------------|-----------|
| 1 | ETA Predictivo | Muy Alto | Media | ✓ | — |
| 2 | Predicción Fallos | Muy Alto | Alta | ✓ | — |
| 3 | Service Time Adaptativo | Alto | Baja | ✓ | ✓ |
| 4 | NLP Notas Excepción | Alto | Baja | ✓ | ✓ |
| 5 | Geocodificación | Alto | Media | Parcial | — |
| 6 | Búsqueda Semántica | Medio | Alta | ✓ | — |
| 7 | Demand Forecasting | Alto | Media | ✓ | — |
| 8 | Driver-Zone Affinity | Alto | Media | ✓ | — |
| 9 | Comunicación Proactiva | Medio | Media | ✓ | — |
| 10 | Anomalías Flota | Medio | Media | ✓ | — |

## Quick Wins Recomendados (implementar primero)

1. **Service Time Adaptativo (#3)**: Calcular media de service time real por zona desde RouteAnalysisService y usar como default en VroomRequestMapper. Sin ML, solo estadística básica. Mejora inmediata en la calidad de rutas.

2. **NLP Notas Excepción (#4)**: Enviar notas de excepción a Claude API para clasificación en subcategorías. Almacenar resultado. Dashboard de patrones.

## Infraestructura IA Necesaria

Para los puntos más avanzados (1, 2, 6, 7):
- **Neon con pgvector** (ya recomendado en plan anterior) para embeddings y analytics
- **Claude API** para NLP (clasificación de notas, generación de mensajes)
- **Feature store** básico: tablas PostgreSQL con métricas agregadas por zona/conductor/dirección
- **Cron/worker** para recalcular modelos periódicamente

## Verificación

- Cada punto se implementa como un servicio PHP independiente
- Tests unitarios con datos de fixtures
- A/B testing: comparar métricas antes/después de cada integración
- Dashboard de monitorización de predicciones vs realidad
