# mxo-track — Guide for Claude Code

<!-- GENERIC-START: Project identity section — adapt for any project -->
## What This Project Is

**transporte-tracking** (mxo-track) — Logistics tracking portal built on **Symfony 7.4 LTS** (strict lock, no 8.x components). Monorepo with `backend/` (Symfony) and `docs/`. Deployed on Railway.

The system tracks vehicles via Traccar integration, manages delivery routes with driver proof-of-delivery (POD), and provides real-time position updates via Mercure. Multi-tenant via `customer_id` Doctrine SQL filter.

**Core business value:** Route optimization — the business sells saved kilometers and saved time. Everything else (fleet management, multi-tenancy, portals, tracking) is infrastructure serving that goal.
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
### Tech Stack

- PHP 8.4, Symfony 7.4 LTS (Flex + recipes), PostgreSQL 16, Redis 7 (sessions), Mercure (SSE)
- Doctrine ORM 3.x with attribute mapping (`naming_strategy: underscore_number_aware`)
- Twig + Turbo for legacy frontend, React SPA for map views (`/app/*`)
- Traccar for GPS device tracking

### Common Commands

```bash
cd backend && composer install          # Install dependencies
php bin/console about                   # Verify Symfony is working
php bin/console doctrine:migrations:migrate -n  # Run migrations
php bin/console doctrine:fixtures:load -n       # Load fixtures
make lint                               # PHP syntax lint
php vendor/bin/phpunit                  # Run tests
```
<!-- PROJECT-SPECIFIC-END -->

---

<!-- GENERIC-START: Core philosophy — applies to any project using this harness -->
## How Claude Thinks in This Repo

### Context Is a Scarce Resource

Claude Code assembles context once per conversation and memoizes it. Every token
loaded into the system prompt is a token unavailable for reasoning about your code.
This is why instructions are distributed across a hierarchy:

- **This file (root CLAUDE.md)** loads in every conversation — it contains philosophy,
  workflow, and cross-cutting rules
- **Subdirectory CLAUDE.md files** load only when working in that directory —
  `backend/CLAUDE.md` has architecture rules, `backend/src/CLAUDE.md` has implementation
  rules, etc.
- **AGENTS.md** loads when dispatching subagents — full instructions for reviewers,
  parallel agents, etc.

This way, answering a question costs ~400 tokens of instructions, not ~2000.

### The Manifest as Codebase Cache

#### Why a pre-computed snapshot, not live search

Every Grep call consumes context tokens — a search returning 50 results costs ~2000
tokens. Reading the manifest costs ~500 tokens for the same information. Over a session
with 10+ lookups, that's 15,000 tokens saved — tokens that become available for reasoning
about your code instead of storing search results. **The manifest exists because context
is a zero-sum budget: every token spent on discovery is a token unavailable for thinking.**

The alternative — grepping the codebase each time — also has a hidden cost: search results
lack structure. A grep for "Route" returns entity files, controllers, templates, tests,
and config mixed together. The manifest pre-organizes this into entity list, service map,
route map, and metrics, so the answer is immediately usable without further filtering.

#### How the manifest stays current

The manifest is regenerated with `make manifest` (~1 second). The flow has two paths:

**Fast path (0 tool calls):** Read `docs/codebase-manifest.md` or the relevant
`docs/knowledge/` module → the answer is there → use it directly. This is the common
case and the reason the manifest exists.

**Update path (1-3 tool calls):** The answer isn't in the manifest → directed search
(Grep/Glob with specific terms) → find the answer → **update the manifest before
continuing.** This last step is critical: if you found something the manifest didn't
know, the manifest is stale and must be fixed. Otherwise the next session hits the
same gap and wastes the same tokens rediscovering it.

This feeds everything downstream: brainstorming reads the manifest to inventory existing
functionality (Step 3 of the checklist), planning reads it for exact file paths,
verification regenerates it post-push to capture what changed.

### Session-State: Memory That Survives Compaction

#### Why a file, not conversation markers

Claude Code compacts old messages when the context window fills up. Compaction is
lossy — the model loses track of which phases it completed, what evidence it gathered,
and where it is in a plan. Any state stored only in conversation messages is volatile.

The alternative — keeping state in conversation — fails precisely when you need it most:
long sessions with many tool calls compact aggressively, which is exactly when tracking
"where was I?" matters. **`.claude/session-state.json` is external memory because disk
is persistent and conversation is not.**

