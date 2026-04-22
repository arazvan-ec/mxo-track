---
type: plan
date: 2026-04-22
feature: workflow-enforcement-gates
spec: docs/superpowers/specs/2026-04-22-workflow-enforcement-gates-design.md
tags: [workflow, enforcement, hooks, validators, phase-advance, todowrite-mirror, status-line]
---

# Plan — Workflow Enforcement Gates (Option 3-Enforced)

Reference: [design spec](../specs/2026-04-22-workflow-enforcement-gates-design.md)

## Phase 1 (v0) — Working gates with minimum viable coverage

Goal: every gate blocks or warns as designed, with one test per gate. Render
fixes render correctly for light/micro/explore/debug.

### [parallel] Wave 1: schema seed + mirror hardening

**1a — Schema: add `retrospective_shown` flag**
- Files: `.claude/hooks/session-start.sh`, `.claude/hooks/user-prompt-state.sh`
- Changes:
  - Add `"retrospective_shown": false` to initial evidence object in session-start
  - Add `"retrospective_shown": false` to auto-reset block in user-prompt-state
- → produces: schema field available for Layer B exit gate
- TDD: test that `session-start.sh` produces state with `retrospective_shown: false`

**1b — `todowrite-mirror.sh`: derive + reject**
- Files: `.claude/hooks/todowrite-mirror.sh`, `.claude/hooks/test-todowrite-mirror.sh` (new)
- Changes:
  - Reject input with >1 `in_progress` → exit 2 with message
  - Extract `[prefix]` regex from in_progress label
  - Match against `problems.labels` (case-insensitive substring)
  - Update `problems.current` if match found (no change if no match)
- → produces: auto-derived `problems.current`, single-in_progress guarantee
- TDD: 3 cases in test file (reject, match, no-match)

### [parallel] Wave 2: independent PreToolUse hooks

**2a — Layer A: `classify-validator.sh`**
- Files: `.claude/hooks/validators/classify-validator.sh` (new),
  `.claude/hooks/test-classify-validator.sh` (new), `.claude/settings.json`
- Changes:
  - New hook reads stdin (tool_input), extracts `file_path`
  - Framework path regex: `^(\.claude/|scripts/|backend/src/|backend/templates/|backend/config/|backend/migrations/|frontend/src/|ml-service/)`
  - Carve-outs: `docs/`, `*.md`, `.claude/session-state.json`, `/tmp/`
  - Block if `interaction_classification ∈ {micro, light, explore, informational}` or null
  - Bypass: `SKIP_CLASSIFY_GATE=1`
  - Wire into `settings.json` PreToolUse for `Edit` and `Write`
- → produces: clasificación-forzada gate active
- TDD: 4 cases (block, allow-full, allow-docs, bypass)

**2b — Layer D: `pre-tool-freshness.sh`**
- Files: `.claude/hooks/pre-tool-freshness.sh` (new),
  `.claude/hooks/test-freshness.sh` (new), `.claude/settings.json`
- Changes:
  - Read last-action from session-state (new field: `evidence.last_action`)
  - Emit `⚠ POSIBLE STALE STATE:` warning for 4 conditions listed in spec
  - Non-blocking: always exit 0
  - Wire into `settings.json` PreToolUse (all tools)
- → produces: freshness visibility warnings
- TDD: 4 cases covering each warning condition

**Note:** 2a and 2b touch `settings.json` concurrently. To avoid conflict:
- 2a edits `hooks.PreToolUse` entries matching `Edit|Write`
- 2b appends a catch-all `PreToolUse` entry at end
- If conflict occurs, serialize 2a then 2b

### Wave 3: Layer B — phase exit gates

