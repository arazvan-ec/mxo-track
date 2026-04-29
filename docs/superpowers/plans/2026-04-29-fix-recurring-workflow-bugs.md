# Plan — I8: Fix Two Recurring Workflow Bugs

**Spec:** `docs/superpowers/specs/2026-04-29-fix-recurring-workflow-bugs-design.md`

## Phase 1: edit + verify

### Wave 1: Bug 1 fix — HEAD-vs-upstream guard
- **1:** En `.claude/hooks/user-prompt-state.sh:157-188`, añadir verificación `git rev-parse HEAD == git rev-parse @{upstream}` antes del auto-reset. Si no coincide → defer reset.
  → files: `.claude/hooks/user-prompt-state.sh`

### Wave 2: Bug 2 fix — snapshot sync after mutations
- **2:** En el mismo archivo, tras cada mutación de state (set `user_approved=true` líneas 75/84, y tras auto-reset línea 185), `cp $STATE_FILE /tmp/ptc-state-snapshot.json`.
  → files: `.claude/hooks/user-prompt-state.sh`

### Wave 3: Verificación
- **3a:** `bash -n` syntax check.
- **3b:** 31 tests existentes siguen pasando.
- **3c:** Smoke este flow: durante finalize, push sin manual jq de `branch_strategy`.
- **3d:** Smoke segundo: jq con texto `user_approved = true` redundante no dispara revert.

## Estimación

| Métrica | Estimación |
|---|---|
| `user-prompt-state.sh` Bug 1 fix | +6 lines |
| `user-prompt-state.sh` Bug 2 sync | +3-5 lines (3 puntos de inserción) |
| Total net | ~10-12 lines |
| Files (incl artefactos) | 5 (1 fuente + spec + plan + log + manifest) |

## Done criteria

- [ ] HEAD-vs-upstream check añadido
- [ ] Snapshot sync tras mutations
- [ ] Tests 31/31 pasan
- [ ] Smoke: push sin manual branch_strategy reset
- [ ] Smoke: user_approved sobrevive jq con texto pattern
- [ ] Commit + push
