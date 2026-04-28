# Spec — Hito 1: Norms & Safeguards as Mandatory Spec Sections

**Date:** 2026-04-28
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)
**Backlog ref:** Hito 1 del análisis comparativo SPDD vs CLAUDE.md (2026-04-28)

## Problem

Spec template currently requires `## Existing Functionality Inventory`,
`## Omission Decisions`, conditional `## Prior Art Audit` (Layer H),
conditional `## Architectural Adversarial Review` (Layer C), and conditional
`## Maximal Version Considered` (Layer K). Missing: explicit articulation of
business invariants (Norms) and risks-with-mitigations (Safeguards). Both
are dimensions of SPDD's REASONS Canvas (Fowler 2026) and correspond to
DDD's invariants and risk-driven design principles.

Without these sections, specs can ship without the model articulating what
must hold post-implementation and what could fail during it. The Layer K
spec itself (commit `0923cdb`) had a "Risks and mitigations" section as
informal prose — useful but not canonical, not paired, not validated.

## Approach Chosen

**A — Universal HARD gate with structured content validation:**

1. `## Norms`: presence required; section must contain ≥1 bullet line with
   at least one imperative keyword from the closed list:
   `must`, `shall`, `never`, `always`, `no se permite`, `no debe`,
   `siempre`, `jamás`.
2. `## Safeguards`: presence required; section must contain at least one
   markdown table row with both "Risk" and "Mitigation" columns (case
   insensitive, any column order).
3. Block (exit 2) on any failure.
4. Universal application — any spec in full/debug flow. Light/deviation
   flows do not produce specs, so no false-positive surface.

## Alternatives Rejected

**B — Universal HARD + bullet count only** (≥2 bullets per section, no
content classification).

- Rejected: gameable. The model can write empty bullets and pass.
  Layer K (commit `0923cdb`) explicitly mechanizes this anti-pattern.

**C — Conditional HARD on critical paths only** (Layer H style).

- Rejected: Norms/Safeguards apply universally per SPDD and DDD. Conditional
  application creates two classes of spec rigor and undermines the universal
  invariant "every change articulates its invariants and risks".

**D — Heading presence only, conditional SOFT/HARD by criticality**
(literal proposal from external analysis).

- Rejected on two grounds:
  1. Heading-only validation is the gameable pattern Layer K just made an
     explicit anti-pattern (commit `0923cdb`).
  2. SOFT/HARD by criticality is the recoil pattern forbidden by commit
     `d3ce7c5` and mechanically blocked by Layer K. Accepting D would
     violate the rule instated 30 minutes earlier in the same branch.

## 4-Test Application (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | The Layer K spec (commit `0923cdb`) has a "Risks and mitigations" section as informal prose, not a canonical Risk\|Mitigation table. Without the gate, future specs default to the same informal pattern or omit risks entirely. |
| 2. Fase correcta | ✓ | brainstorm-exit, identical placement to Layers H, C, K. Rollback cost: minutes. Catching missing risks at finalize would cost the entire feature's worth of rework. |
| 3. Coste proporcional al valor | ✓ | ~25 lines validator + ~80 lines tests, same order as Layer K (37+99). Forced Risk\|Mitigation pairing addresses the most common spec defect: identifying risks without addressing them. |
| 4. Backed by source | ✓ | SPDD REASONS Canvas (Fowler 2026, "N" and "S" dimensions); DDD invariants (Evans); execution log 2026-04-28-layer-k-anti-reduction-validator.md (the spec produced by the very flow this proposal augments would have benefited from explicit canonical Safeguards). |

