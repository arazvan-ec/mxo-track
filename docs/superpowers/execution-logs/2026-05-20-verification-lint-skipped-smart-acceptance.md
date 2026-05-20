---
type: feature
tags: [harness, verification-validator, lint-skipped, smart-acceptance, dry-helper, meta-validation]
files_touched:
  - .claude/hooks/lib/git-refs.sh
  - .claude/hooks/validators/verification-validator.sh
  - .claude/hooks/pre-push-gate.sh
  - .claude/hooks/test-verification-validator.sh
patterns:
  - shared-helper-extraction
  - smart-conditional-acceptance
  - soft-warn-propagation
  - meta-validation
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 100
actual_lines: 230
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-05-20 — Verification `lint_clean=skipped` Smart Acceptance (P3 of 3)

## Spec / Plan
- Spec: `docs/superpowers/specs/2026-05-20-verification-lint-skipped-smart-acceptance-design.md`
- Plan: `docs/superpowers/plans/2026-05-20-verification-lint-skipped-smart-acceptance.md`

## Brainstorming
- **Alternatives:** A (2 scenarios + lint_skip_reason + ⚠ propagation), B (shellcheck-only scenario), C (manual `lint_skip_reason` field). Chose A.
- **Maximal version considered (and rejected):** install shellcheck in sandbox. Rejected on pattern-alignment + single-source-of-truth + generalizability grounds (not cost).
- **Complexity estimate:** ~80 lines (helper + validator + propagation + tests).

## Planning
- Wave 1 (extract `lib/git-refs.sh::get_plan_commit_parent` as foundation) → Wave 2 (TDD red, 7 cases) → Wave 3 (TDD green: smart accept + pre-push propagation) → Wave 4 (verification).

## Implementation
- **`.claude/hooks/lib/git-refs.sh`:** new helper. `get_plan_commit_parent()` reads `evidence.plan_path`, finds the introducing commit, returns its parent. Fallback to `origin/main` when plan missing or uncommitted. 25 lines.
- **`verification-validator.sh`:** extended `lint_clean=skipped` case with two scenarios — (1) `command -v shellcheck` fails → `SMART_ACCEPT=1, LINT_SKIP_REASON=shellcheck_missing`, (2) no `*.sh`/`*.bash` files in `git diff $DIFF_BASE...HEAD` plus working tree → `SMART_ACCEPT=1, LINT_SKIP_REASON=no_shell_files_in_diff`. When accepted, writes `evidence.lint_skip_reason` and propagates as ⚠ via WARNINGS path (exit 1, not 0).
- **`pre-push-gate.sh`:** when `lint_clean=skipped`, surfaces `lint_skip_reason` in checklist: `⚠ lint_clean (skipped: <reason>)`. Provides visibility downstream.
- **Test isolation fix:** original test failed because `verification-validator` sub-invokes `sync-validator`. The test state had a real plan_path but the test repo diff included many files not in the plan → sync blocked. Added `SKIP_SYNC_GATE=1` to test invocation to isolate lint logic.
- Actual: 230 lines total (helper 25, validator +60, pre-push +12, test 150) vs 100 estimated.

## Verification
- `test-verification-validator.sh`: 6/6 ✓ (lint=true pass, null block, false block, skipped+shellcheck-missing → exit 1 ⚠, lint_skip_reason set, helper returns valid ref/empty).
- **Self-validation on this very interaction:** when advancing `verification → capture`, `lint_clean=skipped` was emitted as evidence; my new smart acceptance fired (`reason=shellcheck_missing`), accepted as soft warn — **NO bypass needed**. The feature closed its own friction in the same interaction that produced it.

## Retrospective

### Estimate accuracy
100 lines estimated, 230 actual (~2.3x). Underestimated: (1) the test isolation problem with sync-validator (~30 lines of refactor + the SKIP_SYNC_GATE addition), (2) the helper exposing edge cases (plan not yet committed → fallback to origin/main), (3) the pre-push-gate propagation (which I'd estimated at ~5 lines, actually ~12 because the existing checklist string-build pattern needed careful preservation).

### Process gap
The sync-validator sub-invocation inside verification-validator wasn't surfaced as a risk in the spec's Prior Art Audit. **Lesson:** when extending a validator, check whether it sub-invokes other validators and whether those interfere with isolated testing. Should be a checklist item in Prior Art Audit for any validator extension.

### Emergent patterns
- **Validator sub-invocation as test obstacle:** 1st occurrence formally tracked. If recurs (e.g., when extending other validators), graduate to a Prior Art Audit checklist item.
- **Meta-validation (3rd occurrence):** the feature self-validated immediately upon deployment. The phase-advance from `verification → capture` exercised the new smart acceptance with `evidence.lint_clean=skipped` against current sandbox state. Threshold ≥3 — should now graduate.

## Backlog candidates

- **Meta-validation pattern graduation** — 3 occurrences now (2026-05-18 P2 gate-drift detection, 2026-05-20 P1 approval verb, 2026-05-20 P3 smart acceptance). Should graduate to a knowledge-module entry: "Features that auto-validate by closing their own friction in the implementing interaction". Useful design heuristic.
- **Validator sub-invocation checklist** — extend Prior Art Audit format to include "sub-invocations that may interfere with isolated testing". (1st occurrence — tracking, but specifically requested by user via spec.)
