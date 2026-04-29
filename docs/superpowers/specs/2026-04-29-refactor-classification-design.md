# Spec — Hito 5: Refactor as Distinct Interaction Classification

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)

## Problem

The repo's `interaction_classification` enum currently distinguishes
`informational`, `documentation`, `bug fix`, `code change`, and
`exploration` — but lacks `refactor`. As a result, behavior-preserving
refactor work gets classified as generic `code change`, even though
the execution log frontmatter `type:` field already uses `refactor`
as a value (commits `8aec691`, `b23dab4`, `ed799be` are all
`type: refactor` but were classified as `code change`).

This disconnect costs:

- Future `consult.sh` queries that want to find past refactors can
  query by `type:` but not by classification.
- Gate-level distinction is impossible: there's no place to add
  refactor-specific rules (e.g., stricter test-pass requirements)
  without conflating with feature work.
- The Manus plan (analyzed 2026-04-28) didn't include refactor as
  a classification, and I identified the gap during that review.

## Approach Chosen

**A — Add `refactor` to the accepted classification set, sharing
full flow phases with `code change`.**

1. `classify-validator.sh`: extend the "sufficient" case from
   `full|debug` to also accept `refactor` when paired with
   `flow_type=full`.
2. `CLAUDE.md` "Classify First" table: add a row for `refactor`
   with trigger semantics ("behavior-preserving change to existing
   code") and a note that gates are identical to `code change`
   (full flow) for now.
3. No new flow_type, no new phase array — `refactor` uses
   `FULL_PHASES`. The classification carries semantic intent;
   gate behavior is shared.

Future divergence (refactor-specific gates like
"tests_passed=true is mandatory, no skip") is a deliberate
follow-up, not part of this hito.

## Alternatives Rejected

**B — `refactor` as a separate flow_type with its own phase array.**

- Rejected: ceremony without value. Refactor genuinely has
  alternatives (extract vs inline, lib vs duplicate, structural
  patterns) — the brainstorming phase applies. Phases would be
  identical to FULL_PHASES; duplicating the array creates drift
  risk without behavior gain.

**C — Stricter validation for `refactor` (HARD tests_passed=true,
no skip).**

- Defensible but out of scope. Already partially covered:
  `verification-validator.sh` is strict on `tests_passed` for
  `flow_type=full|debug`. Refactor inherits that. Adding extra
  refactor-specific gates is a future Hito if a real failure
  surfaces.

**D — Don't add the classification; stick with `code change`
+ `type: refactor` in frontmatter.**

- Rejected: leaves the disconnect documented in 3 recent execution
  logs unaddressed. The classification is the right field to
  carry intent; the frontmatter `type:` is for queryability.
  Fixing the classification field aligns the two.

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Three recent logs (commits 8aec691, b23dab4, ed799be) classified as `code change` despite being `type: refactor`. The model didn't reach for a non-existent enum value. |
| 2. Fase correcta | ✓ | At classification time (start of interaction). The earliest possible point to express semantic intent. |
| 3. Coste/valor | ✓ | ~10 lines total: 1 in classify-validator (regex extension), 1 row in CLAUDE.md table, plus spec/plan/log artefacts. Value: aligns classification with frontmatter type, prepares hook for future refactor-specific gates. |
| 4. Backed by source | ✓ | Three recent `type: refactor` logs; identification of the gap in the SPDD vs CLAUDE.md analysis (2026-04-28); precedent of `bug fix` as distinct classification despite sharing some gate behavior with `code change`. |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/validators/classify-validator.sh` line 61 case statement | Transform | Add `refactor` to the `full\|debug)` arm |
| `CLAUDE.md` "Classify First" table | Transform | One new row for `refactor` |
| `.claude/hooks/lib/flow-phases.sh` | Omit | Refactor uses FULL_PHASES; no new phase array |
| `.claude/hooks/phase-advance.sh` | Omit | Reads flow_type, not classification |
| Existing `bug fix` classification | Omit (read for pattern) | Same shape: distinct classification, full flow gates |
| Execution logs with `type: refactor` | Omit | Historical records; not retroactively re-classified |

## Omission Decisions

- **Refactor-specific gates (e.g., HARD tests_passed=true):** out
  of scope. Add when a real failure surfaces.
- **Migration of past `code change`-classified refactors:** out of
  scope. Logs are immutable historical records.
- **CLAUDE.md trigger heuristics for refactor vs feature:** brief
  note in the table only. Detailed examples can be added if the
  model confuses the two in practice.
- **Tests for the validator change:** the existing
  `test-classify-validator.sh` (if any) covers the case structure;
  a one-line addition to the accepted set doesn't merit a new
  fixture. Smoke test: classify as refactor, edit a framework
  file, verify it passes.

## Norms

- The `refactor` classification **must** map to `flow_type=full`
  and use FULL_PHASES; **shall never** be paired with a reduced
  flow type.
- Refactor interactions **must** have all existing tests pass
  post-implementation; behavior preservation is the definitional
  invariant of refactor (enforced by the same gate as
  `code change`, not duplicated).
- The `interaction_classification` enum **shall** treat `refactor`
  as semantically distinct from `code change`; future gate
  divergence is permitted but **must** be justified by execution
  log evidence (3+ occurrences of refactor-specific failure).
- The classify-validator **must** accept `refactor` only when
  `flow_type` is also `full` (or `debug` — refactor during a
  debug investigation is rare but possible).

## Safeguards

| Risk | Mitigation |
|------|------------|
| Adding `refactor` to the accepted set breaks the case statement's catch-all | Place `refactor` inside the existing `full\|debug)` arm rather than a new arm. The catch-all (`*)`) and insufficient (`micro\|light\|...`) arms remain unchanged. |
| Future refactor-specific gates get added without distinguishing classification | Any future Hito proposing refactor-specific gates must apply 4-test on its own merits, citing 3+ execution logs of the failure mode. |
| Confusion between refactor and feature in classification | Add a short trigger description in CLAUDE.md table: "behavior-preserving change to existing code (no new feature, no bug fix)". If 3+ specs misuse, expand the description with examples. |
| Existing `code change`-classified logs that are actually refactor get found by future consult queries searching for refactors | Acceptable. The `type: refactor` frontmatter remains queryable via `consult.sh tag refactor` or `consult.sh by-frontmatter type=refactor`. Classification is forward-looking, not retroactive. |
| The minimal change masks a deeper problem (e.g., the enum should be richer) | Single-line addition is reversible. If the enum needs expansion (e.g., separate `feature` from `code change` too), that's a future Hito with its own justification. |
| Classify-validator tests don't exist for the refactor case | Smoke test post-implementation; if a regression emerges, fixture follows. |

## Implementation outline

1. **Wave 1 — Update `classify-validator.sh`.** Extend the
   `full|debug)` case arm to `full|debug|refactor)`.
2. **Wave 2 — Update `CLAUDE.md`.** Add one row to the "Classify
   First" table for `refactor` with trigger description and
   "Full" flow note.
3. **Wave 3 — Verify.**
   - `bash -n` clean.
   - 31 existing tests pass.
   - Smoke: classify as refactor (`jq
     '.interaction_classification = "refactor" | .flow_type = "full"'`)
     then attempt edit on a framework path; expect classify-validator
     to pass.

## Verification plan

- 31 existing tests pass.
- `bash -n` clean.
- Smoke: classify-validator accepts `refactor` for framework edits.
