# Plan — Hito 3 Phase C tooling: Vocab Enforcement + Maintenance

**Spec:** `docs/superpowers/specs/2026-04-29-uls-phase-c-tooling-design.md`

Tooling subset (4 of 5 backlog items). Curation deferred to Phase C-bis.

## Phase 1: edit + verify

Tasks 1–4 are independent (disjoint files; each new script reads
`lib/vocabulary-reader.sh` only). Wave 5 depends on the four scripts
existing on disk. Wave 6 depends on everything previous.

### [parallel] Wave 1: C-1 + C-2 + C-3 + C-4

- **1 — C-1 pre-commit-deprecated-alias.sh** Create `.claude/hooks/pre-commit-deprecated-alias.sh`.
  Reads `git diff --cached --name-only`, filters out `docs/`, `*.md`,
  `*.yaml`, `_vocabulary.yaml`, execution-logs and specs paths. For
  remaining staged files, reads staged content via
  `git diff --cached -U0 -- <path>` and matches `+` lines against
  deprecated aliases from `vocab_deprecated_aliases`. Emits
  `WARN: deprecated alias '<term>' (canonical '<canonical>') in <file>`
  to stderr. Exits 0 by default; exits 1 when `STRICT=1`. Bypass:
  `SKIP_VOCAB_PRECOMMIT=1` short-circuits to exit 0. Match uses bash
  `grep -wE` (mawk-safe per portability constraint).
  → produces: `.claude/hooks/pre-commit-deprecated-alias.sh`
  → files: `.claude/hooks/pre-commit-deprecated-alias.sh`

- **2 — C-2 ci-vocab-deprecation-check.sh** Create `scripts/ci-vocab-deprecation-check.sh`. Same
  scan logic as C-1 but operates on `git diff origin/main...HEAD`
  (with fallback to `HEAD~1..HEAD` if no `origin/main`). Exits 1
  when any deprecated alias appears in `+` lines of code paths.
  Designed to be invoked from `.github/workflows/deploy.yml` step
  `bash scripts/ci-vocab-deprecation-check.sh`. CI integration is
  scoped to a single new step in the existing `lint` job — no new
  workflow.
  → produces: `scripts/ci-vocab-deprecation-check.sh`,
    new "Vocab deprecation gate" step in `deploy.yml`
  → files: `scripts/ci-vocab-deprecation-check.sh`,
    `.github/workflows/deploy.yml`

- **3 — C-3 vocab-drift.sh** Create `scripts/vocab-drift.sh`. Iterates entries via
  `awk` over `_vocabulary.yaml`, extracting `canonical` +
  `authoritative_path` + `bounded_context`. For each entry:
  - Skip if `bounded_context: TODO` OR `definition` contains `TODO:`
    (uncurated, not drift candidates).
  - If `authoritative_path` missing on disk → `MISSING_PATH` row.
  - Else if `canonical` not present in file (`grep -wF`) →
    `NAME_DRIFT` row, with hint of where canonical does grep-match
    in repo.
  - Else → silent (no drift).
  Output: TSV-like rows `kind\tcanonical\tpath\tdetail`. Exit 0 when
  no drift, exit 1 when any row reported. Read-only; never modifies
  registry.
  → produces: `scripts/vocab-drift.sh`
  → files: `scripts/vocab-drift.sh`

- **4 — C-4 vocab-rename.sh** Create `scripts/vocab-rename.sh`. Usage:
  `vocab-rename.sh <old_canonical> <new_canonical> [<new_authoritative_path>]`.
  Validation pass (no writes):
  - `old_canonical` exists in registry (else exit 2).
  - `new_canonical` not already a canonical (else exit 2).
  - If `new_authoritative_path` provided, must exist on disk (else
    exit 2).
  Write pass (atomic temp + mv):
  - In the entry block of `old_canonical`:
    - Replace `canonical: <old>` → `canonical: <new>`.
    - Insert into `aliases:` a new entry
      `- {term: "<old>", lang: "en", surface: "deprecated"}`.
      If `aliases:` is `[]` or null, replace with the single-entry
      list.
    - If `<new_authoritative_path>` provided, replace
      `authoritative_path:` line.
  Print summary (`renamed <old> → <new>; alias added; path: <p>`).
  Single-entry edit; if multiple entries match `old_canonical` (data
  bug), abort. Idempotency: re-running after rename detects new is
  canonical → exit 2 ("already renamed").
  → produces: `scripts/vocab-rename.sh`
  → files: `scripts/vocab-rename.sh`

### Wave 2: Makefile targets (needs Wave 1 outputs)

