---
type: feature
tags: [harness, validator, sync, plan-drift, spdd, tdd]
files_touched:
  - .claude/hooks/validators/sync-validator.sh
  - .claude/hooks/test-sync-validator.sh
  - .claude/hooks/validators/verification-validator.sh
  - CLAUDE.md
  - docs/superpowers/specs/2026-04-28-sync-validator-design.md
  - docs/superpowers/plans/2026-04-28-sync-validator.md
patterns: [conditional-hard-gate, plan-baseline-anchoring, workflow-artifact-scope]
outcome: success
outcome_verified_at: 2026-04-28
regressions_later: []
pr_number: null
estimated_lines: 200
actual_lines: 230
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-28 — Hito 2: Sync Validator (Plan ↔ Diff drift)

**Type:** feature (harness — workflow gate)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Backlog ref:** Hito 2 of 5, SPDD analysis 2026-04-28
**Spec:** `docs/superpowers/specs/2026-04-28-sync-validator-design.md`
**Plan:** `docs/superpowers/plans/2026-04-28-sync-validator.md`

## Summary

Added `sync-validator.sh` invoked from `verification-validator.sh` at
`verification → capture`. Parses `→ files:` declarations from the plan,
computes git diff anchored at the plan-introduction commit's parent
(falling back to `origin/main`), filters out workflow artifact paths,
and blocks if any touched file is undeclared.

Two design decisions during implementation are notable:

1. **Plan-introduction anchoring instead of `origin/main`.** Initial
   smoke test against the real branch (which had accumulated 5
   prior interactions of work) flagged ~30 unrelated files as drift —
   the gate was scoping the entire branch, not the current interaction.
   Switched baseline to `git log --diff-filter=A --reverse -- "$PLAN_PATH" | head -1`'s
   parent commit. Falls back to `origin/main` for fixtures or
   first-commit-on-branch cases.

2. **Backtick stripping in the path parser.** Real plans format
   `→ files:` declarations with backticks (`` → files: `path/to/file.sh` ``),
   while the existing parser in `brainstorm-validator.sh:228+` does
   not strip them. Smoke test caught the mismatch immediately. Added
   `tr -d '\`'` to the sync-validator parser. Follow-up: align
   brainstorm-validator's parser when extracting `lib/section-validator.sh`
   (graduation pending from Layer K + N+S convergence).

## Origin

External analysis (Manus) proposed a SOFT warning at finalize. Two
errors in that proposal were rejected:

- **Severity:** SOFT is the recoil pattern blocked by Layer K
  (commit `0923cdb`).
- **Phase:** finalize is too late — push imminent, rollback cost is
  highest. `verification → capture` is the natural moment: tests
  pass, log not yet written, drift is correctable in minutes.

## Approach Chosen

A — HARD gate at verification → capture, separate validator invoked
from `verification-validator.sh` (mirrors Layer C sub-invocation
pattern: `brainstorm-validator → socratic-review-validator`).

Scope explicitly bounded by `WORKFLOW_ARTIFACTS_PATHS`:

- `docs/superpowers/(specs|plans|execution-logs|retrospectives)/`
- `docs/codebase-manifest.md`
- `docs/decisions/log.md`
- `.claude/{session-state,parallel-tasks}.json`

Documented in spec and CLAUDE.md as "scope of the gate, not exception
list" — these paths have no canonical declaration in any plan because
they are workflow output, not feature deliverable.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/validators/sync-validator.sh` | new (~95 lines): parser + baseline + drift computation + workflow filter + bypass |
| `.claude/hooks/test-sync-validator.sh` | new (~120 lines): 5 TDD fixtures with constructed git fixtures |
| `.claude/hooks/validators/verification-validator.sh` | +14 lines: sub-invocation block |
| `CLAUDE.md` | +2 rows (gates table + bypass env var) |

Net lines: ~230 (estimate 200; +15% — within calibration).

## Verification

- `bash .claude/hooks/test-sync-validator.sh` → **6/6 pass** (5 unique
  cases + 1 sub-assertion on Y2 drift output)
- `bash .claude/hooks/test-brainstorm-validator.sh` → **19/19 pass** (no regression)
- `bash -n` syntax check → clean
- Smoke test: validator against this very interaction's plan after
  commit → exit 0 (all touched files declared).
- Forward chain: phase-advance verification → capture executed
  successfully, sync sub-invocation passed cleanly.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Validator | +70 | +95 | +36% |
| Tests | +120 | +120 | OK |
| verification-validator | +6 | +14 | +133% |
| CLAUDE.md | +3 | +2 | OK |
| Total | ~200 | ~230 | +15% |

**Root cause of the gap:** baseline-anchoring logic (plan-introduction
commit detection + working-tree diff merge) added ~25 lines I had not
budgeted. The original plan assumed `origin/main` as baseline; smoke
test forced the rethink mid-implementation. Calibration: when a
validator depends on git history anchoring, budget +30 lines for the
anchor logic alone.

### 2. Process gaps

- **Smoke test against real branch is irreplaceable.** The unit
  fixtures all passed before I noticed the baseline issue. Only the
  smoke test (real plan + real branch with accumulated commits) caught
  it. Lesson: always include a smoke test step in the plan's
  verification phase, separate from unit fixtures. This was already
  done here; reinforce in future hitos.

- **Parser asymmetry between validators.** The brainstorm-validator's
  parallel-conflict parser (line 228+) does not strip backticks, while
  the sync-validator does. Both consume the same `→ files:` format.
  This is the second piece of evidence that the parser should be
  shared. Adds urgency to the `lib/section-validator.sh` graduation
  pending from Layers K + N + S.

### 3. Emergent patterns

- **Plan-introduction anchoring** as a baseline-determination pattern.
  Single occurrence so far. If a second validator needs the same
  baseline (e.g., a future "test coverage delta vs plan" check), this
  graduates to a shared helper.

- **Sub-invocation pattern (Layer C-style)** now used for the second
  time in the harness. First: `brainstorm-validator → socratic-review-validator`
  (Layer C). Second: `verification-validator → sync-validator` (Layer Sync).
  Pattern is stabilizing as the right way to compose validators.

## Follow-ups

1. **Extract shared `→ files:` parser** with backtick-stripping into
   `lib/files-decl-parser.sh`. Used by brainstorm-validator (parallel
   conflict) and sync-validator (drift). Currently duplicated.
2. **Edit-time PreToolUse warning** as complementary layer — flag
   when an Edit/Write targets a file outside the plan, before
   verification catches it. Recorded in spec as out-of-scope follow-up.
3. **Brainstorm-validator parser alignment** — port the
   backtick-stripping fix back so parallel-conflict detection works
   on real plan formatting. Graduates with the shared lib extraction
   in follow-up #1.
