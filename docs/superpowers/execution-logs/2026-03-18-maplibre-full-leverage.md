---
type: feature
tags: []
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

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
- **Decision log entry needed?** yes — Protomaps CDN como proveedor de vector tiles (DONE)

### Phase: Design Retrospective (Skill 12 Step 5)

**Decisiones de diseño revisadas:**

1. **Protomaps CDN como tile provider** — Decisión sólida. Sin API key, flavor system extensible, path claro a self-hosted. No se siente forzado ni over-engineered.

2. **DOM markers para Vehicle/Stop, WebGL para Shipment/Exception** — Pragmática y correcta. DOM para <50 elementos con SVG custom, WebGL para 500+ puntos. La separación escala bien.

3. **Click handling en ShipmentClusterLayer via `useMemo` + map events** — Funciona pero es un patrón frágil: el cleanup del event listener depende de que `useMemo` se re-ejecute. Un `useEffect` sería más idiomático para side-effects. **Candidato a mejora futura.**

4. **`FALLBACK_RASTER_STYLE` definido pero no conectado** — El fallback a raster OSM está exportado pero MapCanvas no lo usa automáticamente si vector tiles fallan. El fallback requiere detección de error en la carga de tiles, lo cual no es trivial con MapLibre. **Deuda técnica documentada — prioridad baja.**

**¿Algún patrón se siente over-engineered?** No. Los componentes son directos, sin abstracciones innecesarias.

**¿Documentación a actualizar?**
- `docs/knowledge/` no necesita cambios — los módulos existentes no cubren frontend map layers
- El spec y plan ya están actualizados con la decisión de Protomaps CDN

### Phase: Verification Evidence (Skill 9)

| Claim | Command | Exit Code | Evidence |
|-------|---------|-----------|----------|
| TypeScript 0 errores | `npx tsc --noEmit` | 0 | Sin output |
| Build producción | `npm run build` | 0 | 172 modules, 5.54s |
| dark-style.ts usa API correcta | grep | — | `import { layers, DARK } from '@protomaps/basemaps'` |
| MapCanvas usa dark style | grep | — | `mapStyle={getDarkStyle()}` |
| ShipmentClusterLayer conectado | grep | — | import + `<ShipmentClusterLayer>` en RoutePlannerPage |
| ExceptionHeatmapLayer conectado | grep | — | import + `<ExceptionHeatmapLayer>` en ExceptionMapPage |
| Toggle funcional | grep | — | `useState<'heatmap' \| 'points'>` |
| Sin MAP_STYLE residual | grep | — | 0 matches en `src/` |
| Sin imports muertos en pages | grep | — | 0 matches de layers viejos |
