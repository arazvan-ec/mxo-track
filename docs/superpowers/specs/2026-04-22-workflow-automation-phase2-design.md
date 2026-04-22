---
type: design-spec
date: 2026-04-22
topic: workflow-automation-phase2
interaction: automation-phase2
status: approved
tags: [workflow, automation, derivation, hooks, auto-evidence, plan-progress, classify-validator, test-health, pattern-graduation]
---

# Design Spec — Workflow Automation Phase 2

Consolidates 5 follow-up ideas extracted from the retrospective of
`2026-04-22-workflow-enforcement-gates.md`. Each idea addresses a concrete
friction observed during Option 3-Enforced implementation, either by removing
manual bookkeeping ("derive, don't track"), fixing a self-inflicted gate
bug, reducing test-harness noise, or surfacing classification early.

## Problema

After Option 3-Enforced landed, 5 friction points remained:

1. The hardened capture gate has a **chicken-and-egg**: it blocks the initial
   `Write` of the execution log because the file does not yet exist, requiring
   a manual `touch` via Bash before every first-time log creation.
2. Evidence fields like `task_progress.current`, `tests_written`, `spec_path`,
   `plan_path`, and `lint_clean` still require **manual `jq` updates by the
   model**. Each one is a drift vector — the model forgets and the status line
   lies. The Option 3-Enforced `problems.current` derivation worked; the same
   principle applies to these five other fields.
3. Three pre-existing test suites fail on every harness run
   (`test-self-gating`, `test-workflow-engine`, `test-status-line`). Every new
   developer and every new session re-investigates these **as if they were
   regressions**.
4. During the last session, `interaction_classification` silently drifted to
   `null` twice. The `classify-validator` caught both, but **the model never
   saw a proactive suggestion** until it tried an edit and got blocked.
   Catching the shortcut is good; preventing it is better.
5. Patterns from the last retrospective (`evidence-before-trust`,
   `bypass-env-vars`, `derive-dont-track`) are candidates for graduation to
   knowledge modules once they hit 3+ occurrences.

## Alternativas consideradas (trade-offs)

**Approach A — Resolver individualmente en sesiones separadas:**
- Ventaja: scope limpio por sesión, menos riesgo de contaminar PRs.
- Desventaja: 5× overhead de workflow (consult + brainstorm + plan + verify +
  capture + retro + finalize por feature). Total estimado ~8h serializado.
  No aprovecha paralelismo entre ideas disjuntas.

**Approach B — Batch con waves paralelas (elegido):**
- Ventaja: 4 de las 5 ideas tocan archivos disjuntos → Wave 1 con 4 agentes
  concurrentes. Wave 2 serializa 3 derivaciones en `auto-evidence.sh` (mismo
  archivo). Ahorro estimado ~65% wall clock vs serial. Un solo PR con
  artefactos coherentes (1 spec + 1 plan + 1 execution log).
- Desventaja: PR grande (~300-400 líneas estimadas); review más denso.

