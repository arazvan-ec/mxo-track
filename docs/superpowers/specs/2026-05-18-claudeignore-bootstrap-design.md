# Spec — `.claudeignore` Bootstrap

**Date:** 2026-05-18
**Branch:** `claude/compare-claude-workflows-yrl2P`
**Type:** code change (full flow)
**Scope ref:** Harness improvement P1 of 3 — derived from comparative analysis of Anthropic blog "How Claude Code Works in Large Codebases" vs. our `CLAUDE.md` workflow.

## Problem

Claude Code's file-search tools (`Grep`, `Glob`) currently scan all repository paths. Voluminous directories without semantic value for code understanding (compiled assets, dependency trees, build caches) inflate search results and waste context tokens. The repo has **no `.claudeignore`** to exclude these paths at tool level.

Concrete evidence of the cost:
- `backend/vendor/` (~Composer dependencies) and `frontend/node_modules/` (~npm dependencies) account for the majority of files by count.
- `backend/var/cache/` and `backend/var/log/` are runtime artifacts that change every command run.
- `frontend/dist/`, `frontend/.vite/`, `backend/public/build/` are build outputs regenerable from source.

A grep for any common token (e.g., `Route`, `User`, `Service`) returns dozens of hits from these directories before any source-code hit, forcing the model to filter mentally or scroll past noise.

## Approach Chosen

**Single `.claudeignore` at repository root** with conservative exclusions limited to build/dependency artifacts. Explicitly **does NOT exclude** `docs/superpowers/execution-logs/`, `backend/tests/Fixtures/`, `backend/migrations/`, or any source/spec content — those carry semantic value the model may legitimately search.

The file is checked into the repo so future contributors and CI environments inherit the same exclusions.

### Paths to exclude

| Path | Category | Justification |
|---|---|---|
| `backend/vendor/` | Dependencies | Composer-installed; regenerable; high noise |
| `frontend/node_modules/` | Dependencies | npm-installed; regenerable; very high noise |
| `backend/var/cache/` | Runtime | Symfony cache; changes per command |
| `backend/var/log/` | Runtime | Logs; transient |
| `frontend/dist/` | Build output | Vite build artifacts; regenerable |
| `frontend/.vite/` | Build cache | Vite dev cache |
| `frontend/build/` | Build output | Alternate Vite output dir (if present) |
| `backend/public/build/` | Build output | Symfony Webpack Encore output |
| `*.lock` | Lock files | composer.lock / package-lock.json — large and rarely searched by content |
| `.git/` | VCS internal | Git internals — already implicitly excluded by most tools, made explicit |

### Paths explicitly NOT excluded

| Path | Reason |
|---|---|
| `docs/superpowers/execution-logs/` | Free-text body of logs carries information (decisions inline, error messages) not captured in YAML frontmatter; `Grep` complements `consult.sh` |
| `backend/tests/Fixtures/` | Referenced from specs and debugging |
| `backend/migrations/` | Consulted for schema history |
| `docs/decisions/log.md` | Frequently grepped for bypass entries, decisions |
| `.claude/hooks/` | Searched during workflow debugging |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.gitignore` (repo root + subdirs) | **Keep, unchanged** | Different purpose (VCS staging vs. Claude tool scope). `.claudeignore` does not duplicate but extends. |
| `Grep`/`Glob` tool behavior | **No code change** | Honored automatically by Claude Code's tool layer when `.claudeignore` exists at root |
| `consult.sh` | **Keep, unchanged** | Operates over `docs/superpowers/execution-logs/` regardless of `.claudeignore` (uses explicit path) |
| `make manifest` | **Keep, unchanged** | Manifest generator operates on source paths explicitly; not affected by tool-layer exclusions |
| `make lint` / `make lint-shell` | **Keep, unchanged** | Operate on explicit globs; not affected |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Per-subdir `.claudeignore` files (`backend/`, `frontend/`) | **Omit** | No proven need; duplication risk; single root file is simpler and easier to audit |
| `tmp/`, `coverage/`, `.idea/`, `.vscode/` | **Omit for now** | Not present in repo currently; add if/when they appear |
| Documentation of `.claudeignore` syntax in CLAUDE.md | **Omit** | The file itself is self-documenting (gitignore-style syntax); CLAUDE.md is already at the 4-test threshold for ceremony |

## Norms

- The `.claudeignore` file **must** live at repository root only; per-subdirectory `.claudeignore` files **are not permitted** in this iteration.
- Patterns **must** target build artifacts and dependencies; source code, specs, plans, execution logs, and decisions **never** appear in `.claudeignore`.
- Any addition to `.claudeignore` after initial bootstrap **shall** be justified in `docs/decisions/log.md` or in an execution log — silent additions are not allowed.
- The file **must** be committed to the repository (not `.gitignore`d).

## Safeguards

| Risk | Mitigation |
|---|---|
| Exclusion accidentally hides a path the model needs to search (e.g., a fixture file referenced from a spec) | Conservative initial scope — only build/dep artifacts; "explicitly NOT excluded" table documents the boundary |
| Future contributor adds an exclusion that silently removes searchable content | Norm requires justification in decision log; future Learning Reviews can audit `.claudeignore` diff |
| Tool layer ignores `.claudeignore` (different Claude Code version) | Verification step runs `Glob` against an excluded path post-bootstrap; if results return, document as known limitation |
| Patterns conflict with `.gitignore` semantics (different glob rules) | Initial patterns use only basic gitignore-style globs (no negation, no `**` ambiguity) |

## Verification

Post-bootstrap, run:
1. `Glob` for `**/*.php` and confirm zero results from `backend/vendor/`.
2. `Glob` for `**/*.ts` and confirm zero results from `frontend/node_modules/`.
3. `Grep` for a common term in `docs/superpowers/execution-logs/` and confirm hits return (NOT excluded).

If steps 1-2 still return excluded paths, document as known tool-layer limitation in execution log.
