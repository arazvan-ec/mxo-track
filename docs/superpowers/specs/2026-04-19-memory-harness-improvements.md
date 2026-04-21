# Spec — 2026-04-19 — Memory/Harness Workflow Improvements (PR1)

**Type:** process (workflow infrastructure)
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Phased:** sí — este spec cubre PR1 (schema + consult foundation). PR2 cubrirá surfacing + pattern audit + outcome tracking.

---

## Context

Mapeo a las 6 mejoras propuestas a partir del tweet de Harrison Chase sobre harness-as-memory. Objetivos:

1. Explicitar qué sobrevive a la compactación
2. Frontmatter + consult script sobre execution logs
3. Surfacing proactivo de logs relevantes al branch actual [PR2]
4. Pattern audit automatizado (3+ ocurrencias) [PR2]
5. Outcome tracking post-deploy [PR2]
6. Portabilidad — ya resuelta por arquitectura markdown, no requiere cambios

**Principio rector:** la memoria debe ser **legible por el modelo sin overhead de grep**. El frontmatter YAML permite consultas O(n) simples sobre 86+ logs en <100ms.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `docs/superpowers/execution-logs/*.md` (86 archivos) | **Transform** | Inyectar frontmatter YAML mediante script de backfill idempotente |
| `docs/superpowers/templates/execution-log-template.md` | **Transform** | Añadir bloque frontmatter al principio como convención nueva |
| `session-start.sh` (contexto de sesión) | **Include** (sin cambios en PR1) | Ya inyecta último log, branch, commits. PR2 extenderá con "Related past logs" |
| `.claude/README.md` (reference técnica) | **Transform** | Añadir sección "Compaction Contract" con tabla de artefactos safe vs ephemeral |
| `CLAUDE.md` sección "Closing the Cycle" | **Transform** (mínimo) | Mencionar `consult.sh` como herramienta en Step 0 de brainstorming |
| `docs/decisions/log.md` | **Omit** en PR1 | Mismo problema conceptual pero scope distinto — decisions log es menor (30 entradas) y formato es más libre. PR futuro si vale la pena |
| `docs/knowledge/` modules | **Omit** | Ya tienen estructura propia (headers), no requieren frontmatter |
| `scripts/` existentes | **Include** | Patrón validado para scripts one-shot (ver `scripts/` existentes) |
| `.claude/hooks/` scripts | **Include** | Nuevo script `consult.sh` añade a este directorio |

## Omission Decisions

| Elemento considerado | Decisión | Justificación |
|---|---|---|
| Índice JSON derivado | **Omit** (PR1) | YAGNI — awk sobre 86 archivos <100ms. Re-evaluar si >500 logs o >1s query |
| Auto-tagging semántico (LLM sobre contenido) | **Omit** | Over-engineering. Tags manuales funcionan; escala bien hasta cientos |
| Migración a SQLite/DB | **Omit** | Rompe portabilidad (punto #6). Markdown plano > DB binaria |
| `customer_id` en frontmatter | **Omit** | Logs son del equipo dev, no del producto. No aplica multi-tenant |
| Backfill perezoso (solo forward) | **Omit** | Perdería valor inmediato de `consult.sh file <path>` sobre histórico |
| Nombres de archivo estructurados (type encoded in filename) | **Omit** | Rígido, no re-tagueable sin rename, pierde capacidad de múltiples tags |
| Surfacing proactivo en session-start | **Defer** (PR2) | Requiere consult.sh funcional como precondición |
| pattern-audit.sh dedicado | **Defer** (PR2) | `consult.sh stats` cubre la mitad. Hook a finalize es lo que falta |
| Outcome tracking automatizado | **Defer** (PR2) | Requiere post-merge hook + campos ya definidos en schema |
| Regressions linking automático | **Defer** (PR2) | Depende de outcome tracking |

## Design

### Schema (frontmatter YAML)

```yaml
---
type: feature|bugfix|refactor|docs|process
tags: []
files_touched: []
patterns: []
outcome: success|partial|reverted|null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
```

**Reglas de diseño:**
- Listas vacías `[]`, no `null`, donde apliquen (evita ramas en consumidores)
- Campos null-safe (parser no falla si faltan)
- Enum abierto en `tags`/`patterns` (no valid-set fijo), cerrado en `type`/`outcome`

### Backfill extraction

| Campo | Heurística |
|---|---|
| `type` | Parse `**Type:**` line → normalizar a enum (bugfix si contiene "bug\|fix\|debug", etc.). Fallback: `process` |
| `files_touched` | Parse secciones `## Files changed` / `## Files modified` / `## Files` → regex `` `[^`]+\.[a-z]+` `` → dedup |
| `outcome` | `success` si branch aparece en `git branch --merged main` o git log tiene merge commit; else `null` |
| `pr_number` | `git log --grep` por branch → extraer `#NNN` del merge commit |
| `actual_lines` | Sumar `(+X / -Y)` patterns del "Files changed" section si existen |
| Resto | Vacío/null |

**Casos edge:** logs sin "## Files changed" → `files_touched: []`. Logs con type ambiguo → primera palabra gana. Logs sin Type: header → `type: process`.

### consult.sh API

Subcomandos: `tag`, `file`, `file-glob`, `pattern`, `type`, `recent [N]`, `by-outcome`, `stats`, `show`, `unverified`.

Output universal: `YYYY-MM-DD | <type> | <outcome> | <filename> | <title>`

Modo programático: `--quiet` (sin headers) para uso en hooks.

Exit codes: 0=resultados, 1=válido sin resultados, 2=error sintaxis.

Implementación: awk sobre bloques YAML entre `---`. Sin `yq` ni dependencias externas.

### Compaction contract

Sección nueva en `.claude/README.md`:

| Artefacto | Compaction-safe | Ephemeral | Re-inyectado vía |
|---|---|---|---|
| `.claude/session-state.json` | ✅ | | `UserPromptSubmit` hook |
| CLAUDE.md hierarchy | ✅ | | Claude Code autoload |
| Execution logs (markdown) | ✅ | | Lectura bajo demanda |
| Conversación previa (mensajes) | | ✅ | — (se pierden en compaction) |
| Tool call results (output) | | ✅ | — |
| `task_progress.{current,label}` | ✅ | | Status line entre tool calls |
| `work_context.{description,wave,problems}` | ✅ | | Status line |
| Skills cargados (Skill tool result) | | ✅ | Re-invocar Skill tool |

## Plan de archivos PR1

Waves detalladas en `docs/superpowers/plans/2026-04-19-memory-harness-pr1.md` (se escribirá en phase planning).

## Aprobación

Usuario aprobó las 4 secciones A-D vía diálogo brainstorming 2026-04-19:
- A (schema de 12 campos) ✅
- B (backfill extraction) ✅
- C (consult.sh API) ✅
- D (plan de archivos y commits) ✅
