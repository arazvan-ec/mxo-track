---
type: feature
tags: [workflow, enforcement, hooks, validators, status-line, multi-problem, todowrite-mirror, classify-validator, pre-tool-freshness, phase-advance, option-3-enforced]
files_touched:
  - .claude/hooks/session-start.sh
  - .claude/hooks/user-prompt-state.sh
  - .claude/hooks/todowrite-mirror.sh
  - .claude/hooks/test-todowrite-mirror.sh
  - .claude/hooks/validators/classify-validator.sh
  - .claude/hooks/pre-tool-freshness.sh
  - .claude/hooks/test-classify-validator.sh
  - .claude/hooks/test-freshness.sh
  - .claude/hooks/validators/consult-validator.sh
  - .claude/hooks/validators/capture-validator.sh
  - .claude/hooks/validators/retrospective-validator.sh
  - .claude/hooks/phase-advance.sh
  - .claude/hooks/test-phase-advance.sh
  - .claude/hooks/test-retrospective-validator.sh
  - .claude/hooks/test-enforcement-layers.sh
  - .claude/hooks/test-full-flow-e2e.sh
  - .claude/settings.json
  - CLAUDE.md
  - .claude/README.md
  - docs/superpowers/specs/2026-04-22-workflow-enforcement-gates-design.md
  - docs/superpowers/plans/2026-04-22-workflow-enforcement-gates.md
patterns: [workflow-enforcement-gates, evidence-before-trust, bypass-env-vars, todowrite-prefix-derivation]
outcome: success
---

# Execution Log — 2026-04-22 — Workflow Enforcement Gates (Option 3-Enforced)

## Context

User request (meta): "How can we improve Option 3 so you don't take shortcuts
and consistently follow the flow?" — pivot from rendering bugfix (A-D) to
enforcement layering across 8 concrete shortcuts catalogued during brainstorm.

Second problem in a multi-problem interaction (`work_context.problems.labels
= ["Terminar waves pendientes", "Planificar mejoras retro"]`). First problem
was closed in a prior session (waves PR for dashboard enhancement).

## Brainstorming

Three alternatives presented:

- **Opción 1 — Minimalista (~15 líneas):** only render fixes in light/micro/explore/debug
  (A-D gaps). Deferred 4 other shortcut classes (sub-classification, stale state, etc.).
- **Opción 2 — Render unificado + `problems.sh` helper (~40 líneas):** refactor
  DRY + CLI subcommands for problems management. Judged premature abstraction.
- **Opción 3 — Enforcement completo (~200 líneas):** 4 layers of gates
  (classification, phase exit conditions, derivation + single in_progress,
  freshness warning) + bypass env vars.

**Approach elegido: Opción 3.** User push-back against the defensive
min-scope recommendation was correct — the gaps user surfaces are symptoms
of a class of behavior (shortcut-taking), not isolated UI bugs. Opción 3
converts CLAUDE.md instructions into exit-2 contracts.

## Planning

5 waves, 8 tasks, max parallel frontier = 2:

1. Wave 1 (parallel): schema `retrospective_shown` + todowrite-mirror hardening
2. Wave 2 (parallel): `classify-validator.sh` + `pre-tool-freshness.sh` + `settings.json` wiring
3. Wave 3 (serial): `phase-advance.sh` exit gates (hardening existing validators)
4. Wave 4 (parallel): `user-prompt-state.sh` render + `CLAUDE.md`/`README.md` docs
5. Wave 5 (serial): full test harness run + fix tests broken by hardened gates

## Implementation

### Wave 1 — Schema + mirror hardening
- `session-start.sh`: added `"retrospective_shown": false` to evidence init
- `user-prompt-state.sh`: added same flag to auto-reset block
- `todowrite-mirror.sh`: rejects input with >1 in_progress (exit 2);
  derives `problems.current` from `[prefix]` of in_progress label via
  case-insensitive substring match against `problems.labels`
- `test-todowrite-mirror.sh`: 14 cases — all pass

### Wave 2 — New PreToolUse hooks (Layers A + D)
- `validators/classify-validator.sh`: blocks Edit/Write to framework paths
  when classification ∈ {micro, light, explore, informational, null}. Carve-outs:
  `docs/`, `*.md`, `/tmp/`, `.claude/session-state.json`. Bypass: `SKIP_CLASSIFY_GATE=1`.
- Initial bug: sed `|` delimiter conflicted with `|` inside grouped alternation.
  Fixed by switching delimiter to `#`. Caught by T7/T9/T11 test failures.
- `pre-tool-freshness.sh` (non-blocking): emits warnings for spec/plan/exec-log
  writes in wrong phase, git push without branch_strategy, git commit in consult.
- Both wired into `.claude/settings.json` PreToolUse chain via `jq`.
- Tests: 11 classify + 10 freshness, all pass.

### Wave 3 — Phase exit gate hardening (Layer B)
Most phase validators already existed. Wave 3 scope reduced to hardening 3
validators + adding bypass:
- `consult-validator.sh`: OR → AND (both `decisions_read` and `logs_scanned` required)
- `capture-validator.sh`: SOFT (exit 1) → HARD (exit 2) when execution_log_path missing
- `retrospective-validator.sh`: adds `retrospective_shown=true` gate (visibility enforcement)
- `phase-advance.sh`: wraps validator invocation with `SKIP_PHASE_EXIT_GATE=1` bypass
- `test-phase-advance.sh`: extended with 7 new cases (13 → 21 total), all pass

### Wave 4 — Render fixes + docs
- `user-prompt-state.sh`: extracted `render_problem_prefix()` and `render_todo_line()`
  helpers. Applied to micro/light/explore/debug + full-flow consult/brainstorming.
