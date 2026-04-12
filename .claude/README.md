# Workflow Engine — Technical Reference

This document contains detailed reference for the workflow engine mechanics.
It is NOT loaded automatically by Claude Code. Consult when you need detail
beyond what's in the root CLAUDE.md.

---

## session-state.json Schema

The session state file is the single source of truth for where Claude is in the
workflow. Hooks read it to decide whether to block or allow file edits. Without
it, gates cannot enforce anything.

```jsonc
{
  "flow_type": "micro|light|debug|full|explore|null",  // Declarar al clasificar interacción
  "current_phase": "consult|brainstorming|planning|implementation|verification|capture|retrospective|finalize|null",
  "interaction_id": 0,              // Incrementar al detectar scope change (nueva interacción)
  "last_work_summary": {            // Preservado automáticamente por session-start.sh al resetear por nuevo día
    "previous_date": "YYYY-MM-DD",
    "previous_branch": "...",
    "previous_flow": "full|debug|...",
    "previous_phase": "...",
    "recent_commits": ["..."],      // Últimos 10 commits al momento del reset
    "merged_branches": ["..."],     // Branches claude/* mergeadas a main
    "last_execution_log": {
      "file": "...",
      "preview": "..."
    }
  },
  "evidence": {
    "interaction_id": 0,            // Debe coincidir con interaction_id top-level
    "decisions_read": false,        // true tras leer docs/decisions/log.md
    "logs_scanned": false,          // true tras escanear execution-logs/
    "user_turns": 0,                // +1 por cada respuesta del usuario en brainstorm
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,              // ruta al spec guardado
    "plan_path": null,              // ruta al plan guardado
    "tests_written": 0,
    "tests_passed": null,           // true|false|"skipped"|null — "skipped" when no test infra
    "lint_clean": null,             // true|false|"skipped"|null — "skipped" when no lint tooling
    "execution_log_path": null,
    "branch_strategy": null,        // merge|pr|keep|discard
    "root_cause_identified": false, // (debug-flow) true tras Skill 8 Phase 1
    "pattern_wide_search_done": false, // (debug-flow) true tras Skill 8 Phase 2.5
    "task_progress": {              // Progreso de tareas dentro de implementation/fix
      "current": 0,                 // Índice de tarea actual (1-based, 0 = no iniciado)
      "total": 0,                   // Total de tareas del plan
      "label": null,                // Descripción corta de la tarea actual
      "completed_labels": []        // Lista de labels de tareas completadas
    },
    "work_context": {               // Contexto jerárquico de trabajo — mostrado en status line
      "description": null,          // Qué se está haciendo (todos los flujos). Truncado a ~40 chars
      "problems": {                 // (debug) Tracking de problemas independientes
        "total": 0,                 // Total de problemas diagnosticados
        "current": 0,               // Problema actual (1-based)
        "labels": []                // Descripción corta de cada problema
      },
      "wave": {                     // (full impl) Wave actual del plan
        "total": 0,                 // Total de waves
        "current": 0,               // Wave actual (1-based)
        "label": null               // Descripción de la wave actual
      }
    }
  },
  "deviation": {
    "active": false,
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
```

---

## How to Update session-state.json

State updates use `jq` for atomic writes. **Phase transitions MUST use
`phase-advance.sh`** — direct writes to `phase_history` are detected and
reverted by `phase-transition-controller.sh`.

### Phase transitions (MANDATORY: use phase-advance.sh)
```bash
.claude/hooks/phase-advance.sh consult
.claude/hooks/phase-advance.sh brainstorming
# etc. — enforces legal sequence, adds timestamps automatically
```

**DO NOT** write `phase_history` directly via `jq`. The phase-transition-controller
will detect and revert it.

### phase_history format (new — timestamped objects)
```json
"phase_history": [
  {"phase": "consult", "at": "2026-04-07T10:20:00Z"},
  {"phase": "brainstorming", "at": "2026-04-07T10:25:00Z"}
]
```

### user_approved

#### Why only the hook can set this

