# Decision Log

Registro de decisiones de diseño significativas. Cada entrada captura el contexto, la decisión, las alternativas y el resultado. Con el tiempo, los patrones recurrentes enriquecen las guías en `docs/knowledge/` y `CLAUDE.md`.

**Cuándo añadir:** Decisiones no triviales — nueva abstracción, nuevo patrón, refactor de arquitectura, trade-off con implicaciones.

**Cuándo actualizar knowledge:** Si la misma lección aparece 3+ veces, actualizarla en la guía correspondiente.

---

### [2026-04-12] Extract ListFilterApplier service for dual QueryBuilder pattern

- **Problema:** 5 admin list controllers repetían la mecánica de aplicar filtros a `$qb` + `$countQb` en paralelo (~80 líneas duplicadas). Riesgo de count mismatch si se olvida aplicar filtro al countQb.
- **Decisión:** Service inyectable `ListFilterApplier` + value object `FilterDefinition` con static factories tipadas por tipo de filtro (boolean, like, enum, dateFrom, dateTo, entity). `withCountJoin()` para el caso especial de Route (re-alias en countQb).
- **Alternativas descartadas:** Trait (viola SRP, no testable independientemente), AbstractController (single inheritance limit, Symfony lo desaconseja)
- **Resultado:** 5 controllers refactorizados. ~80 líneas duplicadas → ~30 líneas netas. Cada controller declara filtros en 4-7 líneas. Nuevo tipo de filtro = nueva factory method, sin tocar controllers.

### [2026-04-08] Consolidate PostToolUse hooks via dispatcher pattern

- **Problema:** Claude Code web UI mostraba 3-4 notificaciones de hook por cada herramienta (Edit: workflow-engine PreToolUse + auto-evidence + workflow-status-line + plan-persistence PostToolUse). En sesiones con 30+ tool calls, esto generaba ~100 entradas genéricas que ocultaban el progreso real al usuario.
- **Decisión:** Dispatcher pattern — un único script `post-tool-handler.sh` que lee stdin una vez y llama secuencialmente a los sub-scripts existentes (auto-evidence → plan-persistence → workflow-status-line). Reduce las entradas PostToolUse de 3 a 1 por herramienta, sin modificar la lógica interna de cada sub-script.
- **Alternativas descartadas:** (A) Merge completo — fusionar los 3 scripts en uno solo (~750 líneas). Más eficiente pero frágil, pierde testabilidad individual. (B) Solo mejorar statusMessage — no reduce el número de entradas, impacto cosmético. (C) Eliminar hooks de Read — reduce ruido pero pierde auto-evidence detection para decisions/logs.
- **Resultado:** 50% reducción de notificaciones (Edit: 4→2, Read: 2→1). Patrón reutilizable: misma técnica que commit 2773ab9 (consolidación Bash). Los sub-scripts siguen siendo testables independientemente.

### [2026-04-07] Validator invocation: prerequisites vs completion

- **Problema:** El workflow-engine ejecutaba brainstorm-validator al escribir specs y planning-validator al escribir plans. Ambos validators verifican que el artefacto exista — pero el Write es lo que lo crea. Dependencia circular.
- **Decisión:** Separar dos contextos de invocación. **File gates** (workflow-engine) verifican **prerequisitos** — fases anteriores completadas. **Phase-advance** verifica **completitud** — el artefacto de la fase actual existe. Concretamente: spec files solo requieren `consult` validator; plan files solo requieren `brainstorm` validator. Los checks de completitud se ejecutan al avanzar de fase.
- **Alternativas descartadas:** (A) Detectar "estoy creando el artefacto" y skip el validator — frágil, depende de inferir intención. (B) Permitir Write sin validación para specs/plans — pierde la verificación de prerequisitos. (C) Split brainstorm-validator en pre-requisite y completion parts — over-engineering.
- **Resultado:** El ciclo Write-durante-brainstorming funciona. El brainstorm-validator sigue ejecutándose al avanzar de fase, manteniendo la verificación de calidad.

### [2026-04-07] Approval detection: strip system-reminder noise

