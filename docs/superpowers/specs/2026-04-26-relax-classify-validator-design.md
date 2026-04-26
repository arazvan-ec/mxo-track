# Spec — Relax classify-validator for Pure-Config Edits

**Date:** 2026-04-26
**Author:** Sesión `claude/remove-edit-confirmations-f5AhV`
**Status:** Draft (pendiente aprobación)

## Problema (Approach + Trade-off)

`classify-validator.sh` (Layer A) bloquea cualquier edit en `.claude/`,
`scripts/`, `backend/src/`, etc. salvo que la clasificación sea `full` o
`debug`. Esto fuerza ceremonia completa (consult + brainstorm + plan) para
edits triviales de configuración pura — agregar una línea a `.gitignore`,
crear `settings.local.json` con 3 líneas, etc.

Observado en esta misma sesión: el usuario pidió "agregar
settings.local.json a .gitignore" y la primera Edit fue bloqueada,
forzando bypass con `SKIP_CLASSIFY_GATE=1`. Bypass como flujo normal es
una señal de que la gate sobre-aplica.

## Goal

Permitir edits sobre archivos de **configuración pura** (declarativos,
sin lógica) sin requerir reclasificación a `full`/`debug`. Mantener el
bloqueo intacto para código y configuración con efecto runtime no
trivial.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `classify-validator.sh` carve-outs (`docs/*`, `*.md`, `/tmp/*`, `session-state.json`) | **Transform** | Agregar nuevas entradas al case statement existente |
| `pre-tool-freshness.sh` (matcher `.*`, blocking warning) | **Omit** | Usuario dijo "todo lo demás sí" pero async tiene downside (warning puede llegar tarde); fuera de scope de esta interacción |
| `ddd-boundary-check.sh` (Layer F) | **Omit** | Solo ejecuta en `full`/`debug`; no afecta config-only |
| Deviation flow (criterios de wiring-only) | **Omit** | Usuario explícito: "Las desviaciones no las apruebo nunca" |
| `test-classify-validator.sh` (no existe) | **Omit** | Existe `test-enforcement-layers.sh` que cubre Layer A; agregar casos ahí |
| `test-enforcement-layers.sh` | **Transform** | Agregar 2 casos: config-only se permite, código en mismo dir se sigue bloqueando |
| `SKIP_CLASSIFY_GATE=1` bypass env var | **Include** | Mantener — última red de seguridad documentada |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Hacer `pre-tool-freshness` async | Omit | Tradeoff real (warning async puede perderse); usuario apuntó pero el costo de ~50ms/edit no justifica el riesgo |
| Auto-aprobar deviations | Omit | Usuario rechazó explícitamente |
| Cambios a brainstorm/plan/verification gates | Omit | No son la fuente de fricción reportada |

## Design

### Allowlist de configuración pura

Agregar al `case "$REL_PATH"` de `classify-validator.sh`:

```bash
# Pure-config edits: declarative, no executable logic
.gitignore|*/.gitignore) exit 0 ;;
.editorconfig|*/.editorconfig) exit 0 ;;
.gitattributes|*/.gitattributes) exit 0 ;;
.claude/settings.local.json) exit 0 ;;
```

### Criterios de qué cuenta como "config pura"

Un archivo califica si **todos** estos son ciertos:
1. Formato declarativo sin sintaxis ejecutable (no bash, no PHP, no JS).
2. No referencia hooks, paths a scripts, ni lógica de runtime.
3. No es SoT arquitectónico (`_ddd-boundaries.yaml`,
   `_graduations.yaml` quedan FUERA — encodean reglas).
4. Cambios de 1 línea típicamente no requieren brainstorming.

### Lo que sigue bloqueando

- `.claude/settings.json` (define hooks → afecta runtime del workflow).
- `composer.json`, `package.json` (dependencias → afecta build/deploy).
- `docs/knowledge/_*.yaml` (SoT arquitectónico).
- Cualquier `.sh`, `.php`, `.ts`, `.tsx`, `.py`.
- Configs de framework (`backend/config/**`, `frontend/vite.config.ts`).

## Prior Art Audit

