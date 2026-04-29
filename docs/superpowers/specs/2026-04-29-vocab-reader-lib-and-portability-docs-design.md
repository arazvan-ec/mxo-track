# Spec — I12: Vocabulary-Reader Lib + Shell Portability Docs

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** refactor (full flow)
**Backlog ref:** Two follow-ups graduated to 3/3 in Hito 3 Phase B retrospective.

## Problem

Two recurring patterns crossed the 3-occurrence threshold during
Phase B:

**Pattern 1 — Vocabulary consumer pattern (3 occurrences):**
- `pre-agent-check.sh` Gate 4 (B-1): scans agent prompts for
  deprecated aliases.
- `pattern-audit.sh` (B-3): scans recent execution logs for the same.
- `ddd-boundary-check.sh` (B-2): different concern (canonical →
  bounded_context lookup) but reads the same YAML structure.

Each implements its own awk extraction of `_vocabulary.yaml` plus
its own match logic. Phase C will add at least two more consumers
(`vocab-drift.sh`, `vocab-rename.sh`); without extraction now, the
4th and 5th implementations land before any consolidation.

**Pattern 2 — mawk vs gawk regex portability surprises (3 occurrences):**
- `f4d6d36` files-decl-parser backtick stripping (mawk doesn't strip
  backticks in `tr` the way gawk did in dev env)
- Pre-push gate heredoc bug (still 2/3 outside this list, but
  documented)
- Phase B's vocab match: `\<\>` word boundaries + `IGNORECASE`
  silently fail in mawk

Each occurrence consumed ~10-30 minutes debugging. The class is
"awk runtime is mawk in this env, NOT gawk" — undocumented anywhere
in the repo until now.

## Approach Chosen

**A — Combined extraction in one interaction:**

1. **`lib/vocabulary-reader.sh`** with three primitives:
   - `vocab_deprecated_aliases <vocab_file>` — emits `alias|canonical`
     pairs (one per line) for entries with `surface: "deprecated"`.
   - `vocab_canonicals_in_text <vocab_file> <text>` — emits canonical
     names mentioned (whole-word match, case-insensitive) in the
     given text. Uses bash `grep -wE` over awk-extracted canonicals.
   - `vocab_bounded_context <vocab_file> <canonical>` — emits the
     `bounded_context` value for a canonical, or empty if unset/TODO.

2. **Migration of 3 callers** to use the lib (B-1, B-2, B-3 from
   Phase B). All existing behavior preserved; verified by running
   the 3 hooks with the existing smoke tests.

3. **`.claude/README.md` "Shell Portability Constraints" section**
   documenting the mawk-vs-gawk class (≥3 occurrences in this
   branch). Codifies the rules the lib follows by construction:
   - awk regex: no `\<\>`; use `match()` with explicit alternation
     OR extract data and match in bash with `grep -wE`.
   - awk: `IGNORECASE` is gawk-only; use `tolower()` or external
     `grep -i`.
   - bash: `set -uo pipefail` without `-e` → pipeline failure
     doesn't kill script; `set -euo pipefail` with `-e` → DOES.
     Defuse pipelines that legitimately may fail with `|| true`.

## Alternatives Rejected

**B — Lib only; defer docs.**

- Rejected: portability docs is at 3/3 threshold. Deferring
  bends the rule the very Hito after invoking it. Docs are
  inexpensive and naturally co-located with the lib's
  implementation comments.

**C — Docs only; defer lib.**

- Rejected: the 4th and 5th consumers come in Phase C. Without
  the lib, those interactions write inline implementations and
  the consolidation argument repeats indefinitely.

**D — Extract a `lib/awk-portable-helpers.sh`** with portable wrappers.

- Rejected: scope creep. The constraints are simple (no `\<\>`,
  no `IGNORECASE`, defuse pipes). Documenting them is enough.
  A wrapper library would add abstraction debt without solving
  problems the constraints don't already address.

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Three vocab consumers each wrote the same parser inline; the model didn't extract spontaneously. Three portability surprises across the branch. |
| 2. Fase correcta | ✓ | Before Phase C introduces 4th+5th consumers and 4th portability surprise. Same argument that justified I5 (`lib/section-validator.sh`) before the 6th caller landed. |
| 3. Coste/valor | ✓ | ~110 lines net (lib+docs) - ~110 lines inline duplication removed from 3 callers ≈ 0 net delta with consistency gain. ROI immediate in Phase C. |
| 4. Backed by source | ✓ | Phase B execution log explicitly flags both as 3/3; CLAUDE.md graduation pattern; precedent I5 commit `8aec691`. |