- **Problema:** El regex de rejection `(no[, ]|cambia|...)` matcheaba texto dentro de `<system-reminder>` tags como "no existe spec document", revirtiendo aprobaciones legítimas del usuario.
- **Decisión:** Stripear `<system-reminder>` tags del `user_prompt` con `sed` antes de aplicar los regex de approval/rejection. El texto del sistema no es input del usuario y no debe influir en la detección de aprobación.
- **Alternativas descartadas:** (A) Hacer el rejection regex más específico — fragilidad incremental, cada nuevo system message podría romperlo. (B) Dar prioridad a approval sobre rejection — peligroso, un rechazo real se perdería. (C) Pedir al framework que separe user_prompt de system context — no controlamos el formato del hook input.
- **Resultado:** Approval detection solo analiza texto del usuario. Rejection sigue teniendo prioridad sobre approval (conservador por diseño).

### [2026-04-07] Verification "skipped" state for environments without test infra

- **Problema:** `verification-validator` hard-bloqueaba con `tests_passed=null`. En environments sin composer/npm, el modelo seteaba `tests_passed=true` sin ejecutar tests — minando el sistema de evidencia.
- **Decisión:** Nuevo valor `"skipped"` aceptado como soft warning (exit 1) en verification-validator y pre-push-gate. El warning se propaga hasta el PR reviewer: ⚠ en vez de ✅.
- **Alternativas descartadas:** (A) Auto-detectar si test tooling está disponible — complejo, frágil entre environments. (B) Skip verification validator completo — pierde lint_clean check. (C) Permitir null como pass — elimina el incentivo de ejecutar tests cuando sí están disponibles.
- **Resultado:** El modelo declara honestamente que no verificó. El gap llega visible al reviewer. El incentivo de ejecutar tests reales se mantiene intacto.

### [2026-04-07] Documentation format: philosophy+technique+flow+rules

- **Problema:** Las secciones de CLAUDE.md eran listas de reglas y comandos ("haz esto") sin explicar por qué existen, cómo funcionan internamente, ni cómo cada paso alimenta al siguiente. El modelo las seguía mecánicamente pero no internalizaba el razonamiento, lo que causaba que racionalizara saltar pasos cuando no entendía su propósito.
- **Decisión:** Adoptar un formato estándar para toda sección instructiva con 4 capas: (1) **Filosofía** — por qué este sistema y no otro, qué decisión de diseño lo justifica, (2) **Técnica** — cómo funciona el mecanismo interno, no solo el comando, (3) **Flujo causal** — qué produce cada paso (→) y qué lo consume, no solo "ejecuta en orden", (4) **Reglas integradas** — cada regla aparece junto al punto donde aplica, no en sección aparte.
- **Alternativas descartadas:** (A) Mantener listas de reglas con comentarios breves — fácil de escribir pero el modelo no internaliza el porqué. (B) Documentación filosófica separada de las instrucciones — duplica contenido y se desincroniza. (C) Solo agregar "why" al inicio de cada sección — insuficiente, el flujo causal entre pasos sigue invisible.
- **Resultado:** 10 secciones de CLAUDE.md reescritas (+289/-75 líneas). El formato se aplicó primero a Parallel-First Planning como prueba, luego se extendió a: Manifest, Session-State, Classify First, Deviation Mode, Fix Invalidation, Workflow Engine, Brainstorming, Evidence Before Claims, Closing the Cycle, Commits and Push. **Este formato debe aplicarse a toda nueva sección instructiva.**

### [2026-03-23] Completion gate via phase_history[] on pre-push

- **Problema:** Claude podía pushear código sin completar capture/finalize/retrospective. Los validators solo corrían en Edit/Write, no en push.
- **Decisión:** Approach C — pre-push gate que verifica phase_history[] + cross-valida evidencia real (archivos existen, tamaño ≥500B, campos válidos). Protected paths = todo excepto docs/ y .claude/.
- **Alternativas descartadas:** (A) SOFT→HARD en validators existentes — no cubre push. (B) PostToolUse commit validator — más complejo, menos directo.
- **Resultado:** Implementado. Gate bloquea push con HARD en verification/capture/finalize, SOFT en retrospective. Deviation mode preserva escape hatch.

### [2026-03-23] Bottom Sheet pattern for map+data views

- **Problema:** TestRoutingPage usaba sidebar para métricas y route cards, dejando poco espacio al mapa.
- **Decisión:** Reemplazar `DualMenuShell` con Bottom Sheet estilo Google Maps (3 estados: collapsed/half/full). Métricas agrupadas en "Metric Pairs" (3 pares lógicos: scope/distance/time con hero+delta). Map↔panel sincronizado bidireccionalmente.
- **Alternativas descartadas:** (A) Split pane horizontal — no resuelve el problema de espacio. (B) Floating panels — más complejo, menos familiar.
- **Resultado:** Implementado. El BottomSheet y MetricPairs son componentes reutilizables para otras vistas de mapa. MapCanvas.fitBounds extendido para soportar padding asimétrico (necesario para ajustar viewport al espacio visible sobre el sheet).

