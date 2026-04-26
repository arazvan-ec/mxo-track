---
type: spec
feature: socratic-review-relocation-into-brainstorm
date: 2026-04-24
branch: claude/enhance-routes-widget-8UzuC
related_log: docs/superpowers/execution-logs/2026-04-24-workflow-enforcement-layers-CHFIJ.md
---

# Spec — Relocate `socratic_review` Into Brainstorm Gate

## Context

The 2026-04-24 commit `3c235e9` introduced a `socratic_review` phase between
`verification` and `capture`. In post-implementation discussion, the user
pointed out that this placement was wrong: architectural adversarial
questions belong at the earliest phase where they are answerable, which is
**brainstorm** (during design), not post-verification (after code is
written and tested).

Catching architectural issues post-verification forces rollback to
planning/implementation — multiple phases of backward work. Catching them
at brainstorm costs zero rollback because no code exists yet.

## Problem

`socratic-review-validator.sh` runs at the `socratic_review → capture`
transition. If it flags an issue:
- Code is already written (rewrite cost)
- Tests already passed (re-verify cost)
- Design already committed (possible planning revisit)

The user's insight is not to delete the validator but to **reuse** it at
the brainstorm exit, where questions cost nothing to answer.

## Approaches Considered

### Approach α — Delete `socratic-review-validator.sh`, absorb logic into `brainstorm-validator.sh` (rejected)

- **Ventaja:** single file, less indirection.
- **Desventaja:** monolithic validator; losing discrete testable identity; no path to reuse the check at another phase in the future.
- **Rejected** per user feedback: the validator did valuable work; preserve its identity, just reposition its invocation.

### Approach β — Keep `socratic-review-validator.sh` as a discrete script, invoke from `brainstorm-validator.sh` (chosen)

The validator's input contract changes: instead of reading
`evidence.socratic_questions` (JSON array in session-state), it reads a
`## Architectural Adversarial Review` section from the spec file.

`brainstorm-validator.sh` invokes it (pointing at `$SPEC_FULL`) when the
spec references critical paths — same trigger as Layer H.

- **Ventaja:** modular; validator is independently testable; can be reused
  elsewhere later (e.g., pre-commit hook, or bringing back as a separate
  phase if that becomes useful).
- **Ventaja:** review lives with the design (the spec) where reviewers
  naturally look, not buried in session-state JSON.
- **Desventaja:** brainstorm gate becomes heavier (H + J + socratic).
- **Trade-off accepted:** the heaviness at one gate is preferable to
  rollback from later phases.

### Approach γ — Dual-run: keep at socratic_review AND add at brainstorm (rejected)

Run both positions. Belt + suspenders.

- **Ventaja:** maximum coverage.
- **Desventaja:** ceremony-proliferation; if brainstorm catches everything,
  the later run is pure noise. If later catches something brainstorm
  missed, it's rollback we're trying to avoid.
- **Rejected:** doubles ceremony without adding enforcement value.

## Trade-offs accepted

1. **Adversarial review happens pre-code.** Questions evaluate the design
   (dependency direction, boundary crossing, tradeoff acknowledgment) not
   shipped implementation. Some emergent issues only visible post-code are
   not caught by this gate — they are caught by Layer F (edit-time) and
   Layer I (retrospective) as safety nets.
2. **Ceremony concentrates at brainstorm.** Spec writer now handles H + J +
   adversarial review in one pass. Accepted because brainstorm is the
   highest-leverage moment in the flow.
3. **Validator input format changes.** Questions live in the spec as a
   markdown section instead of in `evidence.socratic_questions` JSON.
   Requires rewriting the 5 existing test cases to use spec fixtures.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `socratic-review-validator.sh` | **Keep, refactor** | Change input from session-state JSON to spec file path; preserve contract (≥3 questions, ≥30 chars, ≥1 arch keyword for critical paths). |
| `test-socratic-review-validator.sh` | **Keep, refactor** | Rewrite fixtures from JSON states to spec `.md` files; preserve 5 test scenarios. |
| `brainstorm-validator.sh` | **Extend** | Invoke socratic-review-validator after H/J checks when critical paths referenced. |
| `test-brainstorm-validator.sh` | **Extend** | Add cases for spec-without-architectural-review (block) and spec-with-review (pass). |
| `flow-phases.sh` FULL_PHASES | **Modify** | Remove `socratic_review`; FULL back to 8 phases. |
| `flow-phases.sh` DEBUG_PHASES | **Modify** | Remove `socratic_review`; DEBUG back to 7 phases. |
| `phase-advance.sh` usage banner | **Modify** | Restore 8-phase list for full, 7-phase for debug. |
| `user-prompt-state.sh` | **Modify** | Remove `socratic_review` from PHASES, PHASE_SHORT, debug late-phase case, NEXT actions. |
| `workflow-status-line.sh` | **Modify** | Same as above. |
| `test-phase-advance.sh` | **Modify** | Walk back to 8 phases; remove socratic_review case from evidence setup. |
| `test-enforcement-layers.sh` | **Modify** | Walk back to 8 phases; update assertions. |
| `CLAUDE.md` 14-shortcuts table | **Modify** | Remove "verification → capture without adversarial review" row; update H row to note it now also includes architectural review. |
| `.claude/README.md` phase evidence matrix | **Modify** | Remove `socratic_review` row; update `brainstorming` row. |
| Layer I (retrospective-validator.sh) | **Keep unchanged** | HARD gate stays; unrelated to this refactor. |
| Layer F (ddd-boundary-check.sh) | **Keep unchanged** | Edit-time backup; unrelated. |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Backward-compatibility for `evidence.socratic_questions` | Omit | This field is only referenced by `socratic-review-validator.sh`; no other consumer. Removing the read is clean. |
| Migration shim reading both spec section AND JSON evidence | Omit | Transitional complexity not warranted; validator is being refactored in a single commit. |
| Parallel runs during transition | Omit | Branch is feature branch, not main; atomic switch is safe. |
| Dual-run at both brainstorm AND socratic_review phase | Omit | Per Approach γ rejection. |
| Moving H and J to socratic_review phase | Omit | H and J are already at brainstorm exit; moving them would invert the refactor. |
| Changing Layer I to SOFT | Omit | User explicitly requested that be tracked separately. |

