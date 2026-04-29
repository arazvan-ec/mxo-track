---
type: feature
tags: [harness, validator, brainstorm, norms, safeguards, spdd, tdd]
files_touched:
  - .claude/hooks/validators/brainstorm-validator.sh
  - .claude/hooks/test-brainstorm-validator.sh
  - CLAUDE.md
  - docs/superpowers/specs/2026-04-28-norms-safeguards-validator-design.md
  - docs/superpowers/plans/2026-04-28-norms-safeguards-validator.md
patterns: [conditional-hard-gate, structured-content-validation, recursive-validator-application, forced-pairing]
outcome: success
outcome_verified_at: 2026-04-28
regressions_later: []
pr_number: null
estimated_lines: 120
actual_lines: 165
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-28 — Hito 1: Norms & Safeguards Validator (Layers N + S)

**Type:** feature (harness — workflow gate)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Backlog ref:** Hito 1 del análisis comparativo SPDD vs CLAUDE.md (2026-04-28)
**Spec:** `docs/superpowers/specs/2026-04-28-norms-safeguards-validator-design.md`
**Plan:** `docs/superpowers/plans/2026-04-28-norms-safeguards-validator.md`

## Summary

Added Layers N (Norms) + S (Safeguards) to `brainstorm-validator.sh` as
universal HARD gates. Every spec produced in full/debug flow must now
include:

- `## Norms` with ≥1 bullet containing an imperative keyword
  (`must`, `shall`, `never`, `always`, `no se permite`, `no debe`,
  `siempre`, `jamás`).
- `## Safeguards` with a markdown table containing `Risk | Mitigation`
  columns (any order) and ≥1 data row.

Both checks use awk state machines to bound scanning to the relevant
section, avoiding false positives from imperatives/tokens appearing
elsewhere in the spec.

## Origin

External analysis (Manus) proposed adopting REASONS Canvas dimensions
(Norms, Safeguards) as spec sections, but with two compromises rejected
on this branch: (a) heading-presence-only check (gameable), and
(b) SOFT/HARD condition by criticality (recoil pattern explicitly
forbidden by Layer K, commit `0923cdb`). This implementation takes the
maximal version: universal HARD with structured content validation.

The Layer K spec (commit `0923cdb`) had a "Risks and mitigations"
section as informal prose without the canonical Risk|Mitigation
pairing — concrete evidence that the bias to articulate risks
without mitigations exists in this very repo's recent history.

## Approach Chosen

**A — Universal HARD + structured content validation** (rejected
alternatives B/C/D documented in spec).

Implementation pattern mirrors Layers H + K + Anti-Omission:
- Section presence check via `grep -qE '^## Norms'` / `^## Safeguards`.
- Section content extraction via awk state machine
  (`/^## Norms/{flag=1; next} /^## /{flag=0} flag`).
- Content classification:
  - Norms: closed-list imperative keyword via `grep -iE`.
  - Safeguards: header line containing both "Risk" and "Mitigation"
    tokens (any order) + at least one data row not matching the
    `|---|---|` separator pattern.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/validators/brainstorm-validator.sh` | +43 lines: Layer N + Layer S blocks after Anti-Omision, before Layer H |
| `.claude/hooks/test-brainstorm-validator.sh` | +120 lines: 4 TDD fixtures (NS1 missing Norms, NS2 Norms without imperative, NS3 missing Safeguards, NS4 valid both) + `run_ns_scenario` helper |
| `CLAUDE.md` | +1 row in "Enforcement gates" table |

Net lines: ~165 (estimate was 120; gap mostly due to Safeguards table
parser requiring more shell logic than anticipated for the `|---|`
separator skip).

## Verification

- `bash .claude/hooks/test-brainstorm-validator.sh` → **19/19 pass**
  (15 existing + 4 new for Layers N+S).
- `bash -n` syntax check → clean.
- Smoke test: validator against this hito's own spec → exit 1 (SOFT
  warning on user_turns count only, no Layer N/S fire). Confirms the
  spec self-validates against the new gates.
- Backward compatibility: gate runs only at brainstorm-exit. Existing
  specs in `docs/superpowers/specs/` (including the Layer K spec) do
  not transit the gate. No retroactive blocking.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Validator lines | +25 | +43 | +72% |
| Test lines | +90 | +120 | +33% |
| CLAUDE.md lines | +1 | +1 | OK |
| Total net | ~120 | ~165 | +37% |
| Files (incl artefacts) | 5 | 5 | OK (calibration applied from log 2026-04-28-layer-k) |

**Root cause of the validator gap:** the Safeguards table parser
required an awk pass to skip the `|---|---|` separator row, which I
underestimated. The Norms section was simpler than expected
(~10 lines), but Safeguards came in at ~30 lines because of the
separator handling. Future estimates: budget ~30 lines for any
markdown table parser in shell, not 15.

### 2. Process gaps

- **No new gaps observed.** Layer K's recursive self-application
  pattern (smoke test against own spec) worked smoothly here — the
  spec's Norms and Safeguards sections were written upfront knowing
  they would be validated, which is the correct order. The
  artifact-counting calibration from the Layer K retrospective
  (commit `94d6e67`) held: estimate of 5 files matched actual.

### 3. Emergent patterns

- **Three-layer convergence on the same shape.** Layers H, K, and now
  N+S all use: section presence + awk state machine extraction +
  content classification by closed-list keywords or structured
  format. Total occurrences: 4 (H, K, N, S). **Threshold reached
  for graduation.** Follow-up: extract a shared helper
  `.claude/hooks/lib/section-validator.sh` with three function
  primitives:
  - `section_present <spec> <heading>`
  - `section_body <spec> <heading>`
  - `section_contains_keywords <body> <keyword-regex>`
  - `section_table_has_columns <body> <col1> <col2>`
  Replace inline implementations in brainstorm-validator with calls
  to the shared helper. ~50 lines saved, plus consistent error
  messages.

- **Forced pairing as a design pattern.** Safeguards' Risk|Mitigation
  table forces pairing — the model cannot list risks without
  attaching mitigations. This is the second occurrence of forced
  pairing in the harness (first: Layer K's Maximal Version with
  4-bullet structure including Independent Superiority). Pattern
  graduating to knowledge module candidate if a third occurrence
  appears. Provisional name: "forced-pairing-content-check".

## Follow-ups

1. **Extract `lib/section-validator.sh`** — graduation pathway from
   the three-layer convergence. Estimated ~50 lines saved across H/K/N/S
   by replacing inline awk + grep with shared helpers. Schedule after
   hitos 2-5 land to avoid mid-flight refactor.
2. **Define Norms vs Safeguards semantics in CLAUDE.md** — currently
   left to convention. If 3+ specs misuse the distinction, graduate to
   explicit definition.
3. **Imperative keyword graduation** — closed list initially. Add new
   imperatives via execution-log evidence (≥3 occurrences in genuine
   Norms content).
