# Para optimizar una ruta

1. Cada entrega debe tener una configuracion de volumen y peso
2. Tambien necesitamos la configuracion de volumen y peso que entra en cada vehiculo

# Demo para cliente

1. CSV para importar
2. Con ese CSV tenemos que crear X rutas, cada vehiculo puede hacer x entregas, poder configurar antes de acceptar la ruta

# CLAUDE.md: claude --resume 2a057aa1-7456-4257-ab81-debee0c6a901 <> eliminar customer vehicle -> seguir

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**transporte-tracking** (mxo-track) — Logistics tracking portal built on **Symfony 7.4 LTS** (strict lock, no 8.x components). Monorepo with `backend/` (Symfony) and `docs/`. Deployed on Railway.

The system tracks vehicles via Traccar integration, manages delivery routes with driver proof-of-delivery (POD), and provides real-time position updates via Mercure. Multi-tenant via `customer_id` Doctrine SQL filter.

**Core business value:** Route optimization — the business sells saved kilometers and saved time. Everything else (fleet management, multi-tenancy, portals, tracking) is infrastructure serving that goal.

## Tech Stack

- PHP 8.4 (Docker image: `php:8.4-cli-bookworm`), Symfony 7.4 LTS (Flex + recipes)
- PostgreSQL 16, Redis 7 (sessions), Mercure (realtime SSE)
- Doctrine ORM 3.x with attribute mapping (requires `naming_strategy: underscore_number_aware` in doctrine.yaml)
- Twig + Turbo (UX Turbo) for frontend
- Traccar for GPS device tracking

## Common Commands

```bash
cd backend && composer install          # Install dependencies
php bin/console about                   # Verify Symfony is working
php bin/console doctrine:migrations:migrate -n  # Run migrations
php bin/console doctrine:fixtures:load -n       # Load fixtures (admin user)
make lint                               # PHP syntax lint (all src files)
php vendor/bin/phpunit                  # Run tests
```

## Principles — Why They Matter Here

These principles shape every code decision. The detail lives in knowledge modules; here is the **why** for this codebase.

### SOLID

**Why:** This codebase has 37 entities, 12 provider factories, and 66 controllers. Without SRP, entities become god objects (like `User.php` which already mixes 5 responsibilities). Without DIP, services become untestable (like `DeliveryService` which depends on concrete repositories). SOLID keeps the system modular as it grows.

**Key for this project:** Open/Closed is critical — the Provider Framework relies on it. New providers = new classes, zero changes to existing code. Violations break the entire multi-tenant architecture.

**Detail:** `docs/knowledge/solid-principles.md`

### DDD Architecture

**Why:** The business sells route optimization. Route Planning, Shipment/Delivery, and Route Optimization are the revenue-generating contexts — bugs here cost money. DDD purity in these contexts means domain logic is testable without a database, and changes to infrastructure (switching OSRM to Google, changing Doctrine to something else) don't touch business rules.

**Hybrid model:** Critical contexts (Route Planning, Shipment/Delivery, Route Optimization) → DDD puro (POPOs, domain interfaces, infrastructure implements). Pragmatic contexts (User, Customer, Vehicle, Driver) → standard Symfony with ORM attributes.

**The rule for new code:** If it touches a critical context, it goes in `src/Domain/{Context}/` as a POPO. Existing entities with `#[ORM\...]` in critical contexts are documented technical debt, NOT examples to follow.

**Detail:** `docs/knowledge/architecture-ddd.md`

### Design Patterns

**Why:** This codebase already uses 15 patterns consistently (Factory+Strategy for providers, Domain Events for side-effects, Null Object for graceful degradation). Following existing patterns reduces cognitive load. But patterns are tools, not recipes — start from the problem, not the pattern.

**The decision process:** (1) Is a pattern necessary? Three clear lines > premature abstraction. (2) How many real implementations? Don't extract an interface for one. (3) Does it improve SOLID? If a pattern violates a principle, it's the wrong pattern.

**Detail:** `docs/knowledge/design-patterns.md`

### Decision Log

After any non-trivial design decision, add an entry to `docs/decisions/log.md` (problem, decision, alternatives, outcome). This feeds the Learning Loop — future sessions read this before brainstorming.

## Conventions