Pass on all four. No reduction needed.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/validators/brainstorm-validator.sh` (228 lines, layers Anti-Omision + H + C + K + parallel-conflict) | Transform | Add Layer N (Norms) and Layer S (Safeguards) blocks immediately after Anti-Omision, before Layer H |
| `.claude/hooks/test-brainstorm-validator.sh` (478 lines) | Transform | Add 4 TDD fixtures using existing `run_X_scenario` helper pattern |
| `CLAUDE.md` "Enforcement gates" table | Transform | Add one row documenting the Norms/Safeguards gate |
| Existing specs in `docs/superpowers/specs/` | Omit | Backward-compat: gate runs on brainstorm-exit; old specs do not transit. No retroactive requirement. |
| `docs/knowledge/_ddd-boundaries.yaml` | Omit | Layer N+S are universal, do not consult critical paths registry |

## Omission Decisions

- **N/A escape clauses are not allowed.** Every change has invariants and
  risks; forcing articulation is the point. The validator does not accept
  "Norms: N/A" or "Safeguards: N/A".
- **Defining Norm vs Safeguard semantics in CLAUDE.md prose:** out of scope.
  The validator enforces structure; meaning is left to convention. If
  misuse appears in 3+ specs, graduate to explicit definition in CLAUDE.md.
- **Imperative keyword list expansion mechanism:** closed list initially.
  Graduation pathway via execution-log evidence (≥3 occurrences of a new
  imperative term in genuine Norms content).
- **Layer N+S as separate validators:** kept as inline blocks within
  brainstorm-validator.sh, matching Layers H/C/K. Extraction to standalone
  validators only if a fourth+ section gate emerges (consolidation
  follow-up from Layer K execution log already pending).

## Norms

- The Norms+Safeguards gate **must** apply universally to all specs created
  during full/debug flows; conditional application by path is forbidden.
- The imperative keyword check **must** scan only lines inside the
  `## Norms` section, never the rest of the spec. Cross-section bleed
  produces false positives.
- The Risk\|Mitigation parser **must** accept any column order
  (`Risk | Mitigation` and `Mitigation | Risk` both valid).
- The validator **shall never** emit false positives on its own spec
  (recursive self-application requirement, smoke-test pattern from
  Layer K).
- The gate **shall never** fire on light/deviation flows that lack a spec
  altogether (no spec → gate is silent).

## Safeguards

| Risk | Mitigation |
|------|------------|
| Imperative keyword regex over-matches words like "always" appearing in casual prose outside the Norms section | Use awk state machine: only scan lines while inside `## Norms` heading until next `## ` heading. Same pattern as Layer K bullet extraction. |
| Markdown table format variation breaks the Risk\|Mitigation parser (e.g., extra spaces, `:---:` alignment markers, multiline cells) | Tolerant grep: extract section, then `grep -iE` for both "Risk" and "Mitigation" tokens on the same heading line. Do not parse table structure formally. |
| Spec template adoption requires migrating existing specs | Out of scope — gate runs only at brainstorm-exit on the spec being created. Pre-existing specs in `docs/superpowers/specs/` do not transit the gate. |
| Recursive self-application: this very spec must pass its own gate post-implementation | Smoke test: run the validator against this spec after implementation. If the validator blocks, fix either the spec or the validator (whichever is wrong). Same protocol used for Layer K. |
| Layer N + Layer S overlap with future "Norms section" content checks (e.g., adding semantic validation later) | Keep current implementation purely structural (presence + minimal keyword/table). Semantic checks require separate justification and are not bundled here. |
| Increased cognitive load on every full/debug spec (~10 extra lines per spec) | Accepted. The cost is a known fixed overhead per spec; the value is forcing articulation of invariants and risk-mitigation pairs. Layer K's lesson: validators that demand content are valuable; validators that demand only headings are ceremony. |

## Implementation outline (informs planning)

1. **Wave 1 — TDD red.** Add 4 fixtures to `test-brainstorm-validator.sh`:
   - **TC-N1:** spec without `## Norms` → block with `- N:` marker.
   - **TC-N2:** spec with `## Norms` heading but no imperative keyword in section content → block.
   - **TC-S1:** spec without `## Safeguards` → block with `- S:` marker.
   - **TC-S2:** spec with both sections, valid imperative keyword + valid Risk\|Mitigation table → pass.
2. **Wave 2 — Implementation (green).** Add Layer N + Layer S blocks to
   `brainstorm-validator.sh` after the Anti-Omision check (lines 59-64) and
   before Layer H (line 66+). Use awk state machine to bound Norms keyword
   scan to the Norms section. Use grep on the Safeguards section heading
   line for the column tokens.
3. **Wave 3 — Verify + document.**
   - Run full test harness: 15 (existing) + 4 (new) = 19/19 green.
   - `bash -n` syntax check.
   - Smoke test: run validator against this spec → exit 0.
   - Document Layer N+S row in CLAUDE.md "Enforcement gates" table.

## Verification plan

- `bash .claude/hooks/test-brainstorm-validator.sh` → 19/19 pass.
- `bash -n` clean on validator + test harness.
- `make lint-shell` if shellcheck installed (precedent from log 2026-04-22:
  acceptable to skip if not installed).
- Smoke test: this spec validates against the new gates.
