# Workflow Engine

## Status

**[CURRENT]** as of 2026-05-04 — Live layers: A, B (incl. B3 session-cut),
C (Architectural Adversarial Review), D, F, H, N, S, Sync, Agent. Removed
under the 4-test framework: I+J (2026-04-26, commit `231f951`), K
(2026-05-04, Hito 0.b). Last verified: 2026-05-04.

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

Ten active enforcement layers (A, B, C, D, F, H, N, S, Sync, Agent), orthogonal
to each other, each targeting a different class of shortcut. All run on every
relevant tool call. Three further layers (I, J, K) were retired under the 4-test
framework — they are documented below for archeological reasons (search hits
and history), but no longer execute.

### Layer A — Classification gate (PreToolUse on Edit/Write)

**File:** `.claude/hooks/validators/classify-validator.sh`

Blocks edits to framework/code paths when the interaction is classified
`micro|light|explore|informational|null`. The gate exists because the model's
natural bias is to call framework changes "light" to skip brainstorming.

- **Path normalization** (`classify-validator.sh:36-38`): accepts both
  repo-relative and absolute forms, trims to the first known framework root.
- **Carve-outs**: `docs/*`, `*.md`, `/tmp/*`, `.claude/session-state.json`
  always pass.
- **Framework regex**:
  `.claude/|scripts/|backend/src/|backend/templates/|backend/config/|backend/migrations/|backend/tests/|frontend/src/|ml-service/|docker/`.
- **Bypass:** `SKIP_CLASSIFY_GATE=1`. Requires a decision log entry.
- **Error output** includes a one-liner `jq` command to reclassify.

### Agent permission model (adjacent to Layer A)

Subagents inherit the main session's `session-state.json` — they read the same
file. Writes to framework paths (including `.claude/**`) from subagents are
subject to the same `classify-validator.sh` gate as the main session, and pass
iff `interaction_classification ∈ {full, debug}`.

**Practical consequence:** always confirm
`jq '.interaction_classification' .claude/session-state.json` returns
`"full"` or `"debug"` before dispatching a subagent that must edit
`.claude/**`.

### Layer B — Phase exit gates (phase-advance.sh)

**File:** `.claude/hooks/phase-advance.sh`

Phase-advance is the ONLY sanctioned way to write `phase_history`. Direct `jq`
writes are reverted by the controller logic embedded in `post-bash-validator.sh`
(formerly the standalone `phase-transition-controller.sh`, removed 2026-05-04
after consolidation). Every transition runs the validator for the phase being
**LEFT**.

- **Autodiscovery**: looks for `validators/${phase}-validator.sh` with a
  `${phase%ing}` fallback for "brainstorming" → `brainstorm-validator.sh`.
- **Exit codes:** validator exit 2 → blocks transition (HARD); exit 1 → stderr
  warning but advances (SOFT); exit 0 → silent pass.
- **Bypass:** `SKIP_PHASE_EXIT_GATE=1`. The script prints a visible warning
  when bypassed.
- **Side effects on target phase:** entering `retrospective` prints a
  three-point reminder; entering `implementation` auto-initializes
  `task_progress` if a plan path is set; entering `finalize` runs
  `pattern-audit.sh`.

### Layer B3 — Session-cut gates (sub-invocation of phase-advance)

**File:** `.claude/hooks/validators/session-cut-validator.sh`

Two transitions enforce a fresh-session boundary so the model reviews prior
work without confirmation bias:

- **`planning → implementation`** blocks when `evidence.plan_session_date`
  equals today's `session_date`.
- **`retrospective → finalize`** blocks when
  `evidence.last_code_commit_session_date` equals today's `session_date`.

**Bypass:** `SKIP_SESSION_CUT_GATE=1` with mandatory `docs/decisions/log.md`
entry. Reserved for emergency hotfixes where independent review is provably
waived.

Origin: 2026-04-30 cross-session resume hardening. Spec:
`docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md`.

### Layer C — TodoWrite mirror (PostToolUse on TodoWrite)

**File:** `.claude/hooks/todowrite-mirror.sh`

Three responsibilities:

1. **in_progress=1 invariant**: rejects inputs with >1 `in_progress` todo (exit 2).
2. **task_progress mirror**: derives `total`, `current`, `label` from todo
   list when no plan task_index is populated.