- All PHP files use `declare(strict_types=1)`
- Doctrine mappings via PHP attributes (not XML/YAML)
- Doctrine ORM 3.x: `naming_strategy: underscore_number_aware` required in doctrine.yaml
- Controllers use attribute routing
- API error responses via `ApiErrorResponder`
- DTOs in `src/Dto/` with `fromArray()` factory + Symfony Validator constraints
- Symfony 7.4 lock enforced: `extra.symfony.require=7.4.*`, `conflict >=8.0`

### Documentation Honesty

Documentation describes **what IS**, not what should be:
- **Current state** is the default voice: "Entities use ORM attributes in `src/Entity/`"
- **Aspirational** uses markers: "**[PLANNED]** Critical entities will migrate to `src/Domain/`"
- **Partial** uses: "**[PARTIAL]** Domain events are POPOs (13 events), but entities remain in `src/Entity/`"

## Development Flow — Why This System Exists

**The problem:** Claude starts every session with zero memory. Without structure, it skips design, writes code that misses edge cases, claims "tests pass" without running them, and loses all lessons learned. The flow exists to prevent this by creating a chain where each phase produces something the next phase consumes. Break one link, the chain collapses.

**The chain:**

```
Consult → Brainstorm → Plan → Implement → Verify → Capture → Retrospective → Finalize
   ↑                                                              |
   └──────────── Learning Loop (execution logs feed future consult) ──┘
```

### Flow Classification (FIRST step before any response)

Every interaction gets classified. This determines how deep the flow goes.

| Type | Signal | Flow | Why it exists |
|------|--------|------|---------------|
| **Informational** | "what does X?", "explain Y" | Micro | Questions don't need design, but may reveal doc gaps |
| **Documentation** | Edit docs, knowledge modules | Light | Docs need consistency checks, not full design |
| **Bug fix** | Error, test failure | Debug | Bugs need root cause analysis, not guess-and-check |
| **Code change** | New feature, refactor | Full | Code without design produces rework |
| **Exploration** | "audit X", "analyze Y" | Explore | Analysis should be captured, not lost |

**Immediately after classifying**, update `session-state.json`. The workflow engine (`.claude/hooks/workflow-engine.sh`) enforces this mechanically — it blocks code edits if phases haven't been completed.

**Technical reference for the workflow engine (gates, validators, session-state schema):** `docs/knowledge/development-workflow.md`

### Full-Flow: The Connected Narrative

Each phase produces something specific. Understanding what and why prevents treating phases as checkbox bureaucracy.

#### Phase 1: Consult

**What you do:** Read `docs/decisions/log.md`, scan recent `docs/superpowers/execution-logs/`, scan `docs/superpowers/retrospectives/`.

**What this produces:** Context about past mistakes and decisions. Without it, brainstorming proposes approaches already proven wrong in previous sessions.

**Declare:** "Consulté decisiones pasadas: [found X relevant / nothing relevant]"

#### Phase 2: Brainstorm (Skill 2)

**What you do:** Explore alternatives WITH the user. Propose 2-3 approaches with trade-offs. Get user approval. Write a design spec.

**What this produces:** A spec (`docs/superpowers/specs/`) that is the contract for everything downstream. The plan checks against it. Verification checks against it. Without a spec, there's nothing to verify against.

**Critical rules integrated here:**
- **Anti-omission:** Every spec MUST inventory existing functionality in the affected area and document decisions (include/omit/transform) for each element. Silent omission is a defect. This prevents the most common spec failure: dropping existing features without noticing.
- **Bounded context gate:** Declare whether the work touches a critical context (DDD puro) or pragmatic (Symfony). This determines where new code goes.
- **Architecture gate:** Evaluate approaches for scalability. The best solution is the one that scales best, regardless of how many files it touches. The flow IS the safety net for big changes.

**Spec output goes to:** `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`

#### Phase 3: Plan (Skill 3)

**What you do:** Write a detailed implementation plan assuming the implementer has zero context (it might be a subagent).

**What this produces:** A step-by-step plan (`docs/superpowers/plans/`) with exact file paths, code snippets, and TDD cycles (write test → verify red → implement → verify green). Without it, implementation skips edge cases the spec covered.

**Two-phase structure:** Phase 1 (v0 — make it work, simplest possible) → Phase 2 (Mature — refactor toward target architecture). Tests from v0 are the safety net for Phase 2.

