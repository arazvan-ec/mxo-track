# Spec — Layer K: Anti-Reduction Validator

**Date:** 2026-04-28
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)
**Backlog ref:** Propuesta #0 del análisis SPDD vs CLAUDE.md (2026-04-28)

## Problem

The 4-test in `CLAUDE.md` is intended as a kill switch: a proposal that
fails any of the four criteria should be discarded. In practice, the
model exhibits a recurring bias — when faced with a Test 3 (cost/value)
failure, it scales the proposal down until it passes, instead of
discarding it. This was observed in this very session (the Ubiquitous
Language System was first reduced to an "MVP that starts empty" before
the user demanded the maximal version).

Commit `d3ce7c5` corrected the textual rule in CLAUDE.md
("Rewriting a failing proposal at reduced scope to make it pass is a
concession, not a fix"). The textual fix is necessary but not
sufficient: the bias originates in the model's priors, not in
CLAUDE.md text. Per CLAUDE.md's own "Why This Workflow Exists" section,
"LLMs do not autonomously apply development practices" — text-only
discipline does not hold.

## Approach Chosen

**A — Trigger-based Layer K** mirroring Layer H's pattern (conditional
HARD gate from `ad11cc4`):

1. Scan the spec for reduction markers (closed list, see "Markers").
2. If any marker appears, require a `## Maximal Version Considered`
   section with at least these four bullets:
   - Maximal version description.
   - Concrete 4-test failure that ruled it out (cite test number).
   - Proposed (reduced) version description.
   - **Independent superiority bullet** — argument that the reduced
     version is genuinely better, not merely cheaper. The validator
     rejects this bullet if it contains only cost-language tokens
     (`coste`, `cost`, `barato`, `cheap`, `simple`, `tokens`,
     `líneas`, `lines`).
3. If markers present and section absent → BLOCK (exit 2).
4. If section present but missing structural bullets or the
   independent-superiority bullet fails the cost-language check →
   BLOCK (exit 2).
5. If no markers present → pass-through (no work).

## Markers (closed list)

```
MVP
mínimo viable
minimum viable
fase 1
phase 1
incremental
ligero
light(?:weight)?
subset
v0
versión reducida
reduced version
scope[- ]down
arrancar vacío
start empty
```

The list is closed (no regex wildcards beyond the explicit alternatives
above) to bound the false-positive surface. New markers graduate via
execution-log evidence (≥3 occurrences).

## Alternatives Considered (and rejected)

**B — Always-required section.** Every spec carries
`## Maximal Version Considered`, with "N/A" allowed when no reduction
applies.

- **Rejected because:** falla el Test 3 del propio 4-test. Inflate
  every spec to capture a minority case → ceremony, not flow. The
  Layer J removal (2026-04-26, commit `231f951`) set the precedent for
  this exact failure mode.

**C — Warning + conditional HARD.** If markers present without
section → exit 1 (warn). If section present but bullet fails →
exit 2 (block).

- **Rejected because:** the warning path is itself the concession this
  layer aims to prevent. A model that omits the section escapes with
  a warning the next stop hook will scroll past. Accepting C here
  would replicate the bias the layer exists to break. This is the
  recursive application of the rule introduced in commit `d3ce7c5`.

## Trade-offs of the Chosen Approach

**Advantages:**
- Mirrors Layer H exactly — patrón validado, predictible, testable in isolation.
- Trigger-based: zero overhead on specs without reduction markers
  (~99% of specs in current logs).
- The independent-superiority requirement forces the model to
  articulate non-cost reasoning, exposing the bias when it occurs.

**Disadvantages:**
- Closed marker list will need maintenance (graduation pathway).
- Cost-language regex is heuristic; a sufficiently creative
  rationalization can bypass it. Mitigation: the user reviews the
  spec — this layer is a backstop, not the only line of defense.

## Maximal Version Considered

Required by Layer K itself, applied recursively to this spec. The spec
contains the marker "MVP" while narrating the motivating incident
(Ubiquitous Language System reduction). Layer K cannot distinguish
narration from proposal, so it requires this section unconditionally.

