# Spec — 2026-04-21 — Memory/Harness PR3 (Regex + KDIR + Doc + Tag Backfill)

**Type:** process (workflow infrastructure)
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Continues:** PR1 + PR2 (memory-harness series)

## Context

PR3 cierra los 4 follow-ups identificados en retrospectivas de PR1/PR2:
- #1 Flexible approval regex (fricción observada 2× en esta sesión)
- #2 Parametrize `KNOWLEDGE_DIR` en pattern-audit.sh (elimina wrapper fixture)
- #3 Documentar "workflow script conventions" en knowledge module (3+ ocurrencias → gradúa)
- #7 Tag backfill oportunístico sobre 86 logs existentes

## Problema

El regex de aprobación en `user-prompt-state.sh` no reconoce confirmaciones por
ID de decisión ("D1a D2b D3a"). El hook fuerza al usuario a reenviar aprobación
con palabras explícitas ("confirmo", "ok"), causando fricción innecesaria.

`pattern-audit.sh` hardcodea `KNOWLEDGE_DIR`, bloqueando tests aislados.

El patrón "idempotent + env-overrideable + --force" apareció 3× sin estar
documentado, perdiendo el valor de la convención emergente.

Los 86 execution logs backfilleados en PR1 tienen `tags: []`, volviendo inútil
`consult.sh tag` y `pattern-audit` hasta llenarlos oportunísticamente.

## Alternativas evaluadas

### Para #1 (approval regex):
- **Approach A (elegido):** detectar ≥2 decision IDs como `\bd[0-9]+[a-e]?\b`
  - Ventaja: threshold conservador (1 solo ID es ambiguo); implementación ~5 líneas
  - Desventaja: no captura "1, 2, 3" (sin letra); requiere que el usuario referencie IDs
- **Approach B (descartado):** detectar listas numeradas genéricas (1., 2., 3.)
  - Ventaja: más permisivo
  - Desventaja: muchos falsos positivos (usuario puede numerar contraargumentos)
- **Approach C (descartado):** requerir emoji ✓ como signal explícito
  - Ventaja: cero ambigüedad
  - Desventaja: cambio de convención para el usuario; friction alta

Trade-off: A pierde algo de recall por precisión. Aceptable.

### Para #2 (KNOWLEDGE_DIR):
- **Approach A (elegido):** env var `PATTERN_AUDIT_KNOWLEDGE_DIR` con fallback
  - Ventaja: consistente con otros scripts (CONSULT_LOGS_DIR, MARK_VERIFIED_LOGS_DIR)
- **Approach B (descartado):** flag CLI `--knowledge-dir <path>`
  - Ventaja: explícito
  - Desventaja: rompe invocación desde phase-advance.sh sin args

### Para #7 (tag backfill):
- **Approach A (elegido):** keyword→tag table inline, match por substring en filename
  - Ventaja: determinístico, auditable, 20 keywords suficientes
  - Desventaja: no captura tags del contenido del body
- **Approach B (descartado):** LLM-based tag extraction
  - Ventaja: más rico semánticamente
  - Desventaja: over-engineering para scope actual; dependencia externa
- **Approach C (descartado):** solo content-based (parse body del log)
  - Ventaja: más completo
  - Desventaja: mucho ruido, difícil de auditar

Trade-off A: precisión por cobertura. Filename carga la señal más importante.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `user-prompt-state.sh` approval regex (PR1 baseline) | **Transform** | Añadir check auxiliar para decision IDs (D1a, D2b) |
| `pattern-audit.sh` KNOWLEDGE_DIR hardcoded (PR2) | **Transform** | Permitir override via env var |
| `superpowers-skills.md` | **Transform** | Añadir sección "Workflow Script Conventions" |
| 86 logs con `tags: []` (backfill PR1) | **Transform** | Inyectar tags basados en keyword matching del filename |
| PR2 `test-pattern-audit.sh` wrapper fixture | **Transform** (después) | Simplificar usando el env var nuevo |

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| ML-based tag extraction | **Omit** | Over-engineering para 20 keywords fijos |
| Tag normalization/merging (ej. "ios" vs "ios-preset") | **Omit** | Primer pass; si aparece ruido, refactor |
| Tag suggestions desde el contenido del body | **Omit** | Filename es suficiente señal + menos ruido |
| Aprobación via sign detection (✓ emoji) | **Omit** | Complejidad sin retorno; decision IDs suficiente |

## Design

### #1 Regex de aprobación flexible

Añadir después del regex principal en `user-prompt-state.sh`:
```bash
# Decision-ID approval: 2+ references like "D1a", "D2b" imply confirmation of a list
decision_ids=$(echo "$PROMPT_LOWER" | grep -oE '\bd[0-9]+[a-e]?\b' | wc -l | tr -d ' ')
if [ "$decision_ids" -ge 2 ] && [ "$CURRENT_APPROVED" != "true" ]; then
  # Still respect rejection priority (already checked above)
  jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
fi
```

Colocar ANTES del rejection check para que rejection siga teniendo prioridad.

### #2 Parametrize KNOWLEDGE_DIR

En `pattern-audit.sh`:
```bash
KNOWLEDGE_DIR="${PATTERN_AUDIT_KNOWLEDGE_DIR:-$REPO_ROOT/docs/knowledge}"
```

Simplifica `test-pattern-audit.sh` eliminando el wrapper.

### #3 Workflow Script Conventions (knowledge doc)

Nueva sección en `docs/knowledge/superpowers-skills.md`:
```markdown
## Workflow Script Conventions

Patrón observado 3+ veces (backfill-exec-logs, mark-verified, link-regression).
Convención para scripts en `scripts/` y `.claude/hooks/`:

1. **Idempotent**: detect-state → skip-if-done → do. Output "SKIP: ..." en vez de error.
2. **--force flag**: permite overwrite explícito cuando skip-by-default no es deseado.
3. **Env-overrideable paths**: accept `<SCRIPT>_LOGS_DIR` o similar para test isolation.
4. **Exit codes**: 0=éxito con cambios, 1=éxito sin cambios/skip, 2=error.
5. **Dry-run mode**: `--dry-run` preview sin escribir, para scripts destructivos.
```

### #7 Tag backfill (suggest-tags.sh)

Keyword table (inline en script):
```bash
declare -A KEYWORD_TAGS=(
  [glass]=glass-overlay   [sidebar]=sidebar       [menu]=menu
  [overlay]=overlay       [dark]=dark-theme       [theme]=theme
  [ios]=ios-preset        [widget]=widget         [dashboard]=dashboard
  [card]=card             [route]=route           [fleet]=fleet
  [vehicle]=vehicle       [stop]=stop             [shipment]=shipment
  [hook]=hook             [workflow]=workflow     [retrospective]=retrospective
  [session]=session       [refactor]=refactor     [fix]=fix
  [cleanup]=cleanup       [migrate]=migration     [test]=testing
  [gps]=gps               [traccar]=traccar       [driver]=driver
  [customer]=customer     [portal]=portal         [kpi]=kpi
)
```

Para cada log:
1. Lowercase filename, extraer slug (sin fecha/extensión)
2. Para cada keyword, si aparece como substring → añadir tag correspondiente
3. Dedupe tags existentes del frontmatter
4. Update `tags: [...]` line

Modos: `--dry-run` (default), `--apply`.

## Aprobación

Usuario aprobó todo en brainstorm 2026-04-21 (D1 ≥2 threshold, D2 env name, D7 inline + dry-run default).
