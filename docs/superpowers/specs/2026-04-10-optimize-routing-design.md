# Spec: Optimización Integral del Sistema de Routing

**Fecha:** 2026-04-10
**Branch:** `claude/optimize-routing-NE2kv`
**Estado:** Aprobado por usuario
**Alcance:** 4 direcciones — A) Parámetros VROOM, B) Datos históricos, C) Selección de estrategia, D) Políticas de re-optimización

---

## Problema

El sistema de optimización de rutas tiene infraestructura rica pero subutilizada:

1. **Re-optimización degradada:** `RouteOptimizationService.reoptimizePendingStops()` pierde TODOS los constraints (service times hardcoded 300s, ignora time windows, skills, prioridades). Las rutas re-optimizadas son peores que las originales.
2. **Datos históricos muertos:** AddressRisk, DriverFeedback, RoutePerformanceMetric, RouteAnalysisService capturan datos valiosos que NO retroalimentan al optimizador.
3. **Sin selección de estrategia:** El admin no puede elegir ni comparar optimizadores. `OptimizationStrategyComparison` entity existe pero no está wired.
4. **Sin políticas configurables:** Re-optimización automática depende de un boolean (`Route.autoReoptimize`), sin granularidad por customer.

## Restricciones

- Symfony 7.4 LTS, PHP 8.4, PostgreSQL 16
- VROOM + OSRM como infra de optimización principal
- Provider Framework con `TenantAwareProxy` para resolución per-tenant
- ML sidecar (FastAPI) existe pero no operacional — diseñar para funcionar sin él
- Specs previas: `2026-03-20-optimization-strategy-selection-design.md` y `2026-03-20-reoptimization-policy-design.md`

## Enfoque: Full-Stack Incremental (Enfoque C)

Cada phase entrega valor independiente. Phase 1 mejora calidad sin UI. Phase 2 agrega inteligencia backend. Phase 3 pone control en manos del admin.

---

## Phase 1: Fundación (1 sesión)

### 1-A) Fix re-optimización con constraints completos

**Problema específico:** `RouteOptimizationService.reoptimizePendingStops()` (línea ~182) construye `OptimizableJob` sin constraints:
- Service time: hardcoded 300s (línea ~101, ~251)
- Time windows: no se construyen
- Skills: no se pasan
- Priority: no se pasa

**Cambios:**

| Archivo | Cambio |
|---------|--------|
| `RouteOptimizationService.php` | `reoptimizePendingStops()` y `optimizeStopOrder()` construyen `OptimizableJob` con datos del `Shipment` original (service time, TW, skills, priority) |
| `RouteOptimizationService.php` | Pasar capacity del vehículo asignado como `OptimizableVehicle` |
| `RouteOptimizationService.php` | Método helper `buildJobFromStop(RouteStop): OptimizableJob` para extraer constraints del shipment asociado |

**Resultado esperado:** Re-optimización genera rutas con la misma calidad que la optimización inicial.

### 1-B) Calibración automática de service times

**Problema específico:** `ServiceTimeCalibrationService` existe pero es opcional. `DriverFeedback.actualServiceTimeSeconds` se captura pero no se usa. Default 300s se aplica siempre que no haya override explícito.

**Cambios:**

| Archivo | Cambio |
|---------|--------|
| `RouteBuilder.php` | `buildRoutes()` llama `ServiceTimeCalibrationService` por defecto si no se pasan `serviceTimeOverrides` |
| `ServiceTimeCalibrationService.php` | Incorporar `DriverFeedback.actualServiceTimeSeconds` como fuente adicional (prioridad sobre SQL window function si existe) |
| `ServiceTimeCalibrationService.php` | Nuevo método `getCalibratedServiceTimesWithFeedback(customerId, limit, minSamples)` que combina ambas fuentes |

**Jerarquía de service time (mayor a menor prioridad):**
1. `serviceTimeOverrides` explícito del caller
2. `DriverFeedback.actualServiceTimeSeconds` (promedio por dirección, si ≥2 muestras)
3. `ServiceTimeCalibrationService` SQL window function (histórico de rutas completadas)
4. `Shipment.serviceTimeSeconds` (configurado por admin)
5. Default 300s

**Resultado esperado:** Service times reflejan realidad histórica automáticamente.