- **5 — Makefile targets** Add to `Makefile`:
  - `vocab-drift:` runs `bash scripts/vocab-drift.sh`.
  - `vocab-rename:` documents usage and runs
    `bash scripts/vocab-rename.sh` (primary entry point is the
    script directly; Makefile target is convenience).
  - Add to `help` block.
  - Add `vocab-drift vocab-rename` to `.PHONY`.
  → files: `Makefile`

### [parallel] Wave 3: smoke tests for new scripts (needs Wave 1)

- **6a — test pre-commit hook** Create `.claude/hooks/test-pre-commit-deprecated-alias.sh`.
  Builds a temp git repo, stages a file with `tour = 1;`, runs C-1
  with the harness `_vocabulary.yaml`, asserts WARN message
  contains `tour` + `Route` + filename. Asserts exit 0 (default).
  Then runs with `STRICT=1`, asserts exit 1. Then with
  `SKIP_VOCAB_PRECOMMIT=1`, asserts exit 0 + no WARN.
  → files: `.claude/hooks/test-pre-commit-deprecated-alias.sh`

- **6b — test CI check** Create `scripts/test-ci-vocab-deprecation-check.sh`.
  Builds two-commit temp repo: base file clean; HEAD adds
  `tour = 2;`. Runs C-2; asserts exit 1 + WARN message present.
  Replaces with clean diff; asserts exit 0.
  → files: `scripts/test-ci-vocab-deprecation-check.sh`

- **6c — test vocab-drift** Create `scripts/test-vocab-drift.sh`. Builds temp YAML:
  - entry A with valid path + canonical present (clean) → must NOT
    appear in output.
  - entry B with non-existent path → must appear with `MISSING_PATH`.
  - entry C with valid path but canonical absent in file →
    `NAME_DRIFT`.
  - entry D with `bounded_context: TODO` → must NOT appear (skipped).
  Asserts exit 1; asserts row content for B + C.
  → files: `scripts/test-vocab-drift.sh`

- **6d — test vocab-rename** Create `scripts/test-vocab-rename.sh`. Builds temp YAML
  with two entries `Foo` and `Bar`. Cases:
  - `vocab-rename.sh Foo Baz` → success, asserts canonical changed,
    `Foo` alias inserted with `surface: "deprecated"`.
  - `vocab-rename.sh Foo Baz` again → exit 2 (`Foo` no longer
    canonical).
  - `vocab-rename.sh Foo Bar` → exit 2 (`Bar` already canonical).
  - `vocab-rename.sh Foo Quux /nonexistent/path` → exit 2.
  → files: `scripts/test-vocab-rename.sh`

### Wave 4: verification (needs Wave 1–3)

- **7a — bash -n** `bash -n` clean on the 4 new scripts + 4 new test scripts.
- **7b — make lint-shell** `make lint-shell` clean (shellcheck severity=warning).
- **7c — new test scripts** Run all 4 new test scripts; all assertions pass.
- **7d — existing tests** 31 existing tests still pass.
- **7e — smoke drift** Smoke `vocab-drift` against real registry: capture output;
  if drift found, document in execution log (expected — informational).
- **7f — smoke rename** Smoke `vocab-rename` against a temp copy of real
  `_vocabulary.yaml`: rename `RouteSnapshot` → `RouteSnap` in temp
  copy → diff to confirm shape.

## Estimación

| Métrica | Estimación |
|---|---|
| `pre-commit-deprecated-alias.sh` | ~70 lines |
| `ci-vocab-deprecation-check.sh` | ~60 lines |
| `vocab-drift.sh` | ~80 lines |
| `vocab-rename.sh` | ~110 lines |
| Makefile changes | +8 lines |
| 4 test scripts | ~250 lines aggregate |
| `deploy.yml` step | +5 lines |
| Total net (code) | ~580 lines |
| Artefactos (plan, exec log, decision-log entry, manifest) | +4 |
| Files touched | 11 |

## Done criteria

- [ ] 4 new tooling scripts land + 4 smoke-test scripts.
- [ ] Makefile `vocab-drift` + `vocab-rename` targets work.
- [ ] `deploy.yml` runs C-2 in `lint` job.
- [ ] All new scripts: `bash -n` clean, `make lint-shell` clean.
- [ ] All 4 new test scripts pass.
- [ ] 31 existing tests pass.
- [ ] Smoke against real `_vocabulary.yaml` produces sensible drift
      report (or empty); rename smoke on temp copy produces correct
      diff shape.
- [ ] Execution log written.
- [ ] Decision log entry referencing this interaction.
- [ ] Commit + push to `claude/phase-c-tooling-Ks1mw`.

## Out of scope (Phase C-bis)

- Curation of 47 `TODO: curate definition` entries.
- Pre-commit framework integration.
- Cache strategy for vocab lookups (deferred per Safeguards row).
- Batch rename / alias migration in `vocab-rename.sh`.
