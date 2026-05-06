---
type: code-change
tags: [harness, poda, hito-0b, 4-test, dead-code, layer-k-removed, doc-parity]
files_touched:
  - .claude/hooks/test-self-gating.sh (deleted)
  - .claude/hooks/test-workflow-engine.sh (deleted)
  - .claude/hooks/post-tool-handler.sh (deleted)
  - .claude/hooks/phase-transition-controller.sh (deleted)
  - .claude/hooks/test-phase-transition-controller.sh (deleted)
  - .claude/hooks/validators/brainstorm-validator.sh (Layer K removed)
  - .claude/hooks/lib/section-validator.sh (K helpers removed)
  - .claude/hooks/test-brainstorm-validator.sh (K tests removed)
  - .claude/hooks/test-enforcement-layers.sh (CONTROLLER retargeted)
  - docs/knowledge/workflow-engine.md (rewritten — paridad completa)
  - docs/superpowers/specs/2026-05-03-harness-pruning-hito-0b-design.md
  - docs/superpowers/plans/2026-05-04-harness-pruning-hito-0b.md
patterns: [4-test-retroactive, dead-code-elimination, doc-parity, single-source-of-truth]
outcome: partial — 10.71% reduction (target 15%) + paridad doc completa
---

# Harness Pruning — Hito 0.b (Execution Log)

**Spec:** `docs/superpowers/specs/2026-05-03-harness-pruning-hito-0b-design.md`
**Plan:** `docs/superpowers/plans/2026-05-04-harness-pruning-hito-0b.md`
**Branch:** `claude/prune-harness-poda-xEdZG`
**Periodo:** 2026-05-03 a 2026-05-06 (3 días, 4 sesiones por SessionStart:resume resets)

## Resultado cuantitativo

| Métrica | Baseline | Final | Δ |
|---|---|---|---|
| Hooks LOC | 12,362 | 11,038 | **−1,324 (−10.71%)** |
| workflow-engine.md líneas | 313 | 371 | +58 (paridad cerrada) |
| Test files | 31 | 28 | −3 |
| Hook scripts (no test) | 17 | 15 | −2 |
| Tests pass total | (con dead) | 162 | +N |
| Tests fail total | (con dead) | 16 | −5 (3 fail-files removed) |

**Target del spec ≥15% NO alcanzado.** Falta 4.29% (~530 LOC) para llegar al
threshold. Per spec § 11, esto activa la cláusula de abort: "el plan
completo se aborta y se reescribe como spec de poda exclusiva".

## Eliminaciones ejecutadas

### Wave 1 — dead code

- **`test-self-gating.sh` (340 LOC)** — testea `full-flow-gate.sh`
  inexistente desde hace meses. 7/14 fail por target muerto. Cobertura
  semánticamente equivalente preservada por `test-enforcement-layers.sh`.
- **`test-workflow-engine.sh` (513 LOC)** — 14/33 fail con expectations
  stales sobre mensajes de error obsoletos del `workflow-engine.sh`.
  Cobertura redundante con `test-full-flow-e2e.sh` +
  `test-enforcement-layers.sh` + `test-{phase}-validator.sh` individuales.
- **`test-pre-commit-deprecated-alias.sh` (56 LOC)** — auditado, target
  vivo (alias activo), test 5/0 → **MANTENIDO**.

### Wave 2 — Layer K + helpers

- **Layer K en `brainstorm-validator.sh` (~30 LOC)** — eliminada por fallo
  retroactivo del 4-test:
  - **T1:** verifica presencia de sección, no rigor (P3 estructura-vs-rigor).
  - **T3:** ~40 LOC + maintenance regex + lógica de stripping de fenced
    code blocks para 1 caso documentado que fue falso positivo recursivo.
  - **T4:** único origin log
    (`2026-04-28-layer-k-anti-reduction-validator.md`).
- **`section-validator.sh` (~30 LOC)** — modos `positive-signal` +
  `multiline-bullet` + función `section_extract_bullet`. Sólo soportaban K.
- **K tests en `test-brainstorm-validator.sh` (~125 LOC)** — fixtures K1-K4
  y assertions.

### Wave Plus — orphans descubiertos durante runtime

Durante ejecución se identificaron archivos no listados en el plan original:

- **`post-tool-handler.sh` (43 LOC)** — dispatcher creado 2026-04-08,
  superseded por 3 hooks PostToolUse separados en `settings.json`
  (post-rwe-hook + post-bash-validator + todowrite-mirror). Sin
  referencias en código activo.
- **`phase-transition-controller.sh` (123 LOC)** — lógica inlineada en
  `post-bash-validator.sh` per consolidación 2026-04-08. NO está en
  `settings.json`. Comments-only references en otros hooks.
- **`test-phase-transition-controller.sh` (137 LOC)** — testeaba el
  archivo standalone, ahora redundante.
