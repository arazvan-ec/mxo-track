# Spec: Consolidar PostToolUse hooks en uno solo

**Fecha:** 2026-04-08
**Tipo:** code change (hooks infrastructure)
**Branch:** `claude/add-status-line-feedback-Jc4yX`

## Problema

Cada vez que Claude usa Edit, el UI de Claude Code web muestra 3-4 entradas de hook:
- 1 PreToolUse (workflow-engine.sh) — necesario, se mantiene separado
- 3 PostToolUse (auto-evidence.sh, workflow-status-line.sh, plan-persistence)

Esto genera ~100 notificaciones por sesión sin información útil para el usuario.

## Decisión

Consolidar los 3 PostToolUse hooks (auto-evidence + workflow-status-line + plan-persistence)
en un solo script `post-tool-handler.sh`. El resultado: **1 entrada PostToolUse** por
herramienta en vez de 3.

Patrón ya probado: commit `2773ab9` consolidó Bash PostToolUse de la misma forma.

## Diseño

### Script consolidado: `.claude/hooks/post-tool-handler.sh`

Orden de ejecución interno:
1. **Auto-evidence** — detectar evidencia (Read → decisions/logs, Write/Edit → spec/plan/tests)
2. **Plan persistence** — copiar plans de /root/.claude/plans/ al repo (solo Write/Edit)
3. **Workflow status line** — generar status line (solo si state cambió)

### Matcher en settings.json

```json
{
  "matcher": "Read|Write|Edit|Agent",
  "hooks": [{
    "type": "command",
    "command": ".claude/hooks/post-tool-handler.sh",
    "timeout": 5,
    "statusMessage": "📍 Registrando progreso..."
  }]
}
```

Reemplaza las 3 entradas actuales + el Bash post-validator se mantiene separado.

### Bash: mantener `post-bash-validator.sh` separado

El Bash hook hace validación de push (diferente responsabilidad). Se mantiene como está.

## Existing Functionality Inventory

| Componente | Decision | Justification |
|------------|----------|---------------|
| `auto-evidence.sh` | Transform → integrar en post-tool-handler.sh | Su lógica se mueve al nuevo script |
| `workflow-status-line.sh` | Transform → integrar en post-tool-handler.sh | Su lógica se mueve al nuevo script |
| plan-persistence inline | Transform → integrar en post-tool-handler.sh | Inline bash se mueve a función |
| `post-bash-validator.sh` | Omit | Diferente responsabilidad, ya consolidado |
| `workflow-engine.sh` (PreToolUse) | Omit | PreToolUse, separado por diseño |
| `pre-push-gate.sh` (PreToolUse) | Omit | PreToolUse, separado por diseño |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| PreToolUse hooks | Omit | Diferentes event type, no consolidables |
| post-bash-validator.sh | Omit | Ya es hook único para Bash PostToolUse |
| UserPromptSubmit hooks | Omit | Diferente event type |
| SessionStart hooks | Omit | Diferente event type |
