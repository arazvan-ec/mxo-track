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

Exploring the codebase with Grep/Glob to discover what's already documented is a
direct waste of context. `docs/codebase-manifest.md` is a pre-computed snapshot:
entity list, service map, route map, metrics. Regenerated with `make manifest` (~1 second).

**The flow:** Read manifest → answer directly (0 tool calls). If the data isn't there →
directed search (1-3 calls) → update manifest. This feeds everything else: brainstorming
uses it to inventory existing functionality, planning uses it for exact file paths,
verification regenerates it post-push.

Before exploring, check if the answer is already in `docs/codebase-manifest.md` or
the relevant knowledge module in `docs/knowledge/`. Explore only when these don't answer.

### Session-State: Memory That Survives Compaction

Claude Code compacts old messages when the context window fills up. When that happens,
the model loses track of which workflow phases it completed. `.claude/session-state.json`
is external memory — hooks read it to enforce phase progression, and it survives
compaction because it's a file, not a conversation message.

Update it with `jq` after each phase transition. The SessionStart hook resets it daily
but preserves a `last_work_summary` field with previous session context.

**Full schema and update patterns:** `.claude/README.md`
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Workflow classification and flow -->
## The Workflow: Every Interaction Has Structure

Every interaction follows a structured flow. The depth scales with the type, but the
structure is always present. **Why:** Unstructured work skips verification, loses context,
and accumulates technical debt silently.

### Classify First (before any response)

| Type | Signal | Flow |
|------|--------|------|
| **Informational** | "what does X do?", "explain Y" | Micro — consult docs → answer → capture gaps |
| **Documentation** | Edit docs, knowledge modules | Light — check overlap → propose → execute → verify |
| **Bug fix** | Error, test failure, unexpected behavior | Debug — consult → root cause → pattern-wide → TDD fix |
| **Code change** | New feature, refactor, enhancement | Full — consult → brainstorm → plan → implement → verify → capture → retrospective → finalize |
| **Exploration** | "audit X", "how does Z work?" | Explore — consult manifest → explore → answer → capture findings |

After classifying, update session-state: `jq '.flow_type = "<type>"' .claude/session-state.json`

**Phase transitions:** Use `.claude/hooks/phase-advance.sh <next_phase>` to advance phases.
Direct writes to `phase_history` via `jq` are detected and reverted. The script enforces
legal sequence (no skips, no backwards) and adds timestamps automatically.

### Deviation for Wiring-Only Changes

Some code changes are pure wiring — connecting an existing callback, passing a prop,
adding an import. These don't involve design decisions and the full brainstorm+plan
overhead is counterproductive. Use deviation mode when ALL criteria are met:

- **< 30 lines** changed across all files
- **0 design decisions** — no new abstractions, no new patterns, no trade-offs
- **No new entities, migrations, or API endpoints**
- **Pattern already exists** in the codebase (copying an established approach)

**How:** Activate deviation, skip brainstorm+plan, go straight to implement:
```bash
jq '.deviation = {"active": true, "reason": "wiring-only (<30 lines, 0 design decisions)", "skipped_phases": ["brainstorm", "plan"], "return_to_phase": null, "acknowledged_by_user": true}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Still mandatory:** consult, implement, verify, capture, retrospective, finalize.
The retrospective is especially important for wiring changes — it's where you catch
patterns that indicate a systemic issue (e.g., "this is the 3rd page missing onStopClick").

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

When the user reports a fix didn't work ("sigue sin funcionar", "no mejoró", "same problem",
"still broken", etc.), this is a **new debug interaction** — not a continuation of the previous
fix phase. Reset immediately:

```bash
jq '.interaction_id = (.interaction_id + 1) | .evidence.root_cause_identified = false | .evidence.pattern_wide_search_done = false | .evidence.tests_passed = null | .current_phase = "root_cause"' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

The previous root cause was wrong. Start fresh: re-examine, don't assume the previous analysis
was correct.

### Workflow Engine (summary)

The hooks mechanically enforce the flow. Without them, phases get skipped.

| Flow | Can edit `src/`, `tests/` | Gate |
|------|--------------------------|------|
| micro/light/explore | DENY (must reclassify) | — |
| debug | HARD: needs root_cause + pattern-wide | — |
| full | HARD: needs consult + brainstorm + plan | — |