### [2026-03-20] Service Time Calibration via SQL Window Functions

- **Problema:** Necesidad de calcular tiempos de servicio históricos por dirección para calibrar el optimizador. RouteStop tiene `deliveredAt` pero no `arrivedAt`.
- **Decisión:** `ServiceTimeCalibrationService` usa DBAL Connection con SQL CTE + `LAG()` window function para calcular deltas entre `deliveredAt` consecutivos por ruta. Para la primera parada, usa `route.startAt` como referencia. Filtra outliers (>1h) y requiere mínimo de muestras.
- **Alternativas descartadas:** (A) Cargar entidades en memoria con Doctrine → no escala con miles de rutas. (B) Agregar `arrivedAt` a RouteStop → requiere GPS geofencing, over-engineering para MVP.
- **Resultado:** Query eficiente en BD, resultados precisos. Ruta de escalación: agregar `arrivedAt` en Phase 2 con geofencing.

### [2026-03-20] Delay-based reoptimization con cooldown

- **Problema:** Auto-reoptimizar cuando una ruta acumula retraso significativo.
- **Decisión:** `DelayReoptimizationSubscriber` escucha `StopDelivered`, calcula retraso como `elapsed - estimatedDuration`, dispara reoptimización si excede threshold (30min) con cooldown de 10min entre reoptimizaciones. Usa `RouteEventRepositoryInterface::findLastByTypeForRoute()`.
- **Alternativas descartadas:** (A) Comparar ETA por parada individual → requiere ETAs que no existen aún. (B) Cron periódico → latencia innecesaria, el evento de entrega es el momento natural.
- **Resultado:** Patrón consistente con `ExceptionReoptimizationSubscriber` y `SkipReoptimizationSubscriber`. Cooldown previene cascadas.

### [2026-03-17] React SPA + MapView DDD Bounded Context

- **Problema:** Frontend de 73 Twig templates con JS inline duplicado. 6 Mercure listeners dispersos con violación de D (SOLID) y publicación duplicada.
- **Decisión:** (1) React SPA en `/app/*` con MapLibre GL JS + PMTiles self-hosted, coexistiendo con Twig via catch-all controller. (2) Bounded context `MapView` con `MapEventProjector` que consolida los 6 listeners en un punto único, publicando a 3 topics unificados `/map/*` via `RealtimePublisherInterface`. (3) `MapProjectableEventInterface` como marker interface en domain events para type-safe projection.
- **Alternativas descartadas:** (A) Alpine.js event bus sin cambio de stack — no escala. (B) Mantener topics actuales y solo fix listeners — no simplifica frontend. (C) CQRS completo con event sourcing — over-engineering.
- **Resultado:** Backend: MapView domain layer + MapEventProjector + MercureMapPublisher + TopicResolver actualizado + 6 listeners refactorizados + 3 API endpoints. Frontend: React SPA funcional con Fleet Map en `/app/admin/fleet-map`. Twig adaptado a nuevos topics. Tests pasan (9 fallos pre-existentes, 0 nuevos).

<!-- Añadir nuevas entradas al final del archivo -->

### [2026-03-18] Protomaps CDN como proveedor de vector tiles

- **Problema:** MapLibre usaba raster tiles de OSM — sin dark theme, sin personalización de estilo, sin clustering nativo. PMTiles estaba registrado como dead code.
- **Decisión:** Protomaps CDN con `@protomaps/basemaps` para vector tiles. Dark flavor customizado con paleta slate-900. Esquema OpenMapTiles permite migrar a self-hosted (archivo .pmtiles en S3/R2) cambiando una sola URL.
- **Alternativas descartadas:** (A) MapTiler/Stadia — requieren API key. (B) Self-hosted Planetiler — complejidad excesiva para MVP. (C) OpenFreeMap — tooling menos maduro.
- **Resultado:** Mapa dark theme nativo, labels en español, road emphasis para logística, clustering WebGL para shipments, heatmap para excepciones. Build OK, 0 errores TypeScript.

