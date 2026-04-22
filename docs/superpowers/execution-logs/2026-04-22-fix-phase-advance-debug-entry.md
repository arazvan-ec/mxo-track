---
type: bugfix
tags: [harness, phase-advance, debug-flow, agent-flow, testability, tdd]
files_touched: [.claude/hooks/phase-advance.sh, .claude/hooks/test-phase-advance-entry.sh]
patterns: []
outcome: success
outcome_verified_at: 2026-04-22
regressions_later: []
pr_number: null
estimated_lines: 30
actual_lines: 84
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-22 — Fix phase-advance debug/agent flow entry

**Type:** bugfix
**Branch:** `claude/enhance-routes-widget-8UzuC`
**Related prior log:** `docs/superpowers/execution-logs/2026-04-22-fix-brainstorm-validator-regex.md` (flagged this as follow-up #1)

## Summary

`.claude/hooks/phase-advance.sh:89-93` hardcoded `"consult"` as the only legal
first phase for any flow, silently breaking debug-flow entry (which starts at
`root_cause`) and agent-flow entry (which starts at `implementation`). The fix
replaces the literal with a lookup of `PHASES[0]`, which is already defined
from `FLOW_PHASES[$FLOW_TYPE]` earlier in the same script.

Made the script testable by accepting a `STATE_FILE` env override so tests can
run against isolated fixtures without touching the real session-state.

## Root Cause

```bash
# .claude/hooks/phase-advance.sh (before)
if [ "$CURRENT_PHASE" = "null" ] || [ "$CURRENT_INDEX" -eq -1 ]; then
  if [ "$NEXT_PHASE" != "consult" ]; then
    echo "ERROR: From null/undeclared phase, can only advance to 'consult'..."
    exit 1
  fi
fi
```

Despite correctly building `PHASES` from `FLOW_PHASES[$FLOW_TYPE]` at lines
37-48, the entry check at line 90 ignores it and compares against a literal
`"consult"`. For `flow_type=debug` (first phase = `root_cause`) and
`flow_type=agent` (first phase = `implementation`), every valid entry was
rejected.

## Pattern-wide Search

- `grep -rn '"consult"' .claude/hooks/ | grep -v test-` → only
  `phase-advance.sh:90` uses `"consult"` as a validation literal.
  Other matches are listings for status-line rendering (`user-prompt-state.sh`,
  `workflow-status-line.sh`, `workflow-status.sh`) or file-type routing
  (`workflow-engine.sh:175`) — orthogonal concerns.

Bug isolated to a single site.

**Orthogonal inconsistency found** (not fixed here):
`user-prompt-state.sh:206` and `workflow-status-line.sh:521` define
`DEBUG_PHASES=("consult" "root_cause" "pattern_search" "fix")`, but
`phase-advance.sh:39` defines
`FLOW_PHASES[debug]="root_cause pattern_wide fix verification capture retrospective finalize"`.
Two discrepancies: `consult` appears in status-line listings but not in
phase-advance's legal sequence; `pattern_search` (status-line) vs
`pattern_wide` (phase-advance). **Filed as follow-up #1 for a future
interaction** — single-source-of-truth refactor.

## Approach Chosen

- **Change 1 (refactor for testability):**
  `STATE_FILE="${STATE_FILE:-$REPO/.claude/session-state.json}"`.
  Env override for tests; no behavior change under normal invocation.
- **Change 2 (fix):** replace literal `"consult"` with `"${PHASES[0]}"`;
  error message now says `can only advance to '$FIRST_PHASE' (flow: $FLOW_TYPE)`.

No alternatives were strong enough to prefer. Change 1 is a standard bash
idiom; change 2 reuses an array already built a few lines above.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/phase-advance.sh` | +5/-3: STATE_FILE env override + replace hardcoded "consult" with PHASES[0] |
| `.claude/hooks/test-phase-advance-entry.sh` | +79 lines (new): 5 test cases (full/debug/agent entry acceptance + full/debug entry rejection) |

## Verification

- `bash .claude/hooks/test-phase-advance-entry.sh` → **5/5 pass**
  1. full baseline: null → consult accepted ✅
  2. full rejects: null → root_cause denied ✅
  3. debug fix: null → root_cause accepted ✅ (**was failing before**)
  4. debug rejects: null → consult denied ✅
  5. agent fix: null → implementation accepted ✅ (**was failing before**)
- Regression test: `bash .claude/hooks/test-brainstorm-validator.sh` → **4/4 pass**
  (no collateral damage).
- `bash -n` syntax on both files → clean.
- `make lint-shell` → not run (shellcheck absent in sandbox).

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Files | 2 | 2 | ✅ |
| Validator net lines | ~3 | +5/-3 | ≈ ✅ |
| Test cases | 3 | 5 | +67% (added reject-direction cases) |
| Total new lines | ~30 | +84 | +180% |

Reject-direction cases were cheap to add during planning and doubled the
coverage — good trade.

### 2. Process gaps

- **Script not testable without env override.** Blocking — had to include the
  refactor as a precondition to writing the test. **Lesson:** harness scripts
  should accept env overrides by default (pattern: `${VAR:-default}`).
- **Status line during this interaction was stale** — the UserPromptSubmit
  status line showed "Root_cause (2/4)" while the flow was actually further
  along. Didn't affect correctness but confirms the model can diverge from
  hook-displayed state without penalty. Minor.

### 3. Emergent patterns

- **Shell validator test harness (2nd occurrence)** — mirrors
  `test-brainstorm-validator.sh`: tmp fixture dir, `assert_*` helper,
  exit-code + post-state check, exit non-zero on any failure. **3rd
  occurrence → extract** `.claude/hooks/lib/test-harness.sh` with
  `tmp_state()`, `assert_eq()`, and `summary()` helpers.
- **Env-override testability** — at least 2 scripts (phase-advance.sh,
  plan-progress.sh? — not verified) could benefit. If 3+ scripts need
  testing, standardize the `${STATE_FILE:-...}` pattern in `.claude/README.md`
  as a convention.

## Follow-ups

1. **Cross-file flow definition inconsistency** (noted above): reconcile
   `DEBUG_PHASES` between `phase-advance.sh`, `user-prompt-state.sh`, and
   `workflow-status-line.sh`. Single source of truth — likely extract to a
   shared `.claude/hooks/lib/flow-phases.sh` sourced by all consumers.
2. **Extract shared shell-test helpers** once a 3rd validator test appears.
