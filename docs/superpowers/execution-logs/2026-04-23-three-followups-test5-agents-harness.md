---
type: bugfix
tags: [harness, followups, test-infra, agents-doc, test-harness-lib]
files_touched: [.claude/hooks/test-phase-advance.sh, AGENTS.md, docs/knowledge/workflow-engine.md, .claude/hooks/lib/test-harness.sh, .claude/hooks/test-flow-phases.sh, .claude/hooks/test-brainstorm-validator.sh, .claude/hooks/test-phase-advance-entry.sh]
patterns: [parallel-agent-dispatch, single-source-of-truth]
outcome: success
outcome_verified_at: 2026-04-23
regressions_later: []
pr_number: null
estimated_lines: 120
actual_lines: 192
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-23 — Three Parallel Follow-ups (Test 5 fix · AGENTS.md restriction · test-harness.sh)

**Type:** bundle of three follow-ups from 2026-04-22 retrospective
**Branch:** `claude/enhance-routes-widget-8UzuC`
**Related prior log:** `docs/superpowers/execution-logs/2026-04-22-knowledge-module-and-flow-phases-sot.md`

## Follow-ups Addressed

### #1 — Test 5 pre-existing failure

`test-phase-advance.sh` Tests 5 and 6 set `current_phase=consult` without
setting `decisions_read` or `logs_scanned`. Main's Option 3-Enforced wave 5
hardened `consult-validator` from OR to AND on those flags but did not
update these fixtures. Test 5 was failing; Test 6 was passing for the wrong
reason (the stronger block). Fix: set both flags on both fixtures. Result:
21/21 (was 20/21).

### #2 — Document `.claude/**` background-agent write restriction

The sandbox silently blocks background-agent writes to `.claude/**` regardless
of settings. Previous session rediscovered this the hard way when Problem B
of the flow-phases refactor was blocked. Added an "Agent Permission Model"
section to `AGENTS.md` (+52 lines) with the restriction, consequences, and
the split-by-path mitigation pattern. Cross-referenced from
`docs/knowledge/workflow-engine.md` (+12 lines).

### #3 — Extract `lib/test-harness.sh` (rule-of-three graduation)

With `test-flow-phases.sh`, `test-brainstorm-validator.sh`, and
`test-phase-advance-entry.sh` all duplicating PASS/FAIL counters, the
assert_* helpers, tmpdir+trap setup, and the summary footer, the pattern
crossed the graduation threshold. Extracted to
`.claude/hooks/lib/test-harness.sh`:

- `init_harness` — resets counters, creates `TEST_TMPDIR`, registers
  cleanup via `trap`.
- `pass`, `fail` — increment counters and print ✅/❌ with optional detail.
- `assert_eq`, `assert_contains`, `assert_not_contains` — build on pass/fail.
- `summary` — prints the footer and exits non-zero on any failure.

Refactored all three callers to source the lib. Each file's pass count is
preserved exactly (15/15, 4/4, 5/5).

## Parallel Dispatch Strategy

- **#2 (AGENTS.md docs):** dispatched as background agent. `AGENTS.md` is at
  repo root (not under `.claude/**`), so sandbox restrictions don't apply.
- **#1 (Test 5 fix):** 2-line edit under `.claude/hooks/`, done in foreground.
- **#3 (lib extraction):** creates new lib under `.claude/hooks/lib/` plus
  refactors 3 files under `.claude/hooks/`. Done in foreground.

Following the mitigation pattern documented in #2: split parallel work by
path surface — docs agent in background, `.claude/` work in foreground.

## Summary of Changes

| Scope | SHA | Files | Net diff |
|-------|-----|-------|----------|
| Test 5/6 fixtures | `c1c1d36` | 1 | +3/-2 |
| AGENTS.md + knowledge xref | `aa35596` | 2 | +64/-0 |
| test-harness.sh + 3 refactored tests | `fa83b29` | 4 | +122/-102 (net +20) |

## Verification

| Test file | Before | After |
|-----------|--------|-------|
| `test-flow-phases.sh` | 15/15 | 15/15 ✅ |
| `test-brainstorm-validator.sh` | 4/4 | 4/4 ✅ |
| `test-phase-advance-entry.sh` | 5/5 | 5/5 ✅ |
| `test-phase-advance.sh` | 20/21 | **21/21** ✅ |
| `test-phase-transition-controller.sh` | 7/7 | 7/7 ✅ |
| `test-enforcement-layers.sh` | 15/15 | 15/15 ✅ |

All four modified `.sh` files pass `bash -n` syntax check.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Files touched | 7 | 7 | ✅ |
| Net lines | ~120 | +192 (189 adds, -104 deletions; net +85) | +60% |
| Parallel tasks | 3 | 3 (1 agent + 2 foreground) | ✅ |
| Test regressions | 0 | 0 | ✅ |

The line gap came almost entirely from `AGENTS.md` — the agent wrote a
more thorough section than estimated (52 lines vs expected ~30), which is
fine: the restriction is surprising enough to warrant full context.

### 2. Process gaps

- **Split-by-path dispatch strategy worked cleanly.** The 2026-04-22
  mitigation pattern (`docs/*` in background, `.claude/**` in foreground)
  was the first real use — zero permission blockers this time.
- **No spurious commits to other paths.** Each commit touched only the
  paths in its scope — clean history for review/merge.
- **Consult-AND hardening cascaded into test breakage that wasn't
  surfaced by CI.** Test 5 was silently failing since the 2026-04-22
  merge. Worth checking whether the CI harness reports shell-test failures
  as build errors, or whether they're warnings-only. Filed as follow-up.

### 3. Emergent patterns

- **Split-by-path dispatch (2nd occurrence).** Previous session observed
  it accidentally when a background agent was blocked. This session
  applied it deliberately. If a 3rd occurrence applies the pattern, it
  graduates to the dispatch-rule knowledge in `AGENTS.md`.
- **Rule-of-three graduation still works for shell harnesses.** Prior
  retro flagged the graduation candidate at 2 occurrences; this session
  hit the 3rd and extracted. The retrospective-driven extraction timing
  is validated.

## Follow-ups

1. **Check CI visibility of shell-test failures.** Test 5 was failing in
   `test-phase-advance.sh` since 2026-04-22 but never surfaced as a CI
   error. Either CI doesn't run shell tests, or it runs them but doesn't
   gate the build. Should be investigated in a separate interaction.
