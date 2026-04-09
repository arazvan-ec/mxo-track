# Execution Log — 2026-04-09 — Enforce Valid Flow Types + DRY Protected Path Lists

**Type:** code change (hooks infrastructure)
**Branch:** `claude/improve-views-claude-md-NiE60`

## Brainstorming

- **Root cause 1:** `flow_type = "code_change"` no matchea ningún case en `get_validators_for_flow()` → cero validators → edits libres
- **Root cause 2:** `pre-push-gate.sh` tiene `protected_patterns` array separado de `classify_file()` → desincronización
- **Elegido:** Validar flow_type + extraer classify_file a librería compartida
- **Descartado:** Agregar checks en phase-advance.sh (redundante si flow_type es válido)

## Planning

- 3 waves: extraer lib → update consumers → verify
- 3 archivos nuevos/modificados

## Implementation

- **Wave 1:** `.claude/hooks/lib/classify-file.sh` — classify_file() + is_valid_flow_type()
- **Wave 2a:** `workflow-engine.sh` — source shared lib, eliminar classify_file inline, agregar validación de flow_type entre Gate 1 (null check) y Gate 2 (deviation). Usa `elif !is_valid_flow_type` para que flow_type inválido sea bloqueado con la misma severidad que flow_type null.
- **Wave 2b:** `pre-push-gate.sh` — source shared lib, reemplazar has_protected_changes() de array hardcoded a loop con classify_file($REPO/$file)

## Verification

- classify_file desde lib: 4/4 paths correctos ✅
- is_valid_flow_type: 5 válidos (micro/light/debug/full/explore), 5 inválidos (code_change/feature/bugfix/null/"") ✅
- has_protected_changes con classify_file: templates→YES, config→YES, docs→no, CLAUDE.md→no ✅
- Frontend build: ✅

## Lessons Learned

1. **La validación de flow_type debió existir desde el inicio** — el caso "code_change" no reconocido causó el bypass completo de la interacción de badges
2. **DRY entre scripts de hook es crítico** — cuando se extiende classify_file, pre-push-gate se actualiza automáticamente sin cambio adicional
3. **`$REPO/$file` para convertir paths relativos (git diff) a absolutos (classify_file)** — patrón simple que permite compartir la misma función