#### How the feedback loop works

The session-state participates in a loop that runs every turn:

```
Model updates session-state (jq) → Hook reads session-state → Hook injects status line
    → Model sees its own state in the next turn → Model knows where it is
```

1. **Model writes state** with `jq` after each phase transition. `jq` is used because
   it's atomic (read-transform-write) — a manual Edit risks corrupting JSON mid-write.
2. **SessionStart hook** resets state daily but preserves `last_work_summary` so the
   model has previous session context on startup.
3. **UserPromptSubmit hook** reads the file and injects a status line between every
   tool call. This is how the model "remembers" across compaction — even if old messages
   are gone, the hook re-injects the current state into every new prompt.
4. **Phase-advance script** (`.claude/hooks/phase-advance.sh`) enforces legal phase
   transitions — no skips, no backwards. This prevents the model from writing arbitrary
   state that doesn't match the workflow.

**Full schema and update patterns:** `.claude/README.md`
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Workflow classification and flow -->
## The Workflow: Every Interaction Has Structure

Every interaction follows a structured flow. The depth scales with the type, but the
structure is always present. **Why:** Unstructured work skips verification, loses context,
and accumulates technical debt silently.

### Classify First (before any response)

#### Why classify before anything else

Without classification, the model defaults to the path of least resistance: read a file,
edit it, move on. This skips brainstorming for features, skips root cause analysis for
bugs, and skips verification for everything. **Classification is the mechanism that
activates the right gates** — it writes a flow type to session-state, and hooks use that
flow type to decide which phases are required before code edits are allowed.

The alternative — "just start working and I'll figure out the flow as I go" — produces
a consistent failure pattern: the model does 80% of the work, skips verification, and
the user discovers the remaining 20% is broken. Classification front-loads the structure
so the workflow engine can enforce it.

#### How classification produces the right flow

Classification writes to session-state → hooks read session-state → hooks block premature
code edits. Each type activates a different gate chain:

| Type | Signal | Flow activated | What the gates enforce |
|------|--------|----------------|----------------------|
| **Informational** | "what does X do?" | Micro — consult → answer → capture gaps | No code edits allowed (must reclassify if needed) |
| **Documentation** | Edit docs | Light — check overlap → execute → verify | No `src/` edits (must reclassify if scope grows) |
| **Bug fix** | Error, unexpected behavior | Debug — root cause → pattern-wide → TDD fix | Blocks fix until root cause + pattern-wide search done |
| **Code change** | New feature, refactor | Full — consult → brainstorm → plan → implement → verify → capture → retrospective → finalize | Blocks `src/` edits until consult + brainstorm + plan complete |
| **Exploration** | "audit X", "how does Z?" | Explore — manifest → explore → capture | No code edits allowed (must reclassify if needed) |

After classifying: `jq '.flow_type = "<type>"' .claude/session-state.json`

**Why "Exploration" is separate from "Informational":** Informational answers from existing
docs (0-1 tool calls). Exploration actively investigates the codebase (5-20 tool calls)
and captures findings. The distinction matters because exploration produces artifacts
(updated knowledge modules) while informational does not.

**Phase transitions:** Use `.claude/hooks/phase-advance.sh <next_phase>` to advance.
Direct writes to `phase_history` via `jq` are detected and reverted. The script enforces
legal sequence (no skips, no backwards) and adds timestamps automatically.

### Deviation for Wiring-Only Changes

#### Why an escape valve exists

The full flow (brainstorm + plan) adds 10-15 minutes of overhead. For a feature with
design decisions, that overhead prevents rework worth hours. But for wiring changes —
connecting an existing callback, passing a prop, adding an import — there are zero design
decisions, so the overhead produces zero value. Calibration data confirms this: wiring
tasks average ~15 lines, 1 file, <5 minutes (see Calibration Data section below).

**The escape valve exists because the cost-benefit of the full flow inverts for trivial
changes.** Without it, the workflow penalizes exactly the kind of quick fixes that keep
a codebase healthy.

#### How deviation mode works

Deviation skips brainstorm and plan but keeps everything else. The phases that remain —
consult, implement, verify, capture, retrospective, finalize — are the ones that catch
errors even in simple changes:

```
consult → [skip brainstorm] → [skip plan] → implement → verify → capture → retrospective → finalize
                                                                                │
                                                               catches patterns: "this is the 3rd page
                                                               missing onStopClick" → systemic issue
```

