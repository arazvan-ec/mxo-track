# Workflow Engine

## Status

**[CURRENT]** as of 2026-04-26 — Option 3-Enforced layers live on `main`,
plus the 4-test pruning sweep (commits `231f951` removed Layers I+J;
`ad11cc4` strengthened F to conditional BLOCK and consolidated H against
the YAML SoT). Last verified: 2026-04-26.

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

Six active enforcement layers (A, B, C, D, F, H), orthogonal to each other, each
targeting a different class of shortcut. All run on every relevant tool call.
Two further layers (I, J) were retired 2026-04-26 under the 4-test framework —
they are documented below for archeological reasons (search hits and history),
but no longer execute. See
`docs/superpowers/execution-logs/2026-04-26-4test-applied-FIJ.md` for rationale.

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

### Agent permission model (adjacent to Layer A)

Subagents inherit the main session's `session-state.json` — they read the same
file. Writes to framework paths (including `.claude/**`) from subagents are
subject to the same `classify-validator.sh` gate as the main session, and pass
iff `interaction_classification ∈ {full, debug}`.

A prior revision of this module (added 2026-04-22) claimed the Claude Code
**sandbox** blocks subagent writes to `.claude/**` "regardless of auto-approve
settings or `dangerouslyDisableSandbox: true`." **That diagnosis was wrong.**
Empirical reproduction (2026-04-24) confirms that with the main classification
set to `full`, subagents can freely Write/Edit inside `.claude/**`. The actual
block is Layer A's classify-validator, not a sandbox policy. The misdiagnosis
stemmed from a single data point in which the main session's classification
had drifted to a non-`full`/`debug` value at dispatch time — the agent read
the same state file and hit the same hook. See `AGENTS.md` →
"Agent Permission Model" for the full dispatch rule-of-thumb table.

**Practical consequence:** always confirm
`jq '.interaction_classification' .claude/session-state.json` returns
`"full"` or `"debug"` before dispatching a subagent that must edit
`.claude/**`. The `pre-agent-check.sh` hook emits a warning when the
agent prompt references `.claude/**` paths and the classification is
insufficient.

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

### Layer F — DDD boundary check (PreToolUse on Edit/Write)

**File:** `.claude/hooks/ddd-boundary-check.sh`

Catches edits that introduce ORM coupling (`createQueryBuilder`, `getRepository`,
or similar) inside a critical aggregate context. Critical contexts are read from
`docs/knowledge/_ddd-boundaries.yaml` (single source of truth, shared with Layer H
via `.claude/hooks/lib/ddd-boundaries.sh`).

- **Severity is conditional** (`ddd-boundary-check.sh:127-176`): in `full`/`debug`
  flow, the gate **BLOCKS** when the spec's `## Prior Art Audit` section does not
  cover the file being edited. Outside `full`/`debug`, or when no spec exists yet,
  it emits a WARNING and lets the edit through. Strengthened 2026-04-26 — was
  WARNING-only before.
- **Known violations** listed in `_ddd-boundaries.yaml` are exempted (no warning,
  no block) so the gate doesn't yell about pre-existing tech debt on every edit.
- **Bypass:** `SKIP_DDD_BOUNDARY_GATE=1`. Requires a decision log entry — the
  conditional-BLOCK branch is the one most likely to surface false positives, so
  the bypass is documented as a first-class escape hatch.
- The gate reads the YAML directly (not via the shared lib) for hot-path
  performance — every Edit/Write would otherwise pay a `source` cost. The
  authoritative regex still lives in the YAML.

### Layer H — Prior Art Audit gate (brainstorm exit, embedded in Layer B)

**File:** `.claude/hooks/validators/brainstorm-validator.sh` (the H block)

Runs as part of `brainstorm-validator.sh` when leaving the `brainstorming` phase.
Triggered when the spec references a critical context. Requires a `## Prior Art
Audit` section with at least one row classified as ✅ (endorsed), ❌ tech-debt,
or `new`. The trigger regex is read from `docs/knowledge/_ddd-boundaries.yaml`
via the shared helper `.claude/hooks/lib/ddd-boundaries.sh`
(`ddd_critical_regex()`) — the same source Layer F uses, eliminating the class
of "regex out of sync with YAML" bugs.

- **Pairs with Layer F.** H runs at spec time (cheap, conversation cost only);
  F runs at edit time (expensive — every keystroke). H catches the design
  failure early; F is a defense-in-depth net for changes whose spec slipped
  through audit.