### [2026-03-18] Markers DOM vs WebGL — decisión por tipo de dato

- **Problema:** Todos los markers eran DOM elements. Para 500+ shipments es lento.
- **Decisión:** Mantener VehicleMarker/StopMarker como DOM (necesitan SVG custom, son <50 por vista). Migrar ShipmentMarkers a clustering WebGL nativo y ExceptionMarkers a heatmap WebGL.
- **Alternativas descartadas:** Migrar todo a WebGL — perdemos SVG custom y números en markers de stops.
- **Resultado:** ShipmentClusterLayer y ExceptionHeatmapLayer implementados como Source+Layer nativos. DOM markers conservados donde aportan valor visual.

### [2026-03-19] DualMenuShell — Navegación unificada para React SPA

- **Problema:** Las 9 páginas React SPA (`/app/*`) tenían sidebars de datos (métricas, paradas, filtros) pero cero navegación de vuelta al sistema Twig. Los usuarios quedaban "atrapados" sin forma de volver al dashboard o cambiar de sección.
- **Decisión:** Componente `DualMenuShell` con dos hamburger buttons independientes: (1) navegación overlay con links al sistema Twig por rol via `useMe()`, (2) toggle para colapsar/expandir el sidebar de datos contextual de cada página. Cada página pasa su sidebar como prop. NavigationSidebar replica los links de `_sidebar_content.html.twig`.
- **Alternativas descartadas:** (A) Un solo hamburger con nav+datos combinados — pierde control independiente. (B) AppShell wrapper persistente — requiere reestructurar todas las páginas y conflicta con layouts full-screen de mapa. (C) Barra de navegación fija top — consume espacio vertical valioso en vistas de mapa.
- **Resultado:** 9 páginas migradas, build OK (0 errores TypeScript). Ambos hamburgers funcionan independientemente. Links de navegación correctos por rol (admin/customer/driver).
- **Actualización [2026-03-19]:** Feedback del usuario — el overlay de navegación cubría el data sidebar. Cambiado a inline en desktop (prop `mode: 'inline' | 'overlay'` + `useIsDesktop` hook). En mobile se preserva overlay. Layout desktop: `[Nav w-64] | [Data sidebar] | [Map flex-1]`.

### [2026-03-19] NavigationSidebar inline vs overlay — responsive approach

- **Problema:** NavigationSidebar como overlay (z-50 + backdrop) cubría el data sidebar. El usuario quiere ambos sidebars visibles side by side.
- **Decisión:** Prop `mode` en NavigationSidebar (`'overlay' | 'inline'`). `DualMenuShell` usa `useIsDesktop()` hook (matchMedia 1024px) para elegir modo. Desktop = inline, mobile = overlay.
- **Alternativas descartadas:** (A) Dos componentes separados — YAGNI, más indirección. (B) CSS-only responsive — backdrop necesita lógica JS condicional.
- **Resultado:** Build OK, 0 errores. Nav sidebar inline en desktop, overlay preservado en mobile.

### [2026-03-19] User.php SRP — decisión consciente de no refactorizar

- **Problema:** User.php mezcla 5 responsabilidades: identidad (email), autenticación (passwordHash), autorización (roles), multi-tenancy (customer relationship), y lifecycle (timestamps). Esto viola Single Responsibility Principle.
- **Decisión:** NO refactorizar. User está en contexto pragmático (Identity/Auth). El costo de separar responsabilidades (rompe Symfony Security integration, requiere Identity value object, auth adapter, tenant resolver) supera el beneficio. La clase tiene 111 líneas — es compacta y estable.
- **Alternativas descartadas:** (A) Extraer UserIdentity + UserAuth + TenantMembership — over-engineering para una clase estable de 111 líneas. (B) Migrar a DDD POPO — rompe UserInterface/PasswordAuthenticatedUserInterface de Symfony.
- **Resultado:** Documentado como deuda técnica aceptada. Trigger para revisitar: si User.php supera 500 líneas o necesita una 6ta responsabilidad.

### [2026-03-19] Provider framework codegen — trigger no alcanzado

- **Problema:** El backlog arquitectónico define trigger de codegen/proxy genérico cuando >6 proxies TenantAware existen.
- **Decisión:** No implementar codegen. Actualmente hay 5 proxies TenantAware (GpsProvider, RealtimePublisher, RouteOptimizer, RoutingEngine, SmsTransport). El trigger (>6) no se ha alcanzado.
- **Alternativas descartadas:** Implementar codegen proactivamente — YAGNI, solo hay 5 proxies y el boilerplate es manejable.
- **Resultado:** Re-evaluar cuando Fase 7 (user-configurable providers) añada proxies adicionales. Si el total supera 6, diseñar proxy genérico o codegen.

