# Spec — I5b': Remove Deviation + Sync-Validator Working-Tree Fallback

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow) — workflow simplification + behavior fix
**Backlog ref:** I5b deferred from harness consolidation log
(2026-04-28-harness-consolidation.md), expanded to remove deviation
entirely after the user objection that deviation contradicts the
Layer K principle.

## Problem

Two coupled issues:

**(A) The "deviation" workflow path contradicts Layer K.** Layer K
(commit `0923cdb`) mechanizes the rule "discard, don't recoil to a
reduced version of a failed proposal". Deviation is precisely a
reduced version of full flow — same change, less rigor — gated by
heuristic criteria (`<30 lines`, `0 design decisions`, etc.) that
the model cannot reliably evaluate before doing the work. The two
documented justifications in CLAUDE.md and `.claude/README.md`
disagree (wiring-only vs hotfix emergencies), evidence the abstraction
itself is muddled. The only legitimate emergency path needs proper
accountability via `SKIP_PHASE_EXIT_GATE=1` + decision log entry,
already documented.

**(B) Sync-validator falls back to `origin/main` baseline when the
plan is in working tree but uncommitted.** This causes the gate to
report drift on the entire branch (including unrelated prior
interactions' commits), forcing a "commit first, then run gate"
dance every interaction. Documented as recurring follow-up across
Hitos 2, 4, 5 (3 occurrences).

## Approach Chosen

**A — Remove deviation entirely; fix sync-validator fallback.**

Deviation removal is the consistent application of Layer K's
principle. Sync-validator fix resolves the recurring friction. Both
go in the same interaction because the realization that drove (A)
emerged from the deviation request for (B) — the causal link is
auditable in the same execution log.

### (A) Deviation removal

Touches 10 files (see Existing Functionality Inventory). All
deviation-related code paths, schema fields, status display logic,
and documentation removed atomically. The sole legitimate emergency
escape (`SKIP_PHASE_EXIT_GATE=1` with decision log entry) remains
unchanged — it is structurally different (logged, audited, time-bounded
to one phase advance) from deviation (modal flag affecting multiple
gates).

### (B) Sync-validator fallback

When `plan_path` resolves to a file on disk but the file is not in
git's commit history (i.e., authored in the current session and not
yet committed), baseline = HEAD instead of `origin/main`. The
working-tree diff already gets merged into `DIFF_RAW` regardless,
so this branch only changes which commit-anchored diff is included.

## Alternatives Rejected

**B — Keep deviation, just fix sync fallback.**

- Rejected: would leave the structural inconsistency the user
  identified. Layer K + commit `d3ce7c5` close the textual recoil
  loophole; leaving deviation open is leaving the structural recoil
  loophole. Inconsistent rule application is worse than no rule.

**C — Restrict deviation to hotfix emergencies only.**

- Rejected: removes one of the two muddled justifications but keeps
  the other. The hotfix scenario is structurally different from
  "small change" and already has a better-instrumented path
  (`SKIP_PHASE_EXIT_GATE=1` + decision log). Two paths for the same
  case is duplication.

**D — Two separate interactions** (deviation removal + sync fix).

- Rejected: causal link between the two would be lost. The user
  observation that triggered deviation removal came from the I5b
  deviation request; bundling preserves the audit trail in one
  execution log. Splitting also doubles workflow ceremony for what
  is one architectural decision (no recoil paths in the workflow)
  with two implementation surfaces.

## Maximal Version Considered

Required by Layer K applied recursively. This spec discusses
"reduced version" and "reduction" extensively because the topic is
the recoil pattern itself. Layer K cannot distinguish topic from
proposal, so it requires this section.

- **Maximal version:** the proposal in "Approach Chosen" — atomic
  deletion of deviation across 10 files + sync-validator fallback fix
  in one interaction.
- **Why not a smaller version:** alternatives B (keep deviation, just
  fix sync), C (restrict to hotfix), and D (split interactions) were
  evaluated and each fails on quality grounds (inconsistent rule
  application, duplication with `SKIP_PHASE_EXIT_GATE`, lost causal
  link), not cost grounds. Documented in "Alternatives Rejected".
