# Design Spec: Harness Design Improvements

**Fecha:** 2026-03-24
**Contexto:** Aplicar hallazgos del artículo de Anthropic "Harness Design for Long-Running Application Development" al flujo de trabajo de CLAUDE.md
**Fuente:** `docs/superpowers/agent-outputs/2026-03-24-harness-design-patterns-review.md`
**Bounded Context:** Pragmático — cambios de documentación y configuración, no código de dominio

---

## Approach elegido

**Implementación directa de las 7 recomendaciones aprobadas.** No hay alternativas en competencia — cada recomendación fue evaluada individualmente en el análisis previo con trade-offs documentados.

**Trade-off principal:** Relajar gates (tareas 3, 4) puede reducir discipline. Mitigación: son SOFT warnings (no blocks), y la nueva sección Harness Assumptions (tarea 1) permite trackear y revertir si compliance baja.

## Existing Functionality Inventory

| Elemento | Ubicación | Estado actual |
|----------|-----------|---------------|
| Scope Change Detection gate | `workflow-engine.sh:82-88` | HARD deny en `src/`/`tests/` |
| Brainstorm user_turns validator | `brainstorm-validator.sh:18-20` | HARD block si `< 3` |
| Anti-rationalization table (flow) | `CLAUDE.md:513-520` | 6 rows |
| Anti-rationalization table (Skill 1) | `CLAUDE.md:1021-1028` | 6 rows |
| Anti-rationalization table (Skill 7 TDD) | `CLAUDE.md:~1438-1444` | 5 rows |
| Anti-rationalization table (Skill 8 Debug) | `CLAUDE.md:~1514-1519` | 4 rows |
| Anti-rationalization table (Skill 9 Verification) | `CLAUDE.md:~1605-1611` | 5 rows |
| Skill 5 dispatch process | `CLAUDE.md:1211-1225` | No acceptance criteria template |
| Validators evidence table | `CLAUDE.md:642-652` | `user_turns ≥ 3` documented |
| No "Harness Assumptions" section | `CLAUDE.md` | Does not exist |
| No "Context Hygiene" section | `CLAUDE.md` | Does not exist |
| No checkpoint reviews for XL | `CLAUDE.md` Skill 5 | Does not exist |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Scope Change gate | Transform (HARD → SOFT) | Stress-test: Opus 4.6 puede detectar scope changes sin blocking |
| Brainstorm user_turns | Transform (HARD ≥3 → HARD ≥1 + SOFT <3) | Permite brainstorm corto para tareas bien definidas |
| Anti-rat flow table | Transform (reduce 6→3 rows) | Keep most impactful rows, remove redundant |
| Anti-rat Skill 1 table | Transform (reduce 6→3 rows) | Keep most impactful rows |
| Anti-rat Skill 7/8/9 tables | Include (keep as-is) | Critical discipline areas, keep full tables |
| Skill 5 dispatch | Transform (add acceptance criteria) | Sprint Contract Pattern from article |
| Validators table | Transform (update user_turns) | Reflect new threshold |
| Harness Assumptions | Include (new section) | Core recommendation from article |
| Context Hygiene | Include (new section) | Gap identified in analysis |
| Checkpoint reviews XL | Include (new in Skill 5) | Prevents divergence on large features |

## Cambios detallados por archivo

### 1. `CLAUDE.md` — 5 edits

**Edit A: New "Harness Assumptions & Evolution" section**
- Insert after "Workflow Engine Integration" section (after line ~672, before "Automatic Session Context")
- Contains: assumption inventory table, enforcement levels, evolution model, review schedule

**Edit B: New "Context Hygiene" section**
- Insert after "Automatic Session Context" section (after line ~699, before "Automatic Status Line")
- Contains: checkpoint rules, session division guidance, post-compaction verification, handoff protocol

**Edit C: Reduce flow anti-rationalization table**
- Location: lines 513-520
- Keep 3 rows: "Es un cambio de una línea", "Saltemos brainstorming", "Es solo una extensión"
- Remove 3 redundant rows

**Edit D: Reduce Skill 1 Red Flags table**
- Location: lines 1021-1028
- Keep 3 rows: "This is just a simple question", "This doesn't need a formal skill", "I'll just do this one thing first"
- Remove 3 less impactful rows

**Edit E: Add Sprint Contract + Checkpoint Reviews to Skill 5**
- Location: after line 1225 (after step 5 of "The Process"), before "Model Selection"
- Add "Sprint Contract Pattern" subsection with acceptance criteria template
- Add "Checkpoint Reviews (features XL)" subsection

**Edit F: Update Validators table**
- Location: line 645
- Change `user_turns ≥ 3` to `user_turns ≥ 1 (HARD) + SOFT warning si < 3`

### 2. `.claude/hooks/workflow-engine.sh` — 1 edit

**Edit G: Scope Change gate HARD → SOFT**
- Location: lines 84-86
- Change `deny` to `warn`, add "(SOFT)" prefix to message

### 3. `.claude/hooks/validators/brainstorm-validator.sh` — 1 edit

**Edit H: user_turns threshold change**
- Location: lines 18-20
- HARD block only when `< 1` (zero turns)
- SOFT warning (exit 1) when `1-2` turns
- Add WARNINGS variable handling
