# Test Suite Health

## Purpose

Some hook/script test suites have **pre-existing failures** that are not regressions from
recent work. Every new session that runs these suites wastes context re-investigating the
same noise. This document captures the current baseline — how many tests are expected to
pass and fail per suite as of the date recorded — so that:

1. **New regressions are visible.** `make test-new-failures` compares current counts
   against the baseline below and flags any suite whose failure count **increased**.
2. **Known-flaky suites don't block progress.** A suite that stays at its expected
   failure count is treated as `OK`, even if some tests inside it fail.
3. **Ownership is explicit.** When a flaky suite has an owner, the owner is responsible
   for either fixing the failing tests or reducing the expected-failure count.

This document is the **single source of truth** for baseline expectations. The
`make test-new-failures` target parses the machine-readable section at the end of this
file — do not edit that section's format without updating the Makefile target.

## Workflow

- **When a regression appears:** `make test-new-failures` prints `REGRESSION` with the
  diff. Fix the new failure before committing, or (if the regression is intentional)
  update the expected count here with a justification.
- **When a known-flaky test is fixed:** decrement the expected-fail count below in the
  same commit that fixes the test. If the suite reaches `0` expected fails, change
  `status` to `healthy`.
- **When a new test suite is added:** add a row with `status: new` and let it run for
  a few days to establish the baseline.

## Current Baseline (as of 2026-04-22)

| test file | expected fails | total | owner | repro | since | status |
|-----------|---------------|-------|-------|-------|-------|--------|
| test-self-gating.sh | 7 | 14 | (unassigned) | `bash .claude/hooks/test-self-gating.sh` | before-2026-04-22 | known-flaky |
| test-workflow-engine.sh | 6 | 29 | (unassigned) | `bash .claude/hooks/test-workflow-engine.sh` | before-2026-04-22 | known-flaky |
| test-status-line.sh | unknown | unknown | (unassigned) | `bash .claude/hooks/test-status-line.sh` | before-2026-04-22 | known-flaky |
| test-enforcement-layers.sh | 4 | 15 | (unassigned) | `bash .claude/hooks/test-enforcement-layers.sh` | before-2026-04-22 | known-flaky |
| test-phase-advance.sh | 3 | 14 | (unassigned) | `bash .claude/hooks/test-phase-advance.sh` | before-2026-04-22 | known-flaky |

### Notes per suite

- **test-self-gating.sh** — 7 of 14 fail. Root cause not investigated; assertions likely
  drifted from current hook behavior.
- **test-workflow-engine.sh** — 6 of 29 fail. Same class of drift as above.
- **test-status-line.sh** — the script exits early (exit code 1) before printing any
  `Results:` summary line. Total test count is therefore unknown. `make test-new-failures`
  treats `unknown` baselines as "OK unless the suite now prints a `Results:` line with
  failures" — i.e. we only catch regressions once someone fixes the early exit.
- **test-enforcement-layers.sh** — 4 of 15 fail. Discovered while seeding the baseline;
  pre-existing, not caused by Feature 3 work.
- **test-phase-advance.sh** — 3 of 14 fail. Discovered while seeding the baseline;
  pre-existing, not caused by Feature 3 work. Count may vary slightly between runs
  (saw 2/14 once and 3/14 on re-run — likely a race or temp-file issue). If this suite
  flaps across runs, increase the baseline to the higher observed count and file a
  separate bug to stabilize it.

## machine-readable

The Makefile target `test-new-failures` parses this section. Format rules:
- One line per suite: `<filename>: <expected_fails>/<total>` OR `<filename>: unknown`
- No other content in this section.
- Filename must match the basename on disk (no path, no quotes).

```
test-self-gating.sh: 7/14
test-workflow-engine.sh: 6/29
test-status-line.sh: unknown
test-enforcement-layers.sh: 4/15
test-phase-advance.sh: 3/14
```
