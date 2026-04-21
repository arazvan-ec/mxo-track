# Spec — 2026-04-21 — Memory/Harness PR4 (Strict Graduation Registry + Curation)

**Type:** process (workflow infrastructure + knowledge curation)
**Branch:** `claude/view-plan-progress-ddWZc`
**Continues:** PR1 + PR2 + PR3 (memory-harness series)

## Context

La serie memory-harness dejó el sistema funcional pero con dos gaps operacionales
detectados en el análisis de la rama:

1. **Pattern-audit tiene falsos positivos silenciosos de "graduación".** El matcher
   actual usa `grep -F "$tag"` (substring) sobre knowledge docs. `filter` aparece en
   `"filter-based"`, `hook` aparece en `"webhooks"`, `route` aparece en `"you can
   route..."`. Resultado medido: 11 de 13 tags con ≥3 ocurrencias son marcados como
   "graduados" sin documentación real del pattern.
2. **`suggest-tags.sh` viola la convención que graduó en PR3** (`workflow-script-
   conventions`). La tabla `KEYWORD_TAGS` está hardcoded inline, sin env override, pese
   a que PR3 estableció que scripts workflow deben ser env-overrideable.

Además, el sistema detectó 11 candidatos reales de graduación pero ninguno se
ha curado — la inversión en pattern-audit queda inerte sin aplicación.

## Problema

Tres problemas concretos:

1. **Detección rota:** pattern-audit reporta 2 candidatos (`harness`,
   `harness-memory-separation`) cuando la realidad del corpus es 13 tags/patterns
   con ≥3 ocurrencias pendientes de evaluación.
2. **Deuda de convención:** `suggest-tags.sh` tiene un mapping hardcoded que no se
   puede extender sin editar el script.
3. **Curación pendiente:** 11 tags dominantes en el corpus (map, workflow, route,
   dashboard, menu, glass-overlay, widget, stop, sidebar, hook, filter) sin registro
   canónico de dónde está su documentación.

## Alternativas evaluadas

### Para #1 (matching semantics):

- **Approach A (descartado):** heading/bullet estricto en knowledge modules
  (`^## Pattern: <tag>` o `^- \*\*<tag>\*\*`).
  - Ventaja: docs son self-describing, marker vive donde vive el contenido
  - Desventaja: tags cross-cutting (`filter`, `map`) requieren markers duplicados o
    decisión arbitraria de "módulo canónico"
  - Desventaja: fricción al curar — hay que ubicar/elegir sección correcta en cada módulo

- **Approach B (elegido):** registry dedicado `_graduations.yaml` como única fuente
  de verdad para el estado "graduado".
  - Ventaja: single source of truth; curación es O(1) por tag (editar 1 archivo)
  - Ventaja: unifica dos scripts (`pattern-audit` + `suggest-tags`) — mismo file
    consolida mappings de keywords + registro de graduaciones
  - Ventaja: validable — test verifica que cada `module`/`section` referenciado existe
  - Desventaja: nuevo archivo puede desincronizarse de los docs reales (mitigado
    por validator en Wave 4)

- **Approach C (descartado):** híbrido (heading match primario + YAML como override).
  - Ventaja: flexibilidad
  - Desventaja: dos mecanismos → doble superficie de drift

**Trade-off:** B acepta un archivo extra a cambio de curación trivial + validación
mecánica. Dado que la curación tiene 11 items pendientes AHORA, la ergonomía gana.

### Para #2 (formato del registry):

- **Approach A (elegido):** un solo `_graduations.yaml` con secciones
  `tags:`, `patterns:`, `keyword_mappings:`.
  - Ventaja: un solo file para validar y consultar
  - Ventaja: preserva la relación semántica (keyword `glass` → tag `glass-overlay`
    → sección en `ui-layout-contracts.md`)
  - Desventaja: mezcla 3 responsabilidades en un archivo (aceptable dado tamaño <100 líneas)

- **Approach B (descartado):** dos archivos separados (graduations.yaml +
  suggest-tags-keywords.tsv).
  - Ventaja: separación de concerns
  - Desventaja: duplicación potencial (tag name aparece en ambos); dos files a sincronizar

- **Approach C (descartado):** tres archivos (tags, patterns, keywords).
  - Desventaja: over-fragmented para el tamaño actual

### Para #3 (estrategia de curación):

- **Approach A (elegido):** mapping explícito para los 13 tags, 5 pointer-only
  (sección existe) + 8 con sección nueva mínima (~5-10 líneas por sección).
  - Ventaja: audit queda limpio post-PR (0 candidatos), sistema entra en steady state
  - Ventaja: contenido escrito es útil independiente de graduación
  - Desventaja: +200 líneas de doc nuevas en un solo PR

