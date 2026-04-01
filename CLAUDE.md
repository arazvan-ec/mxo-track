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

**Parallel execution by default:** Plans MUST identify which tasks can run in parallel
and group them explicitly. Independent tasks (e.g., backend change + frontend type change,
or changes to unrelated files/subsystems) should be grouped in a `[parallel]` block.
Tasks that depend on prior results remain sequential. When executing, use the Agent tool
to launch parallel tasks concurrently whenever possible.

```markdown
### [parallel] Tarea 1a + 1b
- **1a:** Backend — add field to snapshot (backend/src/...)
- **1b:** Frontend — extend TypeScript type (frontend/src/...)

### Tarea 2 (depends on 1a + 1b)
- Frontend — use new fields in component
```

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
