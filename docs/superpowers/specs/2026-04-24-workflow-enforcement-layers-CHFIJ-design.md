---
type: spec
feature: workflow-enforcement-layers-CHFIJ
date: 2026-04-24
branch: claude/enhance-routes-widget-8UzuC
related_logs:
  - docs/superpowers/execution-logs/2026-04-24-routes-widget-audit-fixes.md
  - docs/superpowers/execution-logs/2026-04-23-three-followups-test5-agents-harness.md
---

# Spec — Workflow Enforcement Layers (C+H+F+I+J) + Agent Permission Model Correction

## Context

The socratic audit of the routes widget feature (logged 2026-04-24) revealed
that a documented architectural rule in `backend/CLAUDE.md` ("adding new code
that deepens ORM coupling in critical contexts is not acceptable, even if the
surrounding code does it") passed through every existing workflow gate
because no validator actually reads specs for architectural compliance.

The user accepted that conclusion and requested the "most complete" solution:
five enforcement layers targeting distinct phases of the flow, plus a
correction to prior misdocumentation about agent permission model.

## Problem

Five distinct failure classes slipped through the existing gates:

1. **Spec-time:** spec said "pragmatic — mirrors existing pattern" without
   checking whether the mirrored pattern was endorsed.
2. **Edit-time:** controller added raw EntityManager queries against a
   critical DDD context (Route) — exactly what `backend/CLAUDE.md` forbids.
3. **Review-time:** tests verified shape (DTO fields, values) but not
   architecture (did this respect the DDD boundary?).
4. **Retrospective-time:** initial retrospective focused on estimate
   accuracy + emergent patterns; it did not raise any architectural
   concern. The DDD issue only surfaced when the user asked for socratic
   analysis hours later.
5. **Cross-cutting:** prior `AGENTS.md` documentation described the
   agent-to-`.claude/**` restriction as a sandbox-level block, but empirical
   testing proves the block is from `classify-validator.sh` and is
   conditional on `interaction_classification`. The wrong diagnosis has
   been live for one day and would misdirect future orchestrators.

## Approaches Considered

### Approach α — Single "audit" phase (rejected)

Add a single adversarial-review phase that catches everything. One hook to
maintain.

- **Ventaja:** minimal ceremony.
- **Desventaja:** one gate at one phase cannot catch issues that spawn at
  other phases. E.g., a spec-time failure caught only post-implementation
  costs implementation effort that could have been prevented. **Rejected.**

### Approach β — Layered defense across phases (**chosen**)

Five gates, each at a distinct phase, each catching a distinct failure class.
Defense-in-depth.

- **Ventaja:** each failure mode is caught at the earliest possible phase,
  minimizing rework.
- **Ventaja:** absence of one layer doesn't defeat the whole system.
- **Desventaja:** more ceremony, more validators to maintain, more places
  a developer can be blocked.
- **Trade-off accepted:** the 2026-04-21→24 work showed that ceremony
  bought was a net positive (caught 5 of 9 issues only because the user
  asked for socratic review; a fraction of those would have been caught
  mechanically with these layers).

### Approach γ — External review (rejected)

Require a human reviewer to pass certain phases. Bypasses the automation
entirely.

- **Ventaja:** most robust — humans catch what validators miss.
- **Desventaja:** doesn't fit the agent-driven workflow; reviewer latency
  negates fast-iteration benefits. **Rejected for this scope.**

## Approach β — Detailed Design

### C — Socratic review phase (catches at review-time)

**What:** new phase `socratic_review` inserted between `verification` and
`capture` in full and debug flows.

**Validator:** `socratic-review-validator.sh`
- Requires `evidence.socratic_questions` array with ≥3 entries (specific,
  non-generic strings).
- Mandatory trigger keywords when critical paths touched:
  - `backend/src/Domain/` or `backend/src/Controller/Api/` or `frontend/src/`
  - Questions must include at least one containing words like
    `endorsed|boundary|DDD|tech.?debt|architecture|coupling`.
- Rejects if questions look templated (e.g., generic phrases from a canned
  list repeated verbatim).

**Files:** `flow-phases.sh`, `phase-advance.sh`, new validator + test,
`user-prompt-state.sh` + `workflow-status-line.sh` (display), CLAUDE.md
discipline section.

### H — Prior-art audit at spec-time (catches at spec-time)

**What:** extend `brainstorm-validator.sh`. If the spec touches critical
paths (regex match on spec content against `src/Domain/(Route|Shipment)`
OR mentions of `backend/src/Controller/Api/`), require a `## Prior Art
Audit` section with at least one row whose "Endorsed?" column contains
`✅`, `❌ tech-debt`, or `new`.

**Fail state:** block brainstorming→planning with pointer to the required
section.

### F — Edit-time DDD-boundary check (catches at edit-time)

**What:** new PreToolUse Edit|Write hook `ddd-boundary-check.sh` that:
- Loads `docs/knowledge/_ddd-boundaries.yaml` (new file) listing critical
  contexts and known violations.
- For Edit|Write to paths matching a critical context:
  - Non-blocking warning if the edit introduces new
    `createQueryBuilder` or `EntityManagerInterface::getRepository` usage.
  - Only blocks if classification is `full`/`debug` AND no spec-level
    Prior Art Audit covers the path.

**Bypass:** `SKIP_DDD_BOUNDARY_GATE=1` with decision log requirement.

### I — Retrospective content gate (catches at retrospective-time)

**What:** extend `retrospective-validator.sh` to require retrospectives
mention at least one of: "adversarial question", "prior art", "DDD",
"architecture boundary", "coupling", OR an explicit
`evidence.retrospective_no_architectural_concerns = true` declaration.

**Rationale:** forces the architectural question even when the author is
not spontaneously asking it.

### J — Graduation registry check (catches at spec/consult-time)

**What:** extend `consult.sh` OR `brainstorm-validator.sh` to load
`docs/knowledge/_graduations.yaml` and warn (non-blocking) if the spec
mentions a pattern name that is NOT in the graduations. Soft-gate.

**Rationale:** surfaces patterns borrowed from non-endorsed sources.

### Wave 0 — Documentation correction

Before the 5 layers: fix the misdiagnosis that the agent restriction is a
sandbox-level block. The correction goes into `AGENTS.md` (Agent Permission
Model section, already added 2026-04-23), `docs/knowledge/workflow-engine.md`
(the adjacent-to-Layer-A subsection), and `pre-agent-check.sh` (extend to
warn when classification is insufficient for likely `.claude/**` work).

## Existing Functionality Inventory

| Element | Currently handled? | Decision |
|---|---|---|
| `classify-validator.sh` | Yes — blocks framework paths if class ∉ {full,debug} | Include — extended by F to consult boundaries YAML |
| `brainstorm-validator.sh` | Yes — parallel conflict + spec keywords | Extend for H (Prior Art Audit) and J (graduation check) |
| `retrospective-validator.sh` | Yes — checks `retrospective_shown` + Lessons section | Extend for I (architectural-question check) |
| `phase-advance.sh` | Yes — legal phase sequences from `flow-phases.sh` | Extend for C (insert `socratic_review`) |
| `flow-phases.sh` | Yes — single source of truth | Extend for C (add `socratic_review` to FULL/DEBUG sequences) |
| `user-prompt-state.sh` + `workflow-status-line.sh` | Yes — display phase timelines | Extend for C (render `socratic_review` phase) |
| `pre-agent-check.sh` | Yes — denies Agent dispatch when dirty | Extend for Wave 0 (warn when class insufficient) |
| `AGENTS.md` Agent Permission Model section | Added 2026-04-23, but misdiagnosed cause | Rewrite the diagnosis (Wave 0) |
| `docs/knowledge/workflow-engine.md` | Added 2026-04-22, mirrors misdiagnosis | Rewrite adjacent subsection (Wave 0) |
| `docs/knowledge/_graduations.yaml` | Exists (graduations.yaml) | Reused by J |
| `docs/knowledge/_ddd-boundaries.yaml` | Does not exist | Create (F) |
| `CLAUDE.md` 8-shortcuts catalog | Exists (added by Option 3-Enforced) | Extend with new shortcuts caught by C/H/F/I/J |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| AST-level PHP analysis for F | Omit | Grep heuristics cover 95% of the concrete case (new `createQueryBuilder` in controllers for critical aggregates). Full AST adds significant complexity for marginal gain. Revisit when false positives appear. |
| Automatic decision log generation on bypass use | Omit | Bypass env vars already require manual decision log entry per policy. Auto-generation would dilute the discipline. |
| Historical retroactive check (scan existing files for violations) | Omit | Out of scope. Current violations listed in `backend/CLAUDE.md` Known Violations table; new violations caught going forward. |
| `socratic_review` for `micro`/`light`/`agent` flows | Omit | Those flows are deliberately low-ceremony for changes that don't warrant architectural review. |
| Multilingual validator messages (English + Spanish) | Omit | Existing validators are Spanish-only; follow that convention for consistency. |

## Design Details

### Phase sequence change (C)

**Before:**
```
full:  consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize
debug: root_cause → pattern_wide → fix → verification → capture → retrospective → finalize
```

**After:**
```
full:  consult → brainstorming → planning → implementation → verification → socratic_review → capture → retrospective → finalize
debug: root_cause → pattern_wide → fix → verification → socratic_review → capture → retrospective → finalize
```

Agent flow unchanged (no socratic_review for sub-agents working under a
parent's design).

### `evidence.socratic_questions` schema

```json
{
  "evidence": {
    "socratic_questions": [
      "Does the approach match the endorsed pattern documented in backend/CLAUDE.md, or does it extend tech debt?",
      "Do the tests validate architecture (boundaries respected) or only shape (fields present)?",
      "What edge case might a functional test catch that unit-mock tests cannot?"
    ]
  }
}
```

### `docs/knowledge/_ddd-boundaries.yaml` shape

```yaml
critical_contexts:
  - path: backend/src/Domain/Route/**
    aggregates: [Route, RouteStop, RouteSnapshot, RouteEvent]
  - path: backend/src/Domain/Shipment/**
    aggregates: [Shipment, Parcel, DeliveryEvidence]

forbidden_in_non_infrastructure:
  - pattern: "createQueryBuilder"
    context: "controllers and services outside Infrastructure/"
    against: "aggregates listed in critical_contexts"

known_violations:
  # Pre-existing tech debt; don't extend, don't flag existing.
  - file: backend/src/Controller/Api/Admin/RouteListApiController.php
    method: list
    note: "Resolved 2026-04-24 — now uses RouteStopRepositoryInterface"
  - file: backend/src/Application/Delivery/DeliveryService.php
    note: "Depends on concrete RouteStopRepository per backend/CLAUDE.md Known Violations"
```

### Retrospective-validator extension (I) — the check

```bash
# Extend retrospective-validator.sh
LOG_CONTENT=$(cat "$LOG_PATH" 2>/dev/null || echo "")

ARCH_KEYWORDS='adversarial|prior.?art|DDD|boundary|coupling|architectural|endorsed|tech.?debt'
EXPLICIT_OPT_OUT=$(jq -r '.evidence.retrospective_no_architectural_concerns // false' "$STATE_FILE")

if ! echo "$LOG_CONTENT" | grep -qiE "$ARCH_KEYWORDS" && [ "$EXPLICIT_OPT_OUT" != "true" ]; then
  ERRORS="${ERRORS}- Retrospective lacks architectural-concern section. Either mention one of: adversarial question, prior art, DDD boundary, coupling, architectural concern; OR set evidence.retrospective_no_architectural_concerns=true with justification in Lessons.\n"
fi
```

## Parallelization Strategy

Wave 0 (sequential, main) → Wave 1 (4 parallel agents) → Wave 2 (main
integration) → Wave 3 (main verify + socratic_review self-application)
→ Wave 4 (main capture + retro + finalize).

Full wave breakdown lives in the plan.

## Verification Plan

- All existing validator tests remain green.
- Three new validator tests: `test-socratic-review-validator.sh`,
  `test-ddd-boundary-check.sh`, extended `test-brainstorm-validator.sh`
  (for H+J), extended `test-retrospective-validator.sh` (for I).
- `test-phase-advance.sh` extended for the new phase sequence.
- The new `socratic_review` phase is self-applied to this very PR
  (recursive dogfooding).

## Non-goals

- Retroactive enforcement on existing repo content.
- AST-level PHP code analysis.
- Multi-language support for validator output.
- Generic "architecture review" that catches every possible SOLID violation
  — this scope is limited to the five specific failure classes documented
  in the 2026-04-24 audit.
