---
type: refactor
tags: [harness, lib-extraction, vocabulary, awk, portability, graduation]
files_touched:
  - .claude/hooks/lib/vocabulary-reader.sh
  - .claude/hooks/pre-agent-check.sh
  - .claple/hooks/pattern-audit.sh
  - .claude/hooks/ddd-boundary-check.sh
  - .claude/README.md
  - docs/superpowers/specs/2026-04-29-vocab-reader-lib-and-portability-docs-design.md
  - docs/superpowers/plans/2026-04-29-vocab-reader-lib-and-portability-docs.md
patterns: [lib-extraction-3-occurrences, mawk-portable-awk, extract-in-awk-match-in-bash]
outcome: success
outcome_verified_at: 2026-04-29
regressions_later: []
pr_number: null
estimated_lines: 50
actual_lines: 90
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-29 — I12: vocab-reader lib + portability docs

**Branch:** `claude/review-workflow-improvements-x78Zp`
**Spec:** `docs/superpowers/specs/2026-04-29-vocab-reader-lib-and-portability-docs-design.md`
**Plan:** `docs/superpowers/plans/2026-04-29-vocab-reader-lib-and-portability-docs.md`

## Summary

Two co-located graduations from Phase B's retro:

1. **`lib/vocabulary-reader.sh`** with three primitives:
   `vocab_deprecated_aliases`, `vocab_canonicals_in_text`,
   `vocab_bounded_context`. Replaces inline awk+bash blocks in
   pre-agent-check (B-1), pattern-audit (B-3), and
   ddd-boundary-check (B-2).
2. **`.claude/README.md` "Shell Portability Constraints"** section
   documenting the mawk vs gawk class — 3 occurrences in this branch
   (backtick stripping in files-decl-parser, pre-push gate heredoc
   tracking, Phase B vocab match). Codifies the "extract in awk,
   match in bash" pattern the libs follow.

## Changes

| File | Change |
|------|--------|
| `lib/vocabulary-reader.sh` | new (~70 lines): 3 primitives + portability comments |
| `pre-agent-check.sh` | -25/+8 lines: Gate 4 uses lib |
| `pattern-audit.sh` | -12/+5 lines: deprecated-alias scan uses lib |
| `ddd-boundary-check.sh` | -32/+15 lines: cross-ref uses lib (also moves spec_text load to bash via `cat`) |
| `.claude/README.md` | +60 lines: Shell Portability Constraints section before Harness Assumptions |

Net: ~+90 lines (estimate 50; +80%). The README section grew larger
than budgeted because documenting all three rules + the canonical
pattern + the verification check made the section ~60 lines instead
of ~30.

## Verification

- `bash -n` clean on lib + 3 callers + README is markdown.
- `test-brainstorm-validator.sh` → **19/19 pass**.
- `test-sync-validator.sh` → **6/6 pass**.
- `test-pre-agent-check.sh` → **6/6 pass** (Gate 3 + Gate 4 still
  functional via lib).
- Smoke B-3 via lib: fixture log mentioning "tour" and "waypoint"
  → both surfaced with canonical replacements ("Route", "RouteStop").
  Behavior identical to pre-migration.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| lib | +80 | +70 | OK |
| caller migrations | -25/+8, -25/+8, -30/+10 | -25/+8, -12/+5, -32/+15 | OK |
| README docs | +30 | +60 | +100% |
| Total net | ~+50 | ~+90 | +80% |

The README section underestimated by 2x. Cause: the 3 rules each
needed explicit examples + the canonical "extract in awk, match in
bash" pattern + the awk-runtime verification snippet. Calibration:
budget ~60 lines for any "harness portability rule + canonical
pattern + workaround" doc.

### 2. Process gaps

- **No new gaps observed.** Lib extraction followed I5's protocol
  cleanly. Pre/post smoke confirmed parity. Existing tests caught
  no regressions.
- **Refactor classification** (`Hito 5`, commit `9a46094`) used
  for the first time on a real interaction. classify-validator
  accepted `refactor` cleanly via the `full|debug|refactor)` arm.

### 3. Emergent patterns

- **Lib graduation at 3 occurrences** — third instance of the
  pattern (1: section-validator, 2: files-decl-parser, 3:
  vocabulary-reader). **Threshold reached for the meta-pattern.**
  Documenting "graduate to lib at 3+ inline duplicates" as a
  process rule worth surfacing — but it already lives implicitly
  in CLAUDE.md's graduation pathway. No new artifact needed.

- **"Extract in awk, match in bash"** as a portable-pattern
  recipe — first occurrence as a documented pattern (existed
  implicitly in files-decl-parser since `f4d6d36`). With this
  README addition, it becomes formal guidance.

- **Refactor classification** validates Hito 5's design — a
  classification value distinct from "code change" lets the
  execution log frontmatter `type: refactor` and the
  `interaction_classification` align.

## Follow-ups

1. **Pre-push gate heredoc bug** — tracking 2/3 (held this commit
   short to avoid trigger).
2. **classify-validator "code change" non-match** — tracking 1/3.
3. **user_turns reset interaction-cross** — tracking 1/3.
4. **Phase C** — next interaction. Now consumes
   `lib/vocabulary-reader.sh` from the start instead of writing
   the 4th and 5th inline duplicates.
