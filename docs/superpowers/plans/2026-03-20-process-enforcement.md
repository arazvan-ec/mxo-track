# Plan: Enforcement Mecánico del Flujo CLAUDE.md

**Spec:** `docs/superpowers/specs/2026-03-20-process-enforcement-design.md`
**Branch:** `claude/start-session-b-0P3vq`

---

## Task 1: SessionStart hook

Crear `.claude/hooks/session-start.sh` que inicialice `.claude/session-state.json`.
Registrar en `.claude/settings.local.json` como hook de tipo SessionStart (si soportado) o como instrucción en CLAUDE.md para que Claude lo ejecute al inicio.

## Task 2: Mejorar full-flow-gate.sh

Añadir checks de session-state.json: flow_declared, learning_loop_done, brainstorm_done.
Mantener bypasses para docs/tests/config.

## Task 3: Crear post-commit-reminder.sh

Hook PostToolUse en Bash que detecte commits y recuerde crear execution log.

## Task 4: Crear preflight.sh + Makefile target

Script que valide lint, tests, manifest, execution log.
Añadir `make preflight` al Makefile.

## Task 5: Actualizar settings.local.json

Registrar todos los hooks nuevos.

## Task 6: Test end-to-end

Verificar que los hooks funcionan correctamente.

## Task 7: Execution log + decision log

Documentar la implementación.
