# Decision Log

Registro de decisiones de diseño significativas. Cada entrada captura el contexto, la decisión, las alternativas y el resultado. Con el tiempo, los patrones recurrentes enriquecen las guías en `docs/knowledge/` y `CLAUDE.md`.

**Cuándo añadir:** Decisiones no triviales — nueva abstracción, nuevo patrón, refactor de arquitectura, trade-off con implicaciones.

**Cuándo actualizar knowledge:** Si la misma lección aparece 3+ veces, actualizarla en la guía correspondiente.

---

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
