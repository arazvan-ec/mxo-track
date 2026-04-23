# Workflow Engine

## Status

**[CURRENT]** as of 2026-04-22 — Option 3-Enforced layers live on `main`.
Last verified: 2026-04-22 (waves 1-5 merged: `95f218c`, `0867a74`, `033159c`,
`6dd33bb`, `63a4e0a`).

## Purpose

The workflow engine is a set of shell hooks that enforce the discipline CLAUDE.md
describes. Hooks exist because the engine cannot trust that the model will follow
process voluntarily — every HARD gate encodes a validated assumption about a known
shortcut. The asymmetry is deliberate: a false positive (blocking legitimate work)
costs minutes to reclassify or bypass; a false negative (allowing an unreviewed
edit) costs hours to debug the resulting regression. CLAUDE.md owns the *discipline*;
this module describes the *mechanism* — the files, the evidence fields, and the
exact failure modes each hook catches.

## Architecture Layers

Four enforcement layers, orthogonal to each other, each targeting a different class
of shortcut. All four run on every relevant tool call.

### Layer A — Classification gate (PreToolUse on Edit/Write)

**File:** `.claude/hooks/validators/classify-validator.sh`

Blocks edits to framework/code paths when the interaction is classified
`micro|light|explore|informational|null`. The gate exists because the model's
natural bias is to call framework changes "light" to skip brainstorming.

- **Path normalization** (`classify-validator.sh:36-38`): accepts both
  repo-relative (`backend/src/x.php`) and absolute (`/home/user/mxo-track/...`)
  forms, then trims to the first known framework root.
- **Carve-outs** (`classify-validator.sh:41-46`): `docs/*`, `*.md`, `/tmp/*`,
  `.claude/session-state.json` always pass. Documentation and state writes do
  not require `full`/`debug`.
- **Framework regex** (`classify-validator.sh:49`):
  `.claude/|scripts/|backend/src/|backend/templates/|backend/config/|backend/migrations/|backend/tests/|frontend/src/|ml-service/|docker/`.
- **Bypass:** `SKIP_CLASSIFY_GATE=1`. Requires a decision log entry.
- **Error output** includes a one-liner `jq` command to reclassify, so recovery
  is a single paste.

### Agent permission model (adjacent to Layer A, not a gate)

Orthogonal to the classification gate: the Claude Code sandbox blocks
**background-agent writes to `.claude/**`** regardless of auto-approve settings
or `dangerouslyDisableSandbox: true`. Reads work, writes (Write/Edit/Bash-heredoc)
deny. This is a harness-level restriction, not a workflow hook. Harness edits
(`.claude/hooks/**`, `.claude/settings*.json`, `.claude/scripts/**`) must run in
the foreground session or under `isolation: "worktree"`. See `AGENTS.md` →
"Agent Permission Model" for full dispatch guidance and the split-parallel-work
mitigation pattern (evidence: execution log
`2026-04-22-knowledge-module-and-flow-phases-sot.md`).

### Layer B — Phase exit gates (phase-advance.sh)

**File:** `.claude/hooks/phase-advance.sh`

Phase-advance is the ONLY sanctioned way to write `phase_history`. Direct `jq`
writes are reverted by `phase-transition-controller.sh`. Every transition runs
the validator for the phase being **LEFT** (not the target — `phase-advance.sh:111-121`).

- **Autodiscovery** (`phase-advance.sh:117-121`): looks for
  `validators/${phase}-validator.sh` with a `${phase%ing}` fallback for
  "brainstorming" → `brainstorm-validator.sh`. Adding a new validator is just
  creating the file.
- **Exit codes:** validator exit 2 → blocks transition (HARD); exit 1 → stderr
  warning but advances (SOFT); exit 0 → silent pass.
- **Bypass:** `SKIP_PHASE_EXIT_GATE=1`. The script prints a visible warning when
  bypassed (`phase-advance.sh:135-137`) — silent bypass would hide evidence gaps.
- **Side effects on target phase:** entering `retrospective` prints a visible
  three-point reminder (`phase-advance.sh:152-159`); entering `implementation`
  auto-initializes `task_progress` if a plan path is set
  (`phase-advance.sh:162-169`); entering `finalize` runs `pattern-audit.sh`
  (`phase-advance.sh:147-149`).

### Layer C — TodoWrite mirror (PostToolUse on TodoWrite)

**File:** `.claude/hooks/todowrite-mirror.sh`

Runs after every TodoWrite. Three responsibilities:

1. **in_progress=1 invariant** (`todowrite-mirror.sh:23-31`): rejects inputs with
   >1 `in_progress` todo (exit 2). The TodoWrite contract and CLAUDE.md both
   require exactly one active todo; this enforces it mechanically.
