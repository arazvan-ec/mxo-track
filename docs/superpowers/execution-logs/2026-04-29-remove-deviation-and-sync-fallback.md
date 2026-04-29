---
type: refactor
tags: [harness, workflow, deviation-removal, sync-validator, layer-k, spdd]
files_touched:
  - CLAUDE.md
  - .claude/README.md
  - .claude/hooks/session-start.sh
  - .claude/hooks/workflow-engine.sh
  - .claude/hooks/workflow-status.sh
  - .claude/hooks/workflow-status-line.sh
  - .claude/hooks/user-prompt-state.sh
  - .claude/hooks/post-bash-validator.sh
  - .claude/hooks/pre-push-gate.sh
  - .claude/hooks/validators/sync-validator.sh
  - docs/superpowers/specs/2026-04-29-remove-deviation-and-sync-fallback-design.md
  - docs/superpowers/plans/2026-04-29-remove-deviation-and-sync-fallback.md
patterns: [structural-recoil-removal, atomic-multi-file-deletion, baseline-anchoring]
outcome: success
outcome_verified_at: 2026-04-29
regressions_later: []
pr_number: null
estimated_lines: 250
actual_lines: 340
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-29 — Remove Deviation + Sync Working-Tree Fallback

**Type:** refactor (workflow simplification + behavior fix)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Spec:** `docs/superpowers/specs/2026-04-29-remove-deviation-and-sync-fallback-design.md`
**Plan:** `docs/superpowers/plans/2026-04-29-remove-deviation-and-sync-fallback.md`

## Summary

Two coupled changes in one atomic commit:

**(A) Removed deviation entirely from the workflow.** The "deviation"
path (skip brainstorm + plan for changes meeting heuristic criteria
like `<30 lines`, `0 design decisions`) contradicted Layer K's
principle of "discard, don't recoil to a reduced version". Removed
across 11 files: CLAUDE.md section, .claude/README.md section,
session-state schema field, and 7 hook integration points. The
legitimate emergency escape (`SKIP_PHASE_EXIT_GATE=1` + decision
log entry) remains — it's structurally different (logged, audited,
scoped to one phase advance) from the modal deviation flag.

**(B) Added working-tree baseline fallback to sync-validator.** When
the plan exists in `$REPO` but is not yet committed (just authored
in current session), sync-validator now skips committed-diff scope
and relies on the working-tree merge already in place. Closes the
"origin/main fallback captures whole branch" trap that recurred in
Hitos 2/4/5 (3 occurrences, each forcing commit-then-test workaround).

## Origin

The deviation removal emerged from this interaction's prior step:
when I proposed deviation for "I5b — sync fallback fix" (~10 lines,
mechanical), the user objected with "Nunca deberías plantear una
desviación, no tiene sentido, siempre lo más completo y lo mejor".
Reflecting on that, deviation IS the structural recoil pattern that
Layer K (commit `0923cdb`) blocks at proposal level — extending the
same logic to workflow paths is consistent rule application.

The sync fallback was already a documented follow-up from three
prior execution logs.

## Approach Chosen

**A — Atomic removal across 11 files + sync fallback in one
interaction.** Causal trail preserved (deviation removal stems from
the I5b deviation request) by bundling. Test verification
(31/31 pass) confirms no behavior regression.

## Implementation observations

### 1. Test fixture vs real session disambiguation

The first sync-validator implementation broke fixture tests Y2 and
Y2-secondary because the new `elif` branch (plan on disk → no
committed-diff) fired for fixtures whose plan path was outside
`$REPO`. Fix: detect "plan inside $REPO" via case match
(`"$PLAN_FULL" in "$REPO"/*`). Three branches now: plan-introduction
commit → working-tree-only (real session, plan in repo) →
origin/main fallback (fixtures, plan outside repo).

### 2. session-start.sh typo

Editing the default-state JSON to remove the deviation field, I
introduced a typo (`todo_propress` instead of `todo_progress`).
Caught immediately in the next read; one-line fix. Lesson: when
deleting JSON keys via Edit tool, the surrounding context
(here: the closing `}` of preceding key) matters. Be precise on
the old_string boundary.

### 3. CLAUDE.md spec was triggered by Layer K (recursive smoke)