Pass on all four. No reduction.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/lib/files-decl-parser.sh` | Omit (read for pattern) | Existing lib pattern; vocabulary-reader follows the same shape (sourced, functions echo to stdout, no globals) |
| `.claude/hooks/lib/section-validator.sh` | Omit (read for pattern) | Same |
| New `.claude/hooks/lib/vocabulary-reader.sh` | Create | Three primitives |
| `.claude/hooks/pre-agent-check.sh` Gate 4 | Transform | Replace inline awk+bash with lib calls |
| `.claude/hooks/pattern-audit.sh` deprecated-alias block | Transform | Same |
| `.claude/hooks/ddd-boundary-check.sh` vocab cross-ref block | Transform | Same |
| `.claude/README.md` | Transform | New "Shell Portability Constraints" section |
| Existing test harnesses | Omit | Behavior preserved; running 31 existing tests post-refactor verifies parity |

## Omission Decisions

- **Test fixtures for the lib's primitives:** smoke via the 3
  existing callers' behavior. Adding lib-level fixtures doubles
  surface for marginal confidence (same trade-off as I5).
- **`vocab_aliases_for <canonical>` and `vocab_canonical_for <alias>`
  helpers:** out of scope. Three consumers don't need them. Add when
  a 4th consumer needs different access patterns.
- **Documenting other shell portability classes** (POSIX `printf` vs
  bashisms, `[[ ]]` vs `[ ]`, etc.): out of scope. Document when each
  hits 3 occurrences.
- **Migrating uncovered text-match patterns** (e.g., classify-validator
  if it has any awk drift): out of scope; only Phase B's 3 vocab
  consumers covered.

## Norms

- The lib **must** never invoke `exit`; functions return via stdout
  + exit codes only.
- The lib **shall** be self-contained: no globals, all parameters
  passed explicitly.
- The lib **must** use only mawk-compatible awk constructs (no
  `\<\>`, no `IGNORECASE`); when word-boundary or case-insensitive
  matching is required, the lib **shall** delegate to `grep -wE`
  or `grep -i` in bash.
- The 3 caller migrations **must not** change observable behavior;
  smoke output of each caller before and after migration **shall**
  be identical for the same input.
- The portability docs in `.claude/README.md` **must** cite the
  three execution logs that triggered each constraint, so future
  contributors can trace the rule to its origin.

## Safeguards

| Risk | Mitigation |
|------|------------|
| Migration of 3 callers introduces silent behavior changes | Pre/post smoke comparison: feed each caller the same input before and after, diff outputs. Plus running 31 existing tests. |
| Lib path resolution breaks when callers source the file (precedent: I5 had this with REPO override in test harness) | Use absolute path constant in callers, decoupled from any mutable REPO variable: `source /home/user/mxo-track/.claude/hooks/lib/vocabulary-reader.sh`. Same pattern as I5's pre-agent-check fix. |
| Portability docs become stale as awk-runtime changes (gawk arrives in env one day) | Docs include `awk --version` recommendation as troubleshooting check. If gawk replaces mawk, the constraints become advisory rather than hard. |
| Consumers depending on inline awk had subtle edge cases (e.g., handling empty vocab file) | Lib's primitives test for `[ -f "$vocab_file" ]` upfront and emit empty output if missing. Mirrors existing safeguard in B-1/B-2/B-3. |
| New lib accidentally encourages future consumers to do `vocab_*` calls in performance-sensitive paths | Document in lib comments: "primitives are not memoized; callers should batch lookups when scanning many tokens". |
| Docs section in README grows the file substantially | Section is concise (~30 lines), placed near the existing harness assumptions. |
| Hook stdin contracts may have hidden expectations | Lib only reads files; doesn't consume stdin. Migration preserves caller-side stdin handling unchanged. |

## Implementation outline

1. **Wave 1 — Create `lib/vocabulary-reader.sh`** with three
   primitives. Self-test by running each function standalone
   against `_vocabulary.yaml` and verifying output.
2. **Wave 2 — Migrate `pre-agent-check.sh` Gate 4** to use
   `vocab_deprecated_aliases`. Run `test-pre-agent-check.sh` →
   6/6 must still pass.
3. **Wave 3 — Migrate `pattern-audit.sh` deprecated-alias block**
   similarly.
4. **Wave 4 — Migrate `ddd-boundary-check.sh` vocab cross-ref**
   to use `vocab_bounded_context` + `vocab_canonicals_in_text`.
5. **Wave 5 — Add "Shell Portability Constraints" section** to
   `.claude/README.md`.
6. **Wave 6 — Verify.**
   - All 31 existing tests pass.
   - `bash -n` clean on lib + 3 modified callers.
   - Smoke: pattern-audit with fixture log mentioning "tour" →
     surfaces deprecated-alias warning (B-3 behavior preserved).

## Verification plan

- 31 existing tests pass.
- `bash -n` clean.
- Smoke confirms B-3 (deprecated-alias surfacing) still works
  via the lib.
