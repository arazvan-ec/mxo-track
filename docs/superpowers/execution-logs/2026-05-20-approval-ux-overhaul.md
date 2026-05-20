---
type: feature
tags: [harness, approval-detection, dry-refactor, proactive-feedback, semantic-probe, ux]
files_touched:
  - .claude/hooks/user-prompt-state.sh
  - .claude/hooks/post-bash-validator.sh
  - .claude/hooks/test-user-prompt-state.sh
patterns:
  - shared-regex-variables
  - proactive-gate-feedback
  - rejection-wins
  - meta-validation
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 60
actual_lines: 130
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-05-20 — Approval UX Overhaul (P1 of 3)

## Spec / Plan
- Spec: `docs/superpowers/specs/2026-05-20-approval-ux-overhaul-design.md`
- Plan: `docs/superpowers/plans/2026-05-20-approval-ux-overhaul.md`

## Brainstorming
- **Alternatives:** A (5 sub-features bundled: regex + DRY + proactive + warn + probe), B (regex only), C (regex + DRY only, no UX). Chose A per user request including the semantic probe.
- **Complexity estimate:** ~60 lines + tests (~150 lines).

## Planning
- TDD red → DRY refactor + extend regex + proactive feedback + semantic probe + post-bash warning log → verification.

## Implementation
- Extracted `APPROVAL_REGEX` and `REJECTION_REGEX` as shared variables (DRY). Eliminated duplicate between general approval (line 73) and retrospective approval (line 106).
- Extended `APPROVAL_REGEX` with: `avanza|sigue|vamos|pasa a|arranca|tira para|tira con|tira|venga|empieza|continúa con|ve con`. Closes 4 documented occurrences.
- **Discovered pre-existing bug:** "no estoy de acuerdo" matched approval ("estoy de acuerdo") before rejection ran. Fix: detect intent first via `IS_APPROVAL` and `IS_REJECTION` flags; rejection short-circuits approval when both match. Bug existed since the regex was introduced — caught by my new test case.
- Added proactive gate feedback: emits `✋ Para avanzar di: ...` when `user_approved=false` near brainstorming/retrospective exit.
- Added lightweight semantic probe: emits `📋 Prompt ambiguo ...` when prompt ≤80 chars + ambiguous (no approval/rejection match) + pre-gate state. Uses orchestrator-side LLM to disambiguate — no external API.
- Made `REPO` and `STATE_FILE` env-overridable for testability.
- `post-bash-validator`: when direct-write to `user_approved` is reverted, persistent warning logged to `/tmp/ptc-revert-warnings.log` so the model sees the revert across turns.
- Actual: 130 lines total (~70 in user-prompt-state.sh, ~5 in post-bash-validator.sh, ~55 in test) vs 60 estimated.

## Verification
- `test-user-prompt-state.sh`: 18/18 ✓ (all old verbs + 12 new verbs + rejection precedence + proactive + probe + warning log).
- `test-retrospective-validator.sh`: 11/11 ✓ (regression — shared regex doesn't break retrospective approval).
- `test-enforcement-layers.sh`: 12/15 (3 PRE-EXISTING failures unrelated to this change — the full-walk test fails because test fixtures lack Norms/Safeguards sections required by universal layers N+S).

## Retrospective

### Estimate accuracy
60 lines estimated, 130 actual (~2.2x). Underestimated: (1) the rejection-wins bugfix (~10 lines + 1 test case), (2) the env override (~2 lines but required test plumbing), (3) test isolation (env-passing via subshell).

### Process gap
The bugfix for "rejection wins" was a side-discovery during P1 — the test I wrote for "no estoy de acuerdo" surfaced behavior the design comment claimed (line 65 of the hook said "rejection wins"). The CODE didn't match the COMMENT. **Lesson:** when extending existing code, comments documenting intended behavior may not be implemented — verify by test, don't trust comment alone.

### Emergent patterns
- **Comment-code drift:** documentation claim vs actual behavior. First occurrence formally tracked; if recurs 3+ times, graduate to a knowledge-module entry about "verify implementation against documented intent".
- **Meta-validation reprise:** the new approval regex includes "avanza" — the exact verb that triggered the original friction in 2026-05-18. The feature would have closed its own bug from the prior session. Same self-validation pattern observed in 2026-05-18 P2 (gate-drift detection auto-flagged this interaction's bypasses).

## Backlog candidates

- **Comment-code drift verification** as a standard step of Prior Art Audit: when an existing script's comment claims behavior X, add explicit test asserting X before accepting the prior code as "Endorsed". (1st occurrence — tracking)