2. **task_progress mirror** (`todowrite-mirror.sh:53-72`): unless a plan has
   populated `task_progress.task_index`, derives `total`, `current`, `label`,
   `completed_labels` from the todo list so the status line shows granular
   progress for flows without a parsed plan.
3. **problems.current derivation** (`todowrite-mirror.sh:74-91`): extracts
   `[prefix]` from the active todo's text and does a case-insensitive substring
   match against `work_context.problems.labels` to update
   `work_context.problems.current` without manual `jq` bookkeeping.

### Layer D — Freshness warnings (PreToolUse, all tools)

**File:** `.claude/hooks/pre-tool-freshness.sh`

Non-blocking. Always exits 0. Emits `⚠ POSIBLE STALE STATE: <reason>` to stderr
when the upcoming tool call signals the session-state likely lags reality.

Warning patterns (`pre-tool-freshness.sh:33-83`):

- `git commit` while `flow=full, phase=consult` — no design/plan artifacts exist yet.
- `git push` in `phase=finalize` without `branch_strategy` set.
- Writing a spec file (`docs/superpowers/specs/*.md`) outside `brainstorming`/`consult`.
- Writing a plan file outside `planning`.
- Writing an execution log outside `capture`/`retrospective`.
- Writing a new spec/plan when the evidence field already points elsewhere.

The warning is visible to the model (stderr) but does not block; the goal is to
prompt the model to reconcile state, not to gate legitimate checkpoints.

## Phase Evidence Matrix

Adapted from `.claude/README.md` with the 2026-04-22 hardening annotations.

| Phase | Evidence required | Level | Validator |
|-------|-------------------|-------|-----------|
| `consult` | `decisions_read` AND `logs_scanned` (hardened 2026-04-22 — was OR) | HARD | `consult-validator.sh` |
| `brainstorming` | `user_turns ≥ 1` (HARD) + SOFT warn if `< 3` + `alternatives_proposed` + `user_approved` + `spec_path` (file ≥500B) + `## Existing Functionality Inventory` + `## Omission Decisions` + parallel-wave file-conflict check | MIXED | `brainstorm-validator.sh` |
| `planning` | `plan_path` (file ≥300B, contains plan keywords) | HARD | `planning-validator.sh` |
| `implementation` | plan exists (HARD) + `tests_written > 0` (SOFT warn) | MIXED | `implementation-validator.sh` |
| `verification` | `tests_passed` AND `lint_clean` must be `true\|false` in `full`/`debug`; `"skipped"` only accepted in `light`/`informational`/`explore`/`micro` flows (hardened 2026-04-22 — `"skipped"` used to be accepted everywhere) | MIXED | `verification-validator.sh` |
| `capture` | `execution_log_path` set AND file exists on disk (hardened 2026-04-22 — was SOFT, now HARD) | HARD | `capture-validator.sh` |
| `retrospective` | `retrospective_shown=true` (visibility gate, new 2026-04-22) + execution log contains `## Lessons\|Retrospectiva\|Retrospective\|Lecciones` section ≥100 chars | HARD | `retrospective-validator.sh` |
| `finalize` | `branch_strategy ∈ {merge,pr,keep,discard}` + knowledge module freshness check | SOFT | `finalize-validator.sh` |
| `debug-code` (file edit gate) | `decisions_read` OR `logs_scanned` + `root_cause_identified` + `pattern_wide_search_done` | HARD | `debug-validator.sh` |

## Shortcuts Catalog

Each row pairs a shortcut the model is prone to take with the gate that catches it.

| Shortcut | Gate |
|----------|------|
| Calling framework changes "light" to skip brainstorm | `classify-validator.sh` (Layer A) |
| `consult → brainstorm` without reading decisions/logs | `consult-validator.sh` (Layer B) — requires BOTH flags |
| `brainstorm → planning` without alternatives or approval | `brainstorm-validator.sh` — requires `alternatives_proposed`, `user_approved`, `spec_path`, ≥1 user turn |
| `verification → capture` without running tests/lint | `verification-validator.sh` — `tests_passed` and `lint_clean` must be `true` (no `skipped` in full/debug) |
| `capture → retrospective` without writing the execution log | `capture-validator.sh` (Layer B, HARD) — path set AND file exists |
| `retrospective → finalize` without presenting retrospective to user | `retrospective-validator.sh` — `evidence.retrospective_shown=true` must be set after visible chat presentation |
| Forgetting to advance `problems.current` when switching petitions | `todowrite-mirror.sh` (Layer C) — auto-derives from `[prefix]` |
| Multiple `in_progress` todos at once | `todowrite-mirror.sh` (Layer C) — rejects input with >1 (exit 2) |
| Stale session-state when committing / writing artifacts | `pre-tool-freshness.sh` (Layer D, non-blocking warning) |