**Full reference:** `.claude/README.md` (gates, validators, deviation mode, harness assumptions)
<!-- GENERIC-END -->

---

<!-- GENERIC-START: The QA loop -->
## Before Writing Code: The QA Loop

### Why Brainstorming Is Not Bureaucracy

Brainstorming is preventive QA — it's cheaper to discover a design flaw in conversation
than to debug it in code. Every code change goes through this, no matter how "simple."

**The checklist:**
0. **Consult past decisions** — Read `docs/decisions/log.md` and recent execution logs. Declare what you found.
1. **Classify bounded context** — Is this critical (DDD pure) or pragmatic (Symfony)? Declare it.
2. **Explore project context** — Check files, docs, recent commits
3. **Inventory existing functionality** — Enumerate what exists in the affected area. Every element gets an explicit decision: Include / Omit / Transform. No silent omissions.
4. **Ask clarifying questions** — One at a time. Multiple choice when possible.
5. **Propose 2-3 approaches** — With trade-offs and recommendation
6. **Present design, get approval** — Section by section
7. **Write spec** — Save to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
8. **Transition to planning**

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

**Parallel-first planning (principio de diseño):** Every plan MUST maximize parallelism.
The planner's job is not just to list tasks — it's to decompose work into the smallest
independent units and identify which can execute concurrently. This is a design principle,
not an optimization: parallel decomposition reveals hidden dependencies, forces clearer
interfaces between tasks, and reduces total execution time.

**How to apply:**
1. **Decompose first, sequence second.** Start by listing all atomic tasks. Then identify
   dependencies between them. Everything without a dependency runs in parallel.
2. **Default is parallel.** A task is sequential only if it REQUIRES output from a prior
   task. "It's easier to do sequentially" is not a valid reason.
3. **Group explicitly.** Independent tasks go in `[parallel]` blocks. Tasks that depend
   on prior results are marked with their dependency.
4. **Maximize the parallel frontier.** At every point in the plan, ask: "what is the
   maximum number of tasks that could run right now?" If the answer is 1, look for ways
   to decompose further.

```markdown
### [parallel] Tarea 1a + 1b + 1c
- **1a:** Backend — add field to snapshot (backend/src/...)
- **1b:** Frontend — extend TypeScript type (frontend/src/...)
- **1c:** Tests — add fixture data for new field (backend/tests/...)

### Tarea 2 (depends on 1a + 1b)
- Frontend — use new fields in component

### [parallel] Tarea 3a + 3b (depends on 2)
- **3a:** Backend — add validation rule
- **3b:** Frontend — add error display component
```

When executing, use the Agent tool to launch parallel tasks concurrently. Each parallel
group runs its tasks simultaneously; the next group starts only when all dependencies
from the previous group are met.

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

**Why:** "Should work" is not evidence. "Tests pass" without running them is a lie.

Before claiming anything is done:
1. **Identify** what command proves the claim
2. **Run** the full command (fresh, not cached)
3. **Read** complete output, check exit code
4. **Only then** make the claim

### Closing the Cycle

**Finish the branch** (Skill 12): verify tests → validate merge with base → present options
(merge/PR/keep/discard) → design retrospective → cleanup.

**Capture to execution log:** `docs/superpowers/execution-logs/YYYY-MM-DD-<feature>.md`
with data from each phase (alternatives, blockers, test results, lessons).
**One log per feature/interaction, not per session.** If a session implements 3 features,
create 3 separate files so each is independently consultable in future brainstorming.

**Update decision log:** If design decisions were made, add entry to `docs/decisions/log.md`.

**The learning loop:** Execution logs and decision logs are read at the START of the next
brainstorming session. This is what makes each iteration better than the last:
```
brainstorm → spec → plan → implement → verify → capture
     ↑                                            │
     └────────── learning loop ───────────────────┘
```
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

**Why push frequently:** Claude Code sessions can crash, context compacts, tool_use errors
can corrupt the conversation. Every commit is a checkpoint. Every push is insurance.

### When to commit
- After each file that works (compiles, doesn't break tests)
- After each completed task in a plan
- After writing a test (even if it fails — commit test alone)
- After making a test pass (commit implementation)

### When to push
- After each commit (or max 2-3 if part of same logical step)
- **Always** before launching subagents
- **Always** run `make manifest` before final push

### Commit format
Prefixes: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
Short, descriptive. One logical change per commit.

### Work artifacts go to the repo
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
