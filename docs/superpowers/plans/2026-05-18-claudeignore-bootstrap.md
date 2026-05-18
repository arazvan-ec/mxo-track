# Plan — `.claudeignore` Bootstrap

**Spec:** `docs/superpowers/specs/2026-05-18-claudeignore-bootstrap-design.md`
**Date:** 2026-05-18
**Branch:** `claude/compare-claude-workflows-yrl2P`

## Task DAG

Single deliverable + verification. One Wave with one task plus follow-up verification.

### Wave 1: Bootstrap

- **1a:** Create `.claudeignore` at repo root with conservative exclusions per spec § "Paths to exclude"
  → produces: `.claudeignore`
  → files: `.claudeignore` (new)
  → No automated test (tool-layer behavior); verification in Wave 2

### Wave 2: Verification (needs Wave 1)

- **2a:** `Glob` for `**/*.php` and `**/*.ts` — confirm zero hits from `backend/vendor/` and `frontend/node_modules/`
  → produces: verification evidence
- **2b:** `Grep` in `docs/superpowers/execution-logs/` — confirm hits return (NOT excluded)
  → produces: verification evidence

## Estimated artifacts

- 1 file (~15 lines)
- Shared execution log + shared decision log entry with P2 + P3

## Risks

- Tool layer ignores `.claudeignore` (version mismatch) — Wave 2 verifies; if not honored, document as known limitation
- `*.lock` exclusion hides needed file — `Read` with explicit path still works; only `Grep`/`Glob` affected

## Commit cadence

- Single commit after Wave 1.