3. **problems.current derivation**: extracts `[prefix]` from active todo and
   matches against `work_context.problems.labels`.

### Layer C (Spec) — Architectural Adversarial Review

**File:** `.claude/hooks/validators/socratic-review-validator.sh`

Sub-invocation from `brainstorm-validator.sh` when a spec references critical
paths (regex from `_ddd-boundaries.yaml`). Requires a `## Architectural
Adversarial Review` section with ≥3 numbered Q/A entries, each ≥30 chars.
At least one question must contain an architectural keyword (endorsed,
boundary, DDD, tech-debt, architecture, coupling, pattern, tradeoff).

Relocated 2026-04-24 from a standalone post-verification phase to spec-exit
to eliminate rollback cost. The discrete validator is preserved (testable in
isolation, reusable).

### Layer D — Freshness warnings (PreToolUse, all tools)

**File:** `.claude/hooks/pre-tool-freshness.sh`

Non-blocking. Always exits 0. Emits `⚠ POSIBLE STALE STATE: <reason>` to
stderr when the upcoming tool call signals the session-state likely lags
reality.

### Layer F — DDD boundary check (PreToolUse on Edit/Write)

**File:** `.claude/hooks/ddd-boundary-check.sh`

Catches edits that introduce ORM coupling (`createQueryBuilder`,
`getRepository`) inside a critical aggregate context. Critical contexts read
from `docs/knowledge/_ddd-boundaries.yaml` (single source of truth, shared
with Layer H via `.claude/hooks/lib/ddd-boundaries.sh`).

- **Severity is conditional**: in `full`/`debug` flow, the gate **BLOCKS**
  when the spec's `## Prior Art Audit` does not cover the file. Outside
  `full`/`debug`, it WARNS.
- **Known violations** in `_ddd-boundaries.yaml` are exempted.
- **Bypass:** `SKIP_DDD_BOUNDARY_GATE=1`. Decision log entry required.

### Layer H — Prior Art Audit gate (brainstorm exit, embedded in Layer B)

**File:** `.claude/hooks/validators/brainstorm-validator.sh` (the H block)

Triggered when the spec references a critical context. Requires `## Prior Art
Audit` with at least one row classified as ✅ (endorsed), ❌ tech-debt, or
`new`. The trigger regex is read from `_ddd-boundaries.yaml` via shared lib
— same source as Layer F.

- **Pairs with Layer F.** H runs at spec time (cheap); F runs at edit time.
- **HARD gate.** Brainstorm cannot exit without the section.

### Layers N + S — Norms & Safeguards (universal, embedded in brainstorm-validator)

**File:** `.claude/hooks/validators/brainstorm-validator.sh` (N + S blocks)

**Universal HARD gates** — every spec in full/debug must include:

- **N (Norms):** `## Norms` section with ≥1 line containing an imperative
  keyword (must|shall|never|always|no se permite|no debe|siempre|jamás).
- **S (Safeguards):** `## Safeguards` section with a markdown table whose
  header has `Risk` + `Mitigation` columns and ≥1 data row.

Both can be satisfied inline OR by spec-reference (`docs/superpowers/specs/X.md`
within ~200 chars of the heading token).

Origin: 2026-04-28 hito 1, SPDD REASONS Canvas. Helpers in
`.claude/hooks/lib/section-validator.sh`.

### Layer Sync — Plan↔code drift detection (verification → capture)

**File:** `.claude/hooks/validators/sync-validator.sh`

Sub-invocation from `verification-validator.sh` at the verification → capture
boundary. Parses `→ files:` declarations from the plan, computes git diff
from the plan-introduction commit's parent (or `origin/main` fallback) plus
working tree, filters `WORKFLOW_ARTIFACTS_PATHS` (specs/plans/logs/manifest/
decision-log/session-state — scope of the gate, not exception list), and
blocks if any touched file is undeclared.

- **Three baseline strategies**: plan committed → parent of plan-introducing
  commit; plan on disk uncommitted → working-tree-only; plan path missing
  → `origin/main` fallback (test fixtures).
- **Bypass:** `SKIP_SYNC_GATE=1` (decision log entry required).

Origin: 2026-04-28 hito 2.

### Layer Agent — Norms & Safeguards in agent dispatches

**File:** `.claude/hooks/pre-agent-check.sh`

PreToolUse on Agent tool. Four gates:

