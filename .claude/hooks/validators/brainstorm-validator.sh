#!/usr/bin/env bash
# Brainstorm phase validator (HARD gate — hardened 2026-04-07)
# Checks: user_turns >= 1, alternatives_proposed, user_approved, spec >= 500B
# Exit 0 = pass, Exit 2 = block (critical checks), Exit 1 = warn (soft checks)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="${REPO:-/home/user/mxo-track}"

USER_TURNS=$(jq -r '.evidence.user_turns // 0' "$STATE_FILE" 2>/dev/null || echo "0")
ALTERNATIVES=$(jq -r '.evidence.alternatives_proposed // false' "$STATE_FILE" 2>/dev/null || echo "false")
USER_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")
SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
CURRENT_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

# git-probe fallback (d, 2026-04-30): when alternatives_proposed=false but
# the spec at SPEC_PATH is tracked-clean AND contains required sections,
# treat alternatives as effectively proposed. user_approved is DELIBERATELY
# excluded from the probe — verbal human approval remains mandatory per
# spec § Alternatives Rejected D.
if [ "$ALTERNATIVES" != "true" ] && [ -n "$SPEC_PATH" ]; then
  PROBE_LIB="$REPO/.claude/hooks/lib/git-probe.sh"
  if [ -f "$PROBE_LIB" ]; then
    # shellcheck disable=SC1090
    source "$PROBE_LIB"
    if is_path_committed_clean "$REPO" "$SPEC_PATH"; then
      SPEC_FULL_PROBE="$REPO/$SPEC_PATH"
      if grep -q -E "^## Alternatives Rejected" "$SPEC_FULL_PROBE" 2>/dev/null \
         && grep -q -E "^## Norms" "$SPEC_FULL_PROBE" 2>/dev/null \
         && grep -q -E "^## Safeguards" "$SPEC_FULL_PROBE" 2>/dev/null; then
        ALTERNATIVES="true"
      fi
    fi
  fi
fi

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

  # Layer N + S — Norms & Safeguards gates (HARD, universal)
  # Every spec must include `## Norms` (≥1 imperative-keyword line) and
  # `## Safeguards` (Risk|Mitigation table with ≥1 data row).
  # Origin: 2026-04-28 hito 1, SPDD REASONS Canvas. Migrated to
  # lib/section-validator.sh in 2026-04-28 harness consolidation.
  # shellcheck source=../lib/section-validator.sh
  source "$REPO/.claude/hooks/lib/section-validator.sh"

  if ! section_present "$SPEC_FULL" "Norms"; then
    ERRORS="${ERRORS}- N: Falta seccion '## Norms'. Declara los invariantes de negocio/arquitectura que el cambio debe preservar. Cada Norm requiere al menos un keyword imperativo (must/shall/never/always/no se permite/no debe/siempre/jamas).\n"
  else
    NORMS_BODY=$(section_body "$SPEC_FULL" "Norms")
    if ! section_satisfied_inline_or_ref "$NORMS_BODY" "Norms" imperative; then
      ERRORS="${ERRORS}- N: Seccion '## Norms' presente pero sin keyword imperativo (must/shall/never/always/no se permite/no debe/siempre/jamas). Cada Norm debe ser una afirmacion imperativa, no descriptiva.\n"
    fi
  fi

  if ! section_present "$SPEC_FULL" "Safeguards"; then
    ERRORS="${ERRORS}- S: Falta seccion '## Safeguards'. Lista los riesgos identificados con sus mitigaciones en una tabla markdown con columnas Risk y Mitigation.\n"
  else
    SAFE_BODY=$(section_body "$SPEC_FULL" "Safeguards")
    if ! section_satisfied_inline_or_ref "$SAFE_BODY" "Safeguards" risk-mitigation-table; then
      ERRORS="${ERRORS}- S: Seccion '## Safeguards' presente pero sin tabla con columnas 'Risk' y 'Mitigation'. Cada riesgo identificado debe tener mitigacion adjunta (forced pairing).\n"
    fi
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
    if ! section_present "$SPEC_FULL" "Prior Art Audit"; then
      ERRORS="${ERRORS}- H: spec referencia contextos criticos pero falta seccion '## Prior Art Audit'. Clasifica cada path existente como endorsed (✅), tech-debt (❌ tech-debt), o nuevo (new). Critical contexts source: docs/knowledge/_ddd-boundaries.yaml.\n"
    else
      H_BODY=$(section_body "$SPEC_FULL" "Prior Art Audit")
      if ! section_satisfied_inline_or_ref "$H_BODY" "Prior Art Audit" classified-rows; then
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

  # Layer K — REMOVED 2026-05-04 under Hito 0.b (4-test retrospective).
  # T1 failed: regex check verified section presence, not rigor of reasoning
  # (P3 — structure-vs-rigor); T3 failed: ~40 LOC + fenced-code-block stripping
  # + maintenance for 1 documented case which was a recursive false positive
  # on its own implementation spec. The semantic role (forcing maximal-version
  # consideration) is preserved by user approval of the spec design — when the
  # user evaluates alternatives in brainstorming and explicitly approves the
  # maximal one, regex enforcement is redundant. Spec:
  # docs/superpowers/specs/2026-05-03-harness-pruning-hito-0b-design.md.

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
    # Parse [parallel] waves, extract → files: declarations via shared lib,
    # detect file overlaps within the same wave.
    # shellcheck source=../lib/files-decl-parser.sh
    source "$REPO/.claude/hooks/lib/files-decl-parser.sh"
    CURRENT_WAVE=""
    declare -A FILE_TASK   # "wave::file" → "task label"
    while IFS= read -r line; do
      # Detect wave headers: ### [parallel] Wave N...
      if echo "$line" | grep -qiE '^\s*#{1,4}\s*\[parallel\]'; then
        CURRENT_WAVE=$(echo "$line" | sed 's/^[# ]*//' | sed 's/\[parallel\]//' | xargs)
      elif echo "$line" | grep -qiE '^\s*#{1,4}\s' && [ -n "$CURRENT_WAVE" ]; then
        CURRENT_WAVE=""
      fi

      if [ -n "$CURRENT_WAVE" ]; then
        FILES_DECL=$(echo "$line" | grep -oE '→ files?:\s*.*' | sed 's/→ files\?:\s*//' || true)
        if [ -n "$FILES_DECL" ]; then
          STRIPPED=$(echo "$FILES_DECL" | sed -E 's/^[[:space:]]*\((.*)\)[[:space:]]*$/\1/')
          TASK_LABEL=$(echo "$line" | grep -oE '\*\*[^*]+\*\*' | head -1 | tr -d '*' || true)
          [ -z "$TASK_LABEL" ] && TASK_LABEL=$(echo "$line" | sed 's/^[-* ]*//' | cut -c1-30)
          while IFS= read -r f; do
            [ -z "$f" ] && continue
            KEY="${CURRENT_WAVE}::${f}"
            if [ -n "${FILE_TASK[$KEY]+x}" ]; then
              ERRORS="${ERRORS}- CONFLICTO PARALELO: En '$CURRENT_WAVE', tareas '${FILE_TASK[$KEY]}' y '$TASK_LABEL' ambas editan '$f'. Mover a waves secuenciales.\n"
            else
              FILE_TASK[$KEY]="$TASK_LABEL"
            fi
          done < <(tokenize_files_payload "$STRIPPED")
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
