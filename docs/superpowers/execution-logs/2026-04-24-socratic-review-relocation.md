---
type: refactor
tags: [workflow, enforcement, socratic-review, architectural-review, phase-relocation, SoT]
files_touched: [.claude/hooks/validators/socratic-review-validator.sh, .claude/hooks/test-socratic-review-validator.sh, .claude/hooks/validators/brainstorm-validator.sh, .claude/hooks/test-brainstorm-validator.sh, .claude/hooks/lib/flow-phases.sh, .claude/hooks/phase-advance.sh, .claude/hooks/user-prompt-state.sh, .claude/hooks/workflow-status-line.sh, .claude/hooks/test-phase-advance.sh, .claude/hooks/test-enforcement-layers.sh, CLAUDE.md, .claude/README.md]
patterns: [defense-in-depth-relocation, single-source-of-truth]
outcome: success
outcome_verified_at: 2026-04-24
regressions_later: []
pr_number: null
estimated_lines: 150
actual_lines: 711
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-24 — Relocate socratic_review Into Brainstorm Gate

**Type:** refactor (phase-relocation of an enforcement layer)
**Branch:** `claude/enhance-routes-widget-8UzuC`
**Spec:** `docs/superpowers/specs/2026-04-24-socratic-review-relocation-design.md`
**Plan:** `docs/superpowers/plans/2026-04-24-socratic-review-relocation.md`
**Triggering discussion:** user pushed back on Layer C's position after the
CHFIJ shipped, pointing out that catching architectural issues at
post-verification forces backward work.

## What shipped

- `socratic-review-validator.sh` input changed from `evidence.socratic_questions`
  JSON to `<spec_path>` argument, reading the `## Architectural Adversarial Review`
  section. Contract preserved: ≥3 questions, each ≥30 chars, ≥1 with arch keyword
  when critical paths referenced.
- `brainstorm-validator.sh` invokes the validator as a sub-check when the spec
  references critical contexts (same trigger as Layer H).
- `socratic_review` removed as a separate phase. FULL_PHASES reverted to 8,
  DEBUG_PHASES reverted to 7. All consumer scripts updated.
- Docs updated: CLAUDE.md shortcut catalog, .claude/README.md evidence matrix.

## Verification

100/100 harness tests green.

| Test | Result |
|---|---|
| test-socratic-review-validator | 6/6 (rewritten with spec-file fixtures) |
| test-brainstorm-validator | 13/13 (11 existing + C1/C2) |
| test-phase-advance | 21/21 (walk reverted to 8 phases) |
| test-phase-advance-entry | 5/5 |
| test-phase-transition-controller | 7/7 |
| test-enforcement-layers | 15/15 (walk + fabricated-history reverted) |
| test-flow-phases | 15/15 |
| test-ddd-boundary-check | 10/10 |
| test-retrospective-validator | 8/8 |

## Lessons

### Estimate accuracy

Files estimated 12, actual 13. Lines estimated ~150, actual +711/-132
(net +579). The gap is in the spec + plan documents (~450 lines) and new
test fixtures (~100 lines); the code change proper was ~80 lines.

### Process gaps — architectural

- **Phase placement was my error, not the user's push-back.** The original
  CHFIJ placement was "after code, before capturing log" — optimizing for
  confronting reality AFTER shipping. The correct optimization is
  confronting reality at DESIGN, where rollback is zero-cost. Lesson: when
  placing an architectural-review gate, ask "where is the architecture
  chosen?" not "where is the architecture visible?"

- **Meta-dogfooding:** this very spec had to satisfy Layer H. Body text
  references `src/Domain/Route` and `src/Controller/Api/Admin` (discussing
  what the validator triggers on, not because the refactor touches them).
  Layer H fired. Fixed by adding a proper Prior Art Audit table covering
  the harness files being modified. The gate behaved correctly; the spec
  needed honest documentation.

- **Test coupling revealed by C2 case:** H2 fixture previously passed with
  only a Prior Art Audit table; now needs an Architectural Adversarial
  Review section too. Intentional coupling between brainstorm-validator
  and the socratic sub-invocation. Future test fixtures touching critical
  paths must include both sections.

### Process gaps — mechanical

- **AWK whitespace-preamble bug.** First implementation treated the blank
  line between the `## Architectural Adversarial Review` header and the
  first `N. **Q:**` as an empty "short question" and double-counted. Fixed
  with a `finalize()` function that skips buffers lacking a `**Q:**`
  marker. TDD caught it before shipping.

### Emergent patterns

- **Phase relocation as a refactor.** First relocation of an enforcement
  layer in the harness's history. The pattern: identify the phase with
  lowest rollback cost for the check, move the validator there, keep it
  discrete for reuse. Worth remembering when future gates are proposed at
  late phases: ask first "why not earlier?"

- **Single-SoT applied to gate composition.** brainstorm-validator now
  INVOKES socratic-review-validator instead of duplicating its logic.
  Same pattern as `flow-phases.sh` being sourced by multiple consumers:
  discrete authoritative source, consumers call/source it.

## Follow-ups

1. Layer I (retrospective content gate) severity review — still HARD;
   the earlier keyword-matching critique applies. Separate interaction.
2. Layer F from WARNING to BLOCK when no Prior Art Audit row covers the
   edited file. Separate interaction.
3. Critical-paths regex duplication between Layer H (hardcoded regex)
   and Layer F (reads `_ddd-boundaries.yaml`). Unify to read from the
   YAML in both places.
4. AWK parser alternative-format tolerance — handles `N. **Q:**` today;
   unclear behavior for `- **Q:**` or other conventions. Document or
   tighten if alternatives appear.