1. **Dirty repo block** — non-`Explore` agents denied if uncommitted changes
   exist (forces clean handoff).
2. **Classify warning** — when prompt references `.claude/**` paths and
   classification is insufficient, emits a warn.
3. **Norms & Safeguards (HARD)** — non-`Explore` Agent dispatches must
   include `## Norms` (imperative keyword OR spec-reference) AND `## Safeguards`
   (Risk|Mitigation table OR spec-reference). Single source of truth: prompt
   can reference spec § Norms / § Safeguards instead of duplicating.
4. **Vocabulary WARN** — surfaces deprecated-alias mentions in the agent prompt.

Origin: 2026-04-28 hito 4, SPDD REASONS Canvas applied to subagent prompts.

### spec-compliance-validator (file edit gate during implementation)

**File:** `.claude/hooks/validators/spec-compliance-validator.sh`

Verifies code changes align with the active spec. Invoked from
`workflow-engine.sh` for `code` and `test` files during implementation phase.
Documented as part of the validator chain `brainstorm planning spec-compliance
implementation` for full-flow code edits.

### Layer I — [REMOVED 2026-04-26]

Historically: retrospective-validator scanned the Lessons section for
architectural-concern keywords. Removed 2026-04-26 under 4-test: T1 (the
retrospective phase already forces reflection via the visibility gate); T3
(byte-level keyword regex without proportional value). The visibility gate,
execution-log existence check, and ≥100-char Lessons section are preserved.
See `docs/superpowers/execution-logs/2026-04-26-4test-applied-FIJ.md`.

### Layer J — [REMOVED 2026-04-26]

Historically: brainstorm-validator scanned the spec for pattern names and
cross-referenced `_graduations.yaml` to surface "this pattern is already
graduated" hints. Removed 2026-04-26: T1 (model already consults the
manifest in Step 0); T2 (right place to surface graduation candidates is
post-hoc, not at spec exit). `pattern-audit.sh` provides cleaner post-hoc
surfacing at retrospective → finalize.

### Layer K — [REMOVED 2026-05-04]

Historically: brainstorm-validator detected reduction markers (MVP, v0,
ligero, "minimum viable") outside fenced code blocks and required a
`## Maximal Version Considered` section with an "Independent superiority"
bullet defended by design-quality keywords (not cost). Removed 2026-05-04
under Hito 0.b retrospective application of the 4-test:

- **T1 failed** — regex check verified section presence, not rigor of
  reasoning. Exemplifies the P3 (structure-vs-rigor) failure mode the
  harness intends to prevent.
- **T3 failed** — ~40 LOC validator block + fenced-code-block stripping +
  helper functions in `section-validator.sh` (positive-signal,
  multiline-bullet modes; `section_extract_bullet` function). Maintained
  for 1 documented case which was itself a recursive false positive on
  Layer K's implementation spec.
- **T4 weak** — single origin log
  (`docs/superpowers/execution-logs/2026-04-28-layer-k-anti-reduction-validator.md`)
  documenting the recursive false positive.

The semantic role (forcing maximal-version consideration) is preserved by
user approval of the spec design — when the user evaluates alternatives in
brainstorming and explicitly approves the maximal one, regex enforcement is
redundant. Re-introduce only if 3+ retrospectives document "MVP-first
without maximal" with concrete cost. Spec:
`docs/superpowers/specs/2026-05-03-harness-pruning-hito-0b-design.md`.

## Phase Evidence Matrix

Adapted from `.claude/README.md`.