- **Proposed version:** identical to maximal. No reduction.
- **Independent superiority:** removing deviation is structurally
  superior because it eliminates an entire class of inconsistent
  rule application — the workflow now has one principled path
  (full flow) and one documented escape (`SKIP_PHASE_EXIT_GATE`),
  matching the pattern Layer K already enforces for proposal scope.
  Coupling the deviation removal with the sync-validator fix in
  one interaction preserves the causal trail (the I5b deviation
  request triggered the user observation that triggered the
  deviation removal); splitting them would orphan that audit trail
  across two execution logs and degrade `consult.sh`'s ability to
  reconstruct the rationale.

## 4-Test Application (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | The model proposed deviation at I5b first attempt (this very interaction's prior message), did not spontaneously notice the Layer K inconsistency until the user pointed it out. The sync fallback friction recurred 3 times across Hitos 2, 4, 5 without spontaneous fix. |
| 2. Fase correcta | ✓ | At the conceptual root, before more interactions accumulate the inconsistency in muscle memory. Each future interaction without deviation would otherwise need a one-off justification. Sync fix at the validator's natural location (baseline determination block). |
| 3. Coste proporcional al valor | ✓ | ~250 lines of change (mostly deletions across 10 files + ~10-line addition in sync-validator). Eliminates an entire workflow path with its own status display, validator hooks, and revert logic. Net simplification, not net addition. |
| 4. Backed by source | ✓ | Layer K (commit `0923cdb`); textual fix `d3ce7c5`; user objection 2026-04-29 ("Nunca deberías plantear una desviación"); recurring sync-validator follow-up across 3 execution logs. |

Pass on all four. No reduction.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `CLAUDE.md` § "Deviation for Wiring-Only Changes" (lines 226-289) | Transform (delete) | Concept removed |
| `CLAUDE.md` line 420 mention "deviation mode" | Transform | Update reference text |
| `.claude/README.md` § "Deviation Mode" (lines 350-365) + schema docs | Transform (delete) | Concept removed |
| `.claude/hooks/session-start.sh` `deviation` field in default state JSON | Transform (delete) | Schema field removed |
| `.claude/hooks/workflow-engine.sh` Gate 2 + DEVIATION_ACTIVE | Transform (delete) | Gate removed; main gate becomes unconditional |
| `.claude/hooks/workflow-status.sh` DEV_ACTIVE/DEV_REASON display | Transform (delete) | Status display cleaned |
| `.claude/hooks/workflow-status-line.sh` STATE_SIG hash field + DEV_ACTIVE | Transform (delete) | Status line cleaned |
| `.claude/hooks/user-prompt-state.sh` DEV_ACTIVE read | Transform (delete) | Hook simplified |
| `.claude/hooks/pre-push-gate.sh` DEVIATION_ACTIVE gate-or-warn helper | Transform | Convert to unconditional DENY (helper collapses to direct fail) |
| `.claude/hooks/post-bash-validator.sh` Check 3 deviation validation | Transform (delete) | Revert logic removed (no field to revert anymore) |
| `.claude/hooks/validators/sync-validator.sh` baseline determination | Transform | Add working-tree fallback branch + remove "deviation/light" comment |
| Existing test files | Omit | No deviation-specific tests exist; running 31 existing tests post-change verifies no regression |

## Omission Decisions

- **`SKIP_PHASE_EXIT_GATE=1` mechanism:** kept. Structurally different
  from deviation: logged, audited, scoped to one phase advance,
  decision log entry required. This is the legitimate emergency
  escape; deviation was the recoil escape.
- **Status line / status hook visual indicator** for deviation: removed
  along with the concept. No replacement — full flow is the only path.
- **Migration of in-flight sessions** with `deviation.active = true`:
  none observed in practice (no current execution logs reference an
  active deviation). If any existing `session-state.json` has the
  field, it becomes harmlessly orphaned (jq queries return null,
  treated as inactive).