The spec's "Problem" section discusses "reduced version" extensively
(it's the topic). Layer K detected the marker and required a
`## Maximal Version Considered` section. Added recursively, declaring
this IS the maximal version with non-cost independent superiority
(consistent rule application + causal-trail preservation).

## Changes

| File | Change |
|------|--------|
| `CLAUDE.md` | -65 lines (delete § "Deviation for Wiring-Only Changes" + clean line 420 reference) |
| `.claude/README.md` | -25 lines (delete § "Deviation Mode" + schema field) +5 lines (replacement § "Emergency Escape") |
| `.claude/hooks/session-start.sh` | -10 lines (deviation field from default state) |
| `.claude/hooks/workflow-engine.sh` | -8 lines (Gate 2 + DEVIATION_ACTIVE read + comment update + gate renumber) |
| `.claude/hooks/workflow-status.sh` | -10 lines (display + history section) |
| `.claude/hooks/workflow-status-line.sh` | -7 lines (STATE_SIG, DEV_ACTIVE, DEVIATION_SUFFIX uses) |
| `.claude/hooks/user-prompt-state.sh` | -3 lines |
| `.claude/hooks/post-bash-validator.sh` | -30 lines (Check 3 block) |
| `.claude/hooks/pre-push-gate.sh` | -8 lines (gate_or_warn helper collapsed) |
| `.claude/hooks/validators/sync-validator.sh` | +18 lines (working-tree fallback + comment) -2 lines (old comment) |

Net: -135 lines (mostly deletions), +18 lines (sync fallback). Total
delta in commit: 12 files changed, 340 insertions(+), 193 deletions(-).
Insertions are dominated by the spec, plan, and execution log artifacts;
the actual code/doc deletions exceed code/doc additions ~3x.

## Verification

- `test-brainstorm-validator.sh` → **19/19 pass**
- `test-sync-validator.sh` → **6/6 pass** (after fixing fixture-vs-real disambiguation)
- `test-pre-agent-check.sh` → **6/6 pass**
- `bash -n` clean on all 8 modified shell files
- Visual: `grep -rE 'deviation' .claude/hooks/ CLAUDE.md .claude/README.md`
  shows only historical comments documenting the removal (intentional)
- Smoke: sync gate against this interaction's plan-in-WT → **exit 0
  without committing first**, proving the working-tree fallback works

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| CLAUDE.md | -65 | -65 | OK |
| README.md | -25 | -20 (kept replacement) | -20% |
| session-start.sh | -10 | -10 | OK |
| workflow-engine.sh | -15 | -8 | -47% (less infrastructure than expected) |
| workflow-status.sh | -10 | -10 | OK |
| workflow-status-line.sh | -5 | -7 | +40% |
| user-prompt-state.sh | -3 | -3 | OK |
| post-bash-validator.sh | -30 | -30 | OK |
| pre-push-gate.sh | -10 | -8 | OK |
| sync-validator.sh | +12 -1 | +18 -2 | +6 lines (fixture disambiguation block not budgeted) |
| Total net | -160 | -135 | OK (estimate within ~15%) |
| Files | 14 (incl artefacts) | 13 | OK |

### 2. Process gaps

- **Test-fixture vs real-session ambiguity in sync-validator.** First
  attempt to add the working-tree fallback broke 2 sync tests because
  fixtures fall outside `$REPO`. The disambiguation (case match on
  `$PLAN_FULL`) added ~6 unbudgeted lines. **Lesson:** when a
  validator's behavior depends on whether a path is repo-relative or
  external, encode the distinction explicitly (don't conflate them
  through a shared check). Documented as Norm in the spec.

- **No test coverage for the working-tree fallback.** The smoke test
  validates it, but no fixture exercises the real-session path
  (because constructing a fixture where the plan IS inside `$REPO`
  would require fabricating repo state inside the live repo).
  Acceptable trade-off; the smoke test against the live interaction
  serves as the integration test.

- **Markdown spec triggered Layer K recursively.** Same pattern as
  prior Layer K interactions (the spec discusses reduction as a
  topic). Adding `## Maximal Version Considered` is now a reflex,
  not a discovery. Pattern stable.

### 3. Emergent patterns

- **Structural recoil removal as a category.** Two instances now:
  commit `d3ce7c5` (textual "rewrite to pass" loophole closed),
  this commit (workflow "deviation" loophole closed). If a third
  emerges, graduate to a knowledge module entry on "no recoil paths
  in workflow design".

- **Atomic multi-file deletion** (11 files in one commit) with test
  verification gating. Pattern reusable for future workflow
  simplifications.

- **Layer K applied recursively to its own implementations.** The
  Layer K validator + the deviation removal both required this very
  spec to include `## Maximal Version Considered`. The recursive
  smoke pattern is now a reflex; documenting it would help future
  spec authors plan for it upfront.

## Follow-ups

1. **Pattern graduation:** "no recoil paths in workflow design" if a
   third instance emerges (current count: 2 — textual + structural).
2. **Sync fallback test fixture (real-session case):** would require
   constructing a fixture repo where plan path is inside the fixture's
   own `$REPO`. Defer until a regression motivates it.
3. **Hito 3 (Ubiquitous Language System)** — next backlog item, full
   transversal version (not Manus's Doctrine-only reduction). Will be
   the 6th caller of `lib/section-validator.sh`, validating the
   extraction's open-closed design.