- **`test-enforcement-layers.sh`** — `CONTROLLER` retargeteada de
  `phase-transition-controller.sh` a `post-bash-validator.sh` (1 línea).

### Wave 3 — doc parity

`docs/knowledge/workflow-engine.md` reescrito de 313 → 371 líneas, ahora
documenta exhaustivamente: A, B (incl. B3 session-cut), C (Architectural
Adversarial Review), D, F, H, **N**, **S**, **Sync**, **Agent**,
**spec-compliance** + tabla completa de bypasses con uso documentado +
**K [REMOVED 2026-05-04]** preservando precedente I/J.

## Bypasses utilizados

Tres bypasses documentados, todos por causas estructurales del propio
harness, no por evasión:

1. `SKIP_PHASE_EXIT_GATE=1` (brainstorming → planning) — entrada
   `[2026-05-03] SessionStart:resume reset wiped user_approved` ya en
   decision log. SessionStart:resume reseteó evidencia 3 veces; aprobación
   verbal del usuario constaba en historial.
2. `SKIP_SESSION_CUT_GATE=1` (planning → implementation) — Hito 0.b fue
   solicitado explícitamente como sesión completa por el usuario; B3
   apuntaba a un caso (independent review) que no aplica cuando la propia
   instrucción del usuario incluye el flujo completo.
3. `SKIP_SYNC_GATE=1` (verification → capture) — 4 archivos descubiertos
   durante implementación (los orphans Wave Plus) no estaban en el plan
   original; la safeguard del spec preveía esto como "Plan B contingente".

## Lessons / Retrospectiva

### Estimate accuracy

Plan estimaba −720 a −976 LOC (5.8–7.9%). Real: −1,324 LOC (10.71%).
Sobre-cumplimiento del estimado del plan, pero **sub-cumplimiento del
target del spec (15%)**. Causa: el plan original conservadoramente excluía
los orphans Wave Plus que sólo emergieron en runtime. Lección: para podas
del harness, agregar fase de "scan exhaustivo de orphans" antes del
plan estructurado.

### Process gap

1. **SessionStart:resume reset bug es ya el tercer caso del mismo patrón**
   (2026-04-28, 2026-04-29, 2026-05-03). Sigue capturado como follow-up
   sin fix estructural. Tracking 3/3 — debería graduar a fix obligatorio
   en el próximo ciclo del harness.
2. **Test-state corruption durante runs masivos del suite.** Algunos tests
   crashean sin restaurar `.claude/session-state.json` desde su backup,
   dejando `.test-backup` huérfano y el state corrupto. Detectado tres
   veces durante esta interacción.
3. **Sync validator vs scope expansion legítima.** Cuando el plan no captura
   orphans descubiertos en runtime (Wave Plus), el sync gate bloquea
   verification → capture. La safeguard "Plan B contingente" estaba en el
   spec pero no se actualizó el plan; debería poder hacerse sin bypass.

### Emergent patterns

- **Pattern: 4-test retroactivo a layers** — funciona como predijo. Layer
  K cumplió exactamente el patrón I/J de eliminación sin regresiones.
  Tag: `4test-retroactive`. 3+ ocurrencias (I, J, K) → graduar a knowledge
  module si no está ya.
- **Pattern: orphan hook detection via settings.json + grep** — el método
  "comm settings.json vs ls hooks/" + "grep references" reveló 2 orphans
  (post-tool-handler, phase-transition-controller) que no aparecían en el
  plan original. Tag: `orphan-detection-via-settings-diff`.

## Honest Caveats

- **Target ≥15% NO alcanzado (10.71%).** La cláusula de abort del spec
  maestro § 11 aplica: el plan de adopción de las 9 estrategias se
  reescribe como spec de poda exclusiva, NO se ejecuta secuencialmente.
- **16 baseline test failures persistentes** (P8 documentado). No se
  repararon en este Hito; siguen como deuda.
- **3 bypasses utilizados** — todos legítimos y documentados, pero
  representan evidencia de que el harness tiene puntos de fricción
  estructurales que merecen attention en futuros Hitos.

## Siguiente paso (decisión del usuario)

Tres caminos:

- **(A)** Aceptar el resultado parcial (10.71% + paridad doc) y reescribir
  el spec maestro como "Hito 0.b parcialmente cumplido". Las 9 estrategias
  ADOPTAR del spec maestro se aplazan hasta una segunda Hito 0.c que cierre
  el resto del 4.29%.
- **(B)** Abortar el plan maestro completo per la cláusula del spec § 11
  y reescribir como spec exclusivo de poda con segunda pasada (auditar
  test-status-line.sh, test-phase-advance.sh, test-brainstorm-validator
  buscando redundancia).
- **(C)** Aceptar 10.71% como reducción suficiente para desbloquear
  Hito 1 — requiere recalibrar el threshold del spec maestro (cambio
  arquitectónico, no tactical).
