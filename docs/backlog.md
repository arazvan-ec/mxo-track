# Backlog Arquitectónico

## Backlog Arquitectónico

### [2026-05-18] Tooling — Evaluación CORE (RedPlanetHQ) como capa de orquestación cross-tool

**Estado:** Pendiente — decisión "NO adoptar ahora, experimento limitado + re-evaluar en 6-9 meses"
**Problema:** CORE (https://github.com/RedPlanetHQ/core) es un "Personal AI OS" con knowledge graph temporal (Neo4j), approval gates, audit log, multi-modal access (Slack/WhatsApp/Telegram/email) y 50+ connectors vía MCP. Opera a la capa de **orquestación cross-tool**, no de coding workflow — complementario a Claude Code + CLAUDE.md (que opera a nivel single-repo). Question: ¿adoptamos? ¿ahora? ¿en qué scope?
**Análisis hecho:** Deep dive 8-item comparando memory model (KG temporal vs vector RAG), self-hosting cost, approval gates, multi-modal security, benchmark LoCoMo (88.24%), connectors aplicables a mxo-track (GitHub/Sentry/Slack/Linear cubren 80% del valor), alternativas (Cline 62k★, Aider 45k★, Continue 33k★ — pivotó a CI checks). Documentado en chat 2026-05-18 (no en archivo aún — ver decisión propuesta debajo).
**Decisión propuesta:**
  1. **NO adoptar para producción ahora.** Razones: (a) stack stateful overhead alto (Postgres + Neo4j + Redis para uso individual), (b) early-stage churn (74 releases en pocos meses sugiere interfaces inestables), (c) doble fuente de verdad potencial con `docs/backlog.md` + Linear (si lo usamos).
  2. **Experimento limitado (1-2 semanas)** cuando haya bandwidth: conectar GitHub + Sentry + Slack + 1 task recurrente (morning brief con PRs abiertos + errores nuevos). Métrica de éxito: reducción medible de tiempo de coordinación manual.
  3. **Re-evaluar en 2026-Q4 / 2027-Q1** cuando llegue a v1.x con interfaces estables.
  4. **Knowledge module nuevo** `docs/knowledge/ai-tooling-landscape.md` que capture la comparativa (Cline/Aider/Continue/CORE/Claude Code) como referencia durable — la próxima vez que aparezca un "Personal AI OS X" no rehacemos el análisis desde cero.
**Triggers de re-evaluación:**
  - Aparece use case real cross-tool (e.g., coordinación Linear ↔ GitHub ↔ Sentry se vuelve fricción frecuente)
  - CORE alcanza v1.x con changelog estable
  - Equipo crece (>1 dev) y la memoria cross-tool tiene ROI compartido
  - Alternativa equivalente con menor stack-overhead aparece
**Origin:** Investigación 2026-05-18 a petición del usuario tras lectura comparativa de articulo Anthropic + análisis de CORE. 8-item deep dive cubriendo memoria, governance, multi-modal security, benchmarks, connectors, integración, alternativas.

### [2026-05-18] Harness — extender approval-detection regex en `user-prompt-state.sh`

**Estado:** Pendiente (graduable AHORA — 4ª ocurrencia)
**Problema:** Regex de aprobación verbal en `user-prompt-state.sh:73` no matchea verbos directivos comunes en español ("avanza", "sigue", "vamos", "pasa a", "arranca", "tira"). Combinado con la regla "hook es único escritor de `user_approved`" + revert por `phase-transition-controller`, produce fricción cuando el usuario aprueba con wording natural pero el flag queda `false`.
**Decisión propuesta:** [TUNE] del gate. Extender el regex agregando: `avanza|sigue|vamos|pasa a|arranca|tira|empieza|venga|adelante con|ve con`. Adicionalmente, emitir log warning explícito en `phase-transition-controller` cuando se detecta intento de direct-write a `user_approved` desde herramienta no sancionada (ayuda al modelo a notar el error).
**Ocurrencias documentadas:** 2026-04-28 SessionStart resume reset, 2026-04-29 SessionStart resume reset, 2026-05-06 `retrospective_shown` phase-gate mismatch, 2026-05-18 "Avanza a planning" not matched.
**Trigger:** Próxima interacción dedicada (P4 implícito de esta serie). Test: `test-user-prompt-state.sh` debe cubrir los 4 casos documentados.
**Origin:** Decision log 2026-05-18 (3 entries), execution log 2026-05-18-pattern-audit-gate-drift.md follow-ups, retrospective gap C.

### [2026-05-18] Proceso — Retrospective phase debe incluir análisis explícito de "backlog candidates" antes de finalize

**Estado:** Pendiente (1ª ocurrencia formal — petición explícita del usuario tras observar el gap)
**Problema:** La retrospectiva actual cubre 3 puntos obligatorios (estimate accuracy, process gap, emergent patterns) pero **no incluye un paso explícito que conecte las mejoras surfaceadas con su captura en el backlog**. En esta interacción (2026-05-18) la retrospectiva listó 5 follow-ups como "emergent patterns" pero el modelo avanzó hacia `finalize` sin proponer entradas de backlog — el usuario tuvo que interrumpir con "antes de seguir hay que crear un backlog". La conexión retrospectiva → backlog → próxima interacción quedó implícita y se rompió.
**Decisión propuesta:**
  1. **Extender la "Retrospective visibility rule" en CLAUDE.md** con un 4º punto obligatorio: *"Backlog candidates analysis: revisar los emergent patterns y process gaps; para cada uno que cumpla ≥1 de los siguientes criterios → proponer entrada de backlog: (a) cumple threshold ≥3 ocurrencias, (b) usuario lo solicitó explícitamente, (c) supera 4-test."* Si no hay candidatos: escribir explícitamente `Backlog candidates: 0 — no surfaced improvements this interaction` (consistente con el patrón "always present, sometimes empty" de Skill 15 § Gate-drift).
  2. **Extender `retrospective-validator.sh`** para verificar al exit de fase que (a) la retrospectiva contiene la sección "Backlog candidates" Y (b) si tiene candidatos, `docs/backlog.md` ha sido modificado en el git diff de la interacción.
  3. **Heurística auto-propuesta:** el modelo, después de presentar la retrospectiva visible y recibir aprobación, debe automáticamente preguntar "¿propongo entradas de backlog para los siguientes N follow-ups identificados?" antes del `phase-advance.sh finalize`.
**Ocurrencias:** 1ª (esta interacción — la retrospectiva listó 5 follow-ups graduables que NO fueron a backlog hasta que el usuario interrumpió). Petición explícita del usuario eleva prioridad.
**Trigger:** Junto a P4 + UX item del approval — o bien dedicado si la implementación de ambos toca otros subsistemas. Test: `test-retrospective-validator.sh` debe cubrir "retrospectiva sin sección backlog candidates" y "retrospectiva con candidates pero sin edit a backlog".
**Origin:** Petición explícita del usuario 2026-05-18 ("Quiero añadir una logística a la retro para analizar si después de hacerla se pueden implementar mejoras y presentar un plan para añadirlo al backlog"). El gap se evidenció en la propia interacción donde se identificó.

### [2026-05-18] Harness — proactive gate-feedback + semantic approval interpretation (UX layer)

**Estado:** Pendiente (1ª ocurrencia formal — petición explícita del usuario)
**Problema:** El usuario solicitó explícitamente 3 mejoras conectadas a la fricción de approval detection (mensaje 2026-05-18): *"Hay que mejorar la detección de los approves, o me indicas mejor como seguir o entiendes mejor mis respuestas"*. La 1ª (detección regex) está capturada en el item anterior. Las 2 restantes son problemas de **comunicación** y **comprensión semántica** que la extensión del regex no resuelve:
  - **(a) Indicar mejor cómo seguir:** cuando un gate de approval podría bloquear, el sistema (modelo + hook) debe **proactivamente** mostrar al usuario los wordings exactos que desbloquean, ANTES de que el bloqueo ocurra. Actualmente sólo se informa tras el fallo, forzando un turno extra de fricción.
  - **(b) Entender mejor las respuestas:** depender exclusivamente de regex es frágil — cualquier verbo o frase no contemplada produce false negative. Alternativa: usar la propia capacidad del modelo (LLM) para interpretar semánticamente la intención del usuario, con regex como fast-path y fallback al modelo cuando regex no matchea Y el contexto sugiere aprobación implícita.
**Decisión propuesta:**
  - **(a)** Cuando `evidence.user_approved=false` y la fase actual está cerca de un gate que lo requiere (brainstorming exit, retrospective exit), el `UserPromptSubmit` hook debe emitir línea informativa: "✋ Para avanzar, di una de: apruebo / ok / procede / adelante (o similar). Tu último mensaje no se interpretó como aprobación." — ANTES de que el usuario intente avanzar.
  - **(b)** Investigar feasibility de "semantic approval probe": al detectar prompt sin match de regex pero en contexto pre-gate, el modelo emite una self-question (vía mensaje al user o vía structured output) "¿este prompt es una aprobación de lo presentado?" y setea el flag basado en la respuesta. Más caro pero robusto. Decisión adoptar/descartar se toma cuando se implemente el item (a).
**Ocurrencias:** 1ª (esta interacción). Tracking para 3+ ocurrencias antes de exigir implementación, PERO la petición explícita del usuario eleva la prioridad sobre threshold-based graduation.
**Trigger:** Junto a P4 (regex extension) — mismo subsistema, misma sesión de implementación.
**Origin:** Mensaje del usuario 2026-05-18 ("Hay que mejorar la detección de los approves..."), no capturado completamente en el primer pase del backlog. Añadido en interacción inmediatamente posterior cuando el usuario surfaceó el gap.

### [2026-05-18] Harness — `verification-validator` aceptación inteligente de `lint_clean=skipped`

**Estado:** Pendiente (graduable AHORA — 5ª ocurrencia)
**Problema:** `verification-validator.sh` rechaza `lint_clean=skipped` en flow=full/debug, asumiendo que `shellcheck` está siempre instalado. En el sandbox de Claude Code on the web `shellcheck` no está disponible. El bypass `SKIP_PHASE_EXIT_GATE=1` se usa repetidamente con decision-log entry para esta razón.
**Decisión propuesta:** [TUNE] del validator. Detectar dos escenarios donde `lint_clean=skipped` es honesto:
  1. `git diff --name-only` desde el commit del plan no contiene archivos `*.sh`/`*.bash` → bypass shell-lint requirement.
  2. `command -v shellcheck` no encuentra el binario → aceptar `skipped` con campo `lint_skip_reason=shellcheck_missing`, propagar como ⚠ (no ✅) hasta pre-push-gate.
**Ocurrencias documentadas:** 2026-04-22 shellcheck missing (1ª), 2026-05-03 docs-only meta-spec (2ª), 2026-05-04 Hito 0.b implementation phase (3ª), 2026-05-06 Hito 0.b retrospective (4ª), 2026-05-18 esta interacción (5ª).
**Trigger:** Cuando se aborde la P4 de approval-regex (alta correlación de fricción en mismo subsistema).
**Origin:** Decision log 2026-05-18 (entry SKIP_PHASE_EXIT_GATE verification→capture), execution log 2026-05-18-pattern-audit-gate-drift.md follow-ups.

### [2026-05-18] Harness — `capture-validator` chicken-and-egg con `execution_log_path`

**Estado:** Pendiente (2ª ocurrencia explícita)
**Problema:** `capture-validator` requiere que `evidence.execution_log_path` apunte a un archivo existente en disco, pero al escribir el log por primera vez el archivo aún no existe. Workflow-engine bloquea el `Write` inicial. Workaround actual: `touch <path>` vía Bash (Bash no activa el gate capture que sólo matchea `Edit|Write`).
**Decisión propuesta:** Cambiar el workflow-engine para permitir `Write` cuando `file_path == evidence.execution_log_path && file_is_empty_or_missing`. O alternativamente, hacer que `capture-validator` cree el archivo vacío automáticamente al setear `execution_log_path` por primera vez.
**Ocurrencias documentadas:** 2026-04-22 (1ª — entrada inicial de la deuda), 2026-05-18 (2ª — esta interacción, 3 archivos `touch`-ados).
**Trigger:** Próxima vez que aparezca, alcanzando threshold ≥3. Mientras tanto, el workaround manual es la práctica documentada.
**Origin:** Decision log 2026-04-22 "Capture gate chicken-and-egg" + execution log 2026-05-18-pattern-audit-gate-drift.md.

### [2026-05-18] Harness — `sync-validator` necesita mecanismo "amend plan" en runtime

**Estado:** Pendiente (2ª ocurrencia explícita)
**Problema:** `sync-validator.sh` bloquea `verification → capture` si archivos modificados/eliminados no están en el plan original (`→ files:` declarations). Cuando durante implementación se descubren orphans, se cambian convenciones de path (ej. `tests/hooks/fixtures/` → fixture inline en `.claude/hooks/test-*.sh`), o se modifica un test existente para isolation de regresión — el plan estaría desactualizado en runtime. Reabrir brainstorming/planning para amend es fricción inaceptable.
**Decisión propuesta:** Permitir `amend-plan.sh <task-id> <new-file>` que agrega `→ files:` entries al plan sin reabrir fases anteriores. Validar que el amend ocurre en fase `implementation` o `verification` y se commitea junto al cambio. Alternativa: extender plan-progress.sh para auto-detectar drift y proponer amend interactivo.
**Ocurrencias documentadas:** 2026-05-04 Hito 0.b orphans descubiertos en wave Plus (1ª), 2026-05-18 esta interacción path-change + test isolation fix (2ª).
**Trigger:** 3ª ocurrencia (alcanza threshold) o cuando se aborde la suite de mejoras al sync-validator.
**Origin:** Decision log 2026-05-04 SKIP_SYNC_GATE bypass + 2026-05-18 SKIP_PHASE_EXIT_GATE bypass (mismo gate también disparó sync indirectamente).

### [2026-05-18] Proceso — Prior Art Audit debe inspeccionar control flow, no solo API superficial

**Estado:** Pendiente (1ª ocurrencia formal)
**Problema:** En P2 (`pattern-audit.sh` gate-drift), el Prior Art Audit del spec marcó `pattern-audit.sh:1-32` como "✅ Endorsed" pero no flagged el early-exit en línea 32 que short-circuited las detecciones nuevas. El short-circuit solo se descubrió durante implementación, requiriendo refactor en runtime.
**Decisión propuesta:** Actualizar `brainstorm-validator.sh` Layer H (Prior Art Audit) para sugerir explícitamente en su feedback al modelo: "cuando extiendas un script existente, inspecciona también: (a) early-exits, (b) sentinels/short-circuits, (c) shared state mutations, no solo el API público". O alternativamente, agregar columna "Control flow notes" al template de Prior Art Audit.
**Ocurrencias:** 1ª (P2 esta interacción). Tracking para 3+ ocurrencias antes de graduar a fix estructural.
**Trigger:** 3ª ocurrencia documentada de "Prior Art Audit perdió detalle de control flow" — mientras tanto, queda como práctica recordatoria.
**Origin:** Retrospective gap A de 2026-05-18 execution log de P2.

### [2026-03-11] Providers configurables: Proxy + Factory vs alternativas

**Estado:** Pendiente de implementación
**Decisión:** Transparent Proxy + Provider Factory + CustomerIntegration entity
**Spec:** `docs/superpowers/specs/2026-03-11-user-configurable-providers-design.md`
**Plan:** `docs/superpowers/plans/2026-03-11-user-configurable-providers.md`
**Trigger para revisitar:** Si boilerplate de proxies > 6 servicios, considerar codegen o proxy genérico.

### [2026-03-11] GpsDeviceProviderInterface: Métodos Traccar-específicos

**Estado:** Pendiente
**Decisión:** Stubs en WebhookGpsProvider (login→no-op, getSessionCookie→null)
**Trigger:** Al implementar tercer provider GPS, refactoring obligatorio.

### [2026-03-11] Mercure listeners usan HubInterface directamente

**Estado:** Pendiente
**Decisión:** Deuda técnica documentada. Refactorizar antes de configurar tenant con HttpPolling.

### [2026-03-11] Sin encriptación de credenciales en CustomerIntegration

**Estado:** Pendiente
**Trigger:** Antes de producción con customers configurando API keys de terceros.

### [2026-03-15] Selección de estrategia de optimización

**Estado:** Pendiente
**Contexto:** Actualmente la estrategia se selecciona por provider configuration (CustomerIntegration). Sin visibilidad para admin ni comparación.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 1`
**Trigger:** Cuando se diseñe el flujo UI de creación de rutas (GAP-3.1).

### [2026-03-15] Política de re-optimización automática vs manual

**Estado:** Pendiente
**Contexto:** RouteOptimizationService puede re-optimizar paradas PENDING, pero no hay política definida de cuándo hacerlo automáticamente.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 2`
**Trigger:** Cuando se defina la política de negocio de re-optimización.

### [2026-03-15] Datos históricos para alimentar planificación futura

**Estado:** Pendiente
**Contexto:** Existen AddressRisk, DriverFeedback, RouteComparison, PostRouteAnalyzer — potencialmente útiles para mejorar planificación.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 3`
**Trigger:** Cuando se diseñe el módulo de aprendizaje/mejora continua.