### 1-C) Vehicle shifts/breaks en VROOM

**Problema específico:** VROOM soporta time windows de vehículo y breaks, pero no se mapean. `DriverAvailability` entity existe con horarios pero no se conecta al optimizador.

**Cambios:**

| Archivo | Cambio |
|---------|--------|
| `VroomRouteOptimizer.php` | `mapVehicles()` incluye `time_window` del vehículo si hay `DriverAvailability` |
| `RouteBuilder.php` | `mapVehiclesToOptimizable()` acepta `?array $driverAvailabilities` y los pasa como restricción temporal |
| `RoutePlanningService.php` | `buildRoutes()` carga `DriverAvailability` para los drivers asignados y los pasa al builder |
| Value object `OptimizableVehicle` | Agregar campos `?int $shiftStartSeconds`, `?int $shiftEndSeconds`, `?array $breaks` |

**Resultado esperado:** VROOM respeta horarios de conductores y programa breaks.

---

## Phase 2: Inteligencia (1-2 sesiones)

### 2-A) Address intelligence → optimización

**Cambios:**

| Archivo | Cambio |
|---------|--------|
| `RouteBuilder.php` | Al construir `OptimizableJob`, si `AddressRisk.isHighRisk()` → service time += 120s (buffer 2 min) |
| `RouteBuilder.php` | Si `DriverFeedback.correctedLat/Lng` existe (≥3 correcciones consistentes para misma dirección) → usar coordenadas corregidas |
| `ServiceTimeCalibrationService.php` | Incorporar direcciones problemáticas de `RouteAnalysisService` (>600s) como fuente |

### 2-B) Feedback loop automático

**Cambios:**

| Componente | Cambio |
|-----------|--------|
| Nuevo `PostRouteUpdateSubscriber` | Escucha `RouteCompleted` → actualiza `AddressRisk` con datos de la ruta completada |
| Nuevo `CoordinateCorrectionService` | Si DriverFeedback tiene ≥3 correcciones consistentes (desviación <50m entre sí) para una dirección → actualiza `CustomerLocation` o `Shipment` |
| `OptimizationStrategyComparison` | Wire `recordOutcome()` al completarse una ruta → ranking automático de estrategias por contexto (shipment count, zona geográfica, hora del día) |

### 2-C) Optimizer selection API

**Cambios:**

| Archivo | Cambio |
|---------|--------|
| `RoutePlanningService.php` | `buildRoutes(BuildRoutesInput $input)` — `BuildRoutesInput` gana campo `?string $optimizerName` |
| Nuevo `OptimizerRegistryController` | `GET /api/admin/route-planner/optimizers` — lista optimizadores disponibles con stats históricas (avg distanceKm, avg duration, success rate from `RoutePerformanceMetric`) |
| `RouteBuilder.php` | Si `$optimizerName` especificado, resuelve via `ProviderFactoryRegistry` |

---

## Phase 3: UX + Políticas (2-3 sesiones)

### 3-A) Admin strategy selection + comparison

**Cambios:**

| Componente | Cambio |
|-----------|--------|
| Route Planner Step 2 (React) | Selector de optimizador: "Automático (recomendado)" / "VROOM" / "Greedy" |
| Nuevo `StrategyComparisonService` | Ejecuta N optimizadores en paralelo sobre mismo shipment set, retorna tabla comparativa |
| Route Planner (React) | Botón "Comparar con otros" → tabla side-by-side (distancia, duración, stops, unassigned) |
| `OptimizationStrategyComparison` | Wired al flujo: se crea al comparar, `recordOutcome()` al completar ruta |

### 3-B) ReoptimizationPolicy entity

**Cambios:**

| Componente | Cambio |
|-----------|--------|
| Nueva entity `ReoptimizationPolicy` | Per customer: `triggers[]` (JSON), `delayThresholdMinutes`, `consecutiveExceptionThreshold`, `cooldownMinutes`, `enabled` |
| 3 Reopt Subscribers | Consultan `ReoptimizationPolicy` del customer en lugar de `Route.autoReoptimize` boolean |
| `Route` entity | Nuevo campo `?ReoptimizationPolicy $policyOverride` para override per-ruta |
| Admin Customer settings (Twig) | Sección de configuración de política de re-optimización |

### 3-C) Dashboard de inteligencia

