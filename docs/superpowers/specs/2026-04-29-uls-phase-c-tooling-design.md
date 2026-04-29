# Spec — Hito 3 Phase C (tooling): Vocab Enforcement + Maintenance

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)
**Backlog ref:** Hito 3 Phase C, tooling subset (4 of 5 items).
Curation of 47 TODO entries deferred to Phase C-bis (next interaction).

## Problem

Phase A built the vocab registry; Phase B added 3 WARN-only consumers
(subagent dispatch, ddd cross-ref, pattern-audit). The registry now
exists and is consulted, but no enforcement layer exists:

- New code can introduce deprecated alias usage without warning at
  commit time.
- CI accepts commits that mention deprecated terms.
- Vocab entries can drift from code (entity moved, file renamed,
  vocab `authoritative_path` stale) with no detection.
- Renaming a canonical (e.g., `Pod` → `ProofOfDelivery` if we change
  policy) has no atomic helper; manual edits risk inconsistency.

Phase C tooling closes these four surfaces. Curation of the 47
remaining `TODO: curate definition` entries is data work and gets
its own interaction (Phase C-bis) for capacity reasons.

## Approach Chosen

**B — Four tooling pieces in this interaction:**

1. **C-1 `.claude/hooks/pre-commit-deprecated-alias.sh`** — git
   pre-commit hook that scans staged diffs for deprecated-alias
   mentions in code (not docs/specs/logs). Emits warning,
   non-blocking by default; bypass via `SKIP_VOCAB_PRECOMMIT=1`.

2. **C-2 CI deprecation check** — script
   `scripts/ci-vocab-deprecation-check.sh` invoked from existing
   CI workflow (or makefile). Exits non-zero if HEAD diff
   introduces deprecated-alias usage. Same regex contract as C-1
   but stricter (CI = HARD).

3. **C-3 `scripts/vocab-drift.sh`** — verifies each entry's
   `authoritative_path` exists and the canonical name is present
   in that file. Reports drift (missing path, name no longer in
   file). Invoked manually or via `make vocab-drift`.

4. **C-4 `scripts/vocab-rename.sh`** — atomic helper:
   `vocab-rename.sh <old_canonical> <new_canonical>` updates the
   YAML by adding `<old>` as alias with `surface: deprecated`,
   replacing `canonical` with new name, and updating
   `authoritative_path` if user provides a new path. Single
   operation, validation gate before write.

All four leverage `lib/vocabulary-reader.sh` (I12).

## Alternatives Rejected

**A — All 5 items in one interaction (incl. 47-entry curation).**

- Rejected per capacity reasoning. ~730 lines mixed code + content;
  curation needs editorial judgment that doesn't fit shell-impl
  flow. Same logic that justified A/B/C split originally.

**C — Three sub-interactions** (enforcement / maintenance / curation).

- Rejected: enforcement and maintenance share lib + same flow;
  splitting adds ceremony without gain. Curation justifiably
  separate.

**D — Skip C-2 (CI), defer to its own Hito.**