### [2026-03-20] Process enforcement — 3 puntos de control mecánicos

- **Problema:** CLAUDE.md tiene 15 reglas mandatorias de proceso. Solo 2 tenían enforcement mecánico (spec+plan via full-flow-gate.sh). Session B saltó brainstorming, learning loop, execution log y retrospectiva sin que nada lo bloqueara.
- **Decisión:** Approach C — 3 puntos de control: (1) SessionStart hook que inicializa session-state.json requiriendo clasificación de flujo, (2) full-flow-gate.sh mejorado que verifica learning_loop_done y brainstorm_done además de spec+plan, (3) `make preflight` que valida lint/tests/manifest/execution-log/session-state antes de push. PostToolUse reminder para execution logs en commits feat:/fix:.
- **Alternativas descartadas:** (A) Un hook por gap — 10+ hooks, frágil y difícil de mantener. (B) Solo preflight centralizado — no previene trabajo sin flujo en tiempo real, solo valida al final.
- **Resultado:** 5/5 preflight checks pasan. Gate bloquea correctamente en cada escenario (flow_not_declared, learning_loop_missing, brainstorm_missing). Micro/light flows bypasean spec/plan requirements. Non-src files siempre pasan.

### [2026-03-20] GPS Provider ISP split — GpsDeviceProviderInterface → 2 interfaces

- **Problema:** `GpsDeviceProviderInterface` (6 métodos) violaba ISP y LSP. `WebhookGpsProvider` y `NullGpsProvider` tenían stubs para `login()`, `getSessionCookie()`, `getDevices()`, `createDevice()` — métodos que no soportan genuinamente. El backlog [2026-03-11] lo documentaba como deuda técnica.
- **Decisión:** Split en `GpsPositionProviderInterface` (2 métodos: `getPositions()`, `isAvailable()`) y `GpsDeviceManagerInterface` (4 métodos: `login()`, `getSessionCookie()`, `getDevices()`, `createDevice()`). `TraccarGpsProvider` implementa ambas. `WebhookGpsProvider` y `NullGpsProvider` solo implementan `GpsPositionProviderInterface`. TenantAwareGpsProvider renombrado a `TenantAwareGpsPositionProvider` (solo proxya posiciones).
- **Alternativas descartadas:** (A) Default methods en interface — viola LSP igualmente, los stubs siguen ahí. (B) Adapter pattern wrapping webhook — indirección innecesaria, el problema es la interface, no la implementación.
- **Resultado:** 0 regresiones. 152 tests relacionados pasan. WebhookGpsProvider pasó de 6 métodos (4 stubs) a 2 métodos genuinos. Contract tests documentan las expectativas de cada interface.

### [2026-03-20] Mercure abstraction — HubInterface → RealtimePublisherInterface en consumers

- **Problema:** 4 archivos usaban `HubInterface` directamente en lugar de `RealtimePublisherInterface`, la abstracción que ya existía con implementaciones Mercure/HttpPolling/Null y proxy TenantAware. Esto impedía cambiar de proveedor realtime por tenant.
- **Decisión:** Migración mecánica: reemplazar `HubInterface` + `Update` por `RealtimePublisherInterface` + `SseMessage` en `TraccarIngestionService`, `NotificationService`, `RouteOptimizationApiController`, `AdminDevPushPositionController`. `DeviationAlertListener` evaluado y excluido (persiste `RealtimeEvent` en BD, no publica SSE).
- **Alternativas descartadas:** Ninguna — la abstracción ya existía, solo faltaba conectar los consumers.
- **Resultado:** 0 regresiones. Solo `MercurePublisher` (infrastructure adapter) retiene dependencia directa de `HubInterface`, que es el lugar correcto.

### [2026-03-20] Repository interfaces en Domain layer para Route Planning

