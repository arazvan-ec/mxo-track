# Plan: Consolidar PostToolUse hooks

**Spec:** `docs/superpowers/specs/2026-04-08-consolidate-posttooluse-hooks-design.md`

## Wave 1: Crear script consolidado

### Tarea 1: Crear `post-tool-handler.sh`
- Crear `.claude/hooks/post-tool-handler.sh`
- Integrar: auto-evidence logic + plan-persistence logic + workflow-status-line logic
- Orden: evidence → persistence → status-line
- Test: ejecutar manualmente con JSON simulado, verificar que detecta evidencia y genera status

## Wave 2: Actualizar configuración

### Tarea 2: Actualizar `settings.json`
- Reemplazar 3 PostToolUse entries (auto-evidence, workflow-status-line, plan-persistence) por 1
- Mantener post-bash-validator.sh separado
- Test: verificar JSON válido con jq

## Wave 3: Verificación

### Tarea 3: Test de integración
- Verificar que hooks siguen funcionando (Read → evidence detection, Edit → status line)
- Limpiar scripts obsoletos (mover a .bak o eliminar)
