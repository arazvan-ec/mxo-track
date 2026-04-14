# Design Spec — 3 backlog items from retro

**Fecha:** 2026-04-14

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `lib/classify-file.sh` `is_valid_flow_type` | Transformar | Añadir `agent` |
| `workflow-engine.sh` `get_validators_for_flow` | Transformar | Case `agent` sin gates |
| `phase-advance.sh` `FLOW_PHASES` | Transformar | Añadir secuencia `agent` |
| `AGENTS.md` | Transformar | Documentar light agent mode + session-state isolation |
| `GitLogReaderTest.php:106-131` | Transformar | Fix aserción frágil |
| `session-start.sh` | Omitir | Worktrees ya tienen session-state aislada |
| `.claude/test-baseline.txt` | Transformar | Ajustar a 0 tras fix del test |

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Hook auto-detect worktree en session-start | Omitir | La isolation ya funciona; el problema real fue path stale, no contaminación cruzada |
| Pre-push gate para `agent` flow | Omitir | Agentes en worktree pushean a ramas hijas, no pasan por el gate principal |