| Archivo / Patrón | Endorsed (✅) / Tech-debt (❌) / New | Justificación |
|---|---|---|
| `.claude/hooks/validators/classify-validator.sh` carve-outs | ✅ Endorsed | El validator ya tiene 4 carve-outs (docs, md, tmp, session-state). El patrón es agregar entradas al `case` — no introduce nueva abstracción |
| Bypass por env var (`SKIP_CLASSIFY_GATE=1`) | ✅ Endorsed | Mecanismo documentado en CLAUDE.md "Bypass env vars" |
| Test pattern en `test-enforcement-layers.sh` | ✅ Endorsed | Convención existente para Layers A/C/F |

No hay tech-debt relevante que el cambio replique. No hay abstracción nueva.

## Architectural Adversarial Review

1. **Q:** ¿La carve-out abre puerta a "edits trampa" donde alguien mete lógica en un archivo declarativo? **A:** Los archivos en el allowlist (`.gitignore`, `.editorconfig`,
`.gitattributes`, `.claude/settings.local.json`) tienen formatos sin
sintaxis ejecutable. No se puede meter PHP/bash/JS en `.gitignore`. Para
`settings.local.json`, el archivo es local-only (gitignored), no afecta
a otros desarrolladores ni a CI. El blast radius está acotado por
diseño.

2. **Q:** ¿Por qué no usar el deviation flow existente en vez de agregar carve-outs? **A:** El usuario explícitamente NO aprueba deviations. Más allá de eso,
deviation requiere aprobación verbal cada vez (criterio: "Wait for
explicit user confirmation"). Para edits triviales recurrentes
(.gitignore, settings personales), la fricción de pedir aprobación
verbal es desproporcionada al riesgo (zero design decisions, zero
runtime effect). Carve-out estática es la herramienta correcta cuando
el patrón se repite.

3. **Q:** ¿Qué pasa si se agregan más carve-outs sin disciplina y la gate se vuelve permisiva (boundary, pattern, architecture)? **A:** Cada nueva entrada al allowlist debe pasar el 4-test (forced
practice, right phase, token cost, source backed). El criterio de
admisión está documentado en este spec ("Criterios de qué cuenta como
config pura"). Si en el futuro alguien quiere agregar `package.json` al
allowlist, la 4ª regla ("cambios de 1 línea típicamente no requieren
brainstorming") falla — package.json afecta build, requiere review.
Auto-corrige.

4. **Q:** ¿Toca el cambio algún critical context (DDD, arquitectura, boundary)? **A:** No. Modifica `.claude/hooks/validators/classify-validator.sh` y
`.claude/hooks/test-enforcement-layers.sh`. Ningún archivo en
`backend/src/Domain/Route/**` o `backend/src/Domain/Shipment/**` es
tocado. Layer F (DDD boundary) no se activa.

5. **Q:** ¿Cuál es el costo de NO hacer este cambio (tradeoff)? **A:** Cada edit de config pura sigue forzando bypass con
`SKIP_CLASSIFY_GATE=1` o reclasificación a `full` (que arrastra
consult+brainstorm+plan ceremony). Documentado en logs: esta sesión usó
bypass en la primera Edit. Si el bypass se vuelve hábito, debilita el
significado del bypass (que debería ser "última red de seguridad").

## Files Touched

- `.claude/hooks/validators/classify-validator.sh` — agregar 4 carve-outs
- `.claude/hooks/test-enforcement-layers.sh` — agregar 2 casos
- `CLAUDE.md` — actualizar tabla "Enforcement gates" para reflejar el allowlist
- `.claude/README.md` — actualizar referencia a Layer A si menciona el carve-out set

## Estimate

- Líneas: ~25 (4 lines en validator + 15 lines de test + 5-6 doc)
- Archivos: 3-4
- Wall time: ~10 min (low complexity, cobertura de test existente)

## Validation Plan

1. Suite completa: `bash .claude/hooks/test-enforcement-layers.sh` debe seguir verde.
2. Smoke test manual:
   - Edit `.gitignore` sin clasificación → permitido
   - Edit `backend/src/Foo.php` sin clasificación → bloqueado
   - Edit `.claude/settings.json` sin clasificación → bloqueado
   - Edit `.claude/settings.local.json` sin clasificación → permitido
3. Lint: `make lint-shell` verde.