**3 — Extend `phase-advance.sh` with exit conditions**
- Files: `.claude/hooks/phase-advance.sh`, `.claude/hooks/test-phase-advance.sh`
- Changes:
  - Add `check_exit_conditions()` function called before phase_history write
  - Implement 7 transition checks per spec (consult→brainstorm, brainstorm→planning, etc.)
  - Output failing evidence list + exact `jq` to set each field
  - Bypass: `SKIP_PHASE_EXIT_GATE=1`
  - Depends on Wave 1a (retrospective_shown flag must exist in schema)
- → produces: 7 phase-exit gates active
- TDD: extend existing test file with 14 cases (7 block + 7 allow)

### [parallel] Wave 4: render + docs

**4a — `user-prompt-state.sh` render fixes**
- Files: `.claude/hooks/user-prompt-state.sh`, `.claude/hooks/test-status-line.sh`
- Changes:
  - Extract `render_problem_prefix()` bash function (returns `[label] ` or empty)
  - Extract `render_todo_line()` bash function (returns `· <todo> (n/m)` or empty)
  - Call both in micro, light, explore, debug, full-consult, full-brainstorming branches
- → produces: `📍 [problem] flow | desc` + `· <todo>` in all flows
- TDD: 6 cases (one per flow × with/without problems+todos)

**4b — CLAUDE.md + session-state.json docs**
- Files: `CLAUDE.md`, `.claude/README.md`
- Changes:
  - Add section "Bypass env vars" listing SKIP_CLASSIFY_GATE, SKIP_PHASE_EXIT_GATE
  - Add "Shortcuts caught by enforcement gates" table (8 shortcuts → gate that catches it)
  - Update `.claude/README.md` with new hooks list
- → produces: documentation of new gates for future sessions

### Wave 5: integration + lint

**5 — Full test + shell lint**
- Run `make lint-shell` — fix any shellcheck warnings in new hooks
- Run all `test-*.sh` harness tests — all must pass
- Manual smoke test:
  - Try `Edit .claude/hooks/foo.sh` with `interaction_classification=light` → verify block
  - Try `phase-advance.sh finalize` from retrospective without `retrospective_shown` → verify block
  - Try `TodoWrite` with 2 `in_progress` items → verify block
- → produces: green test suite + manual validation

## Phase 2 (Mature) — Deferred

Phase 2 (refactor unification of render helpers into shared lib, machine-readable
phase transition log, helper `problems.sh`) is explicitly deferred. All marked
Omit in the spec. Re-evaluate if 3+ execution logs show friction.

## Task Execution Rules

- Each wave completes fully (including tests) before the next wave starts
- Within a parallel wave, tasks execute as background agents or direct edits
  based on file-conflict analysis
- Commit after each wave completes with `feat:` or `test:` prefix
- Push after waves 2, 3, 5 (subagent handoffs + checkpoints)
- Session-state update **before** action at every phase transition

## Task Counter Index

| Task ID | Wave | Label |
|---------|------|-------|
| 1a | 1 | Schema: retrospective_shown flag |
| 1b | 1 | todowrite-mirror: derive + reject |
| 2a | 2 | classify-validator (Layer A) |
| 2b | 2 | freshness-warning hook (Layer D) |
| 3 | 3 | phase-advance exit gates (Layer B) |
| 4a | 4 | user-prompt-state render fixes |
| 4b | 4 | CLAUDE.md + README docs |
| 5 | 5 | Full test + shell lint |

Total: 8 tasks across 5 waves. Parallel frontier: max 2 concurrent.

## Acceptance (from spec)

Copied here for reference during execution:

1. Edit `.claude/hooks/foo.sh` with `interaction_classification=light` → blocked
2. `phase-advance.sh brainstorming` without `decisions_read=true` → blocked
3. `phase-advance.sh finalize` from retrospective without `retrospective_shown=true` → blocked
4. TodoWrite with 2 in_progress → blocked
5. In-progress todo `[Retro] Foo` + labels `["Waves","Retro"]` → `problems.current=2`
6. Status line shows `📍 [Problem] light | ...` and `· <todo>` line in all flows
7. All tests pass, `make lint-shell` clean
