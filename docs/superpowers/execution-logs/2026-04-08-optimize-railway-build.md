---
type: process
tags: []
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-08 — Optimize Railway Docker Build

**Type:** Infrastructure optimization
**Branch:** `claude/investigate-slow-build-ochX2`

## Brainstorming

- 3 enfoques evaluados: (A) Layer caching, (B) BuildKit cache mounts, (C) A+B combinado
- Enfoque A aprobado: menor riesgo, mayor beneficio en caso común

## Implementation

### Cambios realizados

1. **`.dockerignore` (nuevo)** — Excluye `.git/`, `docs/`, `tools/`, `ml-service/`, Dockerfiles no usados, artifacts locales. Reduce build context de ~8MB a ~2MB.

2. **`Dockerfile.railway` Stage 2 reestructurado:**
   - Lock files (`composer.json`, `composer.lock`, `symfony.lock`) se copian ANTES del código
   - `composer install --no-scripts` ejecuta con lock files solamente (layer cacheable)
   - Código backend se copia después
   - `composer dump-autoload --classmap-authoritative` regenera autoloader con codebase completo

### Qué NO se cambió
- Stage 1 (frontend) ya estaba optimizado
- `railway.toml`, `nginx-railway.conf`, `railway-start.sh` — no afectados

## Verification

- Tests: N/A (no hay tests para Dockerfiles)
- Validación lógica: .dockerignore no bloquea paths referenciados en Dockerfile ✅
- Verificación real: pendiente en próximo deploy a Railway

## Resultado esperado

| Escenario | Antes | Después |
|-----------|-------|---------|
| Cambio solo en PHP | ~3:21 | ~1:30-2:00 |
| Cambio en dependencias | ~3:21 | ~3:00-3:20 |
| Build context transfer | ~8MB | ~2MB |

## Lessons

- La separación de lock files vs código fuente es el patrón #1 para Docker layer caching en apps con package managers
- Railway usa Docker BuildKit — los layer caches se preservan entre deploys del mismo servicio