**The retrospective is the most important phase for wiring changes** — it's where you
detect that a "simple" change is actually a symptom of a missing abstraction. Three
similar wiring fixes in a week means the codebase needs a structural fix, not a fourth
wiring patch.

**Criteria (ALL must be met):**
- **< 30 lines** changed across all files
- **0 design decisions** — no new abstractions, patterns, or trade-offs
- **No new entities, migrations, or API endpoints**
- **Pattern already exists** in the codebase (copying an established approach)

**Activate:**
```bash
jq '.deviation = {"active": true, "reason": "wiring-only (<30 lines, 0 design decisions)", "skipped_phases": ["brainstorm", "plan"], "return_to_phase": null, "acknowledged_by_user": true}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

### Full-Flow: The 8 Phases

Each phase produces something that feeds the next. The workflow engine (hooks in
`.claude/hooks/`) blocks code edits if prior phases aren't completed.

```
consult → brainstorm → plan → implement → verify → capture → retrospective → finalize
   │           │          │         │          │        │            │            │
   │     produces spec  produces  follows    runs    writes     reflects    merges/
   │     with design    plan with  TDD       tests   execution  on what     creates PR
   │     + approval     tasks      cycle     + lint  log        worked
   │                                                            
   reads decisions,                                             
   execution logs,                                              
   retrospectives                                               
```

**Scope change detection:** If the user requests something NOT in the current plan,
it's a new interaction. Increment `interaction_id`, reclassify, restart the flow.

### Fix Invalidation

#### Why "still broken" means start over, not continue

When a fix doesn't work, the natural instinct is to build on the previous analysis: "the
root cause was X, my fix just didn't address it completely, let me adjust." This is almost
always wrong. **If the fix didn't work, the root cause analysis was incorrect** — the model
anchored to a plausible-but-wrong explanation and the fix followed logically from a false
premise.

Continuing from the same analysis inherits the same false premise. The model will propose
variations of the same wrong fix, each one "almost right," consuming time without progress.
This is the sunk cost fallacy applied to debugging: "I've already invested in this root
cause analysis, I can't abandon it."

**Reset completely.** Treat it as a new debug interaction with fresh eyes:

```bash
jq '.interaction_id = (.interaction_id + 1) | .evidence.root_cause_identified = false | .evidence.pattern_wide_search_done = false | .evidence.tests_passed = null | .current_phase = "root_cause"' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

The reset forces re-examination without anchoring bias. The previous analysis is not
consulted — if it was right, fresh analysis will rediscover it. If it was wrong, fresh
analysis won't be poisoned by it. This is the same principle as Skill 8's rule: "if 3+
fixes failed, STOP — question the architecture."

### Workflow Engine (summary)

#### Why mechanical enforcement, not just instructions

The model has a bias toward action — when given a task, the impulse is to edit code
immediately. Instructions saying "brainstorm first" are suggestions; the model can
rationalize skipping them ("this is simple enough," "I already know the approach").
**Hooks are gates, not suggestions.** They physically reject `Edit` calls to `src/`
or `tests/` when prerequisite phases aren't completed in session-state.

The alternative — trusting the model to self-enforce discipline — was the original
approach. It failed consistently: ~70% of sessions skipped at least one phase when
gates weren't present. The hooks exist because **the model cannot reliably judge when
it's safe to skip phases.** The phases it most wants to skip (brainstorm, verification)
are exactly the ones that catch the most errors.

#### What each gate blocks and why

| Flow | Gate | What it prevents |
|------|------|-----------------|
| micro/light/explore | DENY edits to `src/`, `tests/` | Scope creep — a "quick look" turning into unplanned code changes without design review |
| debug | HARD: needs root_cause + pattern-wide | Symptom fixes — patching what's visible without understanding what's broken |
| full | HARD: needs consult + brainstorm + plan | Cowboy coding — implementing the first idea without evaluating alternatives or checking existing patterns |

The gates are deliberately strict. A false negative (blocking a legitimate edit) costs
minutes to reclassify. A false positive (allowing an unreviewed edit) costs hours to
debug the resulting regression.

**Full reference:** `.claude/README.md` (gates, validators, deviation mode, harness assumptions)
<!-- GENERIC-END -->

---

<!-- GENERIC-START: The QA loop -->
## Before Writing Code: The QA Loop

### Why Brainstorming Is Not Bureaucracy

#### The cost asymmetry that justifies the overhead