| Phase | Evidence required | Level | Validator |
|-------|-------------------|-------|-----------|
| `consult` | `decisions_read` AND `logs_scanned` | HARD | `consult-validator.sh` |
| `brainstorming` | `user_turns ≥ 1` (HARD) + SOFT warn if `< 3` + `alternatives_proposed` + `user_approved` + `spec_path` (file ≥500B) + `## Existing Functionality Inventory` + `## Omission Decisions` + `## Norms` (N) + `## Safeguards` (S) + (conditional) `## Prior Art Audit` (H) + `## Architectural Adversarial Review` (C) + parallel-wave file-conflict check | MIXED | `brainstorm-validator.sh` (+ embedded N, S, H, C; K removed) |
| `planning` | `plan_path` (file ≥300B, contains plan keywords) + B3 session-cut on `planning → implementation` | HARD | `planning-validator.sh` + `session-cut-validator.sh` |
| `implementation` | plan exists (HARD) + `tests_written > 0` (SOFT warn) + spec-compliance per code edit | MIXED | `implementation-validator.sh` + `spec-compliance-validator.sh` |
| `verification` | `tests_passed` AND `lint_clean` must be `true\|false` in `full`/`debug`; `"skipped"` only in light/informational/explore/micro flows + Sync gate (plan↔code drift) | MIXED | `verification-validator.sh` + `sync-validator.sh` |
| `capture` | `execution_log_path` set AND file exists on disk | HARD | `capture-validator.sh` |
| `retrospective` | `retrospective_shown=true` (visibility gate) + execution log contains Lessons section ≥100 chars | HARD | `retrospective-validator.sh` |
| `finalize` | `branch_strategy ∈ {merge,pr,keep,discard}` + B3 session-cut on `retrospective → finalize` + knowledge module freshness check | MIXED | `finalize-validator.sh` + `session-cut-validator.sh` |
| `debug-code` (file edit gate) | `decisions_read` OR `logs_scanned` + `root_cause_identified` + `pattern_wide_search_done` | HARD | `debug-validator.sh` |

## Shortcuts Catalog

| Shortcut | Gate |
|----------|------|
| Calling framework changes "light" to skip brainstorm | `classify-validator.sh` (Layer A) |
| `consult → brainstorm` without reading decisions/logs | `consult-validator.sh` — requires BOTH flags |
| `brainstorm → planning` without alternatives or approval | `brainstorm-validator.sh` — requires `alternatives_proposed`, `user_approved`, `spec_path`, ≥1 user turn |
| Spec mirrors tech-debt pattern without acknowledging | Layer H — Prior Art Audit (conditional on critical contexts) |
| Edit adds ORM coupling in critical context without audit | Layer F — `ddd-boundary-check.sh` (conditional BLOCK) |
| Spec lacks invariants or risk-mitigation pairing | Layers N + S — universal section gates |
| Spec lacks adversarial architectural review | Layer C — `socratic-review-validator.sh` (conditional on critical paths) |
| `verification → capture` without running tests/lint | `verification-validator.sh` — `tests_passed` and `lint_clean` must be `true` |
| Code drifts from plan declarations | Layer Sync — `sync-validator.sh` (verification → capture) |
| Subagent prompt lacks architectural framing | Layer Agent — `pre-agent-check.sh` Gate 3 |
| `capture → retrospective` without writing the execution log | `capture-validator.sh` — path set AND file exists |
| `retrospective → finalize` without presenting retrospective | `retrospective-validator.sh` — `retrospective_shown=true` |
| `planning → implementation` or `retrospective → finalize` same-day | Layer B3 — `session-cut-validator.sh` |
| Forgetting to advance `problems.current` when switching petitions | Layer C TodoWrite — auto-derives from `[prefix]` |
| Multiple `in_progress` todos at once | Layer C TodoWrite — exit 2 |
| Stale session-state when committing/writing artifacts | Layer D — `pre-tool-freshness.sh` (non-blocking) |

## Bypass Env Vars

Every HARD gate has a documented escape hatch. Using a bypass **requires** a
corresponding entry in `docs/decisions/log.md`.

| Env var | Effect | Documented uses (as of 2026-05-04) |
|---------|--------|------------------------------------|
| `SKIP_CLASSIFY_GATE=1` | Disables `classify-validator.sh` | 0 |
| `SKIP_PHASE_EXIT_GATE=1` | Disables all phase exit validators in `phase-advance.sh` | 5 (incl. SessionStart:resume reset cases) |
| `SKIP_DDD_BOUNDARY_GATE=1` | Disables `ddd-boundary-check.sh` | 0 |
| `SKIP_SYNC_GATE=1` | Disables `sync-validator.sh` | 0 |
| `SKIP_SESSION_CUT_GATE=1` | Disables `session-cut-validator.sh` | 0 (used in this Hito 0.b session — entry pending) |

There is no bypass for Layer C TodoWrite (in_progress=1 invariant is
non-negotiable) or Layer D (already non-blocking).

A gate that blocks legitimate work repeatedly is a gate whose conditions need
to be tuned — not a gate to silence. Bypasses with <3 documented uses after
≥4 weeks of operation are candidates for removal in subsequent Hitos.

## File Index

