# Execution Log — 2026-04-10 — Optimize Routing Phase 2 (Inteligencia)

**Type:** feature (route optimization)
**Branch:** `claude/optimize-routing-NE2kv`
**Spec:** `docs/superpowers/specs/2026-04-10-optimize-routing-design.md`
**Plan:** `docs/superpowers/plans/2026-04-10-optimize-routing-phase2.md`

---

## Implementation

### Wave 1 (parallel, 3 agents) — Infrastructure
- **1a:** `AddressRiskService.updateFromRouteStops()` — 4 tests, incremental risk calculation
- **1b:** `CoordinateCorrectionService` — 5 tests, haversine-based consistency check (≥3 feedbacks, <50m deviation)
- **1c:** `RoutePerformanceMetricRepository.getMetricsByOptimizer()` — verified/extended

### Wave 2 — RouteBuilder Address Intelligence (2-A)
- AddressRisk high-risk → +120s service time buffer
- DriverFeedback correctedLat/Lng → coordinate override in OptimizableJob
- 4 tests covering both risk buffer and coord correction

### Wave 3 — Feedback Loop (2-B)
- `PostRouteUpdateSubscriber` listens to `RouteCompleted`
- Updates AddressRisk from completed stops
- Records `OptimizationStrategyComparison.recordOutcome()` with delivery metrics
- 5 tests

### Wave 4 — Optimizer Selection API (2-C)
- `GET /api/admin/route-planner/optimizers` endpoint
- Lists available optimizers filtered by ServiceType::RouteOptimizer
- Joins with RoutePerformanceMetric stats (90-day lookback)
- 3 tests

## Files Changed

| File | Type | Change |
|------|------|--------|
| `src/Service/AddressRiskService.php` | Extend | +updateFromRouteStops() |
| `src/Service/CoordinateCorrectionService.php` | **New** | Haversine-based coord correction |
| `src/Repository/RoutePerformanceMetricRepository.php` | Extend | getMetricsByOptimizer() |
| `src/Service/RouteBuilder.php` | Extend | +2 deps, risk buffer, coord override |
| `src/EventSubscriber/PostRouteUpdateSubscriber.php` | **New** | Feedback loop subscriber |
| `src/Controller/Api/OptimizerRegistryController.php` | **New** | Optimizer listing API |
| Tests (7 new files) | New | ~20 tests |

## Lessons

1. **TDD en subagentes no es TDD real** — los agentes escriben test+impl juntos. Para TDD stricto, necesito escribir test → verificar RED → implementar GREEN paso a paso, o usar agentes en 2 fases.
2. **Status line mejorado funciona** — Wave/Phase/Tarea visible en hook output después de extender task_progress con wave_current/wave_total/wave_label.
3. **Constructor dependency chain** — cada nuevo servicio en RouteBuilder requiere actualizar ~7 test files que lo instancian manualmente. Un builder/factory pattern para tests reduciría este overhead.
