# Spec — 2026-04-21 — Memory/Harness PR5 (Workflow Flow Improvements)

**Type:** process (workflow improvements)
**Branch:** `claude/view-plan-progress-ddWZc`
**Continues:** serie memory-harness (PR1-4)

## Context

Objetivo: **mejorar el flujo descrito en CLAUDE.md**, no añadir deuda técnica.
Cierra 3 gaps operacionales con evidencia concreta en execution logs de PR1-4:

1. El valor `lint_clean = "skipped"` pasa el pre-push gate con ⚠ (soft warn). Todas
   las retros de PR1-4 dicen "Lint: skipped (no shellcheck)". El gate no tiene
   dientes cuando el model declara skipped.
2. CLAUDE.md sección "Closing the Cycle" habla de "graduation" pero no menciona
   `graduate.sh` ni `_graduations.yaml` (infra creada en PR4). El flujo descrito
   ignora la infra real.
3. `plan-progress.sh` wave parser usa regex `^###\s+Wave` que no matchea
   `### [parallel] Wave N`. PR4 reportó "2 waves, 17 tareas" cuando el plan real
   tenía 5 waves.

## Alternativas evaluadas

### Para #1 (lint_clean skipped bypass):

- **Approach A (elegido):** añadir `make lint-shell` (shellcheck) + endurecer
  validator para que "skipped" sea ERROR en full/debug flows (aceptable solo en
  informational/light/explore donde no hay verification real).
  - Ventaja: teeth reales, consistente con intent del gate
  - Desventaja: requiere fix de warnings previos antes de aplicar el endurecimiento
- **Approach B (descartado):** mantener "skipped" como soft warn, cambiar prompt
  visible para forzar al model a justificar cada skip.
  - Ventaja: menos disruptivo
  - Desventaja: signal sin teeth; 4 retros consecutivas demuestran que el warning
    no cambia comportamiento
- **Approach C (descartado):** context-aware — si `.sh` file en diff, requiere
  lint-shell pass; si solo `.php` requiere make lint.
  - Ventaja: precisión quirúrgica
  - Desventaja: requiere git integration en validator; scope creep

### Para #2 (CLAUDE.md integración de PR4):

- **Approach A (elegido):** editar sección "Closing the Cycle" + "Knowledge
  Modules" para referenciar `graduate.sh` y `_graduations.yaml` como el blessed
  path cuando un pattern aparece 3+ veces.
  - Ventaja: alinea documentación con realidad
- **Approach B (descartado):** crear sección nueva "Graduation Workflow" separada.
  - Desventaja: añade scope; el pattern ya encaja en "Closing the Cycle"

### Para #3 (plan-progress wave regex):

- **Approach A (elegido):** extender regex para aceptar prefijos tipo
  `### [parallel] Wave N`.
  - Regex nuevo: `^###\s+(?:\[[^\]]*\]\s+)?Wave\s+(\d+)...`
  - Ventaja: backward-compatible; resuelve el bug observado
- **Approach B (descartado):** cambiar la convención de plans para NO permitir
  prefijos en waves.
  - Desventaja: fricción para el autor; prefijos son informativos útiles

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `.claude/hooks/validators/verification-validator.sh` | **Transform** | Endurecer aceptación de "skipped" para full/debug flows |
| `.claude/hooks/plan-progress.sh` (regex parser) | **Transform** | Extender wave regex |
| `CLAUDE.md` sección "Closing the Cycle" | **Transform** | Integrar graduate.sh narrativamente |
| `Makefile` | **Transform** | Añadir `lint-shell` target |
| Shell scripts `.claude/hooks/*.sh`, `scripts/*.sh` (14 archivos) | **Transform** | Fix de 9+ bugs reales detectados por shellcheck |
| `make lint` (PHP lint existente) | **Include** (sin cambios) | No tocamos lint PHP |

## Omission Decisions