In conversation, changing approach costs zero: no lines deleted, no tests broken, no
commits reverted. In code, every approach change is a `git reset` — tests rewritten,
interfaces re-designed, dependent code updated. **Brainstorming exploits this asymmetry:
it moves design failures from code-time (expensive) to conversation-time (free).**

The model's natural bias is "I understand the problem, let me code." But understanding
and designing are different activities. The model can understand a problem perfectly and
still choose a suboptimal approach because it didn't inventory existing functionality,
didn't consider alternatives, or didn't check how similar problems were solved before.

#### How the checklist produces a validated design

Each step produces an artifact that the next step needs. The order is not arbitrary —
it's a dependency chain:

0. **Consult past decisions** — Read `docs/decisions/log.md` and recent execution logs.
   → produces: context about what was tried before and why.
   This prevents re-discovering lessons the codebase already learned. Without it, the
   model proposes approaches that were previously tried and rejected.

1. **Classify bounded context** — Is this critical (DDD pure) or pragmatic (Symfony)?
   → produces: the architectural style for this change.
   This determines whether to use value objects or primitives, domain events or direct
   calls, repository interfaces or Doctrine queries. Getting it wrong means rewriting
   after review.

2. **Explore project context** — Check files, docs, recent commits.
   → produces: awareness of recent changes that might conflict or overlap.

3. **Inventory existing functionality** — Enumerate what exists in the affected area.
   → produces: a map of every element with an explicit decision: Include / Omit / Transform.
   **Why explicit decisions?** Silent omissions are the #1 brainstorming bug. The model
   "forgets" that a component exists, designs without it, and the implementation collides
   with it. The Include/Omit/Transform decision forces acknowledgment of every element.

4. **Ask clarifying questions** — One at a time. Multiple choice when possible.
   → produces: resolved ambiguities. Multiple choice is preferred because it constrains
   the answer space — "Do you want A, B, or C?" gets a decision faster than "What do
   you want?"

5. **Propose 2-3 approaches** — With trade-offs and recommendation.
   → produces: evaluated alternatives. This is where the consult (step 0) and inventory
   (step 3) pay off — the approaches account for past decisions and existing code.

6. **Present design, get approval** — Section by section.
   → produces: validated design with user sign-off.

7. **Write spec** — Save to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
   → produces: persistent artifact that planning and implementation reference.

8. **Transition to planning** — The spec feeds directly into the planning phase.

**The spec must include:**
```markdown
## Existing Functionality Inventory
[List of existing elements, or "No existing functionality affected"]

## Omission Decisions
| Element | Decision | Justification |
```

### Planning: v0 → Mature

**Why two phases:** Validating a concept is separate from perfecting its architecture.
v0 proves the solution works. Phase 2 refactors toward production quality. Tests from
v0 are the safety net — they must stay green throughout Phase 2.

Plans go to `docs/superpowers/plans/YYYY-MM-DD-<feature>.md` with:
- Phase 1 (v0): simplest working implementation with tests
- Phase 2 (Mature): refactor toward target architecture
- Each task follows TDD: write test → verify fail → implement → verify pass → commit
- **Never create a separate "add tests" task.** Tests are integral to each task via TDD —
  writing the test IS the first step of implementing the task, not a task on its own.

### Parallel-First Planning

#### Why parallel, not sequential

Sequential plans are the natural default — list steps in order, execute top to bottom.
But sequential plans hide a critical design flaw: **they don't reveal which tasks are
truly dependent and which are merely ordered by habit.** When you force yourself to
identify what can run concurrently, you discover the real dependency graph of the work.
This has three effects:

1. **Surfaces hidden coupling.** If two tasks "feel" sequential but have no data
   dependency, the sequentiality was masking unclear interfaces between them.
2. **Forces cleaner task boundaries.** Parallel tasks can't share mutable state, so
   each task must have well-defined inputs and outputs — this is better design.
3. **Reduces wall-clock time.** A 6-task plan where 4 tasks are parallel might execute
   in the time of 3, not 6.

The alternative — "just list tasks in order" — works for small changes but collapses at
scale: a 12-task plan executed sequentially takes 12x, while the same work decomposed
into 4 parallel waves takes 4x. **This is why parallel decomposition is a design
technique, not an optimization to apply later.**

#### How parallel decomposition works

The technique is a three-step analysis that produces a dependency graph:

