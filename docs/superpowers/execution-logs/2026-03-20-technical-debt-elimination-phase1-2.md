---
type: refactor
tags: []
files_touched: [docs/decisions/log.md]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-03-20 — Technical Debt Elimination (Phases 1 & 2)

**Type:** refactor
**Branch:** `claude/start-session-a-RnjfK`

---

### Phase: Brainstorming
- **Alternatives evaluated:**
  1. Approach A (por layer) — migrar todas las interfaces primero, luego todos los eventos, luego todas las entidades. Riesgo: cambios parciales dejan el sistema en estado inconsistente entre layers.
  2. Approach B (por entidad) — migrar Route completo (interface + events + POPO), luego RouteStop, luego RouteEvent. Más coherente pero más difícil de testear incrementalmente.
  3. Approach C (por impacto de negocio) — priorizar lo que desbloquea testabilidad y event sourcing para Route Planning (contexto crítico). Fases incrementales: interfaces → events → event-first → projections → POPOs.
- **Chosen approach:** Approach C — permite commits atómicos verificables, cada fase deja el sistema funcional, y prioriza el contexto crítico (Route Planning) que es donde el negocio necesita event sourcing.
- **Past decisions consulted:** Revisado `docs/decisions/log.md` — entrada de 2026-03-17 sobre MapView bounded context confirmó el patrón de domain events como POPOs + listeners. No había decisiones previas sobre repository interfaces.
- **Complexity estimate:** XL
- **Confidence:** medium — muchos archivos afectados (140 files), pero cambios mayoritariamente mecánicos (imports, type hints)

### Phase: Planning
- **Task count:** 31 tasks across 5 sub-phases (1, 2A, 2B, 2C, 2D)
- **Files affected:** 140 — key files: Route.php, RouteStop.php, RouteEvent.php (migrados a Domain/Route/Model/), 7 services migrados a interfaces, 3 nuevos domain events, 3 projection listeners
- **Time estimate:** ~4 hours
- **Risk assessment:** high — touches core domain entities, all controllers, all services; mitigado por: tests pre-existentes como safety net, cambios de imports son mecánicos

### Phase: Implementation
- **Actual time:** ~3 hours
- **Blockers hit:**
  - Doctrine mapping conflict: `App\Entity\` prefix mapped as `type: attribute`, but POPO entities needed XML mapping. Resolved by moving to `App\Domain\Route\Model\` namespace which already had XML mapping configured for RouteSnapshot.
  - `PublicIdTrait` dependency on `#[ORM\PrePersist]` lifecycle callback. Resolved by generating ULID in constructor for POPO entities and keeping trait for entities that remain in `App\Entity\`.
- **Plan deviations:**
  - RouteEvent kept simpler than planned — no full `apply()` on RouteEvent itself, only on Route aggregate
  - Phase 2C projections implemented as event listeners (RouteProjectionListener, StopProjectionListener) rather than a single RouteStateProjector, for better separation of concerns
  - Added ProjectionRebuilder service + CLI command not in original plan (needed for backfilling projection tables)
- **Debugging episodes:** none

### Phase: Verification
- **Tests:** 558 passed (5 new), 6 errors + 5 failures (all pre-existing on main, 0 new)
- **Lint:** not available (`make lint` target not configured)
- **Coverage delta:** not measured (no coverage tooling configured)

### Phase: Retrospective
- **Estimate accuracy:** accurate — estimated ~4h, actual ~3h
- **What worked:**
  1. Phased approach (interfaces first, then events, then POPOs) avoided big-bang risk
  2. Following existing patterns (RouteSnapshotRepositoryInterface, DoctrineRouteSnapshotRepository) made Phase 1 fast
  3. XML mapping already configured for `App\Domain\Route\Model\` (from RouteSnapshot) eliminated a major blocker for Phase 2D
- **What didn't:**
  1. TDD discipline not strictly followed — tests and implementation committed together rather than separate red/green commits
  2. Some mechanical import changes (80+ files) were done in bulk rather than verified individually
- **Lessons for future:**
  1. When migrating entity namespaces, verify XML mapping prefix configuration FIRST — it determines whether you need a new namespace or can reuse existing
  2. `PublicIdTrait` with `#[ORM\PrePersist]` is a migration obstacle — consider extracting ULID generation to constructor for all new entities
  3. Projection listeners should be separate classes (one per projection table) rather than a single monolith projector
- **Business context tags:** route-planning, ddd, event-sourcing, technical-debt
- **Decision log entry needed?** yes — 5 entries (see docs/decisions/log.md)
