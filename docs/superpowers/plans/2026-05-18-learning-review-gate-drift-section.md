# Plan — Learning Review § Gate-Drift Sub-Section

**Spec:** `docs/superpowers/specs/2026-05-18-learning-review-gate-drift-section-design.md`
**Date:** 2026-05-18
**Branch:** `claude/compare-claude-workflows-yrl2P`

## Task DAG

Single-file documentation edit.

### Wave 1: Edit Skill 15

- **1a:** Edit `docs/CLAUDE.md` Skill 15 § Process: insert new step 4 ("Gate-drift review"); renumber current 4→5, 5→6
  → produces: updated Skill 15
  → files: `docs/CLAUDE.md` (modified)

### Wave 2: Dry-run validation (needs Wave 1 + P2 implementation)

- **2a:** Walk through new step 4 against current state — run `pattern-audit.sh` (post-P2), draft hypothetical `[TUNE]`/`[LEGITIMIZE]` decisions
- **2b:** Confirm wording consistency with rest of Skill 15

## Inter-spec dependency

P3 Wave 2a depends on **P2 Wave 1 complete**. At interaction level:

```
Batch 1 (parallel):  P1·Wave1   P2·Wave1   P3·Wave1
Batch 2 (parallel):  P1·Wave2   P2·Wave2   P3·Wave2  (P3·Wave2 needs P2·Wave1)
```

## Estimated artifacts

- 1 file modified (`docs/CLAUDE.md` +~10 lines)
- Shared execution log + decision log entry

## Risks

- Renumbering errors — review the diff; only steps 4→5 and 5→6 shift
- Wording divergence from Skill 15 style — Wave 2b cross-reads surrounding skills

## Commit cadence

- Single commit after Wave 1.
