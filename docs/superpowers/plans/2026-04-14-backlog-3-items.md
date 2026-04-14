# Plan — 3 backlog items

**Spec:** `docs/superpowers/specs/2026-04-14-backlog-3-items-design.md`

## Fase 1 (v0)

### Wave 1 — 3 cambios paralelos (archivos disjuntos)

#### **1a — Agent light mode: hooks**

- `lib/classify-file.sh`: añadir `agent|agent-flow` a `is_valid_flow_type` + VALID_FLOW_TYPES
- `workflow-engine.sh`: case `agent|agent-flow` → sin validadores (todo permitido)
- `phase-advance.sh`: añadir `FLOW_PHASES[agent]="implementation verification"` (solo 2 fases)

#### **1b — Docs: AGENTS.md agent mode + session-state isolation**

- Sección nueva "Light Agent Mode" con instrucciones para prompts de sub-agentes
- Sección "Session-State Isolation in Worktrees"

#### **1c — GitLogReaderTest fix**

- `testGetCommitsReturnsStructuredArray`: cambiar `assertCount(3, ...)` a
  `assertNotEmpty(...)` — el test valida estructura, no cantidad exacta.
- Actualizar `.claude/test-baseline.txt` a `0`.

### Wave 2 — Verificación + commit + push
