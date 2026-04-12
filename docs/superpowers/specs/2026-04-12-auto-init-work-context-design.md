# Spec — Auto-init work_context.description

**Fecha:** 2026-04-12
**Tipo:** Enhancement — hooks de infraestructura
**Branch:** `claude/status-message-problem-id-gd83L`

## Problema

`work_context.description` es puramente informativo — Claude debe recordar escribirlo
al clasificar. Si lo olvida, el status line no muestra contexto. La mejora: auto-copiar
`interaction_classification` a `work_context.description` cuando este último está vacío.

## Diseño

En `user-prompt-state.sh`, después de leer las variables de `work_context` y antes de
la sección "No flow declared", agregar:

```bash
if [ -n "$INTERACTION_CLASS" ] && [ -z "$WC_DESCRIPTION" ]; then
  jq '.evidence.work_context.description = .interaction_classification' "$STATE_FILE" > /tmp/wc.json && mv /tmp/wc.json "$STATE_FILE"
  WC_DESCRIPTION="$INTERACTION_CLASS"
  WC_DESC_SHORT="$WC_DESCRIPTION"
  [ "${#WC_DESC_SHORT}" -gt 40 ] && WC_DESC_SHORT="${WC_DESC_SHORT:0:37}..."
fi
```

## Existing Functionality Inventory

| Elemento | Decisión |
|----------|----------|
| Auto-increment user_turns (L53-56) | Include — mismo patrón |
| work_context variables (L86-100) | Transform — agregar lectura de interaction_classification |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| README.md | Omit | No cambia schema, solo auto-population |
| session-state.json | Omit | No cambia estructura |
| Validators | Omit | work_context es informativo |