- **Approach B (descartado):** solo pointer-only (5 tags), diferir los 8 restantes.
  - Ventaja: scope más pequeño
  - Desventaja: audit seguiría ruidoso con 8 candidatos; inversión parcial

- **Approach C (descartado):** graduación pasiva (solo añadir YAML entries sin
  escribir secciones nuevas).
  - Desventaja: validator falla; YAML apunta a secciones inexistentes

### Para #4 (mecanismo de anti-drift):

- **Approach A (elegido):** combinación de 4 controles:
  1. YAML es única fuente de verdad (no hay segunda cosa que olvidar actualizar)
  2. `graduate.sh` helper atómico (validates + writes)
  3. Pattern-audit output incluye comando copy-paste con defaults sugeridos
  4. Validator bidireccional en tests

- **Approach B (descartado):** solo disciplina (documentar "acuérdate de actualizar").
  - Desventaja: falla conocido — humanos/LLMs olvidan bajo carga

**Trade-off:** A requiere infra adicional (script + test) pero convierte la
graduación en operación idempotente con verificación mecánica.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `.claude/hooks/pattern-audit.sh` (PR2) | **Transform** | Refactorizar matcher: substring → YAML registry lookup |
| `scripts/suggest-tags.sh` (PR3) | **Transform** | Extraer `KEYWORD_TAGS` a YAML registry |
| `.claude/hooks/test-pattern-audit.sh` | **Transform** | Adaptar fixtures al nuevo matcher |
| `scripts/test-suggest-tags.sh` | **Transform** | Apuntar a fixture YAML en vez de inline table |
| `docs/knowledge/` 9 módulos | **Transform (parcial)** | 4 módulos reciben secciones nuevas (ui-frontend, superpowers-skills, domain-model, api-surface); 5 no se tocan (pointer-only) |
| `docs/knowledge/superpowers-skills.md` "Workflow Script Conventions" (PR3) | **Include** | El nuevo `graduate.sh` sigue esta convención |
| Template execution log + frontmatter schema (PR1) | **Include** | PR4 añade entradas a `patterns` field sin cambiar schema |

## Omission Decisions

| Elemento considerado | Decisión | Justificación |
|---|---|---|
| Pattern-audit como HARD gate | **Omit** | Sigue siendo advisory (PR2 decisión D3b); PR4 solo mejora precisión |
| Migración de tags existentes en logs a nombres canónicos del registry | **Omit** | Out of scope; los 61 logs taggueados en PR3 siguen válidos |
| Generación automática de secciones desde logs (LLM) | **Omit** | Over-engineering para 8 secciones cortas |
| Multi-module graduation (un tag apunta a múltiples módulos) | **Omit** | Caso raro; si aparece, el YAML puede extenderse con `modules: [...]` array en PR futuro |
| Integración con `docs/decisions/log.md` | **Omit** | Decisions son distintas de patterns; scope separado |
| Schema versioning del YAML | **Omit** | v1 implícito; si cambia schema, bumpeamos entonces |

## Design

### D1: `_graduations.yaml` schema

Ubicación: `docs/knowledge/_graduations.yaml` (prefijo `_` señala "infra, no doc legible").

```yaml
# Single source of truth for tag/pattern graduation status.
# Consumed by pattern-audit.sh (detection) and suggest-tags.sh (keyword mapping).

tags:
  glass-overlay:
    module: ui-layout-contracts.md
    section: "Contrato 1: Positioning Hierarchy"
  map:
    module: map-components.md
    section: "Layers Inventory"
  route:
    module: route-optimization.md
    section: "*"  # entire module documents this tag
  # ... (13 tags total)

patterns:
  harness-memory-separation:
    module: superpowers-skills.md
    section: "Harness as Memory"
  workflow-script-conventions:
    module: superpowers-skills.md
    section: "Workflow Script Conventions"

keyword_mappings:
  # Keyword (substring in filename slug) → canonical tag
  glass: glass-overlay
  sidebar: sidebar
  menu: menu
  # ... (40 mappings from PR3)
```

**Reglas:**
- `section: "*"` = el módulo entero documenta el tag (convención especial)
- Un tag puede estar en `tags:` o `patterns:` pero no en ambos
- `keyword_mappings` values deben existir como key en `tags:` (validator enforces)
- El archivo vive en `docs/knowledge/` para estar junto a lo que referencia

### D2: `pattern-audit.sh` refactor

Nueva lógica:
```bash
# For each ≥3 tag/pattern from consult.sh stats:
graduated=$(yq ".tags[\"$tag\"] // .patterns[\"$tag\"]" "$REGISTRY" 2>/dev/null)
if [ "$graduated" != "null" ] && [ -n "$graduated" ]; then
  continue  # graduated
fi
# else: candidate
```

