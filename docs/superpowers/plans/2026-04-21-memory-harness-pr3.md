# Plan — 2026-04-21 — Memory/Harness PR3

**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr3.md`
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`

---

## Phase 1: v0

### [parallel] Wave 1: 4 items en archivos disjuntos

- **1a: user-prompt-state.sh — decision-ID approval**
  - Add 5-line auxiliary check after existing approval regex
  - Respect rejection priority (rejection regex runs after)
  - → produces: "D1a D2b" style messages trigger approval

- **1b: pattern-audit.sh — KNOWLEDGE_DIR env**
  - Replace hardcoded KNOWLEDGE_DIR with `${PATTERN_AUDIT_KNOWLEDGE_DIR:-...}`
  - Update test-pattern-audit.sh to drop wrapper, use env var directly
  - → produces: cleaner test + script parametrizable

- **1c: superpowers-skills.md — convention section**
  - Add "Workflow Script Conventions" section with 5 rules + examples
  - → produces: convention documented, graduates 3+ occurrences pattern

- **1d: suggest-tags.sh + test**
  - New script with keyword→tag table, --dry-run default, --apply mode, idempotent
  - Test fixtures with known keyword→tag mappings
  - → produces: script ready to backfill tags

**Commit 1:** `feat: flexible approval regex + KNOWLEDGE_DIR env + tag backfill script`
- ~250 líneas

### Wave 2: Execute tag backfill (needs 1d)

- **2a: Dry-run over 86 logs, spot-check 5 samples**
- **2b: Apply suggestions**
- **2c: Smoke test `consult.sh stats` — tags now populated, any ≥3 patterns?**

**Commit 2:** `chore: backfill tags into 86 execution logs via keyword heuristic`

### Wave 3: Verification + close-out

- 3a: run all tests
- 3b: session-start smoke
- 3c: make manifest
- 3d: capture + retrospective + finalize + push

---

## Task count: 8 tareas, 3 waves, 3 commits
## Time estimate: 60 min
## Risk: Low — 4 items independientes, append-only tag backfill, git revertible