**Step 1: Atomic decomposition.** List every discrete unit of work without thinking
about order. Each unit should touch a single concern (one entity, one component, one
test file). This produces a flat list.

**Step 2: Dependency identification.** For each pair of tasks, ask: "Does task B need
the *output* (file, type, API) that task A creates?" If yes, B depends on A. If B
merely comes "after" A by convention, there is no dependency. **"It's easier sequentially"
is not a dependency.** This produces a directed acyclic graph (DAG).

**Step 3: Wave grouping.** Group tasks into waves (parallel groups). Wave 1 has all
tasks with zero dependencies. Wave 2 has tasks whose dependencies are all in Wave 1.
And so on. The **parallel frontier** at each wave is the number of concurrent tasks —
maximize this number. If a wave has only 1 task, look for ways to split it.

This analysis feeds directly into the plan document:

```markdown
### [parallel] Wave 1: Tarea 1a + 1b + 1c
- **1a:** Backend — add field to snapshot (backend/src/...)
  → produces: updated entity, migration
- **1b:** Frontend — extend TypeScript type (frontend/src/...)
  → produces: updated interface
- **1c:** Tests — add fixture data for new field (backend/tests/...)
  → produces: test fixtures

### Wave 2: Tarea 2 (needs 1a outputs + 1b outputs)
- Frontend — use new fields in component
  → produces: working component with new data

### [parallel] Wave 3: Tarea 3a + 3b (needs Wave 2)
- **3a:** Backend — add validation rule
- **3b:** Frontend — add error display component
```

Each task declares what it **produces** (→), which makes dependencies explicit and
auditable. When a new developer reads the plan, they understand *why* Wave 2 waits
for Wave 1 — not because "it comes next" but because it needs specific artifacts.

#### Execution: waves become Agent dispatches

When executing, each wave maps to a set of concurrent Agent tool calls. Wave 1 launches
all its tasks simultaneously. When all Wave 1 agents complete, Wave 2 starts. This is
mechanical — the plan's structure directly dictates the execution pattern.

**Critical rule:** Never dispatch agents from different waves concurrently. The wave
boundaries exist because of real data dependencies — violating them causes merge
conflicts or compile errors from missing artifacts.

**Self-check during planning:** At every wave boundary, ask: "What is the maximum
number of tasks that could run right now?" If the answer is 1 and the plan has > 4
tasks total, the decomposition likely missed parallelism. Revisit Step 1.

**Detail:** TDD rules in `backend/src/CLAUDE.md`, debugging rules in `backend/src/CLAUDE.md`

### Task Progress Tracking

During the implementation phase, update `task_progress` in session-state so the status
line shows granular progress (e.g., "Tarea 3/5: Verify TypeScript").

**When entering implementation:** Count the tasks in the plan, initialize progress:
```bash
jq '.evidence.task_progress = {"current": 1, "total": N, "label": "first task name", "completed_labels": []}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**When starting each new task:** Advance `current`, move previous label to `completed_labels`:
```bash
jq '.evidence.task_progress.completed_labels += [.evidence.task_progress.label] | .evidence.task_progress.current = N | .evidence.task_progress.label = "next task name"' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**When all tasks complete:** Reset before transitioning to verification:
```bash
jq '.evidence.task_progress = {"current": 0, "total": 0, "label": null, "completed_labels": []}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

This produces status lines like:
```
📍 full | Implementation (4/8) | ✅🔄⬚⬚⬚ t2/5: Add toggle button (TDD, commit after each task)
```

### Message Progress Display

**Regla obligatoria:** Cada mensaje al usuario DEBE incluir indicador de progreso visible.
No basta con actualizar `session-state.json` — el progreso se comunica en el texto del mensaje.

**Principio clave: RESULTADO, no proceso.** Los mensajes comunican qué se completó y qué
sigue — nunca el proceso interno de pensamiento. El usuario no necesita saber "voy a leer
archivos" o "necesito entender X". Solo necesita saber qué se hizo y qué falta.

**PROHIBIDO:**
- Narrar intenciones: "Voy a hacer...", "Necesito ver...", "Primero tengo que..."
- Repetir lo obvio: "Tengo toda la info", "Ahora voy a implementar"
- Mensajes sin contenido útil: solo texto que describe lo que harás a continuación

**CORRECTO:** Cada mensaje incluye (1) qué se completó con dato concreto, (2) qué sigue.

**En cada transición de fase:**
```
✅ Consult (1/8) — 3 decision logs relevantes, 2 execution logs recientes
🔄 Brainstorm (2/8) — 8 páginas analizadas, 17 componentes mapeados
```

**En cada tarea durante implementación:**
```
📍 Tarea 1/3 — Derivar visibleRoutes
Resultado: 3 archivos modificados, TypeScript limpio

