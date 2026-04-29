---
type: refactor
tags: [harness, validator, lib-extraction, graduation, dry, spdd]
files_touched:
  - .claude/hooks/lib/section-validator.sh
  - .claude/hooks/lib/files-decl-parser.sh
  - .claude/hooks/validators/brainstorm-validator.sh
  - .claude/hooks/validators/sync-validator.sh
  - .claude/hooks/pre-agent-check.sh
  - docs/superpowers/specs/2026-04-28-harness-consolidation-design.md
  - docs/superpowers/plans/2026-04-28-harness-consolidation.md
patterns: [pattern-graduation, behavior-preserving-refactor, atomic-lib-extraction]
outcome: success
outcome_verified_at: 2026-04-28
regressions_later: []
pr_number: null
estimated_lines: 250
actual_lines: 280
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-28 — Harness Consolidation (lib extractions)

**Type:** refactor (no behavior change)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Backlog ref:** Follow-ups from logs 2026-04-28-layer-k, -norms-safeguards,
-sync, -agent-prompt. Sync fallback deferred to I5b.
**Spec:** `docs/superpowers/specs/2026-04-28-harness-consolidation-design.md`
**Plan:** `docs/superpowers/plans/2026-04-28-harness-consolidation.md`

## Summary

Pulled two duplicated patterns out of inline implementations into shared libs:

- **`lib/section-validator.sh`** — primitives `section_present`,
  `section_body`, `section_satisfied_inline_or_ref` (5 inline-check modes:
  imperative, risk-mitigation-table, classified-rows, positive-signal,
  multiline-bullet) + `section_extract_bullet`. Replaces 5 inline
  implementations (Layers H/K/N/S + pre-agent-check Gate 3).
- **`lib/files-decl-parser.sh`** — `parse_files_decl` (whole plan) +
  `tokenize_files_payload` (per-line). Replaces 2 inline parsers
  (brainstorm-validator parallel-conflict + sync-validator drift) and
  unifies backtick-stripping behavior.

Behavior preserved: all 31 existing tests pass post-refactor (19
brainstorm + 6 sync + 6 pre-agent). Side benefit: parallel-conflict
detection now handles backticked plan paths correctly (was a silent
asymmetry since commit `f4d6d36` fixed sync-validator only).

## Origin

Pattern graduation. Section-validation crossed 5 occurrences (H, K, N,
S, Layer Agent); files-decl parser at 2 with known asymmetry. CLAUDE.md
documents 3+ as graduation threshold; explicit follow-ups in 4
consecutive execution logs flagged this convergence.

## Approach Chosen

**B — Two extractions in this interaction; sync fallback deferred to
I5b (deviation).**

Pure refactor — no observable behavior change. Verified by running
all 31 existing tests with zero changes to test files. Sync fallback
(working-tree plan baseline) is a behavior change and gets its own
deviation interaction.

## Implementation observations

### 1. Lib path coupling discovered post-migration

`pre-agent-check.sh` initially sourced the lib via
`$REPO/.claude/hooks/lib/section-validator.sh`. The test harness rewrites
`REPO=` to point at fixture repos that lack `.claude/hooks/lib/`, so the
source failed silently and tests A3-A6 stopped executing mid-script
(`set -euo pipefail` aborted on the bad source). Fixed by hardcoding
the lib path: `/home/user/mxo-track/.claude/hooks/lib/section-validator.sh`.

This is an architectural inconsistency: tests should be able to
override REPO without breaking lib resolution. Follow-up:
graduate `LIB_DIR` as a separate constant decoupled from REPO.

### 2. tokenize_files_payload added mid-migration

Initial design only had `parse_files_decl` (whole-file). The
parallel-conflict detector iterates line-by-line tracking
current-wave + per-wave file map, so it needs per-line tokenization,
not whole-file parsing. Added `tokenize_files_payload` to the lib
during Wave 4. `parse_files_decl` now wraps awk + tokenize_payload.

## Changes

| File | Change |
|------|--------|
| `lib/section-validator.sh` | new (~95 lines) — 4 functions, 5 inline-check modes |
| `lib/files-decl-parser.sh` | new (~45 lines) — 2 functions |
| `validators/brainstorm-validator.sh` | -120 / +35 lines (5 inline blocks → 5 lib calls) |
| `validators/sync-validator.sh` | -15 / +5 lines (parser → lib call) |
| `pre-agent-check.sh` | -55 / +15 lines (Gate 3 → lib call) |
| Net | +280 lines added (libs grow more than callers shrink, but consolidated) |

## Verification

- `test-brainstorm-validator.sh` → **19/19 pass**
- `test-sync-validator.sh` → **6/6 pass**
- `test-pre-agent-check.sh` → **6/6 pass**
- `bash -n` clean on all 5 modified/new files
- Smoke: sync gate at I5's verification → capture → exit 0

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| section-validator.sh | 180 | 95 | -47% |
| files-decl-parser.sh | 30 | 45 | +50% |
| Caller deletions | -195 | -190 | OK |
| Caller additions | 45 | 55 | OK |
| Total net | ~250 | ~280 | +12% |
| Files | 8 | 8 | OK |

**Cause of section-validator gap (under):** I overestimated the
function count. The 5 inline-check modes consolidate into a single
case statement inside one function (`section_satisfied_inline_or_ref`),
not 5 separate functions. Cleaner than expected.

**Cause of files-decl-parser gap (over):** added per-line
`tokenize_files_payload` mid-implementation when parallel-conflict
turned out to need it. Original spec only foresaw whole-file parsing.

### 2. Process gaps

- **Test harness REPO rewriting incompatible with REPO-relative lib
  paths.** The bug in pre-agent-check sourcing took ~10 minutes to
  diagnose because `set -e` silently exited mid-script — no error
  message. Lesson: when `set -euo pipefail` aborts after a `source`
  fails, the test harness should be defensive enough to catch it.
  Mitigation: use absolute lib paths in hooks (already applied).
  Follow-up candidate: make the test harness print a diagnostic if the
  hook exits before reaching `summary`.

- **Sync gate baseline issue (third recurrence).** Same pattern as
  Hitos 2 and 4: plan in working tree but uncommitted → fallback to
  `origin/main` captures whole branch as drift. Workaround: commit
  before running sync gate. **This is exactly the I5b deferred fix.**

### 3. Emergent patterns

- **Behavior-preserving refactor verified by existing tests.** Pattern
  used here (extract → migrate caller-by-caller → run existing tests
  unchanged at each wave) is the standard refactor protocol from
  knowledge modules. Working as designed.

- **Pattern graduation pathway** confirmed at 5 occurrences. The
  CLAUDE.md threshold (3+) was conservative; in practice the model
  doesn't extract at 3, only when the refactor pays for itself in
  upcoming work (Hito 3 will be the 6th caller). Documenting:
  graduation threshold may need adjustment to "3+ AND a 4th use is
  imminent" to match observed behavior.

- **Hardcoded lib path** vs `$REPO`-derived path. Hardcoded is
  test-friendly but fragile if the repo moves. Trade-off accepted for
  now; follow-up to introduce a `LIB_DIR` constant.

## Follow-ups

1. **I5b — sync-validator working-tree fallback** (deviation, ~10
   lines): when plan is in working tree but uncommitted, baseline =
   HEAD instead of origin/main.
2. **`LIB_DIR` constant** decoupled from `REPO` for cleaner lib
   resolution under test harness.
3. **Hito 3 (Ubiquitous Language System)** — sixth caller of
   section-validator lib, validates the extraction's open-closed
   design (adding a 6th `inline_check` mode if needed should be
   trivial).
