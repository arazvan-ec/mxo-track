#!/usr/bin/env bash
# Brainstorm phase validator (HARD gate — hardened 2026-04-07)
# Checks: user_turns >= 1, alternatives_proposed, user_approved, spec >= 500B
# Exit 0 = pass, Exit 2 = block (critical checks), Exit 1 = warn (soft checks)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

USER_TURNS=$(jq -r '.evidence.user_turns // 0' "$STATE_FILE" 2>/dev/null || echo "0")
ALTERNATIVES=$(jq -r '.evidence.alternatives_proposed // false' "$STATE_FILE" 2>/dev/null || echo "false")
USER_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")
SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
CURRENT_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

ERRORS=""
WARNINGS=""

if [ "$USER_TURNS" -lt 1 ]; then
  ERRORS="${ERRORS}- Brainstorming requiere al menos 1 turno de dialogo con el usuario (actual: $USER_TURNS)\n"
fi

# Soft warning for < 3 turns (relaxed from HARD >= 3 per harness evolution 2026-03-24)
if [ "$USER_TURNS" -ge 1 ] && [ "$USER_TURNS" -lt 3 ]; then
  WARNINGS="${WARNINGS}- SOFT: Brainstorming con $USER_TURNS turno(s). Considera mas dialogo si el scope es complejo.\n"
fi

if [ "$ALTERNATIVES" != "true" ]; then
  ERRORS="${ERRORS}- No se han propuesto alternativas de diseno\n"
fi

if [ "$USER_APPROVED" != "true" ]; then
  ERRORS="${ERRORS}- El usuario no ha aprobado el diseno\n"
fi

# Check spec file exists and has sufficient content.
# The spec must exist as a real file before advancing out of brainstorming.
SPEC_FULL=""
if [ -n "$SPEC_PATH" ]; then
  if [ -f "$REPO/$SPEC_PATH" ]; then
    SPEC_FULL="$REPO/$SPEC_PATH"
  elif [ -f "$SPEC_PATH" ]; then
    SPEC_FULL="$SPEC_PATH"
  fi
fi

if [ -z "$SPEC_FULL" ] || [ ! -f "$SPEC_FULL" ]; then
  ERRORS="${ERRORS}- No existe spec document (spec_path: $SPEC_PATH). Escribe el spec antes de avanzar.\n"