`user_approved` represents a real human decision, not a model belief. If the model
could set it directly, it would rationalize approval ("the user will approve this,
let me skip ahead"). The `phase-transition-controller.sh` detects any `jq` command
containing `user_approved = true` and reverts it — this is the enforcement mechanism.

#### How approval detection works

The `UserPromptSubmit` hook (`user-prompt-state.sh`) is the ONLY sanctioned path:

```
User types message → hook receives .prompt → strip <system-reminder> tags
    → lowercase → match approval regex → set user_approved = true
    → match rejection regex → set user_approved = false
```

**Why strip system-reminder tags:** The `.prompt` field from the hook input may
contain injected `<system-reminder>` blocks with text like "no existe spec document".
Without stripping, the rejection regex `(no[, ]|...)` matches "no existe" and reverts
a legitimate approval. The `sed '/<system-reminder>/,/<\/system-reminder>/d'` filter
isolates the actual user text before pattern matching.

**Why rejection runs after approval:** The regex check order matters. If both patterns
match (unlikely but possible with complex messages), rejection wins — this is
deliberately conservative. A false negative (missing approval) costs the user one
extra confirmation message. A false positive (approving when the user rejected) causes
implementation of the wrong design.

**DO NOT** set `user_approved = true` directly via `jq`. Direct writes are detected
by `phase-transition-controller.sh` and reverted silently.

**Single evidence field update:**
```bash
jq '.evidence.decisions_read = true' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Scope change (new interaction):**
```bash
jq '.interaction_id += 1' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Task progress — initialize when entering implementation (read total from plan):**
```bash
jq '.evidence.task_progress = {"current": 1, "total": 5, "label": "Add showArrows prop", "completed_labels": []}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Task progress — advance to next task:**
```bash
jq '.evidence.task_progress.completed_labels += [.evidence.task_progress.label] | .evidence.task_progress.current = 3 | .evidence.task_progress.label = "Verify TypeScript"' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Task progress — reset (when leaving implementation or starting new interaction):**
```bash
jq '.evidence.task_progress = {"current": 0, "total": 0, "label": null, "completed_labels": []}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Work context — set description when classifying interaction:**
```bash
jq '.evidence.work_context.description = "Status line con work_context"' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Work context — set problems (debug multi-problema):**
```bash
jq '.evidence.work_context.problems = {"total": 2, "current": 1, "labels": ["phase-advance.sh", "Tests pre-existentes"]}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Work context — set wave (full implementation):**
```bash
jq '.evidence.work_context.wave = {"total": 3, "current": 1, "label": "Schema + initial state"}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

**Work context — reset:**
```bash
jq '.evidence.work_context = {"description": null, "problems": {"total": 0, "current": 0, "labels": []}, "wave": {"total": 0, "current": 0, "label": null}}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

---

## Gates by Flow and File Type

Gates exist because the engine cannot trust that Claude will follow the process
voluntarily — every HARD gate encodes a validated assumption about a known
failure mode. See "Harness Assumptions" below.

### What Each Flow May Edit

| Flow | code (`src/`, `templates/`, `config/`, `migrations/`, `assets/`, `docker/`, `scripts/`, `ml-service/`, `openspec/`), `tests/` | `specs/`, `plans/` | `docs/`, config | otros |
|------|-------------------|--------------------|-----------------|-------|
| **micro** | DENY (reclasificar) | DENY (reclasificar) | pass | pass |
| **light** | DENY (reclasificar) | DENY (reclasificar) | pass | pass |
| **explore** | DENY (reclasificar) | DENY (reclasificar) | pass | pass |
| **debug** | HARD (debug-validator) | pass | pass | pass |
| **full** | HARD (full validators) | HARD (phase validators) | SOFT | SOFT |

**HARD** = bloquea la edición (exit 2). **SOFT** = warning, permite continuar (exit 1). **DENY** = bloquea y pide reclasificar.

### Full-Flow Gates by File

#### Why specs and plans have lighter gates than code

Specs and plans are *products of* brainstorming and planning — they're created
during those phases. Running the brainstorm-validator when writing a spec creates
a circular dependency: the validator requires the spec to exist, but you're trying
to create it. The same applies to plans.

The correct enforcement point is `phase-advance.sh`, which runs the validator when
*leaving* a phase. This means:
- Writing a spec during brainstorming → only needs `consult` completed
- Writing a plan during planning → only needs `brainstorming` completed
- Advancing from brainstorming to planning → runs brainstorm-validator (checks spec
  exists, user approved, alternatives proposed)
- Advancing from planning to implementation → runs planning-validator (checks plan
  exists with tasks)

This separation follows a principle: **gates on file writes verify prerequisites;
gates on phase transitions verify phase completion.**

| Para editar... | Validators en Write | Gate | Verified on phase-advance |
|----------------|---------------------|------|---------------------------|
| `docs/superpowers/specs/*` | `consult` | HARD | brainstorm-validator (spec exists, approved) |
| `docs/superpowers/plans/*` | `brainstorm` | HARD | planning-validator (plan exists, has tasks) |
| code (`src/*`, `templates/*`, `config/*`, `migrations/*`, `assets/*`, `docker/*`, `scripts/*`, `ml-service/*`, `openspec/*`), `tests/*` | `brainstorm` + `planning` + `spec-compliance` + `implementation` | HARD | — |
| `docs/superpowers/execution-logs/*` | `capture` | SOFT | — |
| `docs/decisions/*` | `retrospective` | SOFT | — |

### Debug-Flow Gates (for all code paths and `tests/*`)

| Step | Requires | Gate |
|------|----------|------|
| Paso 1: Consultar | `decisions_read` OR `logs_scanned` | HARD |
| Paso 2: Root Cause (Skill 8 Phase 1) | `root_cause_identified = true` | HARD |
| Paso 3: Pattern-Wide (Skill 8 Phase 2.5) | `pattern_wide_search_done = true` | HARD |

### Excluded Files (never validated)

`.claude/session-state.json`, `.claude/hooks/*`, `.claude/settings*`,
`node_modules/`, `vendor/`, `.git/`

---

## Validators — Evidence Required per Phase

#### Why evidence, not trust

Each validator encodes the minimum evidence that proves a phase was done honestly,
not just declared. Evidence fields are set by Claude; hooks verify them mechanically.
Without this, the model declares "brainstorming done" without having proposed
alternatives — the validator catches this because `alternatives_proposed` is still
`false`.

### Two invocation contexts — and why they exist

Validators serve two roles that must not be confused:

1. **Phase transitions** (`phase-advance.sh`): `validator.sh $STATE_FILE`
   → Answers: "Is this phase COMPLETE? Can I leave it?"
   Called when advancing between phases. Uses autodiscovery: looks for
   `validators/${phase}-validator.sh` (with `${phase%ing}` fallback for "brainstorming").
   To add a new validator, create `validators/{phase}-validator.sh` — no registration needed.

2. **File edit gates** (`workflow-engine.sh`): `validator.sh $STATE_FILE $FILE_PATH`
   → Answers: "Are the PREREQUISITES for writing this file met?"
   Called as PreToolUse hook when the model tries to edit a file. The `$FILE_PATH` is used
   by `implementation-validator.sh` (TDD check) and `debug-validator.sh` (code gate).
   Validators must handle `$2` being empty (transition context) or a path (edit context).

**Critical design rule:** A phase's own validator should NOT run as a file gate for
artifacts produced DURING that phase. The brainstorm-validator checks "is brainstorming
complete?" — running it when writing a spec (which is the brainstorming output) creates
a circular dependency. File gates check prerequisites (prior phases); phase-advance
checks completion (current phase).

| Fase | Evidencia requerida | Nivel |
|------|---------------------|-------|
| `consult` | `decisions_read` OR `logs_scanned` | HARD |
| `brainstorming` | `user_turns ≥ 1` (HARD) + SOFT warn if `< 3` + `alternatives_proposed` + `user_approved` + `spec_path` (archivo ≥500B) | MIXED |
| `planning` | `plan_path` (archivo ≥300B con keywords) | HARD |
| `implementation` | plan exists (HARD) + `tests_written > 0` (SOFT warning) | MIXED |
| `verification` | `tests_passed` = `true` or `skipped` + `lint_clean` = `true` or `skipped` | MIXED |
| `capture` | `execution_log_path` exists | SOFT |
| `retrospective` | `execution_log_path` exists + `## Lessons`/`## Retrospectiva` section ≥100 chars | HARD |
| `finalize` | `branch_strategy` declared (`merge\|pr\|keep\|discard`) + knowledge module check | SOFT |

### Verification: the "skipped" state

#### Why accept incomplete verification

Some environments lack test infrastructure (no `composer install`, no `node_modules`).
Forcing `tests_passed = true` when tests cannot run teaches the model to lie about
evidence — the opposite of what the evidence system exists to do. The `"skipped"` value
is an honest declaration: "I could not verify this, and I know it."

#### How it flows

```
tests available → run tests → tests_passed = true|false
tests unavailable → tests_passed = "skipped" → SOFT warning on verification + pre-push
                                              → reviewer knows to verify before merge
```

**`"skipped"` propagates through the gate chain:** verification-validator accepts it
(exit 1 = soft warning), pre-push-gate accepts it (⚠ instead of ✅), but the warning
travels all the way to the PR reviewer. The gap in verification is never hidden —
it's escalated to the human who can actually close it.

**Rule:** Never set `tests_passed = true` when tests did not actually run. Use
`"skipped"` and let the warning propagate.
| `debug-code` | `decisions_read` OR `logs_scanned` + `root_cause_identified` + `pattern_wide_search_done` | HARD |

---

## Deviation Mode

Deviation mode exists for genuine emergencies (hotfixes, production outages) where
waiting for the full flow would cause more harm than the risk of skipping it.
It requires explicit user acknowledgment — it cannot be self-activated.

**Activate:**
```bash
jq '.deviation.active = true
  | .deviation.reason = "hotfix: production down, needs immediate fix"
  | .deviation.skipped_phases = ["brainstorming","planning"]
  | .deviation.acknowledged_by_user = true' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

When `deviation.active = true`, the engine shows warnings but does not block.
**Requires explicit user confirmation before activating.**

---

## Harness Assumptions & Evolution

Every mechanism in the harness encodes an assumption about a model limitation.
These assumptions should be re-tested with each model upgrade — what was a real
failure mode in one model may be solved in the next, and keeping unnecessary HARD
gates adds friction without value.

### Assumption Inventory

| Componente | Asunción | Nivel | Última validación |
|---|---|---|---|
| Workflow engine HARD gates | Claude se salta fases sin enforcement mecánico | HARD | 2026-03-24 (baseline) |
| Anti-rationalization tables | Claude inventa excusas para saltarse pasos | Docs | 2026-03-24 (consolidated) |
| Brainstorm `user_turns ≥ 1` + SOFT `< 3` | Claude puede no conversar suficiente | SOFT | 2026-03-24 (relaxed from HARD ≥ 3) |
| `session-state.json` evidencia granular | Estado externo necesario cross-session | HARD | 2026-03-24 (validated: necessary) |
| Subagent output limits (300 líneas) | Subagentes producen output excesivo | Docs | 2026-03-24 (pending stress-test) |
| Pre-Exploration Gate | Claude explora redundantemente sin manifest | Docs | 2026-03-24 (validated: saves tool calls) |
| Scope Change Detection | Claude mezcla tareas sin detectar scope change | SOFT | 2026-03-24 (relaxed from HARD) |
| Atomic commits | Se pierde trabajo en sesiones largas | Docs | 2026-03-24 (validated: safety reason) |

### Enforcement Levels

- **HARD** — Blocks the action (exit 2). For validated necessary assumptions.
- **SOFT** — Warning but allows continuation (exit 1). For assumptions in transition.
- **Docs** — Documented best practice, no mechanical enforcement.
- **Removed** — Obsolete assumption, mechanism deleted.

### Evolution Model

```
HARD → (stress-test: 5 tasks, ≥90% compliance) → SOFT → (10 tasks, ≥95%) → Docs → Remove
```

### Review Schedule

- **Trigger:** Each base model change (e.g., Sonnet 4.6 → 5.0)
- **Process:** 5 real tasks with gate relaxed one level, measure compliance
- **Record:** Update "Última validación" column with date and result

---

## Automatic Session Context

Session continuity is a mechanical guarantee, not something that depends on Claude
remembering to check. The `session-start.sh` hook fires automatically and provides
context before Claude ever sees the first user message.

### What the Hook Provides (no action required)

At session start or resume, `session-start.sh` prints to stdout:
- Current branch, date, resume/new-day status
- Previous session info (date, flow, phase) from `last_work_summary`
- Last 10 commits
- `claude/*` branches merged to main
- Preview of last execution log (first 6 lines)

The `last_work_summary` field in `session-state.json` survives daily resets and
contains: `previous_date`, `previous_branch`, `previous_flow`, `previous_phase`,
`recent_commits`, `merged_branches`, `last_execution_log`.

**Claude MUST read this output before responding to the first user message.**

### Manual Fallback (only when hook output is insufficient)

| Cuándo | Qué consultar |
|--------|---------------|
| Before any code change (already in full-flow) | `docs/decisions/log.md` |
| Don't know current branch | `git branch -v` |
| Task touches a specific subsystem | Relevant knowledge module |

---

## Automatic Status Line

Every response must start with the workflow status line because it creates a
consistent signal that both Claude and the user can use to track where the
conversation is in the workflow. Omitting it breaks the feedback loop.

### Rules

1. **Read** `.claude/workflow-status-line.txt` before composing each response
2. **Display** its content as the FIRST line, verbatim
3. **Two display levels:**
   - **Full** (phase just changed): show the complete line as-is
   - **Compact** (same phase as last response): show only `📍 {flow} | {Phase} ({index}/{total})`
4. **Never skip** — applies to short answers, clarifying questions, error messages
5. **File missing or empty:** show `📍 status unavailable`
6. **No flow declared:** show `📍 no flow declared`

### Format Examples

Full (phase just changed):
```
📍 full | Brainstorming (2/8) | ✅ consult → 🔄 brainstorm | Pendiente: planning, implementation, verification, capture, retrospective, finalize
```

Compact (same phase as previous response):
```
📍 full | Brainstorming (2/8)
```

Other flows:
```
📍 micro | Responder
📍 light | Documentar
📍 explore | Investigar
📍 debug | Root_cause (2/4) | ✅ consult → 🔄 root_cause | Pendiente: pattern_search, fix
📍 no flow declared
```

### Anti-patterns

- Showing status only sometimes → MUST be every response
- Generating the status from memory → MUST read the file
- Putting status after text → MUST be the FIRST line

---

## Known Tooling Issues

These are client-side bugs in the Claude Code / Agent SDK, not in the workflow or
codebase. They surface as cryptic API errors. The mitigation is the same for all
three: atomic commits + TodoWrite + short task slices.

### Issue 1: Subagent Infrastructure Failures

**Symptom:** Subagent reports it cannot execute any tool. JavaScript internal errors
like `undefined is not an object (evaluating 'H.includes')`. All tools fail.

**Root cause:** The subagent's execution environment is broken. Retrying the same
subagent does not help.

**Resolution:**
1. Do NOT retry the same subagent
2. Execute the task in the main thread, OR
3. Dispatch a fresh subagent (new environment)
4. If it persists: tell the user and suggest restarting Claude Code

**Rule:** Never mark a task complete when its subagent failed with infrastructure errors.

---

### Issue 2: `tool_use ids must be unique` (HTTP 400)

**Symptom:** API rejects with `messages.N.content.M: tool_use ids must be unique`.
Conversation cuts off abruptly. Tools stop working.

**Root cause:** Client-side bug — conversation history contains duplicate `tool_use`
block IDs. Common in long sessions with many parallel tool calls, or resumed sessions
with corrupted history.

**Mitigation (reduce risk):**
1. Commit after every completed task — progress survives session corruption
2. Keep TodoWrite updated — resume point is always clear
3. Prefer atomic tasks — less work lost if session breaks mid-step
4. Limit parallel tool calls — >20 sequential tool calls in one task increases risk

**Recovery (when it happens):**
1. `/clear` — resets conversation history, may allow continuing
2. Start new session — `claude` without `--resume`
3. `claude --resume <id>` — try if error was isolated; avoid if history is corrupt
4. `git log` — verify what was committed before the error
5. Check TodoWrite — identify which tasks are done and which remain

**Rule:** Never assume prior work was saved. Check `git log` and `git status` first.

---

### Issue 3: `assistant message prefill` (HTTP 400)

**Symptom:** API rejects with `This model does not support assistant message prefill`.
Session interrupts abruptly. Identical behavior to Issue 2.

**Root cause:** Client constructs the API request with an assistant message as the
last message — a client-side bug. Triggered by long sessions where context compression
corrupts message structure, or malformed resumed sessions.

**Mitigation and recovery:** Identical to Issue 2 above. The best protection remains:
**atomic commits + short task slices + TodoWrite updated**.
