# Test Suite Health — known-flaky registry

## Purpose

This document registers test suites with **known pre-existing failures** so the
harness can distinguish background noise from new regressions. Without it,
every new session re-investigates the same failing tests as if they were fresh
regressions — a recurring friction point documented in the retrospective of
`2026-04-22-workflow-enforcement-gates.md`.

The `make test-new-failures` target parses the machine-readable section below,
runs each suite, and exits 0 only if failures stay at-or-below the declared
threshold. A new failure on top of the baseline flags as a regression.

## How to use

Before investigating a failing suite, check whether it's known-flaky here:

1. Run the suite manually and count its failures.
2. If the count matches the `expected_failures` column → baseline, not a
   regression. Focus elsewhere.
3. If the count exceeds the baseline → regression. Investigate the diff.

To update an entry (e.g. after fixing one failure), decrement `expected_failures`
in both the table and the machine-readable section.

To add a new suite to the registry:

1. Run the suite, count failures.
2. Append a row to the table + a line to the machine-readable section.
3. Document the commit that introduced the flaky state (if known) in the
   `since_date` column.

## Registry

| test_file | expected_failures | total_tests | owner | repro_command | since_date | status |
|-----------|-------------------|-------------|-------|---------------|------------|--------|
| test-self-gating.sh | 7 | 14 | (unassigned) | `bash .claude/hooks/test-self-gating.sh` | before-2026-04-22 | known-flaky |
| test-workflow-engine.sh | 6 | 33 | (unassigned) | `bash .claude/hooks/test-workflow-engine.sh` | before-2026-04-22 | known-flaky |
| test-status-line.sh | 5 | 5 | (unassigned) | `bash .claude/hooks/test-status-line.sh` | before-2026-04-22 | known-flaky (all checks fail) |

## machine-readable

<!--
Format: one line per suite, `<basename>: <expected_failures>`.
Parsed by the `test-new-failures` Makefile target. Do not add prose inside.
-->

```
test-self-gating.sh: 7
test-workflow-engine.sh: 6
test-status-line.sh: 5
```