#### Phase 4: Implement (Skills 4/5, TDD via Skill 7)

**What you do:** Execute the plan. For each task: write failing test → verify it fails → implement → verify it passes → commit.

**What this produces:** Code AND tests. The test you write here is NOT just good practice — it IS the evidence that verification in Phase 5 will check. Without a test, Phase 5 degrades to "does it compile?", which catches zero logic bugs.

**TDD is not optional.** If you wrote code before the test, delete it and start over. The test proves you understood the requirement before implementing it.

**Atomic commits:** Every completed task gets committed and pushed immediately. Sessions are ephemeral — a crash loses all unpushed work. Commits are checkpoints, not ceremony.

#### Phase 5: Verify (Skill 9)

**What you do:** Run the full test suite. Run the linter. Check exit codes. Read full output.

**What this produces:** Evidence that the code works. "Should pass" is not evidence. "I'm confident" is not evidence. Only command output with 0 failures is evidence.

**The test from Phase 4 is what makes this phase meaningful.** Without it, verification is just a build check.

#### Phase 6: Capture

**What you do:** Write an execution log to `docs/superpowers/execution-logs/YYYY-MM-DD-<feature>.md`.

**What this produces:** Data that feeds the Learning Loop. The next session's Consult phase reads these logs. Without capture, the same mistakes repeat across sessions.

**Data per phase:** Alternatives evaluated, plan deviations, blockers hit, verification results, estimate accuracy.

#### Phase 7: Retrospective

**What you do:** Update `docs/decisions/log.md` with non-trivial design decisions. Evaluate estimate accuracy. Note lessons.

**What this produces:** Entries that directly feed Phase 1 (Consult) of future sessions. This closes the learning loop.

#### Phase 8: Finalize (Skill 12)

**What you do:** Verify tests on merged result. Present branch strategy options (merge/PR/keep/discard). Run `make manifest`.

**What this produces:** Clean integration. `make manifest` updates `docs/codebase-manifest.md` so future sessions have fresh metadata.

### Scope Change Detection

When the user makes a NEW request that can't be satisfied by the current spec+plan, it's a scope change. STOP. Increment `interaction_id`. Re-classify. Start the flow from the beginning.

**Detection signals:** Request not in plan, user asks for something new ("also add X"), user changes topic, file to edit not in plan's file list.

### Debug-Flow (variant)

For bug fixes, the chain is shorter but equally strict:

1. **Consult** — Check past retrospectives for similar bugs
2. **Root Cause** (Skill 8) — Systematic investigation. Read error messages completely. Reproduce. Trace data flow. NO fixes until root cause is identified.
3. **Pattern-Wide Search** — After finding root cause in ONE place, search the entire codebase for the same defective pattern. Fix ALL instances, not just the reported one.
4. **TDD** — Write a failing test that reproduces the bug, THEN fix
5. **Capture + Retrospective** — Log the root cause pattern for future sessions

### Light Flows (micro, light, explore)

These flows don't produce code, so they skip design/planning:
- **Micro:** Consult → respond → capture doc gaps if found
- **Light:** Verify existing docs for overlap → propose change → execute → verify consistency
- **Explore:** Read manifest first → explore → respond → capture findings if substantive

## Working Principles

These principles apply every turn. Each exists for a specific reason.

### Atomic Commits & Push

**Why:** Claude sessions are ephemeral. A crash, timeout, or API error loses ALL unpushed work. Commits are recovery checkpoints, not ceremony.