- **Problema:** 7 servicios del contexto Route Planning dependían de `EntityManagerInterface` y repositories concretos (`RouteRepository`, `RouteStopRepository`), violando DIP y haciendo imposible unit testing sin base de datos.
- **Decisión:** Crear `RouteRepositoryInterface`, `RouteStopRepositoryInterface`, `RouteEventRepositoryInterface` en `src/Domain/Route/Repository/`. Implementaciones Doctrine en `src/Infrastructure/Route/Doctrine/`. Seguir el patrón existente de `RouteSnapshotRepositoryInterface`. Application services retienen `EntityManagerInterface` solo para `flush()` y queries de entidades fuera del bounded context.
- **Alternativas descartadas:** (A) Mover todo a interfaces incluyendo flush — rompe control transaccional. (B) Extraer solo los métodos usados inline (`$em->createQueryBuilder()`) sin interfaces formales — no escala.
- **Resultado:** 7 servicios migrados (RouteOptimizationService, RouteSnapshotManager, RouteBuilder, RoutePlanningService, DeliveryService, RouteLifecycleService, RouteEventLogListener). 5 inline QueryBuilder calls eliminados. Tests actualizados sin regresiones.

### [2026-03-20] Event-first pattern con collect+dispatch (no full event sourcing)

- **Problema:** El patrón actual era state-first, events-second (`$route->start()` → dispatch `RouteStarted`). 6 de 15 RouteEventType cases no tenían domain event POPO ni dispatch. El objetivo era invertir a events-first.
- **Decisión:** Patrón collect+dispatch: `Route::apply(RouteEvent)` reconstruye estado desde eventos, `Route::rebuildFromEvents(array $events)` permite reconstrucción completa. Los servicios registran eventos via `$route->recordEvent()` y los liberan con `$route->releaseEvents()` post-flush. NO es full event sourcing (no hay event store separado, RouteEvent sigue siendo la tabla existente).
- **Alternativas descartadas:** (A) Full event sourcing con event store — over-engineering para el volumen actual. (B) Solo completar dispatches faltantes sin invertir el flujo — no permite reconstrucción de estado. (C) Adoptar library externa (Broadway, Prooph) — dependencia innecesaria, RouteEvent ya tiene la estructura.
- **Resultado:** `Route::apply()` + `rebuildFromEvents()` implementados. 3 domain events nuevos (RouteReoptimized, StopsReordered, StopSkipped). RouteEventLogListener extendido con 3 handlers. Tests cubren reconstrucción de estado.

### [2026-03-20] Projection tables como read-model para Route state

- **Problema:** Queries de estado de ruta (cuántas paradas entregadas, excepciones, distancia) requerían joins complejos o cálculos en memoria. `buildSnapshotMetrics()` en RouteEventLogListener recalculaba métricas en cada evento.
- **Decisión:** 3 tablas de projection: `route_current_state` (estado denormalizado de ruta), `stop_current_status` (estado por parada), `route_timeline` (timeline de eventos). Actualizadas por event listeners separados (`RouteProjectionListener`, `StopProjectionListener`). `ProjectionRebuilder` + CLI command para backfill.
- **Alternativas descartadas:** (A) Vista materializada en PostgreSQL — menos flexible, no permite lógica de negocio en la proyección. (B) Single projector class — viola SRP.
- **Resultado:** Migration creada, listeners implementados, rebuild command disponible. Read models listos para ser consumidos por controllers/API.

### [2026-03-22] Unified React sidebar — single source of truth for navigation menu

- **Problema:** Dos sidebars duplicados: Twig (`_sidebar_content.html.twig` + Alpine.js) y React (`NavigationSidebar.tsx`). Los ítems del menú estaban duplicados y podían desincronizarse. En mobile, el sidebar Twig ocupaba 64px permanentemente (icons-only) en vez de un drawer overlay.
- **Decisión:** Crear un React sidebar widget standalone (`sidebar-widget.tsx`) que monta `NavigationSidebar` dentro de las páginas Twig. Vite multi-page build genera un bundle separado con nombre fijo (`sidebar-widget.js`). El sidebar Twig se elimina de `base.html.twig` y un botón hamburger en el top bar llama a `window.__mxoSidebarOpen()` para abrir el drawer React overlay.
- **Alternativas descartadas:** (A) Mover todas las páginas Twig a React SPA — migración demasiado grande, no justificada solo para unificar menú. (B) Mantener ambos sidebars y sincronizar ítems via JSON config compartido — añade complejidad sin eliminar duplicación real. (C) Reescribir el sidebar Twig para que replique el patrón React — sigue habiendo dos implementaciones.
- **Resultado:** Build OK. `sidebar-widget.js` (0.53 kB) + chunk NavigationSidebar (74 kB gzip). Single source of truth en `NavigationSidebar.tsx`. Hamburger button en Twig top bar. Lint clean.