## Bypass Env Vars

Every HARD gate has a documented escape hatch for the cases where the gate is
wrong. Using a bypass **requires** a corresponding entry in `docs/decisions/log.md`
explaining the situation.

| Env var | Effect | Intended use |
|---------|--------|--------------|
| `SKIP_CLASSIFY_GATE=1` | Disables `classify-validator.sh` | Emergency edits to framework paths when reclassification has already been discussed but session-state is stuck |
| `SKIP_PHASE_EXIT_GATE=1` | Disables all phase exit validators in `phase-advance.sh` | Recovery from corrupted evidence state; rebuilding session after interruption |

There is no bypass for Layer C (TodoWrite mirror) — the `in_progress=1` invariant
is non-negotiable and always correct. Layer D is already non-blocking.

A gate that blocks legitimate work repeatedly is a gate whose conditions need to be
tuned — not a gate to silence. Bypass is a last resort, not a workflow.

## File Index

| File | Role |
|------|------|
| `.claude/hooks/phase-advance.sh` | CLI for legal phase transitions; runs exit validator; side-effects (retro reminder, plan-progress init, pattern-audit) |
| `.claude/hooks/phase-transition-controller.sh` | Detects and reverts direct `jq` writes to `phase_history` or `user_approved` |
| `.claude/hooks/user-prompt-state.sh` | UserPromptSubmit hook — injects status line, processes approval/rejection regex |
| `.claude/hooks/workflow-status-line.sh` | Renders the full-format status line (`📍 full \| Phase (n/N) \| ...`) |
| `.claude/hooks/pre-tool-freshness.sh` | Layer D — stale-state warnings (non-blocking) |
| `.claude/hooks/todowrite-mirror.sh` | Layer C — `in_progress=1` invariant + task_progress/problems derivation |
| `.claude/hooks/validators/classify-validator.sh` | Layer A — blocks framework edits without full/debug classification |
| `.claude/hooks/validators/consult-validator.sh` | Phase B — requires `decisions_read` AND `logs_scanned` |
| `.claude/hooks/validators/brainstorm-validator.sh` | Phase B — spec size + keywords + anti-omission + parallel-wave conflict |
| `.claude/hooks/validators/planning-validator.sh` | Phase B — plan size/keywords |
| `.claude/hooks/validators/implementation-validator.sh` | File edit gate during implementation — TDD check |
| `.claude/hooks/validators/verification-validator.sh` | Phase B — tests_passed + lint_clean with strict/non-strict per flow |
| `.claude/hooks/validators/capture-validator.sh` | Phase B — execution_log_path set + file exists |
| `.claude/hooks/validators/retrospective-validator.sh` | Phase B — `retrospective_shown` + log section ≥100 chars |
| `.claude/hooks/validators/finalize-validator.sh` | Phase B — branch_strategy + knowledge module freshness |
| `.claude/hooks/validators/spec-compliance-validator.sh` | File edit gate — verifies code changes align with spec |
| `.claude/hooks/validators/debug-validator.sh` | File edit gate for debug flow — `root_cause_identified` + `pattern_wide_search_done` |

## Known Inconsistencies (as of 2026-04-22)

These are tensions between files that serve related purposes. They do not block
correctness; they exist because several subsystems evolved independently and
have not yet been reconciled.

- **Debug flow phase names diverge between phase-advance and status-line scripts.**
  `phase-advance.sh:39` defines the debug sequence as
  `root_cause pattern_wide fix verification capture retrospective finalize`
  (no `consult`; uses `pattern_wide`). The status-line scripts
  (`user-prompt-state.sh:237`, `workflow-status-line.sh:521`) define
  `DEBUG_PHASES=("consult" "root_cause" "pattern_search" "fix")`. Phase-advance
  is the operative source of truth — status-lines are being reconciled in a
  follow-up (see execution log
  `docs/superpowers/execution-logs/2026-04-22-fix-phase-advance-debug-entry.md`,
  follow-up #1).
- **`pattern_search` vs `pattern_wide`** is part of the same divergence:
  phase-advance uses `pattern_wide`, status-lines use `pattern_search`. The
  evidence field itself (`pattern_wide_search_done`) is consistent with
  phase-advance's naming.

## Relationship to CLAUDE.md

CLAUDE.md contains the *discipline* — what the model should do, why, and what
shortcut each rule prevents. This module contains the *mechanism* — which hook
enforces which rule, file by file, with line references.

Read both: CLAUDE.md to know what is required of you, this module to understand
the enforcement surface when debugging why a gate fired or when adding a new
gate. If CLAUDE.md changes a rule, this module should be updated to reflect the
new enforcement; if a hook changes behavior, CLAUDE.md should be updated to
reflect the new discipline.
