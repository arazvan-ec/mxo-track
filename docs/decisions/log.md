# Decision Log

Registro de decisiones de diseño significativas. Cada entrada captura el contexto, la decisión, las alternativas y el resultado. Con el tiempo, los patrones recurrentes enriquecen las guías en `docs/knowledge/` y `CLAUDE.md`.

**Cuándo añadir:** Decisiones no triviales — nueva abstracción, nuevo patrón, refactor de arquitectura, trade-off con implicaciones.

**Cuándo actualizar knowledge:** Si la misma lección aparece 3+ veces, actualizarla en la guía correspondiente.

---

### [2026-03-16] Domain Layer Refactor — Route Planning (Fase 1)

- **Problema:** Entidades de Route Planning acopladas a Doctrine ORM, validación, lifecycle callbacks. Imposible testear lógica de negocio sin BD. Viola DIP/SRP.
- **Decisión:** Wrapper/Delegation pattern — domain entities como `final` POPOs puros, Doctrine entities separadas en Infrastructure con `toDomain()`/`fromDomain()` mappers, repository interfaces en Domain.
- **Alternativas descartadas:**
  - Shared Kernel (misma entity con mapping externo) — no permite POPOs puros
  - XML mapping (recomendado históricamente) — PHP attributes son el estándar post-8.3
  - Migración big-bang — riesgo alto; elegimos migración incremental por bounded context
- **Trade-offs:**
  - (+) Domain entities puras, testeables sin BD (50 unit tests puros)
  - (+) Repository interfaces permiten swap de implementación
  - (-) Duplicación temporal: entities en src/Entity/ y src/Infrastructure/ hasta el swap
  - (-) Servicios fuertemente acoplados (RouteSnapshotManager, RouteBuilder, RoutePlanningService) requieren migración coordinada como batch
- **Resultado:** Fase 1 parcial completada — Value Objects, Domain Entities, Repository Interfaces, Infrastructure Entities con mappers, Doctrine Repositories, RouteLifecycleService migrado, Domain Events reubicados. Servicios complejos pendientes para migración coordinada.

<!-- Añadir nuevas entradas al final del archivo -->
