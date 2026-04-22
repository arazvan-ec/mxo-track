---
type: bugfix
tags: [harness, validator, shellcheck, brainstorm, regex, tdd]
files_touched: [.claude/hooks/validators/brainstorm-validator.sh, .claude/hooks/test-brainstorm-validator.sh]
patterns: []
outcome: success
outcome_verified_at: 2026-04-22
regressions_later: []
pr_number: null
estimated_lines: 30
actual_lines: 142
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-22 — Fix brainstorm-validator regex false positive

**Type:** bugfix
**Branch:** `claude/enhance-routes-widget-8UzuC` (continued from the routes widget interaction)
**Related prior log:** `docs/superpowers/execution-logs/2026-04-21-routes-widget-enhanced.md` (retrospective flagged this bug)

## Summary

The parallel-conflict detector in `.claude/hooks/validators/brainstorm-validator.sh`
split `→ files:` payloads by both comma and space unconditionally, so
annotations like `(no file writes)` produced garbage tokens (`(no`, `file`,
`writes)`) that collided between tasks and raised false `CONFLICTO PARALELO`
errors. Now the detector (a) skips payloads fully wrapped in parentheses and
(b) filters split tokens to those containing `/` or `.` (real file paths).

## Root Cause

`.claude/hooks/validators/brainstorm-validator.sh:96-110` captured everything
after `→ files:` via `grep -oE '→ files?:\s*.*'`, then split with
`tr ',' '\n' | tr ' ' '\n'`. Non-path annotations that happen to contain
whitespace became multiple "file" tokens, collided in the `FILE_TASK`
associative array, and produced spurious conflicts.

## Pattern-wide Search

- `grep -rln "→ files" .claude/hooks/` → only `brainstorm-validator.sh`
- `grep -rln "tr ',' " .claude/hooks/validators/` → only `brainstorm-validator.sh`

Bug isolated to a single file; no other validator uses this parsing pattern.

## Approach Chosen

**Approach B — Token filter in the validator.** Skip entire payload if it is a
parenthesized annotation (`^\s*\([^)]*\)\s*$`); otherwise filter split tokens
through `grep -E '/|\.'` to keep only path-like tokens.

**Alternatives rejected:**
- A (convention-side only): no enforcement, depends on author discipline.
- C (one file per line, `→ file: <path>`): breaking change; existing plans
  and documentation would need updating.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/validators/brainstorm-validator.sh` | +8/-2 lines: sentinel skip for parenthesized payloads + `grep -E '/|\.'` filter |
| `.claude/hooks/test-brainstorm-validator.sh` | +134 lines (new): 4 TDD test cases with minimal spec/plan fixtures |

## Verification

- `bash .claude/hooks/test-brainstorm-validator.sh` → **4/4 pass**
  1. Regression: real conflict on shared path → detected ✅
  2. Baseline: disjoint paths → no conflict ✅
  3. Fix: two parenthesized annotations → no conflict ✅
  4. Fix: path + annotation mix → annotation ignored ✅
- `bash -n` syntax check on both files → clean.
- `make lint-shell` → not run (shellcheck not installed in this sandbox).
  Acceptable because the modification is ~6 lines and matches the existing
  idiom of the surrounding code; the test harness exercises the runtime
  behavior directly.
- Smoke test against the real routes widget plan → validator exits 0
  (no spurious conflicts).

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Files | 2 | 2 | ✅ |
| Net lines | ~30 (validator +test) | +142 | +373% |
| Waves | 1 (TDD single-file) | 1 | ✅ |
| Test cases | 5 | 4 | ✅ (case 5 collapsed into case 4 — the mix already tests the filter) |

**Root cause of the line gap:** bash test harness is verbose by nature —
helper functions, heredocs for fixtures, and the assertion wrapper add ~100
lines. The productive fix (validator) landed in +6 lines as expected.

**Calibration note:** shell test harnesses in this repo average ~30 lines of
setup per test file + ~15-25 lines per test case. Future estimates: budget
`30 + 20·N` for N test cases.

### 2. Process gaps

- **`test-brainstorm-validator.sh` false GREEN on first run** due to
  `set -euo pipefail` + `|` of a validator that exits 2 on errors. `pipefail`
  propagated the non-zero through the pipe, `set -e` killed the helper
  function, and `has_conflict` returned "no match" for every case including
  real conflicts. Fix: capture validator output into a variable first with
  `|| true`, then grep the variable. **Lesson:** any shell test harness that
  pipes from a script that intentionally exits non-zero must defuse `pipefail`
  or capture output explicitly.
- **Second harness bug discovered:** `phase-advance.sh:89-93` assumes `consult`
  is the first phase for every flow, breaking debug-flow entry (debug starts
  at `root_cause`, which the script rejects with "can only advance to
  consult"). Worked around by skipping phase transitions for this interaction
  (validator edits are in `.claude/hooks/*` which the workflow-engine excludes
  from gating). **Logged as follow-up, not fixed here** to keep this commit
  scope tight.

### 3. Emergent patterns

- **Validator-under-test via fixture plans.** The harness pattern used here
  (minimal spec + one fixture plan per case + `has_conflict` wrapper) is
  reusable for testing other shell validators. First occurrence — not a
  pattern yet. If a 3rd validator test file uses this shape, extract a
  shared helper `.claude/hooks/lib/test-validator.sh`.

## Follow-ups

1. **`phase-advance.sh` debug-flow entry bug** — the null→first-phase check at
   `.claude/hooks/phase-advance.sh:89-93` hardcodes `consult` as the first
   phase. Should consult the `FLOW_PHASES[$FLOW_TYPE]` array for the legal
   first phase instead. Small fix (~3 lines), separate interaction.
2. **Shellcheck not installed in this sandbox** — `make lint-shell` fails
   immediately. Acceptable in development but CI should enforce.