📍 Tarea 2/3 — Usar visibleRoutes en capas del mapa
Resultado: FleetMap renderiza 46 paradas de 3 rutas
```

**En verificación:**
```
🧪 Verificación — TypeScript: ✅ | Lint: ✅ | Tests: ✅ (602 tests, 0 nuevos fallos)
```

**Entre fases (cuando se lanzan herramientas/agentes):** Un solo mensaje corto con
lo completado + lo que sigue. NO narrar cada paso intermedio.
```
✅ 6 widgets implementados, registry actualizado (10/10)
🔄 Migrando 7 páginas al widget system (5 en paralelo)
```

**PROHIBIDO entre tool calls:** NO emitir texto entre herramientas consecutivas salvo que
haya un resultado concreto nuevo. Esto incluye:
- NO repetir el status line entre Grep/Read/Bash/ToolSearch — el hook ya lo inyecta
- NO emitir texto antes de ToolSearch (cargar herramientas es técnico, no visible)
- NO emitir texto entre ToolSearch y la herramienta que se carga (son un par atómico)
- NO narrar pasos intermedios: "voy a leer...", "ahora busco..."

**Solo emitir texto entre tool calls cuando:**
- Hay un **cambio de fase** real (root_cause → pattern_search → fix)
- Hay un **resultado concreto** que comunicar al usuario
- Se necesita **decisión del usuario** antes de continuar

**Regla de estado anticipado:** El hook `UserPromptSubmit` inyecta el status line
automáticamente entre tool calls. Por tanto, actualiza `session-state.json` **ANTES** de
ejecutar la acción que cambia la fase, no después. Ejemplos:
- Antes de crear un PR → actualizar `branch_strategy = "pr"` y `current_phase = "finalize"`
- Antes de hacer push → actualizar `tests_passed`, `lint_clean`
- Antes de marcar root cause → actualizar `root_cause_identified`

Así cuando el hook se dispare entre tools, mostrará el estado correcto y no uno stale.
Esto es especialmente importante en pares atómicos como ToolSearch → herramienta MCP,
donde el hook se dispara entre ambos y muestra el estado al usuario.

**Granularidad:** Actualiza el estado con la mayor frecuencia posible — no solo en cambios
de fase, sino antes de cada grupo de tool calls significativo. El usuario ve el status line
entre CADA herramienta, así que cada disparo es una oportunidad de comunicar qué se está
haciendo. Ejemplo durante debug:
- Antes de investigar → `current_phase = "root_cause"` con label de qué se investiga
- Antes de aplicar fix → actualizar `root_cause_identified = true`
- Antes de correr tests → actualizar a fase de verificación
- Antes de push → actualizar `tests_passed`, `lint_clean`

**Hook-driven header:** El `UserPromptSubmit` hook inyecta un `DISPLAY RULE` con el
formato exacto del header **en TODOS los flows** (micro, light, debug, explore, full).
**Copia el template del hook** al inicio de CADA respuesta sin excepción,
reemplazando `[...]` con datos concretos. Ejemplos por flow:
```
💬 El endpoint devuelve 404 porque falta la ruta en routing.yaml
📝 Light — Eliminados 2 imports no usados en RoutePlannerPage, TS limpio
🐛 Debug (fix) — TS6133 por import no usado, eliminado, build pasa
🔍 Explore — 8 controllers encontrados, 3 usan HubInterface directamente
✅✅🔄⬚⬚⬚⬚⬚ Planning (3/8) — Spec aprobado, 12 tareas en plan
```

**Formato:** Usar prefijos emoji (✅ completado, 🔄 en curso, ⬚ pendiente, ❌ fallo)
para que el estado sea visible de un vistazo. Idioma: español.
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Verification and closure -->
## After Writing Code: Verify and Close

### Evidence Before Claims

#### The cognitive trap this prevents

After writing code, the model has a confirmation bias — it "believes" the code works
because it wrote it with intent. This bias produces claims like "tests pass" based on
expectation rather than evidence. The model reasons: "I wrote correct code → therefore
tests pass" — but this syllogism skips the step where reality might disagree.

**The verification command is a circuit breaker against confirmation bias.** It forces
the model to consult reality (command output) instead of consulting its own confidence.

#### Why "fresh, not cached"

A previous test result may have been invalidated by subsequent changes. Reading old
output and claiming "tests pass" is not verification — it's memory. **Freshness is the
difference between evidence and recollection.** The command must run in the current
message, after the latest code changes, with full output read.

#### The gate function

1. **Identify** — What command proves this specific claim?
2. **Run** — Execute the full command (fresh, not cached, not partial)
3. **Read** — Complete output, check exit code, count failures
4. **Only then** — Make the claim

Skip any step = the claim is unverified. "Should work," "probably passes," and "seems
fine" are all synonyms for "I didn't check."

### Closing the Cycle

#### Why capture matters: the learning loop

Without capture, each session starts from zero. The model re-discovers the same lessons,
re-evaluates the same alternatives, re-encounters the same blockers. **Capture is what
makes iteration N+1 better than iteration N** — it converts ephemeral session knowledge
into persistent artifacts that brainstorming reads at the start of the next session.

The mechanism is specific: Step 0 of the brainstorming checklist ("Consult past decisions")
reads execution logs and decision logs. If those logs don't exist or are poorly structured,
Step 0 produces nothing and the brainstorming proceeds blind. **The quality of today's
capture determines the quality of tomorrow's brainstorming.**

```
brainstorm → spec → plan → implement → verify → capture
     ↑                                            │
     └────────── learning loop ───────────────────┘
