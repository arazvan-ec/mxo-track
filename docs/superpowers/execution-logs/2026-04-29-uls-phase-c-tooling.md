---
type: feature
tags: [harness, vocabulary, ubiquitous-language, ddd, spdd, phase-c, enforcement, maintenance, vocab-consumer]
files_touched:
  - .claude/hooks/pre-commit-deprecated-alias.sh
  - .claude/hooks/test-pre-commit-deprecated-alias.sh
  - scripts/ci-vocab-deprecation-check.sh
  - scripts/test-ci-vocab-deprecation-check.sh
  - scripts/vocab-drift.sh
  - scripts/test-vocab-drift.sh
  - scripts/vocab-rename.sh
  - scripts/test-vocab-rename.sh
  - .github/workflows/deploy.yml
  - Makefile
  - docs/superpowers/specs/2026-04-29-uls-phase-c-tooling-design.md
  - docs/superpowers/plans/2026-04-29-uls-phase-c-tooling.md
patterns: [vocab-consumer, deprecated-alias-detection, mawk-vs-gawk-regex, atomic-yaml-rewrite]
outcome: success
outcome_verified_at: 2026-04-29
regressions_later: []
pr_number: null
estimated_lines: 580
actual_lines: 872
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-29 — Hito 3 Phase C tooling

**Branch:** `claude/phase-c-tooling-Ks1mw`
**Spec:** `docs/superpowers/specs/2026-04-29-uls-phase-c-tooling-design.md`
**Plan:** `docs/superpowers/plans/2026-04-29-uls-phase-c-tooling.md`

## Summary

Four tooling pieces closing the enforcement + maintenance surfaces
left open after Phase B's WARN-only consumers:

- **C-1 `.claude/hooks/pre-commit-deprecated-alias.sh`** — local
  pre-commit hook (opt-in). Scans staged code (filters out
  `docs/`, `*.md`, `*.yaml`, `*.yml`, session-state JSON) for
  deprecated aliases from `_vocabulary.yaml`. WARN by default.
  `STRICT=1` makes it BLOCK; `SKIP_VOCAB_PRECOMMIT=1` short-
  circuits.

- **C-2 `scripts/ci-vocab-deprecation-check.sh`** + new step in
  `.github/workflows/deploy.yml` — HARD enforcement at CI. Scans
  `origin/main..HEAD` (fallback `HEAD~1..HEAD`); exits 1 on
  detection. Required `fetch-depth: 0` on `actions/checkout`.

- **C-3 `scripts/vocab-drift.sh`** — read-only drift report.
  For each curated entry (skips `bounded_context: TODO` /
  `definition: TODO:`) verifies `authoritative_path` exists and
  `canonical` grep-matches inside that file. Output rows:
  `MISSING_PATH | NAME_DRIFT \t canonical \t path \t detail`.

- **C-4 `scripts/vocab-rename.sh`** — atomic rename helper.
  Validates (`old` exists, `new` free, optional path exists),
  then awk-rewrites the entry: replaces `canonical:`, inserts
  old as alias `surface: "deprecated"`, optionally updates
  `authoritative_path:`. mktemp + mv pattern.

All four source `lib/vocabulary-reader.sh` (I12) — no YAML
parsing reimplementation.

Plus: Makefile targets `vocab-drift` + `vocab-rename`, four smoke-
test scripts (5/3/7/10 assertions, all green).

## Approach

Per spec — **B (four tooling pieces in one interaction)**. Curation
of the 47 `TODO: curate definition` entries deferred to Phase
C-bis (data work, separate calibration window).

## Phases

- **Consult / brainstorm:** spec was committed in a prior session
  (commit `199bb73`); on resume, advanced through consult/brainstorm
  via `SKIP_PHASE_EXIT_GATE=1` since the artifact + decision-log
  entry already existed.
- **Planning:** parallel plan (Wave 1: 4 disjoint scripts; Wave 2:
  Makefile; Wave 3: 4 smoke tests; Wave 4: verification).
- **Implementation:** Waves 1→2→3 sequentially (each task touches a
  different file; could've launched as concurrent agents but ~80
  lines each made direct edits faster than agent setup).
- **Verification:** all 4 smoke tests green. Real-registry smoke of
  `vocab-drift` reports 14 drift rows (1 MISSING_PATH for
  `Driver`, 13 NAME_DRIFT in validators where canonical is
  CamelCase but the file uses kebab-case). This is expected
  uncurated drift — material for Phase C-bis. `vocab-rename` smoke
  on a temp copy produces correct YAML diff.