**Sin dependencia de `yq`:** usar awk/grep sobre el YAML (sigue el patrón de
`consult.sh`). Grep estricto: `^  ${tag}:` en el archivo (clave YAML indentada 2 espacios).

Output enhancement:
```
⚠ pattern-audit: ungraduated ≥3 occurrences:
  • glass-overlay (6 logs)
    → graduate.sh glass-overlay --module=ui-layout-contracts.md --section="Contrato 1: Positioning Hierarchy"
```

Heurística de sugerencia: busca el tag como substring en knowledge docs (permisivo
aquí porque solo sugiere, no clasifica); propone el primer match. Si 0 matches,
output = `--module=???`.

### D3: `suggest-tags.sh` refactor

Reemplazar `declare -A KEYWORD_TAGS=(...)` inline por lectura del registry:
```bash
# Parse keyword_mappings: from YAML
declare -A KEYWORD_TAGS=()
while IFS=":" read -r kw tag; do
  kw=$(echo "$kw" | tr -d ' ')
  tag=$(echo "$tag" | tr -d ' ')
  [ -n "$kw" ] && [ -n "$tag" ] && KEYWORD_TAGS[$kw]="$tag"
done < <(awk '/^keyword_mappings:/,/^[^ ]/' "$REGISTRY" | grep -E '^  [a-z]' | sed 's/: */:/')
```

Env override: `SUGGEST_TAGS_REGISTRY=path/to/custom.yaml`.

### D4: `graduate.sh` (new)

```
Usage: graduate.sh <tag-or-pattern> --module=<file> --section=<heading> [--force] [--pattern]
```

Comportamiento:
1. Validar que `docs/knowledge/<module>` existe → else exit 2
2. Validar que `<section>` aparece como heading (`^##+ <section>`) en ese módulo,
   o `<section> == "*"` → else exit 2
3. Validar que `<tag>` tiene ≥3 ocurrencias en logs (vía `consult.sh stats`) →
   else exit 2 a menos que `--force`
4. Si ya está en YAML, output `SKIP: already graduated` → exit 1
5. Añadir entrada al YAML (bajo `tags:` o `patterns:` según `--pattern`)
6. Exit 0

Sigue Workflow Script Conventions (idempotent, --force, env-overrideable,
exit codes 0/1/2).

### D5: `test-graduations-validator.sh` (new)

Valida el registry contra la realidad:
- Para cada entrada en `tags:` y `patterns:`:
  - `module: X` existe en `docs/knowledge/`
  - `section: Y` aparece como heading en X (o `Y == "*"`)
- Para cada `keyword_mappings[kw] = tag`:
  - `tag` existe como key en `tags:`

Ejecutado en Wave 4 (verification) y como test regular. Exit 1 si hay drift.

### D6: Curación — 13 entradas

Grupo 1 (5 pointer-only, sección existe):

| Tag | Module | Section |
|-----|--------|---------|
| glass-overlay | ui-layout-contracts.md | Contrato 1: Positioning Hierarchy |
| map | map-components.md | Layers Inventory |
| route | route-optimization.md | * |
| widget | widget-system.md | * |
| dashboard | ui-frontend.md | Registry-Driven Dashboard |

Grupo 2 (8 con sección nueva, ~5-10 líneas c/u):

| Tag | Module | Section nueva |
|-----|--------|--------------|
| workflow | superpowers-skills.md | Workflow Phases Overview |
| menu | ui-frontend.md | Navigation Menu |
| stop | domain-model.md | Stops and Delivery Points |
| sidebar | ui-frontend.md | Sidebar System |
| hook | superpowers-skills.md | Workflow Hooks |
| filter | api-surface.md | List Filters |
| memory | superpowers-skills.md | Harness as Memory |
| harness | superpowers-skills.md | Harness as Memory (shared with `memory`) |

Plus pattern:

| Pattern | Module | Section |
|---------|--------|---------|
| harness-memory-separation | superpowers-skills.md | Harness as Memory (shared) |

**Contenido de secciones nuevas:** definición breve + 1-2 pointers a implementación
real (archivo/script) + 1 línea a logs representativos. No exhaustivas.

## Aprobación

Usuario aprobó vía brainstorm 2026-04-21:
- Q1 Scope: **C** (full — #1 + #2 + curación completa de 13 tags)
- Q2 Graduation mechanism: **B** (YAML registry como single source of truth)
- Q3 Format: **A** (un solo `_graduations.yaml` con 3 secciones)
- Q4 Curation strategy: **A** (mapeo completo, 5 pointer-only + 8 secciones nuevas)

Anti-drift: 4 controles (YAML única fuente + `graduate.sh` atómico + pattern-audit
output con comando sugerido + validator bidireccional).