| File | Role |
|------|------|
| `.claude/hooks/phase-advance.sh` | CLI for legal phase transitions; runs exit validator; side-effects (retro reminder, plan-progress init, pattern-audit, session-cut) |
| `.claude/hooks/post-bash-validator.sh` | PostToolUse:Bash — auto-evidence + workflow-status-line + (formerly standalone) phase-transition-controller logic, all inlined |
| `.claude/hooks/user-prompt-state.sh` | UserPromptSubmit hook — injects status line, processes approval/rejection regex, gates retrospective_shown on phase=retrospective |
| `.claude/hooks/workflow-status-line.sh` | Renders the full-format status line |
| `.claude/hooks/pre-tool-freshness.sh` | Layer D — stale-state warnings (non-blocking) |
| `.claude/hooks/todowrite-mirror.sh` | Layer C — `in_progress=1` invariant + task_progress/problems derivation |
| `.claude/hooks/ddd-boundary-check.sh` | Layer F — PreToolUse Edit/Write gate; conditional BLOCK on ORM coupling in critical contexts |
| `.claude/hooks/pre-agent-check.sh` | Layer Agent — PreToolUse:Agent gate; dirty-repo block + classify-warn + Norms/Safeguards (HARD) + vocab WARN |
| `.claude/hooks/lib/ddd-boundaries.sh` | Shared helper — `ddd_critical_regex()` reads `_ddd-boundaries.yaml` |
| `.claude/hooks/lib/section-validator.sh` | Shared helpers — `section_present`, `section_body`, `section_satisfied_inline_or_ref` (modes: imperative, risk-mitigation-table, classified-rows). Used by N, S, H, Layer Agent |
| `.claude/hooks/lib/files-decl-parser.sh` | Shared helper — parses `→ files:` declarations from plan. Used by Sync and brainstorm-validator parallel-wave check |
| `.claude/hooks/validators/classify-validator.sh` | Layer A — blocks framework edits without full/debug |
| `.claude/hooks/validators/consult-validator.sh` | Phase B — requires `decisions_read` AND `logs_scanned` |
| `.claude/hooks/validators/brainstorm-validator.sh` | Phase B — spec size + keywords + anti-omission + Layer N + Layer S + Layer H + Layer C invocation + parallel-wave conflict |
| `.claude/hooks/validators/socratic-review-validator.sh` | Layer C — Architectural Adversarial Review (≥3 Q/A, arch keyword) |
| `.claude/hooks/validators/planning-validator.sh` | Phase B — plan size/keywords |
| `.claude/hooks/validators/implementation-validator.sh` | File edit gate — TDD check |
| `.claude/hooks/validators/spec-compliance-validator.sh` | File edit gate — code-vs-spec alignment |
| `.claude/hooks/validators/verification-validator.sh` | Phase B — tests_passed + lint_clean + Sync invocation |
| `.claude/hooks/validators/sync-validator.sh` | Layer Sync — plan↔code drift |
| `.claude/hooks/validators/capture-validator.sh` | Phase B — execution_log_path + file exists |
| `.claude/hooks/validators/retrospective-validator.sh` | Phase B — `retrospective_shown` + Lessons ≥100 chars |
| `.claude/hooks/validators/finalize-validator.sh` | Phase B — branch_strategy + knowledge module freshness |
| `.claude/hooks/validators/session-cut-validator.sh` | Layer B3 — fresh-session boundary on planning→impl and retro→finalize |
| `.claude/hooks/validators/debug-validator.sh` | File edit gate for debug flow |

## Known Inconsistencies (as of 2026-05-04)

Tensions that don't block correctness; they exist because subsystems evolved
independently.

- **Debug flow phase names diverge between phase-advance and status-line scripts.**
  `phase-advance.sh:39` defines the debug sequence as
  `root_cause pattern_wide fix verification capture retrospective finalize`;
  the status-line scripts use `DEBUG_PHASES=("consult" "root_cause" "pattern_search" "fix")`.
  Phase-advance is the operative source of truth. Reconciliation is a
  follow-up.

## Relationship to CLAUDE.md

CLAUDE.md contains the *discipline* — what the model should do, why, and
what shortcut each rule prevents. This module describes the *mechanism* —
which hook enforces which rule, file by file. If CLAUDE.md changes a rule,
this module should be updated; if a hook changes behavior, CLAUDE.md should
be updated.
