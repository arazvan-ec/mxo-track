---
type: feature
tags: [harness, validator, agent, subagent, norms, safeguards, spdd]
files_touched:
  - .claude/hooks/pre-agent-check.sh
  - .claude/hooks/test-pre-agent-check.sh
  - AGENTS.md
  - CLAUDE.md
  - docs/superpowers/specs/2026-04-28-agent-prompt-validator-design.md
  - docs/superpowers/plans/2026-04-28-agent-prompt-validator.md
patterns: [conditional-hard-gate, structured-content-or-reference, sub-invocation, recursive-self-application]
outcome: success
outcome_verified_at: 2026-04-28
regressions_later: []
pr_number: null
estimated_lines: 210
actual_lines: 220
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-28 — Hito 4: Layer Agent (Subagent Prompt Validation)

**Type:** feature (harness — workflow gate)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Backlog ref:** Hito 4 of 5, SPDD analysis 2026-04-28
**Spec:** `docs/superpowers/specs/2026-04-28-agent-prompt-validator-design.md`
**Plan:** `docs/superpowers/plans/2026-04-28-agent-prompt-validator.md`

## Summary

Added Gate 3 to `pre-agent-check.sh` enforcing structured architectural
framing on every non-`Explore` Agent dispatch. Each agent prompt must
include `## Norms` and `## Safeguards` sections, satisfied either inline
(imperative keyword for Norms; Risk|Mitigation table for Safeguards) or
by reference to a spec path within proximity of the section token.

Single source of truth: prompts can reference Hito 1's spec sections
instead of duplicating; agent-specific extensions go inline. Mix-and-match
is permitted (Norms via reference + Safeguards inline, etc.).

## Origin

External analysis (Manus) proposed AGENTS.md updates + a SOFT keyword
scan in `pre-agent-check.sh`. Both rejected:

- SOFT scan = recoil pattern blocked by Layer K (commit `0923cdb`).
- Pure-AGENTS.md change is exhortation without enforcement, the same
  failure mode commit `d3ce7c5` was meant to close.

Hito 1 (commit `b39e543`) made canonical Norms/Safeguards available as
spec sections. The right response was to require either citing those
canonical sections from the agent prompt or inlining equivalent content,
both gated mechanically at PreToolUse.

## Approach Chosen

**A — HARD block at `pre-agent-check.sh` Gate 3, inline OR
spec-reference**, mirroring Hito 1's Layer N+S structural validation
adapted for the prompt body (which is the agent's only context).

Implementation pattern:
- Existing exemption logic preserved (`Explore` skipped at line 22).
- Prompt extracted via `jq -r '.tool_input.prompt'` (already done by
  the hook).
- Prompt written to a tmpfile so awk/grep can extract sections cleanly
  without escaping headaches.
- Section satisfaction: either inline keyword/table (mirror Layer N+S)
  or path-token proximity scan (`docs/superpowers/specs/X.md` within
  ~200 chars of the section heading token).

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/pre-agent-check.sh` | +75 lines: Gate 3 block (helper functions for tmpfile + section check + dual criteria) |
| `.claude/hooks/test-pre-agent-check.sh` | new (~125 lines): 6 fixtures using fixture-repo pattern + JSON tool_input fabrication via jq |
| `AGENTS.md` | +50 lines: new "Norms & Safeguards (mandatory)" section with both forms documented + validation criteria |
| `CLAUDE.md` | +1 row in enforcement gates table |

Net lines: ~220 (estimate 210; +5%).

## Verification

- `bash test-pre-agent-check.sh` → **6/6 pass**
  - A1: dirty repo + general-purpose → deny (Gate 1 regression)
  - A2: clean + Explore → no deny (read-only exemption preserved)
  - A3: clean + missing Norms → deny (new Gate 3)
  - A4: clean + inline Norms+Safeguards → no deny
  - A5: clean + spec-reference Norms+Safeguards → no deny
  - A6: clean + Norms heading without imperative or reference → deny
- Regression: `bash test-brainstorm-validator.sh` → **19/19 pass**
- Regression: `bash test-sync-validator.sh` → **6/6 pass**
- `bash -n` clean on validator + test harness.
- Layer Sync at verification → capture: passed (all touched files
  declared in plan).

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| pre-agent-check additions | +50 | +75 | +50% |
| Test harness | +130 | +125 | OK |
| AGENTS.md | +25 | +50 | +100% |
| CLAUDE.md | +1 | +1 | OK |
| Total | ~210 | ~220 | +5% |

**Causes of the gaps:**
- pre-agent-check: helper functions (`check_section`, `write_prompt_to_tmp`)
  were not budgeted; abstracted them mid-implementation to keep Gate 3
  readable. ~15 extra lines.
- AGENTS.md: documenting both forms with full markdown examples ran
  longer than the rough estimate. Adjusting for future doc work:
  budget ~50 lines per "two-example block + criteria summary".

### 2. Process gaps

- **Sync gate baseline still requires committing the plan first.** Same
  pattern as Hito 2: when the model authors a plan and runs verification
  before committing, the sync gate falls back to `origin/main` baseline
  and reports drift on the entire branch. The fix (commit → re-run) is
  trivial but recurring. Follow-up candidate: extend sync-validator's
  fallback to also try `git diff` against the last commit if the plan
  is in working tree but not in git log. Defer to its own interaction.

- **No new architectural gaps.** The hook structure was clear from the
  existing `pre-agent-check.sh` shape; Gate 3 slots in cleanly after
  Gate 2 with no refactoring needed.

### 3. Emergent patterns

- **Three-gate composition in `pre-agent-check.sh`** (Gate 1 clean repo,
  Gate 2 classify warn, Gate 3 Norms+Safeguards). All three short-circuit
  via JSON output. Pattern stable.

- **"Inline OR reference" criterion** as a structural test. First
  occurrence in the harness. If a fourth section-validation gate uses
  this OR-criterion (vs. Layer N+S+H+K which use single-form
  validation), graduate to a shared helper alongside the
  pending `lib/section-validator.sh` (Layer K convergence follow-up).

- **Sub-invocation pattern usage:** not used here (Gate 3 is inline in
  pre-agent-check). Layer C and Layer Sync remain the only sub-invocation
  examples. Pattern still stabilizing at 2 occurrences.

## Follow-ups

1. **Sync-validator fallback for working-tree plans** — if the plan is
   in working tree but not committed, diff against HEAD only (not
   `origin/main`) so the gate doesn't conflate prior interactions
   with current. Captured from Hito 2's retrospective; reinforced here.
2. **Extract `lib/section-validator.sh`** — graduation of the
   section-presence + content-classification pattern, now at 5
   occurrences (H, K, N, S, Layer Agent). Highest-priority follow-up.
3. **Read-only subagent registry** — the exemption list is currently
   one item (`Explore`). When it grows past 3, graduate to a YAML
   registry consumed by the validator.