- **HARD gate.** Brainstorm cannot exit without the section.
- The shared helper exposes `ddd_critical_regex` as a function; brainstorm-validator
  sources it. `socratic-review-validator.sh` (the architectural adversarial
  review hook invoked from brainstorm-validator) uses the same helper for its
  arch-keyword trigger.

### Layer I — [REMOVED 2026-04-26]

Historically, the retrospective-validator scanned the Lessons section for
architectural-concern keywords and required either positive content or an
explicit `retrospective_no_architectural_concerns=true` opt-out flag. Removed
2026-04-26 under the 4-test framework: the keyword scan failed Test 1 (the
retrospective phase already forces reflection via the visibility gate, so the
keyword check did not force a practice the model wouldn't apply spontaneously)
and Test 3 (the byte-level keyword regex was paying maintenance cost without
proportional value). The visibility gate, execution-log existence check, and
the ≥100-char Lessons section requirement are preserved — those tests pass
the 4-test independently. See execution log
`docs/superpowers/execution-logs/2026-04-26-4test-applied-FIJ.md`.

### Layer J — [REMOVED 2026-04-26]

Historically, the brainstorm-validator scanned the spec for pattern names and
cross-referenced `docs/knowledge/_graduations.yaml` to surface "this pattern
is already graduated" hints. Removed 2026-04-26 under the 4-test framework:
the scan failed Test 1 (the model already consults the manifest and knowledge
modules in Step 0 of brainstorming) and Test 2 (the right place to surface
graduation candidates is post-hoc, not at spec exit). `pattern-audit.sh`,
which runs at the `retrospective → finalize` boundary, provides the cleaner
post-hoc surfacing and is retained. See execution log
`docs/superpowers/execution-logs/2026-04-26-4test-applied-FIJ.md`.

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
| Spec mirrors tech-debt pattern without acknowledging | `brainstorm-validator.sh` Layer H — requires `## Prior Art Audit` with ≥1 row classified ✅ / ❌ tech-debt / `new` when spec touches critical contexts (regex from `_ddd-boundaries.yaml` via shared lib) |
| Edit adds ORM coupling in critical context without audit | `ddd-boundary-check.sh` Layer F — BLOCKS in full/debug when spec's Prior Art Audit doesn't cover the file; WARNING outside full/debug or with no spec; bypass `SKIP_DDD_BOUNDARY_GATE=1` |
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
| `SKIP_DDD_BOUNDARY_GATE=1` | Disables `ddd-boundary-check.sh` (Layer F) | Edits that legitimately touch critical contexts without adding new ORM coupling (e.g., refactoring an existing violation); decision-log entry required |

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
| `.claude/hooks/ddd-boundary-check.sh` | Layer F — PreToolUse Edit/Write gate; conditional BLOCK on ORM coupling in critical contexts (full/debug + missing Prior Art Audit row) |
| `.claude/hooks/lib/ddd-boundaries.sh` | Shared helper exposing `ddd_critical_regex()`. Reads `docs/knowledge/_ddd-boundaries.yaml`. Single source of truth for "what counts as a critical context"; consumed by Layer H trigger in brainstorm-validator and the arch-keyword trigger in socratic-review-validator. Layer F reads the YAML directly for hot-path performance |
| `.claude/hooks/validators/classify-validator.sh` | Layer A — blocks framework edits without full/debug classification |
| `.claude/hooks/validators/consult-validator.sh` | Phase B — requires `decisions_read` AND `logs_scanned` |
| `.claude/hooks/validators/brainstorm-validator.sh` | Phase B — spec size + keywords + anti-omission + parallel-wave conflict + Layer H Prior Art Audit gate (sources `lib/ddd-boundaries.sh`); also invokes `socratic-review-validator.sh` for the architectural adversarial review check |
| `.claude/hooks/validators/socratic-review-validator.sh` | Spec-exit hook invoked by brainstorm-validator — requires `## Architectural Adversarial Review` with ≥3 numbered Q/A entries when spec touches critical paths; arch-keyword trigger sources `lib/ddd-boundaries.sh` |
| `.claude/hooks/validators/planning-validator.sh` | Phase B — plan size/keywords |
| `.claude/hooks/validators/implementation-validator.sh` | File edit gate during implementation — TDD check |
| `.claude/hooks/validators/verification-validator.sh` | Phase B — tests_passed + lint_clean with strict/non-strict per flow |
| `.claude/hooks/validators/capture-validator.sh` | Phase B — execution_log_path set + file exists |
| `.claude/hooks/validators/retrospective-validator.sh` | Phase B — `retrospective_shown` + log section ≥100 chars (Layer I architectural-keyword scan REMOVED 2026-04-26) |
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
