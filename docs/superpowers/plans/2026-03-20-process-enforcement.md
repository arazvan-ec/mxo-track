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

---

## Task 8: Self-gating — Evidencia verificable (Nivel 1)

Mejorar `full-flow-gate.sh` para verificar que los artefactos de brainstorming y planning tengan contenido real:

1. **Spec check:** El archivo en `.claude/active-spec` debe tener ≥500 bytes y contener keywords de brainstorming real (`Approach|Alternativa|Trade-off|Problema`)
2. **Plan check:** El plan del día debe tener ≥300 bytes y contener estructura mínima (`Task|Step|File|Archivo`)
3. Estos checks reemplazan la confianza ciega en `brainstorm_done: true` — el flag sigue existiendo pero el hook ya no depende solo de él

**Archivo:** `.claude/hooks/full-flow-gate.sh` — añadir después de Check 5 (spec exists) y Check 6 (plan exists)

## Task 9: Self-gating — Validación temporal anti-rush (Nivel 2)

Añadir timestamps a `session-state.json` y verificar tiempos mínimos entre pasos:

1. **Actualizar `session-start.sh`:** Añadir campos `flow_declared_at`, `learning_loop_done_at`, `brainstorm_done_at` (inicializados a `null`)
2. **Actualizar `full-flow-gate.sh`:** Verificar que `brainstorm_done_at` sea ≥120 segundos antes del momento actual. Si Claude completó el brainstorming hace menos de 2 minutos, denegar.
3. **Documentar en CLAUDE.md o instrucciones:** Claude debe escribir timestamps ISO 8601 al marcar cada fase como completada

**Archivos:**
- `.claude/hooks/session-start.sh` — añadir campos timestamp al JSON
- `.claude/hooks/full-flow-gate.sh` — añadir check temporal

## Task 10: Self-gating — Coherencia plan↔edit (Nivel 3)

Verificar que cada archivo editado esté mencionado en el plan activo:

1. **En `full-flow-gate.sh`:** Para `flow_type=full`, extraer el basename del archivo que se edita y buscarlo (case-insensitive) en el plan del día
2. **Si no está en el plan:** Denegar con mensaje que indique actualizar el plan primero
3. **No aplicar a flow_type micro/light/debug:** Solo full-flow requiere plan↔edit coherence

**Archivo:** `.claude/hooks/full-flow-gate.sh` — añadir como último check antes de "All checks passed"

## Task 11: Test end-to-end de self-gating

Verificar los 3 niveles de self-gating:

1. **Test Nivel 1:** Crear spec vacío (< 500 bytes) → gate deniega. Crear spec con contenido real → gate permite.
2. **Test Nivel 2:** Marcar `brainstorm_done_at` a "ahora" → gate deniega (< 120s). Marcar a hace 3 minutos → gate permite.
3. **Test Nivel 3:** Intentar editar archivo no mencionado en plan → gate deniega. Añadir archivo al plan → gate permite.
4. **Test integración:** Flujo completo desde session-start hasta edit exitoso con los 3 niveles