**Approach C — Solo las más urgentes (#1 + #3):**
- Ventaja: fix de bug + corrección de ruido, sin scope expansion.
- Desventaja: pierde la palanca de paralelismo. Las derivaciones (#2) son el
  pattern de mayor ROI a medio plazo (cada derivación elimina una clase de
  drift de estado).

**Approach elegido: B (Batch con waves).** Razón: las 5 ideas vienen de la misma
retro y responden al mismo meta-principio ("automatizar la disciplina que
actualmente es manual"). Un spec + plan + execution log consolidados alinean
mejor con la narrativa de evolución del workflow engine que 5 micro-PRs.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-engine.sh` PreToolUse gates for Edit/Write | Transform | Carve-out para initial Write del execution_log_path (fix #1) |
| `auto-evidence.sh` PostToolUse hook (evidencia decisiones/logs) | Transform | Extender con 3 derivaciones nuevas (#2b, #2c, #2d) |
| `plan-progress.sh` task_index parsing | Transform | Derivar `task_progress.current` del file_path editado (#2a) |
| `user-prompt-state.sh` status line render | Transform | Añadir sugerencia de clasificación cuando null (#4) |
| `Makefile` (make lint, make manifest, make lint-shell) | Transform | Añadir `make test-new-failures` para aislar ruido pre-existente (#3) |
| `scripts/pattern-audit.sh` + `_graduations.yaml` | Include | Ya funciona; el graduation step (#5) consume su output. Confirmado en consult: 0 candidatos nuevos este run |
| `docs/knowledge/test-suite-health.md` | Create | Documento nuevo registrando tests flaky conocidos (#3) |
| `classify-validator.sh`, `retrospective-validator.sh`, `todowrite-mirror.sh` | Include | Shipped en Option 3-Enforced; no se modifican aquí |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| #5 pattern graduation | **Omit from Wave 1** | Audit confirmó 0 candidatos nuevos (ambos patterns 3+ ya graduados). Se mantiene como verificación en Wave 3 |
| Decomposición extrema de #2 (4 hooks nuevos separados) | Omit | Las derivaciones son instancias del mismo principio; cohesión en `auto-evidence.sh` prevalece sobre paralelismo marginal |
| Auto-clasificación agresiva (auto-setear sin aprobación) | Omit | Solo sugerir en status line; modelo aprueba explícitamente. Menos frágil que override |
| Fix del workflow-engine para todos los artefactos de fase (no solo execution log) | Omit | Scope creep — el bug del capture gate es concreto; generalizar esperaría a 2ª ocurrencia con otro artefacto |
| Test para sugerencia de clasificación que cubra todos los path patterns | Transform → 3 casos representativos | `.claude/` → sugerir full, `docs/` → no sugerir, `backend/src/` → sugerir full |
| `lint_skip_reason` field para shellcheck missing edge case | Omit | Primera ocurrencia; regla del 3× dice esperar |

## Diseño — 4 features activas + 1 verificación

### Feature 1 — Capture gate carve-out (fix chicken-and-egg)

**Archivo:** `.claude/hooks/workflow-engine.sh`

En la rama de `capture`/`retrospective` phase donde se valida Edit/Write, añadir
carve-out: si `tool_input.file_path == evidence.execution_log_path` AND el
archivo no existe o tiene 0 bytes, permitir el Write. Una vez escrito, el
siguiente Edit recae en el gate normal (que requiere file exists).

**Test:** `test-workflow-engine.sh` (extender) — 2 casos: (a) Write al path
declarado con file ausente → allow; (b) Write a otro path en capture → block
como antes.

### Feature 2 — Derivaciones automáticas

**2a — `task_progress.current` desde file_path editado**
`plan-progress.sh`: al PostToolUse de Edit/Write, si `task_index` está poblado
y el `tool_input.file_path` matchea un task's `files` declaration (o heurística
por path), auto-avanzar `task_progress.current`. No revertir — solo avanzar.

**2b — `tests_written` desde `git diff --stat tests/`**
`auto-evidence.sh`: tras cada PostToolUse de Write/Edit a rutas bajo
`backend/tests/` o matching `\.test\.` / `\.spec\.`, recalcular
`evidence.tests_written` como count de archivos modificados/nuevos en esos
paths vía `git diff --name-only` + `git ls-files --others`.

**2c — `spec_path` / `plan_path` desde último Write**
`auto-evidence.sh`: si `tool_input.file_path` matches
`docs/superpowers/specs/*.md` AND `evidence.spec_path` es null →
setearlo. Idem para `plans/`.

**2d — `lint_clean` desde exit code de make lint**
`auto-evidence.sh`: tras PostToolUse de Bash con `tool_input.command` matching
`make lint` o `make lint-shell`, leer el exit code del resultado y setear
`evidence.lint_clean` = `true` si 0, `false` si ≠0.

**Test:** `test-auto-evidence.sh` (extender) — 6 casos (2 por cada 2b/2c/2d:
trigger path + negative path).

### Feature 3 — Test suite health tracking

**Archivos:**
- `docs/knowledge/test-suite-health.md` (nuevo): tabla `test file | known
  failures | owner | repro cmd | since_date | status`.
- `Makefile`: target `test-new-failures` que corre el harness, cruza con la
  tabla de known-failures, y sale con exit 0 si solo aparecen known failures,
  exit 1 si hay regresiones nuevas.

**Seed inicial:** 3 suites documentadas con sus conteos actuales
(`test-self-gating` 7/14, `test-workflow-engine` 6/29, `test-status-line` 5/?).

**Test:** smoke manual — corre `make test-new-failures` sin regresión → 0;
simular regresión añadiendo un fail → exit 1.

### Feature 4 — Classification suggestion in status line

**Archivo:** `.claude/hooks/user-prompt-state.sh`

Nuevo bloque al inicio (antes del render): si `interaction_classification` es
`null` AND hay historial de `Edit`/`Write` a framework paths en el turno
anterior (leer de `evidence.last_action` — campo nuevo seteado por
`auto-evidence.sh`), añadir línea de sugerencia:

```
💡 Sugerencia: edit a .claude/ detectado → clasificar como 'full'
   Set: jq '.interaction_classification = "full" | .flow_type = "full"' ...
```

No auto-setear. Solo sugerencia visible para que el modelo actúe.

**Test:** `test-status-line.sh` (extender, si no está entre los known-failures
post-#3) OR test dedicado nuevo — 3 casos: framework path + null → sugerencia;
docs path + null → no sugerencia; framework path + full → no sugerencia.

### Feature 5 — Pattern graduation verification

**No-code step.** Correr `bash .claude/hooks/pattern-audit.sh` al inicio y al
final de Wave 3. Confirmar 0 candidatos nuevos (ya verificado en consult).
Documentar en execution log.

## Wave Decomposition (feeds the plan)

**Wave 1 (paralelo, 4 agentes en worktrees):**
- **A1** — Feature 1 capture gate carve-out
- **A2** — Feature 2a `task_progress.current` derive
- **A3** — Feature 3 test-suite-health doc + Makefile target
- **A4** — Feature 4 classification suggestion

**Wave 2 (serial en `auto-evidence.sh`, 1 agente con 3 sub-tareas):**
- **2.1** — Feature 2b `tests_written` derive
- **2.2** — Feature 2c `spec_path` / `plan_path` derive
- **2.3** — Feature 2d `lint_clean` derive

**Wave 3 (serial):**
- Integration smoke tests (cada derivación se activa con un edit real)
- Pattern audit (feature 5 verification)
- `make manifest`
- Commit final + PR

## Tests

| Test file | Cases |
|-----------|-------|
| `test-workflow-engine.sh` (extend) | +2 cases (carve-out allow, unrelated-path block) |
| `test-plan-progress.sh` (extend) | +2 cases (file matches task → advance, no match → unchanged) |
| `test-auto-evidence.sh` (extend) | +6 cases (2 per derivation rule 2b/2c/2d) |
| `test-status-line.sh` (extend or new) | +3 cases for #4 suggestion logic |
| Manual smoke | `make test-new-failures` regression behavior |

## Acceptance Criteria

1. Writing `docs/superpowers/execution-logs/<new>.md` for the first time does
   NOT require `touch` via Bash — workflow-engine allows the initial Write.
2. Editing a file under a task's `files:` section auto-advances
   `task_progress.current` without manual `jq`.
3. Write to `backend/tests/**/*.php` auto-increments `evidence.tests_written`.
4. Write to `docs/superpowers/specs/<new>.md` auto-sets `evidence.spec_path`
   if null.
5. `make lint` exit 0 auto-sets `evidence.lint_clean = true`.
6. `make test-new-failures` returns 0 when only known flaky tests fail;
   returns 1 when a new failure appears.
7. Edit to `.claude/hooks/foo.sh` with `classification=null` → status line
   shows `💡 Sugerencia: clasificar como 'full'`.
8. All new + existing harness tests pass (excepting the 3 known-flaky
   documented in `test-suite-health.md`).

## Rollback Plan

Each feature is independently revertible:
- #1 carve-out: remove the `if` block in `workflow-engine.sh`
- #2a-d derivations: remove the relevant case arm in `plan-progress.sh` /
  `auto-evidence.sh`
- #3 test health: delete `make test-new-failures` target + doc
- #4 suggestion: remove the render block in `user-prompt-state.sh`

No destructive schema changes. No evidence fields removed.
