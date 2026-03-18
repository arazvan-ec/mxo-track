# Feedback and Learning System

**Última actualización:** 2026-03-18
**Estado:** Vigente

Sistema de captura de datos y aprendizaje continuo que opera en dos niveles (workflow y negocio) con un doble loop de retroalimentación.

## Arquitectura del Sistema

```
              ┌─────────────────────────────────────┐
              │         CLAUDE.md (behavioral)       │
              │  Flujo Obligatorio + Feedback Capture │
              │  + Learning Loop + Skills 1-15       │
              └──────────┬──────────────────┬────────┘
                         │                  │
          ┌──────────────▼──────┐    ┌──────▼──────────────────┐
          │  Workflow Feedback   │    │   Business Feedback      │
          │  (markdown in docs/) │    │   (Doctrine entities)    │
          ├─────────────────────┤    ├──────────────────────────┤
          │ execution-logs/     │    │ RoutePerformanceMetric   │
          │ retrospectives/     │    │ OptimizationStrategy     │
          │ decisions/log.md    │    │   Comparison             │
          └──────────┬──────────┘    └──────────┬──────────────┘
                     │                          │
                     └────────────┬─────────────┘
                                  │
                     ┌────────────▼────────────┐
                     │     Learning Loop        │
                     ├─────────────────────────┤
                     │ Immediate: consult before│
                     │   each brainstorming     │
                     │ Periodic: monthly review │
                     │   → update knowledge     │
                     └─────────────────────────┘
```

## Nivel 1: Workflow Feedback (docs/)

### Execution Logs

**Ubicación:** `docs/superpowers/execution-logs/YYYY-MM-DD-<feature>.md`
**Template:** `docs/superpowers/templates/execution-log-template.md`
**Cuándo crear:** Después de cada code change o bug fix (ver "Feedback Capture" en CLAUDE.md)

Captura datos en 5 fases:
1. **Brainstorming** — alternativas evaluadas, approach elegido, complejidad estimada
2. **Planning** — task count, archivos afectados, time estimate, risk
3. **Implementation** — tiempo real, blockers, desviaciones, debugging
4. **Verification** — test results, lint, coverage
5. **Retrospective** — estimate accuracy, lecciones aprendidas

### Decision Log

**Ubicación:** `docs/decisions/log.md`
**Cuándo añadir:** Decisiones de diseño no-triviales (nueva abstracción, patrón, trade-off)
**Formato:** Ver template en el propio archivo

### Retrospective Reviews

**Ubicación:** `docs/superpowers/retrospectives/YYYY-MM-review.md`
**Template:** `docs/superpowers/templates/retrospective-review-template.md`
**Cadencia:** Mensual (execution review) + Trimestral (strategic review)
**Proceso:** Ver Skill 15 (Learning Review) en CLAUDE.md

## Nivel 2: Business Feedback (Doctrine)

### RoutePerformanceMetric

**Entidad:** `App\Entity\RoutePerformanceMetric`
**Propósito:** KPIs inmutables por ruta completada. Creado una vez cuando una ruta finaliza.

Campos clave:
- `route` (OneToOne → Route)
- `customer` (ManyToOne → Customer, CustomerScopedEntityInterface)
- `optimizerUsed` — qué estrategia de optimización se usó
- `plannedDistanceKm`, `actualDistanceKm`, `kmSaved`
- `plannedDurationMinutes`, `actualDurationMinutes`, `timeSavedMinutes`
- `totalStops`, `deliveredCount`, `exceptionCount`, `skippedCount`
- `deliverySuccessRate`, `planAccuracyPercent`
- `tags` — etiquetas para filtrado (urban, morning, refrigerated, etc.)

**Diferencia con RouteSnapshot:** RouteSnapshot es mutable y operacional (actualizado durante la ruta). RoutePerformanceMetric es inmutable y analítico (creado una vez al finalizar).

**Integración:** Se crea en `PostRouteAnalysisHandler` usando datos de `PostRouteAnalyzer::gatherStats()` + RouteSnapshot.

### OptimizationStrategyComparison

**Entidad:** `App\Entity\OptimizationStrategyComparison`
**Propósito:** Comparación A/B de estrategias de optimización sobre los mismos shipments.

Campos clave:
- `strategyA`, `strategyB` — JSON con strategy name, params, result metrics
- `chosen` — cuál se eligió ("a", "b", "neither")
- `chosenReason` — por qué
- `actualOutcome` — métricas reales post-ejecución (nullable, se rellena después)
- `resultRoute` — la ruta creada con la estrategia elegida
- `shipmentCount` — cantidad de shipments en la comparación

### Console Command: app:learning:metrics

**Comando:** `php bin/console app:learning:metrics --context=route-optimization --period=30d`
**Propósito:** Query agregado para que Claude consulte durante brainstorming.
**Output:** Avg km saved, delivery rate, top exceptions, optimizer comparison summary.

## Learning Loop

### Inmediato (por brainstorming)

Antes de proponer approaches (paso 0 del checklist de Skill 2):
1. Grep `docs/decisions/log.md` por keywords de la tarea
2. Listar `docs/superpowers/execution-logs/` recientes
3. Listar `docs/superpowers/retrospectives/` recientes
4. Para route optimization: ejecutar `app:learning:metrics`
5. Declarar qué se encontró

### Periódico (mensual — Skill 15)

1. Recopilar execution logs + business metrics del periodo
2. Analizar: estimate accuracy, blocker patterns, decision outcomes
3. Escribir review en retrospectives/
4. Actuar: actualizar knowledge modules, proponer cambios a CLAUDE.md

## Tabla de referencia en CLAUDE.md

Añadir a la tabla de Knowledge Modules:

| Si vas a trabajar en... | Lee primero |
|------------------------|-------------|
| Feedback, execution logs, learning loop, retrospectives | `docs/knowledge/feedback-learning.md` |
