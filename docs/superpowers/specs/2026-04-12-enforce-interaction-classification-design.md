# Spec — Enforce interaction_classification via warning

**Fecha:** 2026-04-12
**Tipo:** Enhancement — hooks de infraestructura
**Branch:** `claude/status-message-problem-id-gd83L`

## Problema

`interaction_classification` no tiene enforcement. Si Claude olvida setearlo,
`work_context.description` queda vacío y el status line pierde contexto.
La cadena completa es: `interaction_classification` → auto-init → `work_context.description` → status line.
Si el primer eslabón falla, todo falla.

## Diseño aprobado

Warning visible en el status line cuando `flow_type` está seteado pero
`interaction_classification` está vacío. Se muestra en cada `UserPromptSubmit`,
así que el modelo lo ve y se auto-corrige.

Punto de inserción: después de leer `INTERACTION_CLASS` y antes de cualquier
sección de flujo, emitir warning si la condición se cumple.

```
⚠ Falta interaction_classification — setear antes de continuar
```

## Existing Functionality Inventory

| Elemento | Decisión |
|----------|----------|
| `user-prompt-state.sh` L88 (lee INTERACTION_CLASS) | Include |
| `user-prompt-state.sh` L102+ (sección "No flow declared") | Include — warning va justo antes |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Validators/gates | Omit | No bloquea, solo advierte |
| phase-advance.sh | Omit | No valida interaction_classification |
| README.md schema | Omit | Campo ya documentado, solo cambia enforcement |