**Cambios:**

| Componente | Cambio |
|-----------|--------|
| Nuevo `OptimizationAnalyticsController` | Endpoints para métricas históricas por optimizador, trend de accuracy, address risk heatmap |
| React page `/app/admin/optimization-dashboard` | Gráficos de performance por optimizador, mapa de calor AddressRisk, demand prediction overlay |

---

## Existing Functionality Inventory

| Componente | Decision | Justificación |
|-----------|----------|---------------|
| `VroomRouteOptimizer` | **Include** — extender con shifts/breaks | Core optimizer, ya mapea 6 constraints |
| `GreedyOptimizer` | **Include (Phase 3)** — mejorar como comparación | Fallback, necesita mejoras para comparación justa |
| `RouteOptimizationService` | **Transform** — fix constraints en re-opt | Gap más crítico del sistema |
| `ServiceTimeCalibrationService` | **Transform** — agregar DriverFeedback source | Existe pero subutilizada |
| `RouteBuilder` | **Transform** — calibración auto + address intel | Orchestrator principal |
| `RoutePlanningService` | **Transform** — optimizer selection + availability | Application layer entry point |
| `AddressRisk` | **Include (Phase 2)** — alimentar optimización | Datos capturados, no usados |
| `DriverFeedback` | **Include (Phase 1-B, 2-A)** — service times + coords | Datos capturados, no usados |
| `RoutePerformanceMetric` | **Include (Phase 2-C, 3-C)** — analytics | Datos capturados, no analizados |
| `OptimizationStrategyComparison` | **Include (Phase 2-B, 3-A)** — wire al flujo | Entity existe, no wired |
| `PostRouteAnalyzer` | **Omit** — no cambia en este scope | Ya funcional con AI |
| `RouteComparisonService` | **Omit** — no cambia en este scope | Ya funcional |
| `VroomRequestMapper` (deprecated) | **Omit** — dead code | Duplica VroomRouteOptimizer |
| `ServiceTimePredictionService` | **Include (Phase 2-C)** — conectar cuando ML sidecar disponible | Interface existe |
| `DemandPredictionService` | **Include (Phase 3-C)** — dashboard overlay | Ya funcional |
| `RouteAnalysisService` | **Include (Phase 2-A)** — alimentar calibración | Detecta direcciones >600s |
| `FleetAnomalyService` | **Omit** — depende de ML sidecar no operacional | Fuera de scope |

## Omission Decisions

| Elemento | Decision | Justificación |
|----------|----------|---------------|
| VROOM P&D pairs | **Omit** | Sin modelo pickup-delivery en dominio |
| VROOM proximity constraints | **Omit** | Sin requerimiento de negocio |
| VROOM custom costs | **Omit (reconsiderar Phase 3)** | Vehicle costs útiles pero no prioritarios |
| Full ML sidecar activation | **Omit** | Diseñar para funcionar sin él, conectar cuando esté disponible |
| GreedyOptimizer time windows/skills | **Defer to Phase 3** | Necesario para comparación justa, no para Phase 1-2 |

---

## Métricas de Éxito

| Métrica | Baseline | Target |
|---------|----------|--------|
| Re-opt constraints preservados | 0/4 (service time, TW, skills, priority) | 4/4 |
| Service time accuracy (planned vs actual) | Unknown (300s default) | ±20% del tiempo real |
| Address risk integration | 0 datos usados | AddressRisk + DriverFeedback + RouteAnalysis |
| Optimizer selection | Automático sin visibilidad | Admin elige + compara + ve stats |
| Reopt policy granularity | Boolean per-ruta | Per-customer con 5 triggers + thresholds |

## Riesgos

| Riesgo | Mitigación |
|--------|-----------|
| VROOM API changes con nuevos parámetros | Tests de integración contra VROOM mock |
| Service time calibration insuficiente (pocas rutas históricas) | Fallback chain de 5 niveles asegura siempre un valor |
| DriverAvailability no configurado para todos los drivers | Si no existe, no se pasa → VROOM usa defaults (sin restricción) |
| ML sidecar no disponible | `ServiceTimePredictionService` ya tiene fallback 300s |

## Siguiente Paso

Crear plan de implementación para Phase 1 (Fundación) con tareas TDD y waves paralelas.
