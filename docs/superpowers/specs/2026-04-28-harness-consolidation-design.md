# Spec — Harness Consolidation (lib/section-validator + lib/files-decl-parser)

**Date:** 2026-04-28
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow) — refactor
**Backlog ref:** Follow-up #1 + #3 from execution logs
2026-04-28-layer-k, 2026-04-28-norms-safeguards, 2026-04-28-sync, and
2026-04-28-agent-prompt. Sync fallback (#2) deferred to I5b deviation.

## Problem

Two distinct patterns are duplicated across multiple validators in the
harness, and both have crossed the documented graduation threshold of
"3+ occurrences" set in CLAUDE.md.

**Pattern 1 — Section presence + body extraction + content classification.**
Five occurrences:

- `brainstorm-validator.sh` Layer H — heading + classified-rows table check
- `brainstorm-validator.sh` Layer K — heading + multiline bullet + positive-signal keywords
- `brainstorm-validator.sh` Layer N — heading + imperative keyword
- `brainstorm-validator.sh` Layer S — heading + Risk|Mitigation table
- `pre-agent-check.sh` Gate 3 — heading + (inline OR spec-reference)

Each reimplements: heading detection (`grep`), section body extraction
(awk state machine), and a content classifier. Adding a sixth gate
(planned for Hito 3) means ~50 more lines instead of ~10.

**Pattern 2 — `→ files:` declaration parser.** Two occurrences:

- `brainstorm-validator.sh` parallel-conflict (lines 228-260) —
  splits payload, filters path-like tokens, **does not strip backticks**
- `sync-validator.sh` drift detection — same parser shape, **with
  backtick stripping** (post-hoc fix in commit `f4d6d36`)

The asymmetry means the parallel-conflict detector silently miscounts
backticked tokens against unbacked tokens, even though they reference
the same file.

## Approach Chosen

**B — Two extractions in this interaction, sync fallback deferred.**

1. **`.claude/hooks/lib/section-validator.sh`** — extract three
   primitives used by all 5 callers:
   - `section_present <file> <heading>` → exit 0/1
   - `section_body <file> <heading>` → echo body
   - `section_satisfied_inline_or_ref <body> <heading_token> <inline_check> [ref_pattern]`
     where `inline_check` is one of:
     `imperative` | `risk-mitigation-table` | `classified-rows` |
     `positive-signal` | `multiline-bullet`
2. **`.claude/hooks/lib/files-decl-parser.sh`** — extract one primitive
   used by both callers:
   - `parse_files_decl <plan_file>` → echo path-like tokens, sort -u,
     with backtick stripping baked in
3. **Update 7 callers** — `brainstorm-validator.sh` (Layers H, K, N, S
   and the parallel-conflict block) and `pre-agent-check.sh` (Gate 3),
   plus `sync-validator.sh` (replace inline parser).
4. **No behavioral changes**; verified by running the three existing
   test harnesses (19 + 6 + 6 = 31 tests) post-refactor, all green.

The sync fallback for working-tree plans is **deferred** to I5b
(deviation flow) because it is a behavioral change (~5 lines, 0
design decisions, established pattern). Mixing refactor with behavior
change complicates verification.

## Alternatives Rejected

**A — One single interaction covering everything (extractions + sync
fallback).**

- Rejected: mixing pure refactor (no behavior change) with behavior
  change (sync fallback) confuses verification. If a test fails, is
  the cause the extraction or the fallback? Hito 4 retrospective
  flagged the same risk: "always run the exact deploy command" and
  "test independent changes independently".

**C — Three separate interactions** (one per follow-up).

- Rejected: I5 (section-validator) and I5-bis (files-decl-parser) are
  the same shape (extract pattern → update callers → verify same tests).
  Bundling is structurally appropriate; separating creates 2× workflow
  overhead without semantic gain. The sync fallback (#2) is genuinely
  different and gets its own deviation interaction.

**D — Defer all follow-ups, proceed to Hito 3 first.**

- Rejected: Hito 3 (Ubiquitous Language System) will introduce a sixth
  section-validation gate. Adding it before the extraction means
  reimplementing the inline pattern a sixth time and refactoring it
  later. Doing the extraction first means Hito 3 trivially uses the
  shared lib.

## 4-Test Application (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Threshold is documented in CLAUDE.md ("3+ occurrences → graduate") but the model didn't extract spontaneously; 5 occurrences accumulated over 2 days without action. |
| 2. Fase correcta | ✓ | Before Hito 3 introduces a 6th caller. Cost of doing it after: 6th implementation + larger refactor surface. Cost of doing it now: clean slate for Hito 3. |
| 3. Coste/valor | ✓ | ~500 net lines of change, but ~200 lines of duplication eliminated and ~40 lines saved per future gate. ROI positive after 2-3 future gates (Hito 3 alone is one). |
| 4. Backed by source | ✓ | CLAUDE.md graduation pattern; Layer K, Norms+Safeguards, and Layer Agent execution logs all flagged this convergence as follow-up. |

Pass on all four. No reduction.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/validators/brainstorm-validator.sh` (~270 lines) | Transform | Replace 5 inline section checks (H, K, N, S, parallel-conflict files-decl) with lib calls |
| `.claude/hooks/pre-agent-check.sh` (~140 lines) | Transform | Replace Gate 3 inline section logic with lib call |
| `.claude/hooks/validators/sync-validator.sh` (~100 lines) | Transform | Replace inline files-decl parser with lib call |
| New `.claude/hooks/lib/section-validator.sh` | Create | Section primitives shared by all 5 callers |
| New `.claude/hooks/lib/files-decl-parser.sh` | Create | `→ files:` parser shared by both callers, with backtick stripping |
| Test harnesses (`test-brainstorm-validator.sh`, `test-sync-validator.sh`, `test-pre-agent-check.sh`) | Omit (no test changes) | Behavior unchanged; existing tests verify the refactor |
| `.claude/hooks/lib/test-harness.sh` | Omit | Reuse, no changes needed |
| Sync fallback for working-tree plans | Omit | Deferred to I5b (deviation, behavior change) |

## Omission Decisions

- **Behavior parity tests for the libraries themselves:** out of scope.
  The 31 existing tests cover the behavior end-to-end; passing them
  post-refactor is sufficient evidence of parity. Adding lib-level
  unit tests doubles the test surface for marginal incremental
  confidence.
- **Backward compatibility shim:** not needed. The lib functions are
  new; old callers replaced atomically in the same commit. No external
  consumer depends on the inline implementations.
- **Documentation in CLAUDE.md** of the new libs: out of scope. Libs
  live under `.claude/hooks/lib/` and are internal infrastructure;
  CLAUDE.md documents enforcement gates, not implementation helpers.

## Norms

- The refactor **must not** change observed behavior of any existing
  validator; all 31 existing tests **shall** pass post-refactor.
- The libs **must** be sourced via `source` (not exec), so callers
  inherit the shell's variable scope and exit-on-error semantics.
- `lib/section-validator.sh` **shall** support all 5 inline-check
  modes currently used (`imperative`, `risk-mitigation-table`,
  `classified-rows`, `positive-signal`, `multiline-bullet`); reducing
  the set is forbidden because it would require a parallel inline
  fallback in callers.
- `lib/files-decl-parser.sh` **must** strip backticks from tokens
  by default; the silent backtick mismatch (asymmetry between
  brainstorm-validator and sync-validator parsers) **shall never**
  recur.
- The libs **must never** invoke `exit` directly; they return values
  via stdout and exit codes only, leaving abort decisions to callers.

## Safeguards

| Risk | Mitigation |
|------|------------|
| Refactor introduces silent behavior changes (e.g., regex subtly different from inline version) | Run all 31 existing tests post-refactor with zero changes to test files; any test failure indicates a divergence to fix before committing |
| `source` semantics break under `set -euo pipefail` if lib has unbound variables or pipeline failures | Keep libs self-contained: no globals, all functions parameterized; pipeline-internal failures masked with `\|\| true` where appropriate (mirroring sync-validator pattern) |
| Backtick stripping in files-decl-parser inadvertently strips legitimate paths containing backticks (none exist in this repo, but theoretical) | Document in lib comment that backticks are stripped unconditionally as a markdown-formatting artifact; flag for revisit if a path with backticks emerges |
| `pre-agent-check.sh` writes prompt to a tmpfile (current behavior); the lib should accept either a file path or content | Lib accepts file path (stable across callers); pre-agent-check continues to write tmpfile then call the lib with that path |
| Sync-validator's tests construct fixture git repos; replacing the inline parser must keep the same input/output contract | Test harness unchanged; same inputs produce same outputs by construction; backtick stripping was added in `f4d6d36` and tests pass with it, so the lib's default behavior is the existing tested behavior |
| Refactor commit is large (~500 lines); review difficulty | Commit message clearly states "no behavior change, all 31 tests pass"; the diff is dominated by deletions in callers and additions in libs, with caller updates being mechanical replacements |
| Future gate (Hito 3 or beyond) needs a content-classifier mode not in the initial 5 | Lib design is open-closed: adding a new `inline_check` mode is a one-line addition to the case statement; no breaking change to existing callers |

## Implementation outline (informs planning)

1. **Wave 1 — Create `lib/section-validator.sh`** with the three
   primitives + 5 supported `inline_check` modes. Self-test by running
   it standalone against fixture files (no test harness — visual
   verification of single function calls).
2. **Wave 2 — Create `lib/files-decl-parser.sh`** with `parse_files_decl`.
   Self-test similarly.
3. **Wave 3 — Update `sync-validator.sh`** to use `lib/files-decl-parser.sh`.
   Run `test-sync-validator.sh` → 6/6 must still pass.
4. **Wave 4 — Update `brainstorm-validator.sh`** parallel-conflict block
   to use `lib/files-decl-parser.sh`. Then update Layers H, K, N, S to
   use `lib/section-validator.sh`. Run `test-brainstorm-validator.sh`
   → 19/19 must still pass.
5. **Wave 5 — Update `pre-agent-check.sh`** Gate 3 to use
   `lib/section-validator.sh`. Run `test-pre-agent-check.sh` → 6/6
   must still pass.
6. **Wave 6 — Final verification.**
   - All 31 tests green.
   - `bash -n` syntax check on all touched files + new libs.
   - Smoke test: phase-advance verification → capture (sync gate
     sub-invocation) on this very interaction → exit 0.

## Verification plan

- 31 existing tests pass (no regression).
- `bash -n` clean.
- Smoke against current branch state.
- Sync gate at I5's verification → capture: pass after committing.
