---
type: design-spec
date: 2026-04-22
topic: workflow-enforcement-gates
interaction: planificar-mejoras-retro
status: approved
tags: [workflow, enforcement, hooks, status-line, multi-problem, todowrite-mirror, phase-advance, classification]
---

# Design Spec — Workflow Enforcement Gates (Option 3-Enforced)

## Problema

User observation: the model takes "shortcuts" in the 8-phase workflow despite
CLAUDE.md instructions. Concrete shortcuts catalogued during brainstorm:

1. **Sub-classifying** — labeling framework/hook changes as `light` to skip brainstorm
2. **Stale session-state** — updating `session-state.json` after acting, so the
   hook's status line lags reality
3. **Skipping brainstorm for "obvious" changes** — workflow-engine only partially
   guards `.claude/hooks/` edits
4. **Implicit advance** — forgetting to advance `problems.current`,
   `task_progress.current`, or phase transitions
5. **Verification without evidence** — "tests should pass" instead of running
   the exact deploy command
6. **Invisible retrospectives** — writing to execution log without presenting
   the reflection to the user first
7. **Defensive min-scope recommendation** — recommending `Opción 1` by reflex,
   not analysis
8. **Multiple concurrent `in_progress` todos** — TodoWrite discipline drift

Mechanisms for multi-problem tracking (`work_context.problems` +
`todowrite-mirror.sh`) exist and render correctly in **full flow only**
(`user-prompt-state.sh:315-322`, `:384-386`). This spec extends the render to
all flows AND adds enforcement gates that block (not warn) the shortcuts above.

## Alternativas consideradas (trade-offs)

**Opción 1 — Minimalista (~15 líneas):** sólo render en light/micro/explore/debug.
- Ventaja: mínimo coste, resuelve los gaps visibles (A-D del catálogo de render).
- Desventaja: no ataca los atajos de disciplina (sub-clasificación, estado stale,
  avance implícito, verificación sin evidencia, retrospective invisible).

**Opción 2 — Render unificado + helper `problems.sh` (~40 líneas):** refactor DRY
+ subcomandos CLI para gestión de `problems`.
- Ventaja: reduce fricción de `jq` crudo.
- Desventaja: premature abstraction — el modelo hace `jq` 1-2 veces por interacción.

**Opción 3 — Enforcement completo (~200 líneas):** 4 capas de gates (clasificación,
exit-conditions por fase, derivación + single in_progress, freshness warning).
- Ventaja: **cierra los 8 atajos del catálogo**, no sólo los visibles.
- Desventaja: mayor superficie; riesgo de falsos positivos mitigado con env vars
  de bypass documentados.

**Approach elegido: Opción 3.** Razón: el usuario identificó correctamente que las
mejoras cosméticas (Opción 1) no evitan la clase de fallo subyacente — el modelo
toma atajos cognitivos que la disciplina documental no corrige. Los gates
exit-2 convierten "instrucción en CLAUDE.md" en "contrato ejecutable".

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `work_context.problems.{total,current,labels}` schema | Include | Reuse; no schema change needed |
| `user-prompt-state.sh` problem prefix (full only) | Transform | Extend render to micro/light/explore/debug |
| `user-prompt-state.sh` todo line (phases planning→retro only) | Transform | Extend to consult/brainstorming + debug + micro/light/explore |
| `todowrite-mirror.sh` → `evidence.todo_progress` | Transform | Add: derive `problems.current` from `[prefix]`; reject >1 `in_progress` |
| `phase-advance.sh` legal-sequence enforcement | Transform | Add exit-condition gates per transition |
| `workflow-engine.sh` PreToolUse gates for business paths | Include | Keep; adds orthogonal classification gate |
| `validators/verification-validator.sh` | Include | Keep; already covers tests/lint |
| `pattern-audit.sh` at retrospective→finalize | Include | Keep |
| `retrospective_shown` evidence flag | **Create** | New flag; required by exit gate retrospective→finalize |
| `SKIP_CLASSIFY_GATE` / `SKIP_PHASE_EXIT_GATE` env vars | **Create** | Documented bypass for false positives |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Shortcut #7 (min-scope bias) enforcement | Omit | Conversational bias, not a technical hole; reinforced by user push-back |
| Auto-classify from prompt verbs ("implementa", "refactor") | Omit | Lexical detection too fragile; misses intention |
| Auto-classify by estimated line count | Omit | Chicken-and-egg — size unknown pre-classification |
| Freshness check as hard block | Transform → warning only | Hard block would cause false positives on legitimate batched writes |
| Helper script `problems.sh set/advance/show` | Omit | Model does `jq` once per interaction; premature DRY |
| Machine-readable phase transition log | Omit | `phase_history` already records; adding structured export is scope creep |

## Design — 4 Enforcement Layers

### Layer A — Classification Gate

**New file:** `.claude/hooks/validators/classify-validator.sh`
**Wiring:** PreToolUse for `Edit` and `Write` in `settings.json`

Logic:
```
tool_input.file_path matches framework path pattern
  (^.claude/|^scripts/|^backend/src/|^backend/templates/|^backend/config/|
   ^backend/migrations/|^frontend/src/|^ml-service/)
AND interaction_classification ∈ {micro, light, explore, informational, null}
→ exit 2, stderr: "Framework/code change requires full or debug classification.
  Current: <class>. Reclassify via: jq '.interaction_classification = \"full\"'"
```

