# Plan — PR 2 + PR 3 en paralelo

**Spec:** `docs/superpowers/specs/2026-04-14-pr2-pr3-parallel-design.md`
**Branch principal:** `claude/enhance-dashboard-widgets-sxseH`

## Fase 1 (v0)

### [parallel] Wave 1 — Dispatch concurrente

#### **1a — Agente A: PR 2 registry migration (worktree)**

Rama hija: `claude/enhance-dashboard-widgets-pr2-registry`
Prompt self-contained con spec + file ownership + criterios de aceptación.
Background agent, isolation worktree.

→ produce: commit pusheado con migración + refactor de AdminDashboardPage

#### **1b — Agente B: PR 3 user preferences (worktree)**

Rama hija: `claude/enhance-dashboard-widgets-pr3-prefs`
Prompt self-contained con spec + file ownership + cambio aditivo CollapsibleWidget
+ criterios de aceptación.
Background agent, isolation worktree.

→ produce: commit pusheado con UserPreference + ProfilePage + CollapsibleWidget aditivo

### Wave 2 — Merge-back (tras ambos agentes)

#### **2a — Merge PR 2**

`git fetch origin && git merge origin/claude/enhance-dashboard-widgets-pr2-registry --no-ff`

#### **2b — Merge PR 3**

`git merge origin/claude/enhance-dashboard-widgets-pr3-prefs --no-ff` (resolver
conflicto en manifest si ocurre)

#### **2c — Preflight + manifest + push**

`make manifest && bash backend/bin/preflight.sh && git push`

## Fase 2 (Mature)

N/A. Se cierra con preflight 7/7 o se itera.