## Prior Art Audit

This spec is meta — it modifies the brainstorm-validator itself. The spec
body references the names of critical domain paths (Route, Shipment)
only as examples of what the validator triggers on, NOT because this
refactor touches them. All code changes are in `.claude/hooks/**` which
is harness, not a critical DDD context. The Prior Art Audit below covers
the harness files being modified.

| Path | Endorsed? | Evidence |
|---|---|---|
| `.claude/hooks/validators/socratic-review-validator.sh` | ❌ tech-debt | Was placed at post-verification (2026-04-24 commit `3c235e9`). Retrospective flagged the position as wrong-phase. This refactor corrects the placement. |
| `.claude/hooks/validators/brainstorm-validator.sh` | ✅ | Endorsed SoT for brainstorm-exit gates; already carries Layers H and J. Adding one more sub-invocation is a natural extension. |
| `.claude/hooks/lib/flow-phases.sh` | ✅ | Single source of truth for phase arrays; this refactor removes one entry from it (reversing a prior addition). |
| `## Architectural Adversarial Review` section convention in specs | new | New content requirement for specs touching critical paths; no prior convention exists. |

## Architectural Adversarial Review

1. **Q:** Does moving `socratic-review-validator.sh` invocation from a
   dedicated phase to a sub-call of `brainstorm-validator.sh` create tight
   coupling between two validators that should stay independent?
   **A:** No — the relationship is a strict composition (brainstorm calls
   socratic-review as a library), not mutual coupling. socratic-review's
   contract (takes a spec path, returns pass/block) is independent of
   brainstorm-validator's other checks. Future code can call
   socratic-review from elsewhere without changing brainstorm-validator.

2. **Q:** By reading the spec file instead of session-state, does the
   validator lose the ability to distinguish "author wrote these questions
   deliberately for this flow" vs "these questions were copy-pasted from
   a template"?
   **A:** The session-state version also could not distinguish
   copy-paste from deliberate authoring — it just counted characters. The
   spec-file version inherits the same weakness. The tradeoff is
   acceptable because the spec lives in version control and is reviewed
   by humans; pasted templates get caught at review, not at the gate.

3. **Q:** Is removing the `socratic_review` phase equivalent to weakening
   the workflow by one checkpoint, given that the catch moves to an
   earlier phase but no new enforcement is added?
   **A:** It is a lateral move, not a weakening. The same check fires at
   brainstorm instead of post-verification. Net: zero checks lost, zero
   added, but the cost of a hit drops from "rewrite + retest" to "rethink
   approach." The workflow is the same strength measured by "what can
   escape the gates," but stronger measured by "cost of correction."

## Design

### Validator refactor

```bash
# socratic-review-validator.sh (refactored)
# Usage: socratic-review-validator.sh <spec_path>
# Reads: ## Architectural Adversarial Review section from the spec
# Checks: ≥3 numbered questions (format: "N. **Q:**"),
#         each entry ≥30 chars of content,
#         when spec references critical paths, ≥1 question contains
#         arch keyword (endorsed|boundary|DDD|tech-debt|coupling|pattern|tradeoff)
# Exit 0 = pass, Exit 2 = block, Exit 1 = warn (soft — not used here)
```

### Brainstorm integration

```bash
# Inside brainstorm-validator.sh, after H + J checks:
if critical_paths_referenced; then
  if ! "$REPO/.claude/hooks/validators/socratic-review-validator.sh" "$SPEC_FULL"; then
    ERRORS="${ERRORS}- Architectural Adversarial Review seccion invalida o faltante (ver output arriba).\n"
  fi
fi
```

### Spec section format

```markdown
## Architectural Adversarial Review

1. **Q:** [Question about approach/design/tradeoff, ≥30 chars total]
   **A:** [Reasoned answer]

2. **Q:** [...]
   **A:** [...]

3. **Q:** [...]
   **A:** [...]
```

At least one Q must contain an architectural keyword when critical paths
are referenced.

### Flow changes

```
Antes (9 fases full):
  consult → brainstorming → planning → implementation → verification → socratic_review → capture → retrospective → finalize

Después (8 fases full):
  consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize
           (H + J + Architectural Adversarial Review)
```

## Verification Plan

1. `socratic-review-validator.sh` unit tests rewritten: 5 cases against
   `.md` fixtures (not JSON states). Expected: 5/5 green.
2. `brainstorm-validator.sh` extended test cases: spec-with-critical-paths-without-review → block; spec-with-review → pass. Full test 13/13.
3. `flow-phases.sh` tests: FULL length = 8, DEBUG length = 7. `socratic_review` not in either. Expected: 15/15 green (will need fixture updates).
4. `test-phase-advance.sh` walk: 8-phase walk with history length 8.
5. `test-enforcement-layers.sh` walk: 8-phase walk.
6. No regression in backend phpunit.

## Non-goals

- Retroactive updates to prior execution logs referencing `socratic_review`.
- Changing Layer I (retrospective content gate) severity.
- Deprecation period for `evidence.socratic_questions` — remove cleanly.
- New adversarial-review tool/UI; this is validator + spec section only.