else
  SIZE=$(wc -c < "$SPEC_FULL")
  if [ "$SIZE" -lt 500 ]; then
    ERRORS="${ERRORS}- Spec demasiado pequeno ($SIZE bytes, minimo 500)\n"
  fi
  if ! grep -qiE '(Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion)' "$SPEC_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- Spec no contiene keywords de brainstorming (Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion)\n"
  fi

  # Anti-Omission Gate: Every spec must inventory existing functionality
  if ! grep -qE '## Existing Functionality Inventory' "$SPEC_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- ANTI-OMISION: Falta seccion '## Existing Functionality Inventory' (si no hay funcionalidad existente afectada, declarar 'No existing functionality affected')\n"
  fi
  if ! grep -qE '## Omission Decisions' "$SPEC_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- ANTI-OMISION: Falta seccion '## Omission Decisions' (si no hay omisiones, declarar 'No omissions — all inventory items addressed')\n"
  fi

  # Layer H — Prior Art Audit gate (HARD when critical paths referenced)
  # If the spec references critical domain contexts or admin API controllers,
  # require a `## Prior Art Audit` section with at least one classified row.
  # Critical-context paths come from docs/knowledge/_ddd-boundaries.yaml via
  # the shared lib (single source of truth, shared with Layer F).
  # shellcheck source=../lib/ddd-boundaries.sh
  source "$REPO/.claude/hooks/lib/ddd-boundaries.sh"
  CRITICAL_REGEX=$(ddd_critical_regex)

  if grep -qE "($CRITICAL_REGEX)" "$SPEC_FULL" 2>/dev/null; then
    if ! grep -qE '^## Prior Art Audit' "$SPEC_FULL" 2>/dev/null; then
      ERRORS="${ERRORS}- H: spec referencia contextos criticos pero falta seccion '## Prior Art Audit'. Clasifica cada path existente como endorsed (✅), tech-debt (❌ tech-debt), o nuevo (new). Critical contexts source: docs/knowledge/_ddd-boundaries.yaml.\n"
    else
      # Extract Prior Art Audit section (from its header to the next top-level ## header)
      # and require at least one row classified in the 'Endorsed?' column.
      if ! awk '/^## Prior Art Audit/{flag=1; next} /^## /{flag=0} flag' "$SPEC_FULL" 2>/dev/null | grep -qE '(✅|❌ tech-debt|\| new \|)'; then
        ERRORS="${ERRORS}- H: Seccion '## Prior Art Audit' existe pero no contiene filas clasificadas. Cada path debe tener columna 'Endorsed?' con ✅, ❌ tech-debt, o new.\n"
      fi
    fi
  fi

  # Layer J — REMOVED 2026-04-26.
  # Original intent: warn (soft) when spec mentioned a pattern name not in
  # _graduations.yaml. Removed because: (1) brainstorm-exit was the wrong
  # phase — registry consultation should inform design, not flag it after;
  # (2) the heuristic that extracted backticked tokens on lines mentioning
  # "pattern" produced too many false positives (file paths, controller
  # names, anti-pattern targets); (3) no execution log ever showed J
  # catching a real issue. pattern-audit.sh provides post-hoc surfacing
  # with cleaner data. Analysis: /tmp/layer-j-analysis.md (2026-04-26).

  # Layer C — Architectural Adversarial Review (HARD when critical paths referenced)
  # Relocated 2026-04-24 from a standalone post-verification phase to a
  # sub-invocation here. Questions live in the spec's
  # `## Architectural Adversarial Review` section instead of JSON evidence.
  # The discrete validator is preserved (testable in isolation, reusable).
  if grep -qE "($CRITICAL_REGEX)" "$SPEC_FULL" 2>/dev/null; then
    SOCRATIC_VALIDATOR="$REPO/.claude/hooks/validators/socratic-review-validator.sh"
    if [ -x "$SOCRATIC_VALIDATOR" ]; then
      SOCRATIC_OUT=$("$SOCRATIC_VALIDATOR" "$SPEC_FULL" 2>&1 || true)
      SOCRATIC_EXIT=$("$SOCRATIC_VALIDATOR" "$SPEC_FULL" >/dev/null 2>&1 && echo 0 || echo $?)
      if [ "$SOCRATIC_EXIT" != "0" ]; then
        # Append the validator's own error lines (skip the "BLOCKED: ..." header)
        SOCRATIC_ERRS=$(echo "$SOCRATIC_OUT" | grep -E '^- ' || true)
        if [ -n "$SOCRATIC_ERRS" ]; then
          ERRORS="${ERRORS}${SOCRATIC_ERRS}\n"
        else
          ERRORS="${ERRORS}- C: Architectural Adversarial Review fallo (ver socratic-review-validator).\n"
        fi
      fi
    fi
  fi

  # Layer K — Anti-Reduction gate (HARD when reduction markers present)
  # Detects when a spec uses reduction language (MVP, "minimum viable", v0,
  # "versión reducida", etc.) outside fenced code blocks. Such specs must
  # include a `## Maximal Version Considered` section that documents:
  #   - the maximal version evaluated,
  #   - the concrete 4-test failure that ruled it out,
  #   - the proposed (reduced) version,
  #   - an "Independent superiority" bullet that defends the reduced version
  #     on grounds OTHER than cost (i.e., contains at least one design-quality
  #     keyword: patrón/pattern, garantiz/ensure, drift, consistency, boundary,
  #     correctness, alignment, etc.).
  # Origin: 2026-04-28 retrospective on Ubiquitous Language System reduction.
  # Strip fenced code blocks before scanning to avoid matching marker tokens
  # that appear inside ```...``` (e.g., this very validator's documentation).
  SPEC_BODY=$(awk '/^```/{f=!f; next} !f' "$SPEC_FULL")
  if echo "$SPEC_BODY" | grep -qiE '(\<MVP\>|mínimo viable|minimum viable|\<v0\>|\<ligero\>|\<ligera\>|lightweight|versión reducida|reduced version|arrancar vacío|start empty|scope[- ]down)'; then
    if ! grep -qE '^## Maximal Version Considered' "$SPEC_FULL" 2>/dev/null; then
      ERRORS="${ERRORS}- K: spec contiene marcadores de reducción (MVP/v0/ligero/etc.) pero falta seccion '## Maximal Version Considered'. Documenta la version maximal evaluada, el fallo concreto del 4-test que la descarto, la version propuesta, y un bullet 'Independent superiority' con argumento independiente del coste.\n"
    else
      # Capture the FULL "Independent superiority" bullet, including any
      # continuation lines (indented text), until the next bullet, the next
      # `## ` heading, or end of file. Single-line extraction misses positive
      # signals that fall on continuation lines.
      K_SUP_BLOCK=$(awk '
        /^## Maximal Version Considered/ { in_section=1; next }
        in_section && /^## / { in_section=0 }
        in_section && /^[[:space:]]*[-*][[:space:]].*([Ii]ndependent [Ss]uperiority|[Ss]uperioridad independiente)/ {
          capturing=1; block=$0; next
        }
        capturing && /^[[:space:]]*[-*][[:space:]]/ { capturing=0 }
        capturing && /^## / { capturing=0 }
        capturing { block = block " " $0 }
        END { print block }
      ' "$SPEC_FULL")
      if [ -z "$K_SUP_BLOCK" ]; then
        ERRORS="${ERRORS}- K: Seccion 'Maximal Version Considered' presente pero falta bullet 'Independent superiority' (o 'superioridad independiente'). Anade un bullet que defienda la version propuesta en terminos no economicos.\n"
      else
        # Verify the bullet contains at least one design-quality keyword
        # (positive-signal closed list). Cost-only language fails.
        K_SUP_LOWER=$(echo "$K_SUP_BLOCK" | tr '[:upper:]' '[:lower:]')
        if ! echo "$K_SUP_LOWER" | grep -qE '(patrón|patron|pattern|garantiz|ensure|document|verifica|verified|drift|consist|boundary|principle|principio|prevent|prevenir|alineación|alineacion|align|correctitud|correctness|semantic|invariante|invariant|atomic|decoupl|encapsul|integridad|mantenib|maintain|robust|safety|safe|reliab|fiab)'; then
          ERRORS="${ERRORS}- K: Bullet 'Independent superiority' apela solo a coste/tamano. Argumenta superioridad independiente del coste (calidad, correctitud, prevencion de un fallo concreto, alineacion con un patron existente).\n"
        fi
      fi
    fi
  fi

  # TDD task isolation: plan must not have standalone "add tests" tasks
  PLAN_PATH_VAL=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
  PLAN_FULL=""
  if [ -n "$PLAN_PATH_VAL" ]; then
    if [ -f "$REPO/$PLAN_PATH_VAL" ]; then
      PLAN_FULL="$REPO/$PLAN_PATH_VAL"
    elif [ -f "$PLAN_PATH_VAL" ]; then
      PLAN_FULL="$PLAN_PATH_VAL"
    fi
  fi
  if [ -n "$PLAN_FULL" ] && [ -f "$PLAN_FULL" ]; then
    if grep -iEn '^[-*]\s.*(add|write|create|agregar|escribir)\s+(unit\s+)?tests?\b' "$PLAN_FULL" 2>/dev/null | grep -ivE '(TDD|test.*implement|implement.*test|red.green|failing test)' > /dev/null 2>&1; then
      WARNINGS="${WARNINGS}- TDD: El plan tiene tareas standalone de tests. Los tests deben ser parte integral de cada tarea (TDD: test first → implement → green).\n"
    fi

    # Parallel file conflict detection (HARD gate)
    # Parse [parallel] waves, extract → files: declarations, detect overlaps
    CURRENT_WAVE=""
    declare -A WAVE_FILES  # wave_name → "file1|file2|..."
    declare -A FILE_TASK   # "wave:file" → "task label"
    while IFS= read -r line; do
      # Detect wave headers: ### [parallel] Wave N...
      if echo "$line" | grep -qiE '^\s*#{1,4}\s*\[parallel\]'; then
        CURRENT_WAVE=$(echo "$line" | sed 's/^[# ]*//' | sed 's/\[parallel\]//' | xargs)
      elif echo "$line" | grep -qiE '^\s*#{1,4}\s' && [ -n "$CURRENT_WAVE" ]; then
        # New non-parallel header resets current wave
        CURRENT_WAVE=""
      fi

      # Inside a parallel wave, look for → files: or → files:
      if [ -n "$CURRENT_WAVE" ]; then
        FILES_DECL=$(echo "$line" | grep -oE '→ files?:\s*.*' | sed 's/→ files\?:\s*//' || true)
        if [ -n "$FILES_DECL" ]; then
          # Strip a single enclosing pair of parentheses so that payloads like
          #   "→ files: (a.ts, b.ts)"        → "a.ts, b.ts"         (lista parentizada)
          #   "→ files: (no file writes)"   → "no file writes"     (sin paths → filter drops all)
          # become tokenizable. Tokens are still filtered below to only keep
          # path-like ones (contain `/` OR `.` OR a known bare-name sentinel
          # such as `Makefile` / `Dockerfile`).
          STRIPPED=$(echo "$FILES_DECL" | sed -E 's/^[[:space:]]*\((.*)\)[[:space:]]*$/\1/')
          TASK_LABEL=$(echo "$line" | grep -oE '\*\*[^*]+\*\*' | head -1 | tr -d '*' || true)
          [ -z "$TASK_LABEL" ] && TASK_LABEL=$(echo "$line" | sed 's/^[-* ]*//' | cut -c1-30)
          # Split by comma or space; keep only path-like tokens.
          # A path is path-like if it contains `/`, contains `.`, or matches
          # a known bare-filename sentinel (Makefile, Dockerfile, etc.).
          for f in $(echo "$STRIPPED" | tr ',' '\n' | tr ' ' '\n' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | grep -v '^$' | grep -E '/|\.|^(Makefile|Dockerfile|Rakefile|Gemfile|Procfile|Caddyfile)$'); do
            KEY="${CURRENT_WAVE}::${f}"
            if [ -n "${FILE_TASK[$KEY]+x}" ]; then
              ERRORS="${ERRORS}- CONFLICTO PARALELO: En '$CURRENT_WAVE', tareas '${FILE_TASK[$KEY]}' y '$TASK_LABEL' ambas editan '$f'. Mover a waves secuenciales.\n"
            else
              FILE_TASK[$KEY]="$TASK_LABEL"
            fi
          done
        fi
      fi
    done < "$PLAN_FULL"
  fi
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Brainstorming incompleto:"
  echo -e "$ERRORS"
  echo "Completa el brainstorming (Skill 2) antes de continuar."
  exit 2
fi

# Soft warnings (exit 1 = warn but allow)
if [ -n "$WARNINGS" ]; then
  echo -e "$WARNINGS"
  exit 1
fi

exit 0
