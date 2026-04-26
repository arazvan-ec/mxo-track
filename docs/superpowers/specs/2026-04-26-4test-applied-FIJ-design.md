---
type: spec
feature: 4test-applied-FIJ
date: 2026-04-26
---

# Spec — Apply the 4-Test to Layers F, I, J

## Context

The 4-test (codified in `CLAUDE.md` "Why This Workflow Exists" earlier
this interaction) demands that any workflow gate pass: (1) forces a
practice the LLM wouldn't do; (2) injected at the right phase; (3)
token-cost proportional; (4) backed by a source. Layers F, I, J
shipped 2026-04-24 had not been evaluated against this filter.

## Approaches Considered

### Approach α — Trust the original specs and leave F/I/J as-is
Skip retroactive review. Adds nothing; preserves whatever weakness
the layers carry. Rejected: the 4-test was created precisely to do
this kind of pruning.

### Approach β — User-facing review only (no code changes)
Run the 4-test, write up findings, defer changes. Rejected: each
finding needs to be acted on at some point; deferring just adds
backlog without commitment.

### Approach γ — Parallel analysis + selective changes (chosen)
Three concurrent read-only agents apply the 4-test to F, I, J. I
synthesize, propose actions, user authorizes Tier 1 + Tier 2. Apply
in foreground. Each layer either keeps, gets strengthened, or gets
removed based on its score.

## Findings (per analysis reports)

- **F:** Tests 1 & 2 partial, 3 & 4 pass. Recommendation:
  STRENGTHEN (WARNING → BLOCK conditional in full/debug) +
  CONSOLIDATE (H reads YAML).
- **I:** Test 2 fails (Layer C now covers the same concern at
  brainstorm). Recommendation: REMOVE the architectural-keyword
  check; preserve the visibility/length checks.
- **J:** Tests 2, 3, 4 fail. Recommendation: REMOVE entirely; no
  log ever showed J catching a real issue and the 3rd extraction
  heuristic produces noise.

## Existing Functionality Inventory

| Element | Decision |
|---|---|
| `ddd-boundary-check.sh` Layer F warning | **Modify** — promote to conditional BLOCK |
| `brainstorm-validator.sh` Layer H critical-paths regex | **Modify** — read from `_ddd-boundaries.yaml` SoT |
| `brainstorm-validator.sh` Layer J pattern-extraction block | **Remove** — see /tmp/layer-j-analysis.md |
| `retrospective-validator.sh` Layer I architectural-keyword check | **Remove** — see /tmp/layer-i-analysis.md |
| `socratic-review-validator.sh` arch-keyword path regex | **Modify** — read from shared lib |
| New `lib/ddd-boundaries.sh` | **Add** — single SoT helper for both H and F (and any future consumer) |
| `test-brainstorm-validator.sh` J cases | **Remove** |
| `test-retrospective-validator.sh` Layer I cases | **Remove**, add post-removal baseline |
| `CLAUDE.md` shortcuts table | **Modify** — drop I and J rows, update F/H descriptions |
| `.claude/README.md` evidence matrix | **Modify** — same |

## Omission Decisions

| Element | Decision |
|---|---|
| Migration shim (transition period) | Omit — feature branch, atomic switch is safe |
| Extending F's BLOCK to debug-only or other refinements | Omit — full-flow + debug is the agreed scope |
| Replacing J with a different graduation-checking mechanism | Omit — `pattern-audit.sh` exists; no evidence J added value |
| Backward-compat for `evidence.retrospective_no_architectural_concerns` | Omit — only Layer I read it; no other consumer |

## Prior Art Audit

This refactor edits harness files (`.claude/hooks/**`). No edits to
`backend/src/Domain/`, `backend/src/Controller/Api/Admin/`, or any
critical DDD context.

| Path | Endorsed? | Evidence |
|---|---|---|
| `.claude/hooks/lib/flow-phases.sh` | ✅ | Existing single-SoT precedent for shared lib pattern. |
| `.claude/hooks/validators/brainstorm-validator.sh` | ✅ | Endorsed brainstorm-exit gate; receives modifications. |
| `.claude/hooks/validators/retrospective-validator.sh` | ✅ | Endorsed retrospective-exit gate; loses Layer I sub-check. |
| `.claude/hooks/ddd-boundary-check.sh` | ✅ | Endorsed Layer F edit-time hook; gains conditional BLOCK branch. |
| `.claude/hooks/lib/ddd-boundaries.sh` | new | New shared helper; no prior art. |

## Architectural Adversarial Review

1. **Q:** Does removing Layers I and J weaken the workflow's enforcement
   surface, given that they were intended as defense-in-depth?
   **A:** No. Layer I duplicated Layer C's coverage at a later phase
   (higher rollback cost, weaker keyword matching); removing it
   eliminates redundant ceremony. Layer J had Test-2/3/4 failures
   under the 4-test — defense-in-depth requires each layer to add
   independent value, which J did not. Net enforcement is preserved
   or improved (F's BLOCK promotion strengthens the boundary).

2. **Q:** Does the new shared `lib/ddd-boundaries.sh` introduce a
   tight coupling between brainstorm-validator and ddd-boundary-check
   that makes future evolution harder?
   **A:** The coupling is in the SOURCE OF TRUTH (the YAML file), not
   in the consumers. Both consumers source a small helper that returns
   a regex — pure read coupling. Either consumer can evolve
   independently. This mirrors `flow-phases.sh` which two status-line
   scripts already source without issue.

3. **Q:** What tradeoff are we accepting by making F a conditional
   BLOCK (full/debug only) instead of an unconditional one?
   **A:** Light/explore/micro classifications skip a real DDD violation
   if they happen to edit critical-context files. Accepted because
   classify-validator already prevents non-full/debug from editing
   framework code; the path that would slip through is narrow (e.g.,
   docs-flagged classifications that incidentally touch backend
   source). The cost of full BLOCK in light flow would be friction
   on legitimate light edits to non-critical paths.

## Verification Plan

- All harness tests remain green: 97 expected (was 100 before;
  retired 2 J cases + 1 net change in I cases).
- No regression in: test-flow-phases, test-phase-advance, etc.
- F's BLOCK branch tested in production scenarios (no unit fixture
  yet; follow-up).

## Non-goals

- Adding new fixtures for F's BLOCK branch (deferred follow-up).
- Touching Layer C — it already passed the 4-test in the prior
  socratic_review-relocation work.
- Migrating any active session evidence.
