# Spec — Workflow Hook Tests & Autodiscovery

**Date:** 2026-04-07
**Type:** Testing + small refactor
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Problem

4 hooks were modified and 1 validator created today without test coverage for the new behavior. Additionally, `phase-advance.sh` uses a hardcoded case statement for validator registration — new validators must be manually wired or they silently do nothing.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `test-phase-advance.sh` (10 tests) | **Extend** | Add validator-blocking tests, fix Test 8 broken by validator gates |
| `test-enforcement-layers.sh` (5 tests) | **Extend** | Add retrospective-validator scenario |
| `retrospective-validator.sh` (new) | **Add tests** | Zero coverage |
| `brainstorm-validator.sh` (modified) | **Add test** | Spec-must-exist not tested |
| `planning-validator.sh` (modified) | **Add test** | Tarea keyword not tested |
| `phase-advance.sh` case statement | **Refactor** | Replace with autodiscovery |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `post-commit-validator.sh` | Not tested | Low risk, side-effect only |
| `post-push-validator.sh` | Not tested | Low risk, side-effect only |
| `pre-push-gate.sh` | Not tested | Complex setup (git hooks), separate effort |
| `user-prompt-state.sh` | Not tested | Requires UserPromptSubmit simulation, separate effort |
| Full e2e multi-day test | Not in scope | Would require session-start simulation |

## Alternativa A — Tests first, then autodiscovery (CHOSEN)

Update existing test files + create 1 new test file. Then refactor phase-advance.sh to use convention-based autodiscovery. Ventaja: tests verify current behavior before refactoring. Trade-off: 4 files to modify.

## Alternativa B — Autodiscovery first, minimal tests

Refactor phase-advance.sh first, then add tests. Desventaja: if autodiscovery has a bug, no safety net.

## Alternativa C — Only tests, no autodiscovery

Keep manual registration. Trade-off: each new validator requires remembering to update phase-advance.sh.

## Architecture

### Autodiscovery convention
`phase-advance.sh` replaces the hardcoded case with:
```bash
VALIDATOR="$REPO/.claude/hooks/validators/${CURRENT_PHASE}-validator.sh"
if [ -f "$VALIDATOR" ]; then ...
```

This works because all validators already follow the naming convention `{phase}-validator.sh`.

Current validators that match: `brainstorm`→brainstorming (needs alias), `planning`, `retrospective`, `consult`, `implementation`, `capture`, `verification`, `finalize`, `debug`.

**Edge case:** `brainstorming` phase maps to `brainstorm-validator.sh` (missing "ing"). Handle with fallback: try `${PHASE}-validator.sh`, then `${PHASE%ing}-validator.sh`.

## Files Affected

- **Modify:** `test-phase-advance.sh` (add validator-blocking tests, fix full walk)
- **Modify:** `test-enforcement-layers.sh` (add retrospective scenario)
- **New:** `test-retrospective-validator.sh` (dedicated validator tests)
- **Modify:** `phase-advance.sh` (autodiscovery replacing case statement)