- Rejected: enforcement needs both pre-commit (advisory at the
  developer's machine) and CI (HARD at merge gate). One without
  the other leaves a hole.

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Without enforcement + maintenance, the vocab registry curated in Phase A erodes silently as code evolves. |
| 2. Fase correcta | ✓ | After Phase B (WARN-only consumers proven). Before curation (data work that benefits from drift script available). |
| 3. Coste/valor | ✓ | ~400 lines tooling reusing I12's lib. Closes 4 enforcement/maintenance surfaces in a single coherent interaction. |
| 4. Backed by source | ✓ | Phase A spec; lib precedent (I5, I12); SPDD bidirectional sync principle. |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `lib/vocabulary-reader.sh` (I12) | Omit (consumer) | Read by all 4 new scripts |
| Existing pre-commit setup | Omit | Hook is standalone shell script; no existing pre-commit framework to integrate |
| `Makefile` | Transform | Add `vocab-drift` and `vocab-rename` targets |
| Existing CI workflow `.github/workflows/` | Omit (read for pattern) | If exists, document; if not, scripts are runnable manually |
| 47 TODO curation entries | Omit (deferred to C-bis) | Data work, separate calibration |

## Omission Decisions

- **Curation (C-5) deferred to Phase C-bis** — capacity. Tooling
  must land first to enable drift validation of curation work.
- **Test fixtures for the 4 scripts** — smoke test via this
  interaction's flow + manual invocation. Adding TDD fixtures for
  shell drift scripts is high-cost.
- **Hard-block in pre-commit hook** — left as opt-in via env var.
  Default WARN matches Phase B's stance; CI is HARD enforcement.
- **`vocab-rename.sh` advanced features** (alias migration, batch
  renames): out of scope. Single canonical rename only.

## Norms

- All 4 tools **must** source `lib/vocabulary-reader.sh` for
  registry access; **shall never** reimplement YAML parsing.
- Pre-commit hook **must** be opt-in (configured by developer) and
  **shall** allow bypass via `SKIP_VOCAB_PRECOMMIT=1`. Never blocks
  by default.
- CI deprecation check **must** be HARD: exit non-zero on detected
  deprecated-alias in code diff.
- `vocab-drift.sh` **shall never** modify the registry; reports
  only.
- `vocab-rename.sh` **must** validate inputs (old canonical exists,
  new canonical not already taken) before any write.
- All scripts **must** follow shell-portability constraints
  documented in `.claude/README.md` (mawk-safe awk + bash matching).

## Safeguards

| Risk | Mitigation |
|------|------------|
| Pre-commit hook adds latency to every commit | Lib lookup is O(entries); acceptable at <100 entries. Cache strategy deferred. |
| CI check fails on legitimate uses (e.g., commit message explaining deprecation history) | Scan only staged code diff (file content), not commit messages. Documentation/spec/log paths excluded via path filter. |
| `vocab-drift.sh` reports false positives if `authoritative_path` is a glob or moved file | Tolerant grep; check both exact path and grep for canonical name in repo if exact path missing. Report uncertainty rather than confident drift. |
| `vocab-rename.sh` corrupts YAML on partial write | Atomic temp-file + mv pattern (existing convention). Validation pass before any write; abort on conflict. |
| Existing 47 TODO entries trigger noisy drift output | drift script skips entries with `bounded_context: TODO` or `definition` containing "TODO:" — those are explicitly uncurated. |
| Phase C-bis (curation) blocked by missing tooling | This interaction lands all 4 tools first. Curation in C-bis can use vocab-drift to validate as it goes. |
| Pre-commit hook conflicts with other repo pre-commit hooks | Hook is in `.claude/hooks/`, not `.git/hooks/`; developer opts in by symlinking or sourcing. Documented in lib comments. |

## Implementation outline

1. **Wave 1 — `pre-commit-deprecated-alias.sh`.** Reads staged
   diff via `git diff --cached --name-only`, filters code paths,
   uses lib to check for deprecated aliases. Emits warning to
   stderr; exits 0 by default, exits 1 if `STRICT=1`.
2. **Wave 2 — `ci-vocab-deprecation-check.sh`.** Same logic but
   exits non-zero on detection. Designed to run as
   `bash scripts/ci-vocab-deprecation-check.sh` from CI.
3. **Wave 3 — `vocab-drift.sh`.** Iterates vocab entries, checks
   `authoritative_path` exists, optionally verifies canonical
   name is in the file. Reports to stdout.
4. **Wave 4 — `vocab-rename.sh`.** Validates → updates YAML →
   prints summary.
5. **Wave 5 — Makefile targets** for drift + rename.
6. **Wave 6 — Verify.** 31 existing tests; smoke each new script.

## Verification plan

- 31 existing tests pass.
- `bash -n` clean on 4 new scripts.
- Smoke pre-commit: stage a file containing "tour" → script warns.
- Smoke CI check: same input → exit non-zero.
- Smoke drift: run on real `_vocabulary.yaml` → reports any drift
  found (curated entries with paths).
- Smoke rename: dry-run mode confirms preview of changes.
