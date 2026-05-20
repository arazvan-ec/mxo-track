# Plan — Approval UX Overhaul (P1)

**Spec:** `docs/superpowers/specs/2026-05-20-approval-ux-overhaul-design.md`
**Date:** 2026-05-20
**Branch:** `claude/compare-claude-workflows-yrl2P`

## Task DAG

### Wave 1: TDD red — write test

- **1a:** Create `.claude/hooks/test-user-prompt-state.sh` covering:
  - Regex matches all 28 existing verbs (regression)
  - Regex matches all 12 new verbs (`avanza|sigue|vamos|pasa a|arranca|tira|tira para|tira con|venga|empieza|continúa con|ve con`)
  - `APPROVAL_REGEX` variable is shared (no duplication)
  - Proactive feedback line emitted when `user_approved=false` + pre-gate phase + evidence consistent
  - Proactive feedback line NOT emitted when `user_approved=true`
  - Semantic probe emitted when prompt ≤80 chars + no match + pre-gate state
  - Semantic probe NOT emitted for long prompts or non-pre-gate phases
  - Direct-write warning appears in `/tmp/ptc-revert-warnings.log` after revert
  → produces: failing test
  → files: `.claude/hooks/test-user-prompt-state.sh` (new)

### Wave 2: TDD green — implement (4 sub-tasks, internal sequential)

- **2a:** Extract `APPROVAL_REGEX` and `REJECTION_REGEX` variables (DRY refactor)
  → produces: shared regex; existing behavior preserved
  → files: `.claude/hooks/user-prompt-state.sh` (modified, ~20 lines refactored)

- **2b:** Extend `APPROVAL_REGEX` with new verbs
  → produces: 4ª ocurrencia closed; "avanza" matches
  → files: `.claude/hooks/user-prompt-state.sh` (modified, +1 line in regex)

- **2c:** Add proactive feedback emission block (conditional on pre-gate + user_approved=false)
  → produces: status line includes feedback when appropriate
  → files: `.claude/hooks/user-prompt-state.sh` (modified, +15 lines)

- **2d:** Add semantic probe emission block + direct-write warning in `post-bash-validator.sh`
  → produces: probe and warning behavior
  → files: `.claude/hooks/user-prompt-state.sh` (modified, +10 lines), `.claude/hooks/post-bash-validator.sh` (modified, +5 lines)

### Wave 3: Verification (parallel internally) — needs Wave 2

- **3a:** Run `test-user-prompt-state.sh` — all assertions pass
- **3b:** Run `test-enforcement-layers.sh` (regression) — direct-write revert + new warning log assertion
- **3c:** Run `test-retrospective-validator.sh` (regression) — retrospective approval still works with shared regex
- **3d:** `make lint` clean

## Estimated artifacts

- Source: 2 files modified (`user-prompt-state.sh` +~50 lines, `post-bash-validator.sh` +~5 lines)
- Tests: 1 new (`test-user-prompt-state.sh`, ~150 lines, 12+ cases)
- Shared interaction log + decision-log entry with P2 + P3

## Risks

- Regex extension matches false positives (e.g., "no avances" matching "avanza") — mitigation: word-boundary anchors `(^|\s)...(\s|$|[,.\!])` already in place; test 1a covers
- DRY refactor breaks behavior — mitigation: regression tests (3b, 3c)
- Semantic probe spam if condition too loose — mitigation: exact phase + evidence + length checks
- Direct-write warning log not visible to model — mitigation: also emit stderr line during phase-advance (visible in tool output)

## Commit cadence

- Commit 1 after 1a (TDD red — failing test alone)
- Commit 2 after 2a-2d (TDD green — refactor + features together as one logical unit)
- Wave 3 produces no commit (verification only)
