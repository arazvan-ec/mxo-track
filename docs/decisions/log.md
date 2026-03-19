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