- Commit after each completed task, each passing test, each doc update
- Push after every commit (or max 2-3 if part of the same logical step)
- Push ALWAYS before launching subagents and when finishing a TodoWrite task
- Run `make manifest` before final push or when finishing a branch
- Artifacts go to the repo (`docs/superpowers/plans/`, `specs/`, `execution-logs/`), never only to ephemeral paths
- Commit format: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:` — one logical change per commit

### Non-Redundancy

**Why:** Every tool call costs context window and time. Redundant exploration is the most common waste.

- If the action won't change system state → don't execute it
- Verify with read tools before write tools
- If the target tool already handles the case (e.g., `Write` creates parent dirs) → skip preliminary steps

### Pre-Exploration Gate

**Why:** `docs/codebase-manifest.md` contains entity counts, service maps, route maps, directory trees — all auto-generated. Exploring the codebase for information that's already there wastes context window.

- **Before any Grep/Glob/Bash to discover structure:** Read `docs/codebase-manifest.md` first
- If the data is there and fresh (< 7 days) → use it directly, no exploration needed
- If missing → explore, then run `make manifest` to update it

**Exploration layers (escalating cost):**
1. **Manifest + Knowledge modules** (0 tool calls) → stop if sufficient
2. **Semantic search** via `codebase_search` MCP (1 call) → for conceptual queries
3. **Directed search** — Grep/Glob/Read (1-3 calls) → for exact names/patterns
4. **Explore agent** → only if layers 1-3 insufficient

**Decision rule:** Know the exact name? → Grep. Know the file pattern? → Glob. Conceptual query? → `codebase_search`. Counting/listing? → Manifest.

### Scalability in Decisions

**Why:** The flow (brainstorm → spec → plan → TDD → review) exists to make big changes safe. A solution that touches 20 files but scales correctly is better than a 3-line patch that doesn't.

- Choose the approach that scales best, not the one with the smallest diff
- Big changes ≠ high risk. Risk comes from absence of plan, not volume of changes
- Never discard the best solution because "it's too much change"

## Critical Patterns

### Entity Identity (mandatory)

- **Internal PK**: BIGINT auto-increment (`id`) — joins, internal processing
- **Public ID**: ULID (`public_id`) via `PublicIdTrait` — APIs, URLs, Mercure topics
- **NEVER expose internal `id` in public APIs**

### Multi-Tenancy

- `CustomerTenantFilter` (Doctrine SQL filter) + `CustomerScopedEntityInterface`
- Admin/Operator bypass; ROLE_CUSTOMER and ROLE_DRIVER scoped

### Role Hierarchy

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

### Constructor Signature Changes (mandatory)

When modifying a class constructor (adding/removing/changing parameters):

1. **Search ALL call sites** — `grep -r "new ClassName("` across `src/` AND `tests/`
2. **Check Factory classes** — This project uses Provider/Factory pattern (`*Factory.php`). Factories use `new` directly — NOT auto-wired by Symfony. WILL break silently if not updated.
3. **Check DI config** — If manually wired in `services.yaml`, update there too.
4. **Run tests** — Verify no `ArgumentCountError` or `TypeError` at runtime.

**Why:** Symfony auto-wires most services, but Factories use `new` directly. Changing a constructor without updating its Factory causes runtime errors.

## Skills Reference

Skills define the techniques used within the flow phases. They live in `docs/knowledge/superpowers-skills.md` and are consulted on-demand when a phase invokes them.

**Skill invocation rule:** If there's even a 1% chance a skill applies, invoke it. This is not optional.

**Priority:** Process skills first (brainstorming, debugging), implementation skills second.

| Skill | When invoked | Type |
|-------|-------------|------|
| 1. Using Superpowers | Every interaction start | Rigid |
| 2. Brainstorming | Phase 2 (full-flow) | Rigid |
| 3. Writing Plans | Phase 3 (full-flow) | Rigid |
| 4. Executing Plans | Phase 4 (single session) | Flexible |
| 5. Subagent-Driven Dev | Phase 4 (with subagents) | Flexible |
| 6. Parallel Agents | Multiple independent tasks | Flexible |
| 7. TDD | Phase 4 (all code changes) | Rigid |
| 8. Systematic Debugging | Debug-flow root cause | Rigid |
| 9. Verification | Phase 5 | Rigid |
| 10. Receiving Code Review | PR feedback | Flexible |
| 11. Requesting Code Review | Before merge | Flexible |
| 12. Finishing Branch | Phase 8 | Rigid |
| 13. Git Worktrees | Feature isolation | Flexible |
| 14. Writing Skills | Creating new skills | Rigid |
| 15. Learning Review | Monthly retrospective | Flexible |

**Full skill content:** `docs/knowledge/superpowers-skills.md`

## Knowledge Modules (consult on-demand)

Before working on a subsystem, **READ the relevant module** in `docs/knowledge/`:

| Working on... | Read first |
|--------------|------------|
| Entities, relations, migrations, enums | `domain-model.md` |
| Providers, factories, per-tenant resolution | `provider-framework.md` |
| Controllers, DTOs, APIs, endpoints | `api-surface.md` |
| Docker, Railway, env vars | `deployment.md` |
| Tests, PHPUnit, coverage | `testing.md` |
| Mercure, SSE, JWT tokens | `realtime.md` |
| Traccar, GPS positions, simulation | `gps-tracking.md` |
| SMS, WhatsApp, push, webhooks | `notifications.md` |
| Claude AI, embeddings, ML | `ai-ml.md` |
| VROOM, OSRM, capacity, routes | `route-optimization.md` |
| DDD, SOLID, decoupling, bounded contexts | `architecture-ddd.md` |
| Design patterns GoF + DDD | `design-patterns.md` |
| SOLID principles detail | `solid-principles.md` |
| Roles, multi-tenancy, CSRF, security | `security.md` |
| Superpowers skills (full content) | `superpowers-skills.md` |
| Workflow engine, gates, session-state | `development-workflow.md` |
| Feedback, execution logs, learning loop | `feedback-learning.md` |
| Twig templates, Alpine.js, Tailwind, React | `ui-frontend.md` |
| Module index | `index.md` |
| Business requirements, gaps, decisions | `docs/analysis/2026-03-15-business-requirements-audit.md` |

**Freshness:** Modules with verification date < 14 days → use directly. Older or unverified → spot-check 2-3 claims before trusting. After any task touching a subsystem → update the corresponding module.

## CLAUDE.md Governance

**Inline in CLAUDE.md:** Behavioral instructions (flow, principles, critical patterns) — needed every turn.
**In `docs/knowledge/`:** Reference data (domain model, deployment, API surface, workflow engine detail) — consulted on-demand.

Before modifying CLAUDE.md: Is it a behavioral instruction? → stays inline. Is it reference data? → goes to knowledge module. Unsure? → ask the user.

## Features Document

`docs/FEATURES.md` — complete feature description. Keep updated with every PR that adds/modifies/removes functionality.

## Backlog Arquitectónico

### [2026-03-11] Providers configurables: Proxy + Factory vs alternativas

**Estado:** Pendiente de implementación
**Decisión:** Transparent Proxy + Provider Factory + CustomerIntegration entity
**Spec:** `docs/superpowers/specs/2026-03-11-user-configurable-providers-design.md`
**Plan:** `docs/superpowers/plans/2026-03-11-user-configurable-providers.md`
**Trigger para revisitar:** Si boilerplate de proxies > 6 servicios, considerar codegen o proxy genérico.

### [2026-03-11] GpsDeviceProviderInterface: Métodos Traccar-específicos

**Estado:** Pendiente
**Decisión:** Stubs en WebhookGpsProvider (login→no-op, getSessionCookie→null)
**Trigger:** Al implementar tercer provider GPS, refactoring obligatorio.

### [2026-03-11] Mercure listeners usan HubInterface directamente

**Estado:** Pendiente
**Decisión:** Deuda técnica documentada. Refactorizar antes de configurar tenant con HttpPolling.

### [2026-03-11] Sin encriptación de credenciales en CustomerIntegration

**Estado:** Pendiente
**Trigger:** Antes de producción con customers configurando API keys de terceros.

### [2026-03-15] Selección de estrategia de optimización

**Estado:** Pendiente
**Contexto:** Actualmente la estrategia se selecciona por provider configuration (CustomerIntegration). Sin visibilidad para admin ni comparación.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 1`
**Trigger:** Cuando se diseñe el flujo UI de creación de rutas (GAP-3.1).

### [2026-03-15] Política de re-optimización automática vs manual

**Estado:** Pendiente
**Contexto:** RouteOptimizationService puede re-optimizar paradas PENDING, pero no hay política definida de cuándo hacerlo automáticamente.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 2`
**Trigger:** Cuando se defina la política de negocio de re-optimización.

### [2026-03-15] Datos históricos para alimentar planificación futura

**Estado:** Pendiente
**Contexto:** Existen AddressRisk, DriverFeedback, RouteComparison, PostRouteAnalyzer — potencialmente útiles para mejorar planificación.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 3`
**Trigger:** Cuando se diseñe el módulo de aprendizaje/mejora continua.