## Blockers / corrections during implementation

1. **plan-progress.sh task regex.** Initial plan used
   `**1 (C-1):**` for task headers — parser regex requires
   `**Na — Title**` (em-dash/hyphen/colon between id and title,
   `**` immediately closing title). Reformatted all 15 tasks.

2. **`REPO_ROOT` short-circuit.** Both `vocab-drift.sh` and
   `vocab-rename.sh` originally used
   `REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null \
       || cd "$(dirname "$0")/.." && pwd)"`.
   Shell precedence: `(A || B) && C` — `pwd` always replaces the
   git result with the script-dir. Smoke `test-vocab-drift.sh`
   caught it (paths resolved against mxo-track root inside a temp
   repo). Fixed with explicit `if [ -z "$REPO_ROOT" ]`.

3. **`set -e` swallowing awk exit.** `vocab-rename.sh` used
   `awk ... > tmp; awk_status=$?` — with `set -e`, awk's nonzero
   exit aborted before capture. Fixed: `awk ... || awk_status=$?`.

4. **mawk-incompatible regex.** Initial `aliases:` matcher used
   `\s*\[\]\s*$`; `\s` is a Perl/gawk extension, mawk lacks it.
   Replaced with literal-string equality (`$0 == "    aliases: []"`).
   Aligned with portability rule documented in I12 / lib.

5. **Sync-gate `→ files:` parser.** Plan declared two paths on
   continuation lines. `parse_files_decl` only reads single lines,
   so `deploy.yml` was absent from declared set — sync-gate flagged
   drift. Fixed by inlining both paths on one line.

6. **Session-state reset on resume.** A `git stash --include-untracked`
   used to A/B test pre-existing shellcheck warnings stashed
   `.claude/session-state.json`. The subsequent SessionStart:resume
   hook reset `flow_type` and `evidence.user_approved`. Reconstructed
   evidence and got verbal re-approval.

## Verification results

- `bash -n` clean on 8 new scripts ✅
- `shellcheck -S warning` clean on the 8 new scripts ✅
- 4 smoke tests: 25/25 assertions passing (5+3+7+10) ✅
- Pre-existing baseline failures (6 harness tests, 6 baseline
  shellcheck warnings in unrelated files) verified via stash A/B
  — NOT regressions from this change.

## Patterns (graduation candidates if 3+ recurrences)

- **vocab-consumer** — 3 already (B-1/B-2/B-3) + 4 here = 7. Already
  graduated to lib (I12). Reaffirms the lib's value.
- **mawk-vs-gawk-regex** — 2nd occurrence in this hito (B-2 and
  C-4). Knowledge module `.claude/README.md § Shell Portability`
  already documents it.
- **atomic-yaml-rewrite (mktemp + awk + mv)** — 1st explicit
  occurrence in `scripts/`. Watch for recurrence in C-bis curation
  helpers; if it recurs, graduate.

## Decisions

- Pre-commit hook is **opt-in by symlink**, not auto-installed.
  Reasons: (a) developers may already have a pre-commit framework;
  (b) `.git/hooks/` is not version-controlled by convention.
- CI step added to existing `lint` job (not a new workflow) —
  matches the spec's Inventory decision.

## Bypass usage

- `SKIP_PHASE_EXIT_GATE=1` used twice during consult→brainstorm and
  brainstorm→planning advance, because the spec was committed in a
  prior session and the harness can't reconstruct the implicit
  approval. Decision-log entry tracks the case (recurring pattern
  for cross-session continuations — see follow-up).

## Follow-ups

- **Phase C-bis curation** — 47 `TODO: curate definition` entries.
  Use `vocab-drift` as a checklist; the 14 current drift rows
  surface exact targets:
  - MISSING_PATH `Driver` → entity moved? Update path or canonical.
  - 13 NAME_DRIFT for validators — canonicals are CamelCase
    (`BrainstormValidator`) but the files are kebab-case
    (`brainstorm-validator.sh`). Either rename the canonical to
    match the file or re-source canonical inside the script.
- **Cross-session continuation pattern.** 2nd interaction using
  `SKIP_PHASE_EXIT_GATE=1` to skip prior phases when a spec exists
  from a previous session. Track occurrences; at 3+ tune the
  consult/brainstorm validators to auto-detect "spec already
  committed" as evidence.
- **Pre-existing harness test failures** in 6 hook tests
  (test-enforcement-layers, test-full-flow-e2e, test-phase-advance,
  test-self-gating, test-status-line, test-workflow-engine). Out of
  scope; filed.