| Elemento considerado | Decisión | Justificación |
|---|---|---|
| Context-aware lint (detectar `.sh` en diff) | **Omit** | Scope creep; endurecer "skipped" en full cubre el intent |
| Graduación de bash-yaml-parsing-idiom | **Omit** | No es workflow improvement; diferido a PR separado |
| Hard-gate de shellcheck en pre-push | **Omit** | Primer iteration = target + fix; gate en PR futuro si cuela regresión |
| Rediseño del approval regex | **Omit** | Scope M; mejor dedicar un PR aparte |
| Validator estructural de retrospectives | **Omit** | Scope M; candidato futuro |
| Fix de warnings info-level (SC2317 etc.) | **Omit** | Falsos positivos; disable selectivo en casos necesarios |

## Design

### D1: `make lint-shell` + fix de warnings

**Nuevo Makefile target:**
```make
lint-shell:
	@shellcheck -S warning .claude/hooks/*.sh scripts/*.sh
```

**Severity threshold = warning:** excluye info/style ruidosos (SC2317 es falso
positivo masivo por indirect calls; SC2086 es estilo). Warning y error sí se
enforcean.

**9 bugs reales a fix:**
- SC2064 (×5 en test files): `trap "rm -rf $TMPDIR" EXIT` → `trap 'rm -rf "$TMPDIR"' EXIT`
- SC2221/SC2222 (×3+3 en backfill-exec-logs.sh): patrones case que se solapan o nunca matchean
- SC1083 (×2): literales `{` en regex — evaluar si disable o fix
- SC1010 (×1): `;` antes de `next` en awk
- SC2155 (×1): `declare -r X=$(cmd)` — separar en dos líneas

**Disables selectivos con comentario:**
- SC2034 en tests: `# shellcheck disable=SC2034` en variables usadas indirectly
- Otros que resulten ser intent: disable con razón

### D2: Endurecer verification-validator

En `verification-validator.sh`, cambiar:
```bash
case "$LINT_CLEAN" in
  skipped)
    WARNINGS="${WARNINGS}- SOFT: lint_clean=skipped..."
    ;;
```
a:
```bash
case "$LINT_CLEAN" in
  skipped)
    if [ "$FLOW_TYPE" = "full" ] || [ "$FLOW_TYPE" = "debug" ]; then
      ERRORS="${ERRORS}- lint_clean=skipped no es aceptable en flow=$FLOW_TYPE. Corre 'make lint && make lint-shell'.\n"
    else
      WARNINGS="${WARNINGS}- SOFT: lint_clean=skipped..."
    fi
    ;;
```

Mismo cambio para `tests_passed=skipped` por consistencia.

### D3: CLAUDE.md edits

**Sección "Closing the Cycle":** añadir mención de graduate.sh tras el bloque
existente sobre 3+ ocurrencias:
> When the same lesson appears 3+ times... it graduates to the relevant
> knowledge module. **Use `scripts/graduate.sh <name> --module=<file>
> --section=<heading>` to register the graduation in
> `docs/knowledge/_graduations.yaml`.** `pattern-audit.sh` runs at finalize
> and surfaces candidates with a ready-to-paste command.

**Sección "Knowledge Modules":** añadir línea sobre cómo se registra la graduación:
> **Graduated tags/patterns:** registered in `docs/knowledge/_graduations.yaml`.
> Query via `consult.sh tag <name>` for logs, validate drift with
> `scripts/validate-graduations.sh`.

### D4: plan-progress wave regex fix

**Bug:** `re_wave = re.compile(r'^###\s+Wave\s+(\d+)...')` no matchea
`### [parallel] Wave 1: Title`.

**Fix:** `re_wave = re.compile(r'^###\s+(?:\[[^\]]*\]\s+)?Wave\s+(\d+)...')`.
Acepta prefijo `[...]` opcional.

**Test regresión:** fixture plan con ambas variantes, verify count correcto.

## Aprobación

Usuario aprobó scope "X reenfocado: #1 + #2 + #3" en brainstorm 2026-04-21.
Items cargan evidencia concreta de execution logs PR1-4 como gaps de flujo,
no como deuda técnica.