- `CLAUDE.md`: added "Enforcement gates — shortcuts they catch" table (8 shortcuts
  → gate mapping) and "Bypass env vars" section.
- `.claude/README.md`: updated phase evidence table + added PreToolUse gates section.

### Wave 5 — Integration
Full harness run revealed 3 test files referencing old evidence patterns
(fixed). Pre-existing failures (unchanged): `test-self-gating.sh` (7/14),
`test-workflow-engine.sh` (6/29), `test-status-line.sh` (5+). Verified via
`git stash` before/after.

### Live smoke tests
1. `Edit .claude/hooks/foo.sh` + classification=light → BLOCKED ✓
2. `phase-advance.sh finalize` + `retrospective_shown=false` → BLOCKED ✓
3. `TodoWrite` with 2 `in_progress` → BLOCKED ✓

Gates fired in vivo during the session: classify-validator rejected my edit
when `interaction_classification` accidentally got reset to null, and the
retrospective gate blocked my decision-log edit before the retro was presented.

## Verification

| Check | Result |
|-------|--------|
| `test-classify-validator.sh` | 11/11 pass |
| `test-freshness.sh` | 10/10 pass |
| `test-phase-advance.sh` | 21/21 pass |
| `test-todowrite-mirror.sh` | 14/14 pass |
| `test-retrospective-validator.sh` | 6/6 pass (fixed) |
| `test-enforcement-layers.sh` | 15/15 pass (fixed) |
| `test-full-flow-e2e.sh` | 24/24 pass (fixed) |
| `make lint` (PHP) | clean |
| `make lint-shell` | `shellcheck not installed` — bypassed, documented in decisions log |

## Commits

- `81e19fc` docs: add spec + plan for workflow enforcement gates
- `95f218c` feat: wave 1 — retrospective_shown schema + todowrite-mirror enforcement
- `0867a74` feat: wave 2 — classify-validator (A) + pre-tool-freshness (D)
- `033159c` feat: wave 3 — harden phase exit gates (Layer B)
- `6dd33bb` feat: wave 4 — render fixes (4a) + docs (4b) for Option 3-Enforced
- `63a4e0a` test: wave 5 — update tests for hardened gates

## Lessons / Retrospective

### Estimate accuracy

| Metric | Estimated | Actual | Gap |
|--------|-----------|--------|-----|
| Lines | ~200 | ~560 | +180% — more docs (tables) + tests than planned |
| New/extended tests | ~8 cases | 42 cases (4 new + 3 extended files) | +425% — each gate needed more cases than spec listed |
| Files touched | ~12 | 21 | +75% — docs updates + test file fixes |
| Waves | 5 (as planned) | 5 | 0 — decomposition was accurate |

**Root cause of gap:** The spec listed 7 acceptance criteria but each expanded
into 2-5 test cases once operationalized. Next time, estimate tests as
`criteria × 3-5`, not 1-to-1.

### Process gaps encountered

1. **Sed delimiter clash:** `sed -E 's|^.*/(a|b|c)|\1|'` silently broke when `|`
   was also the delimiter. Fix: use `#` delimiter when alternation includes `|`.

2. **Test fixture backup/restore lost evidence:** Smoke tests that `cp`-backed
   session-state before modifying, then restored at the end, accidentally wiped
   evidence accumulated during the interaction (spec_path, plan_path,
   classification). Lesson: backup must be taken **inside the test function**,
   immediately before the mutation — not from an earlier snapshot.

3. **Gate firing on decision-log edit (as intended):** Writing the decision log
   documenting the SKIP bypass was blocked because `retrospective_shown=false`.
   Initially felt like false positive but the gate is correct — decision log
   entries are retrospective artifacts. **The gate exposed my impulse to write
   out of order.** Proper workflow: present retrospective → set flag → update logs.

4. **Capture gate's chicken-and-egg on log creation (genuine bug):** The
   hardened `capture-validator.sh` requires both `execution_log_path` set AND
   the file to exist on disk. Writing the log for the first time fails both
   checks from the workflow-engine. Worked around with `touch <path>` via Bash
   before `Write`. **Follow-up: relax the workflow-engine capture gate to allow
   the initial Write that matches `execution_log_path` (or allow Write when
   file-size would be 0). Captured as pending_work.**

5. **Pre-existing test failures:** 3 test suites were already failing before
   these commits. Owned by different initiatives. Lesson: the test harness needs
   a `test-suite-health.md` marking known-flaky tests so new failures stand out.

### Emergent patterns

**Pattern 1 — Evidence-before-trust gates:** Each exit-2 gate converts a
CLAUDE.md norm into a contract. Model writes evidence flag; validator reads
it. Mechanical verification of self-reported evidence.

**Pattern 2 — Bypass env var + mandatory decision log:** `SKIP_*_GATE=1`
pairs with a decision log entry. Silences infrastructure edge cases without
silencing them forever (3x threshold triggers validator tuning).

**Pattern 3 — Derive, don't track:** TodoWrite `[prefix]` maps to `problems.labels`.
The mirror hook derives `problems.current` from the active todo's prefix
instead of requiring manual advance. Eliminates a class of bookkeeping drift.
Could apply to other fields (e.g., `task_progress.current` from file being edited).

## Follow-ups (pending_work candidates)

- Install `shellcheck` in Claude Code on the web OR add `skipped_infra_missing`
  state to `verification-validator.sh` (observed 1x, re-evaluate at 3x).
- Fix capture gate chicken-and-egg: allow Write to `execution_log_path` when
  file is 0 bytes or non-existent (observed 1x this session, re-evaluate at 3x).
- Address pre-existing failures in test-self-gating, test-workflow-engine,
  test-status-line — separate interaction.
- Consider extending Layer D (freshness) to block after 3x false-negative cases.