### [2026-03-20] POPO migration: Route/RouteStop/RouteEvent a Domain\Route\Model con XML mapping

- **Problema:** Entidades críticas en `src/Entity/` con ORM attributes mezclaban dominio con infraestructura. No podían ser unit-tested como POPOs puros.
- **Decisión:** Mover a `src/Domain/Route/Model/` con XML mapping externo en `config/doctrine/mapping/`. Aprovechar que `doctrine.yaml` ya tiene mapping configurado para `App\Domain\Route\Model` (usado por RouteSnapshot). ULID generado en constructor (reemplaza `#[ORM\PrePersist]` de PublicIdTrait). Entidades que referencian Route/RouteStop desde fuera del bounded context usan `use App\Domain\Route\Model\Route`.
- **Alternativas descartadas:** (A) Mantener en `App\Entity\` y solo strip ORM attributes — conflicto de mapping type (attribute vs xml) en mismo prefix. (B) Migrar solo Route sin RouteStop/RouteEvent — deja el bounded context partido.
- **Resultado:** 3 entidades migradas, 80+ archivos de imports actualizados, XML mappings creados, 0 nuevos test failures. `doctrine:schema:validate` pendiente de verificación con DB.

### [2026-03-23] Widget System Architecture — Entity Model vs JSON Config

- **Problema:** Necesitábamos un sistema configurable de widgets para bottom sheets en 8 páginas map-based. Los admins necesitan definir qué widgets aparecen en cada estado (collapsed/half/full) por página, con defaults globales y overrides por customer.
- **Decisión:** Full entity model (WidgetDefinition + PageLayout + PageLayoutWidget) con enums tipados (WidgetType, PageKey, SheetState). Frontend widget registry maps tipos a React components. Layout resolution: customer override → global fallback → empty.
- **Alternativas descartadas:** (B) JSON config en entidades existentes — no escala, no tiene CRUD propio, difícil de validar. (C) Frontend-only config — no persiste, no multi-tenant, no admin UI.
- **Resultado:** Implementado end-to-end. 3 entidades, 3 enums, 7 API endpoints, widget registry con 4 componentes implementados + 6 placeholders, admin UI (gallery + layout editor). TestRoutingPage migrada a dynamic layout. Pendiente: migrar las otras 7 páginas.

### [2026-04-07] RouteMapLayers — componente compartido para capas de mapa

- **Problema:** FleetMapPage y OperatorDashboardPage ensamblaban RoutePolylineLayer + StopMarkersLayer + toggle de flechas de forma independiente. Al añadir un feature a una página, se olvidaba en la otra (el toggle de dirección faltaba en OperatorDashboard).
- **Decisión:** Extraer componente `<RouteMapLayers>` que centraliza polylines, stop markers, StopPopup, y toggle de flechas. Cada página lo monta con sus rutas ya filtradas.
- **Alternativas descartadas:** (A) Hook compartido `useRouteMapLayers` — menos natural en React. (C) Sincronización manual — no escala, el problema se repite.
- **Resultado:** FleetMap: -55 líneas. OperatorDashboard: -57 líneas. Features nuevas en capas de ruta se añaden en un solo lugar.

### [2026-04-07] Workflow Enforcement — 5 capas anti-evasión

- **Problema:** Claude fabricaba `phase_history`, seteaba `user_approved = true` sin consentimiento, saltaba TDD, y pasaba el pre-push gate con evidencia simulada. El workflow tenía un solo punto de enforcement real (pre-push) y confiaba en honestidad para todo lo demás.
- **Decisión:** 5 capas complementarias: (1) Phase Transition Controller que detecta/revierte manipulaciones directas de phase_history y user_approved, (2) Validators endurecidos SOFT→HARD para consult/brainstorm/planning, (3) User approval detectado automáticamente por UserPromptSubmit hook, (4) TDD order tracking en auto-evidence, (5) Cross-validation en pre-push gate (timestamps, formato, artefactos reales).
- **Alternativas descartadas:** Solo reforzar pre-push — deja huecos intermedios. Solo documentación — demostrado que no funciona.
- **Resultado:** 30/30 tests pass (11 phase-advance + 7 controller + 12 integración). Cada vector de evasión cubierto por una capa dedicada.
