# Spec — Hito 2: Sync Validator (Plan ↔ Diff drift detection)

**Date:** 2026-04-28
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)
**Backlog ref:** Hito 2 del análisis comparativo SPDD vs CLAUDE.md (2026-04-28)

## Problem

The plan declares `→ files:` per task — already parsed by
`brainstorm-validator.sh` for parallel-conflict detection. There is no
gate that enforces those declarations against the actual diff produced
by the implementation. As a result, plans drift from code: files
touched without declaration accumulate, and future sessions reading the
plan to understand what changed get a stale view. SPDD's bidirectional
sync principle ("if reality diverges, fix the prompt first, then the
code") demands a closing checkpoint that catches plan↔code drift before
the execution log freezes the narrative.

## Approach Chosen

**A — HARD gate at `verification → capture`, separate `sync-validator.sh`**
invoked from `verification-validator.sh` (mirroring Layer C
sub-invocation pattern from `brainstorm-validator → socratic-review`).

Mechanism:

1. Read `evidence.plan_path` from session-state.
2. Parse `→ files:` declarations from every task in the plan
   (reusing the same parser logic as
   `brainstorm-validator.sh:228+` — strip parentheses, split on
   comma/space, keep tokens that look like paths or known sentinels).
3. Run `git diff --name-only origin/main...HEAD` to get actually
   touched files.
4. Compute `drift = touched − declared − workflow_artifacts`.
5. If `drift` is non-empty → BLOCK (exit 2) with the list.

Gate scope is bounded by `WORKFLOW_ARTIFACTS_PATHS` — paths that the
workflow itself produces and that no plan can meaningfully declare:

- `docs/superpowers/specs/`, `docs/superpowers/plans/`,
  `docs/superpowers/execution-logs/`, `docs/superpowers/retrospectives/`
- `docs/codebase-manifest.md`
- `docs/decisions/log.md`
- `.claude/session-state.json`, `.claude/parallel-tasks.json`

This is **scope of the gate, not a list of exceptions**: workflow
artifacts are categorically not feature deliverables, so they are
not subject to "must be declared in the plan". Files inside
`backend/src/`, `frontend/src/`, `.claude/hooks/`, `CLAUDE.md`,
`docs/knowledge/`, etc. **are** in scope and must be declared.

## Alternatives Rejected

**B — HARD gate at `retrospective → finalize`** (Manus's location, but
HARD instead of SOFT).

- Rejected: rollback cost is highest at finalize (push imminent).
  Catching drift at verification → capture detects the same condition
  earlier with the same mechanism, allowing the model to either revert
  unplanned changes or update the plan retroactively before the
  execution log narrates a stale plan.

**C — SOFT warning anywhere** (Manus's literal proposal).

- Rejected on two grounds:
  1. SOFT is the recoil pattern explicitly forbidden by commit
     `d3ce7c5` and mechanically blocked by Layer K (commit `0923cdb`).
  2. A non-blocking warning at finalize gets scrolled past in the
     stop-hook output and produces no behavioral change.

**D — PreToolUse hook on Edit/Write** (warn at edit time when target
file is outside the plan).

- Rejected as standalone: invasive during legitimate exploration.
  However, valid as a future complementary layer (SOFT warn at edit
  time + HARD verification check) — recorded as follow-up, not part of
  this hito.

## 4-Test Application (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | The model edits files outside the plan during implementation routinely; the plan stays as written, code drifts. Documented across multiple recent execution logs (estimate accuracy gaps in 2026-04-22, 2026-04-28-layer-k, 2026-04-28-norms-safeguards). |
| 2. Fase correcta | ✓ | verification → capture: tests are passing, log not yet written, the diff is final, rollback or plan-update cost is minutes. Earlier (implementation) is too disruptive; later (finalize) is too late. |
| 3. Coste proporcional al valor | ✓ | ~70 lines new validator + ~120 lines tests + ~5 lines integration in verification-validator + ~4 lines CLAUDE.md. Same order as `socratic-review-validator.sh`. Value: keeps plan↔code synchronized so `consult.sh` of future sessions reflects reality, not aspiration. |
| 4. Backed by source | ✓ | SPDD bidirectional sync principle (Fowler 2026); plan-as-canonical-artifact convention in this repo (parser already exists in brainstorm-validator); execution-log evidence of plan↔code drift in past iterations. |

Pass on all four. No reduction needed.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/validators/brainstorm-validator.sh` `→ files:` parser (lines 228-260) | Omit (reuse pattern, do not refactor) | Extracting to a shared lib is a follow-up after Layer K convergence threshold; for this hito, copy the parsing approach into the new validator |
| `.claude/hooks/validators/verification-validator.sh` (73 lines) | Transform | Add sub-invocation of `sync-validator.sh` after the existing tests/lint checks |
| New `.claude/hooks/validators/sync-validator.sh` | Create | Self-contained validator, testable in isolation |
| New `.claude/hooks/test-sync-validator.sh` | Create | TDD harness following existing `test-brainstorm-validator.sh` pattern |
| `.claude/hooks/validators/finalize-validator.sh` | Omit | Knowledge-module suggestions remain SOFT there; sync is a different concern |
| `.claude/hooks/validators/spec-compliance-validator.sh` | Omit | Different concern (inventory↔plan, not plan↔diff) |
| `CLAUDE.md` "Enforcement gates" table | Transform | One row documenting the sync gate |

## Omission Decisions

- **Edit-time warning hook:** out of scope for this hito. Recorded as
  follow-up. Standalone HARD verification gate is sufficient for the
  primary failure mode (drift goes undetected).
- **Reuse vs duplicate of `→ files:` parser:** duplicate for now. The
  Layer K execution log (commit `0923cdb`) already records the
  follow-up to extract `lib/section-validator.sh` after 4 layers
  converged on the same shape; the `→ files:` parser is the second
  shared logic candidate but premature to factor out before this hito
  proves the extraction is needed.
- **Whitelist content review:** the closed list is the minimum set of
  paths that have no canonical plan declaration. Adding to it requires
  an execution-log entry (3+ occurrences of false-positive blocking on
  a path) following the graduation pathway used elsewhere in the repo.

## Norms

- The sync validator **must** apply only when `evidence.plan_path` is
  set and the plan file exists; absence of plan means the gate is
  silent (deviation/light flows).
- The validator **shall** treat workflow artifact paths
  (`WORKFLOW_ARTIFACTS_PATHS`) as out of scope and never report them
  as drift.
- The validator **must never** false-positive on files declared in the
  plan but written with different path representations (relative vs
  absolute) — normalize both sides to repo-relative paths before
  comparison.
- The validator **shall never** modify the plan automatically; drift
  detection is human-in-the-loop (the user / model decides whether to
  revert or update the plan).
- Files inside scope (`backend/src/`, `frontend/src/`,
  `.claude/hooks/`, `CLAUDE.md`, `docs/knowledge/`, etc.) **must** be
  declared in the plan; conditional or context-based exceptions are
  forbidden.

## Safeguards

| Risk | Mitigation |
|------|------------|
| `→ files:` parser produces different tokens than the existing brainstorm-validator parser, leading to inconsistent declaration interpretation | Copy the exact awk/grep idiom from `brainstorm-validator.sh:228-260` (parenthesis stripping + path-token filter); add a TDD case using a fixture plan with parenthesized annotations to verify behavioral parity |
| Path normalization mismatch between plan declarations (`backend/src/X.php`) and git diff output (same string) | Both sides come from repo-relative paths already; document this as an invariant; add a TDD case with both styles to verify |
| Whitelist treated as "exceptions list" inviting drift over time | Name the variable `WORKFLOW_ARTIFACTS_PATHS`, not `WHITELIST` or `EXCEPTIONS`; document in CLAUDE.md as "scope of the gate, not exception"; require execution-log evidence (3+ false-positive blocks) before adding to the list |
| `git diff --name-only origin/main...HEAD` fails when origin/main is unreachable or branch is stale | Validator fails open with a SOFT warning if the diff command exits non-zero (cannot determine drift → cannot enforce). The warning surfaces the issue but does not block on infrastructure problems |
| Validator integration in verification-validator changes that script's exit semantics | Sub-invocation captures sync-validator output and exit code; if sync exits 2, propagate as exit 2; otherwise preserve existing verification-validator exit behavior. Same pattern as Layer C sub-invocation in brainstorm-validator |
| Test harness for sync-validator requires a real git repo state | Use `git init` + `git commit` inside `$TEST_TMPDIR` to construct fixture diffs; precedent from existing `test-*-validator.sh` patterns where state is constructed in tmpdir |

## Implementation outline (informs planning)

1. **Wave 1 — TDD red.** Create `test-sync-validator.sh` with fixtures:
   - **TC-Y1:** plan declares `[a.php, b.php]`, diff = `[a.php, b.php]` → pass
   - **TC-Y2:** plan declares `[a.php]`, diff = `[a.php, c.php]` → block (c.php drift)
   - **TC-Y3:** diff includes only workflow artifacts → pass
   - **TC-Y4:** plan declares `[a.php]`, diff = `[a.php, docs/superpowers/specs/x.md]` → pass (workflow artifact)
   - **TC-Y5:** plan with parenthesized payloads (`→ files: (a.php, b.php)`) parses correctly → pass when diff matches
2. **Wave 2 — Implement** `sync-validator.sh`: parser + diff + drift
   computation + workflow scope filter.
3. **Wave 3 — Integrate** sub-invocation into `verification-validator.sh`
   after existing tests/lint checks; preserve exit semantics.
4. **Wave 4 — Verify + document.**
   - Run `test-sync-validator.sh`: 5/5 pass.
   - Run `test-brainstorm-validator.sh`: still 19/19 pass (no regression).
   - `bash -n` syntax checks.
   - Smoke test: run sync-validator against this very interaction's plan
     once committed → expected to pass (this plan declares its own
     touched files).
   - Document the sync gate in CLAUDE.md "Enforcement gates" table.

## Verification plan

- New test harness: 5/5 pass.
- Existing tests: 19/19 pass (no regression).
- `bash -n` clean on all touched scripts.
- Smoke test: real plan ↔ real diff at the end of this interaction.
