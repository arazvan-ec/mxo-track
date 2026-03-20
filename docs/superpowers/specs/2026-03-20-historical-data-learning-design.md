# Spec: Datos Históricos Alimentando Planificación Futura

**Fecha:** 2026-03-20
**Contexto:** Decisión 3 de `docs/analysis/2026-03-15-business-requirements-audit.md`
**Estado:** Diseño inicial

---

## Problema

El sistema ya genera datos históricos valiosos (AddressRisk, DriverFeedback, RouteComparison, PostRouteAnalyzer, RoutePerformanceMetric), pero estos datos no retroalimentan la planificación de rutas futuras. Cada ruta se planifica "desde cero" sin aprovechar el conocimiento acumulado.

## Datos Disponibles

| Dato | Entidad/Servicio | Estado | Uso Potencial |
|------|-----------------|--------|---------------|
| Tasa de excepciones por dirección | `AddressRisk` | Activo, se muestra en Route Planner | Ajustar service time, priorizar paradas problemáticas |
| Feedback de drivers | `DriverFeedback` | Entidad existe, UI parcial | Coordenadas corregidas, notas de acceso, tiempos reales |
| Tiempos reales vs estimados | `RouteSnapshot` + `RouteComparison` | Activo | Calibrar estimaciones del optimizador |
| Análisis post-ruta | `PostRouteAnalyzer` + `Route.aiAnalysis` | Activo | Patrones de desviación, causas de retraso |
| KPIs de rendimiento | `RoutePerformanceMetric` | Activo (recién expuesto al cliente) | Benchmark entre estrategias, detección de degradación |
| Scoring de conductores | `DriverScoringService` | Activo en Route Planner | Ya alimenta sugerencias de asignación |

## Opciones Evaluadas

### Opción A: Solo analytics/reporting

**No modifica la planificación.** Los datos se muestran en dashboards para que humanos tomen mejores decisiones manualmente.

**Pros:** Zero risk, implementación simple (ya parcialmente hecho con optimization KPIs)
**Contras:** No escala, no cierra el feedback loop automáticamente

### Opción B: Alimentación automática al optimizador

**Modifica inputs del optimizador automáticamente** basándose en datos históricos:
- Service times ajustados por dirección (si históricamente tarda más, usar ese tiempo)
- Risk scoring afecta order de paradas
- Tiempos estimados calibrados por zona/hora

**Pros:** Mejora continua automática, diferenciador competitivo
**Contras:** Riesgo de feedback loops negativos, difícil de debuggear cuando algo va mal

### Opción C: Sistema de recomendaciones (RECOMENDADA)

**El sistema sugiere ajustes basándose en datos, pero no los aplica sin confirmación.** Híbrido entre A y B.

**Flujo:**
1. Al planificar rutas, el sistema analiza datos históricos relevantes
2. Genera "sugerencias de ajuste" visibles en el Route Planner
3. El admin puede aplicar todas, algunas, o ninguna
4. Los ajustes aplicados se registran para medir su impacto

**Pros:**
- Transparencia: el admin entiende por qué se sugiere cada ajuste
- Control: puede ignorar sugerencias irrelevantes
- Medible: se puede comparar rutas con/sin ajustes aplicados
- Seguro: sin riesgo de degradación automática

**Contras:**
- Más trabajo de UX que B (mostrar sugerencias de forma clara)
- El admin puede ignorar siempre (mitigable con defaults "apply all")

## Decisión

**Opción C: Sistema de recomendaciones.**

Implementar en fases incrementales:

### Fase 1: Service Time Calibration

**Input:** `RouteComparison` tiene tiempos reales vs estimados por parada.
**Output:** Cuando se planifica una ruta con direcciones conocidas, sugerir service times ajustados.

- Nuevo servicio: `ServiceTimeCalibrationService`
  - Método: `getSuggestedServiceTimes(array $addresses): array<string, int>`
  - Consulta RouteComparison para esas direcciones
  - Calcula promedio de tiempo real de servicio
  - Si difiere >20% del default (300s), sugiere el ajuste
- Route Planner Step 1: badge "Tiempos calibrados disponibles" junto a shipments con historial
- Route Planner Step 2: toggle "Aplicar tiempos calibrados" (default: on)
- Payload de preview incluye `calibrated_service_times: Record<shipmentId, seconds>`

### Fase 2: Address Risk Integration

**Input:** `AddressRisk` ya tiene risk scores por dirección.
**Output:** En el Route Planner, marcar envíos con riesgo alto y sugerir agruparlos al final de la ruta (para minimizar impacto de excepciones).

- Ya parcialmente implementado (badges "Risk" en Step 1)
- Ampliar: sugerir reordenamiento post-optimización
- Registrar si el admin aceptó o rechazó la sugerencia

### Fase 3: Driver-Address Affinity

**Input:** `DriverFeedback` + historial de entregas exitosas por driver+zona.
**Output:** En Step 3 (driver assignment), ponderar más a drivers con historial positivo en las zonas de la ruta.

- `DriverScoringService` ya tiene criterio "zone"
- Enriquecer con datos de feedback explícito del driver
- Mostrar "Historial positivo en esta zona" en la sugerencia

### Fase 4: Optimizer Benchmarking

**Input:** `RoutePerformanceMetric.optimizerUsed` + KPIs por ruta.
**Output:** Recomendar el optimizador que mejor funciona para rutas similares (tamaño, zona, tipo de envío).

- Depende de Fase 1 de la spec de Strategy Selection
- Complementa: la recomendación de optimizador usa datos reales

## Arquitectura

```
Route Planner Request
    │
    ├─→ ServiceTimeCalibrationService  →  adjusted service_times
    ├─→ AddressRiskService             →  risk badges + reorder suggestion
    ├─→ DriverScoringService           →  driver recommendations (enriched)
    └─→ OptimizerRecommendationService →  optimizer suggestion
    │
    ▼
PlanningRecommendations DTO
    │
    ▼
Route Planner UI (shows suggestions, admin confirms)
    │
    ▼
BuildRoutesInput (with applied adjustments)
```

## Métricas de Éxito

- % de sugerencias aceptadas por admins (target: >60%)
- Mejora en `planAccuracyPercent` en rutas con calibración vs sin ella
- Reducción en exceptions en rutas con risk-based reordering
- Satisfacción de drivers asignados a zonas con afinidad

## Siguiente Paso

Crear plan de implementación para Fase 1 (Service Time Calibration) cuando el usuario lo solicite.