- **Maximal version:** the trigger-based Layer K described in
  "Approach Chosen". Marker detection + section requirement +
  positive-signal bullet check. ~35 validator lines + ~110 test lines.
- **Why not a smaller version:** Approaches B (always-required) and
  C (warning-only) were both rejected on quality grounds, not cost
  grounds (see "Alternatives Considered"). B fails Test 3 of the
  4-test (ceremony for the majority case). C reproduces the exact
  concession pattern this layer exists to prevent — accepting C
  here would be self-contradicting.
- **Proposed version:** identical to maximal. No reduction.
- **Independent superiority:** Approach A maps directly to the
  endorsed Layer H pattern (commit `ad11cc4`), preserving
  architectural consistency in the validator chain. Choosing B or C
  would fracture the convention and require maintaining two
  enforcement styles in the same file. The proposed version is
  superior on consistency and predictability grounds, independent
  of any cost argument.

## 4-Test Application (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Demonstrated in this session |
| 2. Inyectado en fase correcta | ✓ | Spec-exit, before planning. Rollback cost: minutes |
| 3. Coste proporcional al valor | ✓ | ~30 lines validator + ~80 lines tests; same order as Layer H. Prevents a class of design failures already observed in logs |
| 4. Backed by source | ✓ | This conversation + commit `d3ce7c5` (textual rule already in place) |

Pass on all four. No reduction needed.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/validators/brainstorm-validator.sh` (191 lines, layers Anti-Omision + H + C + TDD-isolation + parallel-conflict) | Transform | Add Layer K block alongside H |
| `.claude/hooks/test-brainstorm-validator.sh` (379 lines, fixture pattern from `2026-04-22`) | Transform | Add 4 TDD test cases for Layer K |
| `.claude/hooks/validators/socratic-review-validator.sh` | Omit | Different concern, untouched |
| `.claude/hooks/lib/ddd-boundaries.sh` | Omit | Layer K applies to all specs, not only critical contexts |
| `CLAUDE.md` 4-test section | Omit (deferred) | Already corrected in `d3ce7c5`. Further simplification (removing the second sentence) is a follow-up after Layer K lands |

## Omission Decisions

- **Spec template under `docs/superpowers/specs/`** — omitted. No
  canonical template exists today. Documenting the new section in
  CLAUDE.md is sufficient and is included in the implementation plan.
- **Graduation script for new markers** — omitted. The closed list is
  small; markers will be added by direct edit when execution logs show
  3+ occurrences. A graduation script (analogous to `graduate.sh`) is
  premature.

## Implementation outline (informs planning phase)

1. Add Layer K block to `brainstorm-validator.sh` (after Layer H,
   before TDD-isolation block). Marker detection via `grep -iE`,
   section detection via `awk` (same pattern as H), bullet
   verification via `awk` + `grep -ivE` for cost-language rejection.
2. Add 4 TDD cases to `test-brainstorm-validator.sh`:
   - Spec without markers → no work, pass.
   - Spec with marker, no section → BLOCK.
   - Spec with marker + section but bullet contains only cost-language → BLOCK.
   - Spec with marker + section + non-cost-language bullet → pass.
3. Document Layer K in CLAUDE.md alongside Layers F/H/I/J references.

## Verification plan

- `bash .claude/hooks/test-brainstorm-validator.sh` — all cases (existing + new) pass.
- `bash -n` syntax check on validator and test harness.
- `make lint-shell` if shellcheck available.
- Smoke test: run validator against this very spec — must pass
  (the spec itself does not contain reduction markers in content,
  only as code-block content which the regex must not match against
  fenced blocks; if it does, that is itself a finding to fix).

## Risks and mitigations

- **False positive on this spec.** The Markers section above lists
  marker tokens as content. The regex must restrict matches to
  occurrences outside fenced code blocks. If implementation reveals
  this is non-trivial in shell, fallback: scan only lines not inside
  ``` fences, using a state machine in awk.
- **Marker list drift.** Mitigated by closed-list policy: new markers
  enter only with execution-log evidence.