Bypass: `SKIP_CLASSIFY_GATE=1`
Carve-outs: `docs/`, `*.md`, `.claude/session-state.json` (ignored), `/tmp/`

### Layer B — Phase Exit Gates

**Modified file:** `.claude/hooks/phase-advance.sh`
Adds exit-condition check before writing to `phase_history`:

| From → To | Required evidence |
|-----------|-------------------|
| `consult → brainstorming` | `decisions_read=true && logs_scanned=true` |
| `brainstorming → planning` | `alternatives_proposed=true && user_approved=true && spec_path != null` |
| `planning → implementation` | `plan_path != null` (already partial; formalize) |
| `implementation → verification` | `task_progress.total == 0` OR all plan tasks done (`completed_labels.length >= total`) |
| `verification → capture` | `tests_passed=true && lint_clean=true` |
| `capture → retrospective` | `execution_log_path != null` |
| `retrospective → finalize` | `retrospective_shown=true` |

On failure: exit 2 with list of missing fields and the exact `jq` snippet to set them.
Bypass: `SKIP_PHASE_EXIT_GATE=1`

### Layer C — Derivation + Single `in_progress`

**Modified file:** `.claude/hooks/todowrite-mirror.sh`

**C.1 — Derive `problems.current`:**
```
IP_PREFIX ← regex ^\[([^]]+)\] from in_progress label
for i, label in problems.labels:
  if IP_PREFIX substring-match label (case-insensitive):
    problems.current ← i + 1
    break
```

**C.2 — Reject multiple `in_progress`:**
```
n ← count of todos with status == "in_progress"
if n > 1:
  exit 2, stderr: "TodoWrite has N in_progress items. Exactly 1 allowed."
```

Also applies on first call with 2+ in_progress.

### Layer D — Freshness Warning (non-blocking)

**New file:** `.claude/hooks/pre-tool-freshness.sh`
**Wiring:** PreToolUse (all tools)

Emits `⚠ POSIBLE STALE STATE: <reason>` when:
- Last Bash matched `git commit` AND `branch_strategy` is unset
- Last Write matched `docs/superpowers/specs/*.md` AND `spec_path` doesn't match
- Last Write matched `docs/superpowers/plans/*.md` AND `plan_path` doesn't match
- Last Bash matched `git push` AND `current_phase` is not `finalize`

Non-blocking (exit 0 always). Adds visibility so the user can catch the model.

## Schema Addition

`session-state.json` adds:
```json
"evidence": {
  ...,
  "retrospective_shown": false
}
```

Set by model via `jq` after presenting retrospective in user-visible text.
Session-start hook must initialize this field.

## Test Matrix

| Test file | Cases |
|-----------|-------|
| `test-classify-validator.sh` (new) | framework path + light → block; `docs/` + light → allow; `src/` + full → allow; `SKIP=1` → allow |
| `test-phase-advance.sh` (extend) | each transition: missing evidence → exit 2 with correct message; all evidence → advance; `SKIP=1` → advance |
| `test-todowrite-mirror.sh` (new) | 2 in_progress → exit 2; prefix match → `problems.current` updated; no match → unchanged |
| `test-freshness.sh` (new) | commit without branch_strategy → warn; spec write matches → silent |
| `test-status-line.sh` (extend) | micro/light/explore show `[prefix]` + todo line; debug shows todo line |

## Acceptance Criteria

1. Attempt `Edit .claude/hooks/foo.sh` with `interaction_classification=light` → blocked with reclassification message
2. Attempt `phase-advance.sh brainstorming` without `decisions_read=true` → blocked
3. Attempt `phase-advance.sh finalize` from retrospective without `retrospective_shown=true` → blocked
4. `TodoWrite` input with 2 `in_progress` → blocked
5. In-progress todo `[Retro] Foo` with `problems.labels=["Waves","Retro"]` → `problems.current=2`
6. Status line shows `📍 [Problem] light | ...` with `· <todo>` line in light/micro/explore
7. All existing tests pass; new tests cover each gate

## Rollback Plan

Each gate independently bypassable via env var. If a gate blocks legitimate work
in production use, document the case as a decision-log entry and adjust the
gate's conditions. No destructive changes — all gates are additive to existing
hook infrastructure.

## Wave Decomposition (feeds the plan)

- **Wave 1** (parallel): schema seed + independent validator
  - 1a: add `retrospective_shown` to session-state schema (`session-start.sh`)
  - 1b: `todowrite-mirror.sh` — single-in_progress check + derive problems.current
- **Wave 2** (parallel): hooks that don't collide
  - 2a: new `classify-validator.sh` + settings wiring
  - 2b: new `pre-tool-freshness.sh` + settings wiring
- **Wave 3**: phase exit gates (`phase-advance.sh` — single file, serialized)
- **Wave 4** (parallel): render + CLAUDE.md docs
  - 4a: `user-prompt-state.sh` render fixes (light/micro/explore/debug + consult/brainstorming)
  - 4b: CLAUDE.md section on bypass env vars + shortcuts-caught table
- **Wave 5**: full test pass (`make lint-shell` + all `test-*.sh`)
