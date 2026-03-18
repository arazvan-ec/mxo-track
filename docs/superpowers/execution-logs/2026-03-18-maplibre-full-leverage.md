# Execution Log — 2026-03-18 — MapLibre Full Leverage

**Type:** feature
**Branch:** `claude/fix-map-display-8Ok2x`

---

### Phase: Brainstorming
- **Alternatives evaluated:**
  1. MapTiler/Stadia CDN — vector tiles de alta calidad pero requieren API key y tienen límites de uso
  2. Protomaps CDN — gratuito, sin API key, dark theme listo via `@protomaps/basemaps`, migración trivial a self-hosted
  3. OpenFreeMap/Versatiles — viable pero tooling menos maduro que Protomaps
  4. Self-hosted Planetiler — control total pero complejidad de infra excesiva para MVP
- **Chosen approach:** Protomaps CDN — sin API key, `@protomaps/basemaps` tiene dark flavor listo, migración a self-hosted = cambiar 1 URL
- **Past decisions consulted:** [2026-03-17] React SPA + MapView DDD — ya eligió MapLibre + PMTiles pero solo usaba raster OSM
- **Complexity estimate:** M
- **Confidence:** high

### Phase: Planning
- **Task count:** 9 (1.1, 1.1b, 1.2, 2.3, 2.1, 2.2, 2.4, 3.1, verificación final)
- **Files affected:** 8 — dark-style.ts (new), MapCanvas.tsx, ShipmentClusterLayer.tsx (new), ExceptionHeatmapLayer.tsx (new), RoutePlannerPage.tsx, ExceptionMapPage.tsx, layers/index.ts, index.css
- **Time estimate:** ~45 minutos
- **Risk assessment:** low — cambios frontend aislados, sin impacto en backend

### Phase: Implementation
- **Actual time:** ~30 minutos
- **Blockers hit:**
  - Plan original usaba API inexistente `layersWithCustomTheme` — consultamos los tipos reales del paquete `@protomaps/basemaps` y encontramos que la función correcta es `layers(source, flavor, options)` con `lang` en options object. Resuelto leyendo las declaraciones TypeScript del paquete.
- **Plan deviations:**
  - Combinamos Tasks 1.1, 1.1b, 1.2 y 2.3 en un solo commit (dark style + MapCanvas update + interactivity props) ya que estaban fuertemente acoplados
  - Task 1.2 (labels español) ya estaba resuelto por el parámetro `{ lang: 'es' }` en `layers()` — no requirió trabajo adicional
- **Debugging episodes:** none

### Phase: Verification
- **Tests:** N/A (frontend components, no unit tests escritos para layers de visualización)
- **TypeScript:** 0 errores (`npx tsc --noEmit` exit 0)
- **Build:** producción OK (`npm run build` exit 0)
- **Coverage delta:** not measured

### Phase: Retrospective
- **Estimate accuracy:** accurate — M estimado, M real
- **What worked:**
  1. Leer los tipos d.ts del paquete antes de implementar evitó usar una API inexistente del plan
  2. Combinar tareas acopladas en un solo commit fue más limpio que forzar commits artificialmente separados
  3. El paquete `@protomaps/basemaps` funciona exactamente como se esperaba — el flavor system es extensible
- **What didn't:**
  1. El plan original tenía nombres de funciones incorrectos (`layersWithCustomTheme`, `namedFlavor` para crear el flavor) — los planes deberían verificar APIs antes de documentar código
- **Lessons for future:**
  1. Cuando un plan incluye código con imports de terceros, verificar los tipos reales del paquete ANTES de ejecutar
  2. Para layers de visualización pura (heatmap, clustering) la verificación visual es necesaria además del build — considerar agregar screenshots en verificación
- **Business context tags:** fleet, map-visualization
- **Decision log entry needed?** yes — Protomaps CDN como proveedor de vector tiles