```

#### Why one log per feature, not per session

When future brainstorming searches for "how did we solve something similar?", it needs
the log for *that specific feature* — not a session log mixing 3 unrelated features where
the relevant one is buried on page 4. **Granularity determines searchability.** One log
per feature means each is independently consultable with a single file read.

**Finish the branch** (Skill 12): verify tests → validate merge with base → present options
(merge/PR/keep/discard) → design retrospective → cleanup.

**Capture to execution log:** `docs/superpowers/execution-logs/YYYY-MM-DD-<feature>.md`
with data from each phase (alternatives considered, blockers hit, test results, lessons).

**Update decision log:** If design decisions were made, add entry to `docs/decisions/log.md`.
When the same lesson appears 3+ times across execution logs, it graduates to the relevant
knowledge module — that's the signal it's a pattern, not an incident.
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Subagent skill triggers -->
## Working with Subagents

**Subagent-Driven Development** (Skill 5) — Use when executing plans with independent tasks.
Fresh subagent per task + two-stage review (spec compliance, then code quality).
**Full instructions:** `AGENTS.md`

**Parallel Agents** (Skill 6) — Use when facing 2+ independent problems. One agent per
problem domain, working concurrently. Don't use when failures are related.
**Full instructions:** `AGENTS.md`

**Receiving Code Review** (Skill 10) — When receiving feedback: read completely → restate
requirement → verify against codebase → evaluate technically → respond or push back.
Never "Great point!" before verification. Never implement unclear feedback — ask first.
**Full instructions:** `AGENTS.md`

**Requesting Code Review** (Skill 11) — Mandatory after major features and before merge.
Get base/head SHAs, dispatch reviewer with context + plan.
**Full instructions:** `AGENTS.md`
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Skills invocation rule -->
## Skills: Check Before Every Action

If there's even a 1% chance a skill applies, invoke it. Process skills first
(brainstorming, debugging), implementation skills second.

| If you're about to... | Invoke |
|----------------------|--------|
| Build something new | Brainstorming (Skill 2) |
| Create implementation plan | Writing Plans (Skill 3) |
| Execute a plan | Executing Plans (Skill 4) — see `backend/src/CLAUDE.md` |
| Fix a bug | Systematic Debugging (Skill 8) — see `backend/src/CLAUDE.md` |
| Write code | TDD (Skill 7) — see `backend/src/CLAUDE.md` |
| Claim something works | Verification (Skill 9) |
| Finish a branch | Finishing Branch (Skill 12) |

**Complete skill reference:** `docs/knowledge/superpowers-skills.md`
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Commits -->
## Commits and Push

#### Why commit frequency equals risk management

Claude Code sessions are fragile: context compacts, tool_use errors corrupt conversations,
network failures kill sessions. **Every commit is a savepoint. Every push is a backup.**
The frequency of commits is inversely proportional to the amount of work lost on crash.

A commit after each completed task means a crash loses at most one task's work. A commit
only at the end of a feature means a crash loses everything. The choice isn't about git
hygiene — it's about recovery cost.

**Why push before launching subagents:** Subagents operate on the repository's state at
the time they're dispatched. If uncommitted changes exist, the subagent doesn't see them.
Worse, if the subagent modifies the same files, you get merge conflicts on return. Push
first = clean handoff.

### When to commit

Each commit marks a point where the codebase is internally consistent:
- After each file that works (compiles, doesn't break tests)
- After each completed task in a plan
- After writing a test (even if it fails — the test alone is a valid checkpoint)
- After making a test pass (commit the implementation that greened it)

### When to push
- After each commit (or max 2-3 if part of same logical step)
- **Always** before launching subagents (ensures clean handoff)
- **Always** run `make manifest` before final push (captures codebase changes)

### Commit format
Prefixes: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
Short, descriptive. One logical change per commit.

### Work artifacts go to the repo

Plans, specs, and execution logs are part of the codebase — not ephemeral session data.
They're read by future brainstorming sessions (Step 0 of the checklist) and must be
findable via file path, not buried in conversation history that compacts away.

- Plans → `docs/superpowers/plans/`
- Specs → `docs/superpowers/specs/`
- Execution logs → `docs/superpowers/execution-logs/`
- Never only in ephemeral paths (`/root/.claude/`, TodoWrite)
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Decision principles -->
## Decision Principles

### Scalability Over Convenience
The best solution is the one that scales best, regardless of how many changes it requires.
A solution touching 20 files that scales correctly is superior to a 3-line patch that doesn't.

### No Redundancy
Before executing any action: Is it necessary? Was it already done? Will the result differ
from current state? If not, don't execute.

### Context Hygiene
- Checkpoint after ~50 tool calls or when compaction is noticed
- Post-compaction: verify access to spec and plan paths before continuing
- Tasks > 8 steps: consider splitting into separate sessions
<!-- GENERIC-END -->

---

<!-- PROJECT-SPECIFIC-START -->
## Knowledge Modules

Before working on a subsystem, read the relevant module in `docs/knowledge/`:

| Working on... | Read |
|--------------|------|
| Entities, migrations | `domain-model.md` |
| Providers, factories | `provider-framework.md` |
| Controllers, APIs | `api-surface.md` |
| Deploy, Docker | `deployment.md` |
| Tests | `testing.md` |
| Mercure, SSE | `realtime.md` |
| GPS, Traccar | `gps-tracking.md` |
| Route optimization | `route-optimization.md` |
| DDD, architecture | `architecture-ddd.md` |
| Design patterns | `design-patterns.md` |
| Security, roles | `security.md` |
| UI, Twig, React | `ui-frontend.md` |
| Full index | `index.md` |

**Freshness:** < 14 days → trust directly. Older → spot-check before trusting.
After any task that changed a subsystem, update the relevant module.
<!-- PROJECT-SPECIFIC-END -->

---

<!-- GENERIC-START: Governance -->
## Governance

### This File's Hierarchy

Claude Code loads CLAUDE.md files hierarchically by directory. This project uses:

```
CLAUDE.md          ← Philosophy, workflow, cross-cutting rules (this file, always loaded)
AGENTS.md          ← Subagent instructions (loaded when dispatching agents)
backend/CLAUDE.md  ← Architecture, SOLID, DDD, conventions (loaded in backend/)
backend/src/CLAUDE.md  ← TDD, debugging, critical patterns (loaded in src/)
backend/tests/CLAUDE.md ← Testing conventions (loaded in tests/)
docs/CLAUDE.md     ← Documentation rules, knowledge modules (loaded in docs/)
.claude/README.md  ← Workflow engine technical reference (manual consultation)
```

### What Goes Where

- **Behavioral instructions** (must be present every time) → this file
- **Directory-specific rules** (only needed in that context) → subdirectory CLAUDE.md
- **Reference data** (consulted on demand) → `docs/knowledge/`
- **Subagent instructions** → `AGENTS.md`

### Decision Log

Non-trivial design decisions go to `docs/decisions/log.md`:
```markdown
### [YYYY-MM-DD] Brief context
- **Problem:** What needed solving
- **Decision:** What was chosen and why
- **Alternatives discarded:** What else was evaluated
- **Result:** (fill post-implementation) Did it work? What was learned?
```

When the same lesson appears 3+ times, update the relevant knowledge module.
<!-- GENERIC-END -->