- **Tests for the sync fallback fix:** the existing 6 sync-validator
  fixtures cover post-commit baseline. Adding a fixture for the
  working-tree case would require constructing a fixture repo with
  an uncommitted plan file, which is straightforward but the existing
  smoke test (this interaction's plan, run pre-commit) demonstrates
  the behavior. Fixture left as follow-up.

## Norms

- The workflow **must** offer exactly one path for code changes (full
  flow) and one for bug fixes (debug). Reduced-rigor variants
  **shall never** be reintroduced under any naming.
- The sync-validator **shall always** prefer the plan-introduction
  commit baseline when available; **must** fall back to working-tree
  diff when the plan exists on disk but not in git history; **shall
  never** silently use `origin/main` as baseline when the plan is
  in working tree, because that captures unrelated prior commits as
  drift.
- Deletions of deviation-related code **must** be atomic across all
  10 affected files in a single commit; a partial removal would
  leave the harness in an inconsistent state where some hooks
  reference a removed concept.
- The legitimate emergency escape (`SKIP_PHASE_EXIT_GATE=1` + decision
  log entry) **must** remain documented and functional; it serves a
  structurally different purpose (logged, audited, scoped) than
  deviation served (modal, multi-gate, soft).

## Safeguards

| Risk | Mitigation |
|------|------------|
| Existing `session-state.json` may have `deviation` field set; orphaned data could break jq queries elsewhere | All readers (`workflow-engine.sh`, `pre-push-gate.sh`, `user-prompt-state.sh`, etc.) use `// false` defaults in jq, which return `false` when the field is absent. Removal is safe. New session-states won't have the field at all. |
| Removal touches 10 files; partial commit could leave harness inconsistent | Single atomic commit; verification step runs all 31 existing tests before commit. If any test references deviation behavior (none found in inventory), it would fail and force fix before commit |
| Sync-validator fallback misclassifies a plan that exists in disk AND in git history (e.g., plan was committed long ago and re-edited in WT) | Plan-introduction commit detection runs first via `git log --diff-filter=A`; only when that returns empty (truly never committed) does the new fallback fire. Re-edited committed plans use the existing baseline path |
| Hotfix scenarios that previously used deviation now lack a fast path | `SKIP_PHASE_EXIT_GATE=1` + decision log is the documented replacement. It is logged and audited (deviation was modal and silent post-acknowledgment). Documentation update covers this transition |
| `pre-push-gate.sh` `gate_or_warn` helper currently has two branches; collapsing to unconditional DENY may change error semantics for non-deviation cases | The non-deviation branch is already unconditional DENY; the helper is a thin wrapper over it. Removing the warning branch removes only the deviation-specific path |
| User-prompt-state's removal of DEV_ACTIVE check could leave subtle dependencies | Variable is read but not used to gate any behavior in user-prompt-state itself (line 96 is the read; no subsequent branch uses it). Removal is local and safe |
| Concept removal disrupts the narrative continuity of past execution logs that mention deviation | Past logs are immutable historical records; their references to deviation remain accurate as historical context. Future logs simply do not use the term |

## Implementation outline (informs planning)

1. **Wave 1 — CLAUDE.md + .claude/README.md edits** (delete sections,
   update references). Independent of code; can be done first.
2. **Wave 2 — Schema removal** in `session-start.sh` (remove
   `deviation` field from default state JSON).
3. **Wave 3 — Hook deletions** in parallel (independent files):
   - `workflow-engine.sh` (Gate 2 block)
   - `workflow-status.sh` (display)
   - `workflow-status-line.sh` (STATE_SIG + DEV_ACTIVE)
   - `user-prompt-state.sh` (DEV_ACTIVE read)
   - `post-bash-validator.sh` (Check 3 block)
   - `pre-push-gate.sh` (collapse gate_or_warn helper)
4. **Wave 4 — Sync-validator changes** (remove comment, add
   working-tree fallback branch).
5. **Wave 5 — Verify.**
   - All 31 existing tests pass.
   - `bash -n` syntax checks on all 11 modified files.
   - Smoke: phase-advance verification → capture (sync-validator
     sub-invocation) on this very interaction's plan-in-WT → exit 0
     without committing first (proves fallback fix works).

## Verification plan

- 31 existing tests pass (no regression).
- `bash -n` clean across all 11 files.
- Smoke: sync gate runs on this interaction's plan BEFORE committing,
  proves working-tree fallback works.
- Visual check: `grep -rE 'deviation|Deviation' .claude/hooks/ CLAUDE.md
  .claude/README.md` returns zero results post-removal (modulo
  historical strings in execution logs, which are out of scope).
