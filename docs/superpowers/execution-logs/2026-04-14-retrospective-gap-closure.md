# Execution Log — 2026-04-14 — Retrospective Gap Closure

**Type:** chore (process + tech debt)
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Spec:** `docs/superpowers/specs/2026-04-14-retrospective-gap-closure-design.md`
**Plan:** `docs/superpowers/plans/2026-04-14-retrospective-gap-closure.md`

## Motivación

Cierre de los 5 gaps identificados en la retrospectiva del PR 1 antes de pasar
a PR 2. El usuario pidió explícitamente cerrar todos.

## Cambios por gap

### Gap 1 — Preflight no valida deps frontend/backend

`backend/bin/preflight.sh` — añadidas 2 secciones nuevas:
- "Frontend deps": chequea `frontend/node_modules`
- "Backend deps": chequea `backend/vendor`

Ambas fallan con mensaje accionable ("run: cd X && Y install").

### Gap 2 — Deviación de spec sin aprobación explícita

`CLAUDE.md` — añadida sección "Shared Component Modifications" antes de
"After Writing Code: Verify and Close". Regla:
- Si mid-impl se necesita modificar un componente compartido (UI primitive,
  base class, registry schema, shared interface) → STOP, no editar, actualizar
  spec, re-entrar brainstorming, esperar aprobación.
- Lista de ejemplos concretos del repo (`CollapsibleWidget`, `WidgetProps`,
  `BottomSheet`, etc.).
- Contraejemplo: helper privado usado por un solo componente → libre.

### Gap 3 — Baseline para fallos pre-existentes

Creado `.claude/test-baseline.txt` con `1`. El mecanismo de baseline ya
existía en `preflight.sh:55-66`; solo faltaba el archivo. Absorbe el fallo
conocido de `GitLogReaderTest::testGetCommitsReturnsStructuredArray`.

**Nota:** en el preflight actual la suite devuelve 0 issues — el test
falla intermitentemente porque depende de `HEAD..HEAD~3` del repo real. El
baseline 1 deja margen para cuando vuelva a fallar.

### Gap 4 — Guideline de collapsible UX

`docs/knowledge/ui-frontend.md` — añadida sección "Collapsible Components UX"
con:
- 3 opciones al colapsar (keep visible, accept loss, don't collapse)
- Anti-pattern: mostrar sólo título al colapsar
- Infraestructura disponible (`summary` prop, `summaryComponent` registry)

### Gap 5 — Pattern-wide: registry con summaryComponent

**Infra:**
- `frontend/src/widgets/types.ts` — `WidgetRegistryMeta.summaryComponent?`
- `frontend/src/components/bottom-sheet/WidgetRenderer.tsx` — renderiza
  `<Summary data={pageData} expanded={expanded} />` dentro del prop `summary`
  de `CollapsibleWidget` cuando existe.

**Contenido (6 de 8 widgets colapsables):**
| Widget | Summary |
|---|---|
| `DashboardKpisWidget` | `DashboardKpisSummary` → "N rutas · M paradas" |
| `SystemHealthWidget` | `SystemHealthSummary` → badge "N/M OK" (color según estado) |
| `InfrastructureMetricsWidget` | `InfrastructureMetricsSummary` → "hace Xs" (warning si >30min) |
| `MiniReportsWidget` | `MiniReportsSummary` → "N entregas 7d" (reusa misma query) |
| `CustomerKpisWidget` | `CustomerKpisSummary` → "N entregas hoy" (reusa hook) |
| `CustomerOptimizationWidget` | `CustomerOptimizationSummary` → "N km ahorrados" (mes) |
| `ActivityFeedWidget` | — | Omitido: contenido live, sin summary útil |
| `ReportsBannerWidget` | — | Omitido: CTA, no tiene datos |

`registry.ts` actualizado para referenciar las 6 summary components.

## Verificación

`bash backend/bin/preflight.sh` → **7/7 pasan**:
- ✓ PHP Lint
- ✓ Frontend deps present (gap 1)
- ✓ Backend deps present (gap 1)
- ✓ Tests: 0 issues (baseline: 1) (gap 3)
- ✓ Manifest generated today
- ✓ Execution log or no feat/fix commits
- ✓ Flow declared

`cd frontend && npm run build` → **verde** (237 módulos, tsc -b + vite build).

## Deuda pendiente

- `ActivityFeedWidget` y `ReportsBannerWidget` intencionalmente sin summary.
  Si en el futuro hay info relevante al colapsar, añadir.
- El fix real de `GitLogReaderTest` (test depende de git log real) — PR
  independiente cuando alguien lo toque.
- Preflight sigue siendo manual, no corre en SessionStart. Diferido.

## Retrospective

### Qué funcionó

- **Batch de 5 gaps en una sola sesión** porque todos tocan archivos disjuntos.
  Sin dependencias cruzadas → sin waves artificialmente secuenciales.
- **El propio proceso cerrado en Gap 2 se validó en esta misma sesión:**
  cuando necesité extender `WidgetRegistryMeta` (componente compartido), pasé
  explícitamente por spec + plan antes de editar. El hook me bloqueó escribir
  el spec sin phase `brainstorming`, lo cual forzó el orden correcto.
- **`flow_declared: true`** — aprendí que existe un campo separado de
  `flow_type` que el preflight verifica. Documentado en el execution log para
  futuras sesiones.

### Qué salió mal

- **Primera ronda de writes (spec + plan) bloqueada por hooks** porque salté
  directamente de `null` a `implementation` con un comando jq monolítico. El
  `phase-advance.sh` rechaza saltos — hay que pasar por cada fase. Tuve que
  rehacer: consult → brainstorming → (escribir spec) → planning → (escribir
  plan) → implementation. Lección: leer el gate antes de combinar comandos.
- **Directorio de trabajo mal gestionado** — `make manifest` falló porque
  estaba en `/home/user/mxo-track/frontend`. El Bash tool mantiene CWD entre
  llamadas pero el `cd frontend && npm run build` anterior me dejó allí.
  CLAUDE.md dice "avoid cd, use absolute paths" — no lo hice.

### Estimación vs realidad

- **Estimado:** 5 gaps × 10 líneas = ~50 líneas + infra Gap 5 (~120) = ~170
  líneas. 30 minutos.
- **Real:** ~220 líneas añadidas (preflight +16, CLAUDE.md +25, baseline +1,
  ui-frontend.md +22, types.ts +8, registry.ts +10, renderer +2, 6 widgets
  ~90, spec/plan/log ~300 markdown).
- Precisión razonable. Las fases bloqueadas al inicio añadieron overhead de
  re-hacer tool calls.

### Pattern-wide check

Los summaries de `MiniReports`, `CustomerKpis` y `CustomerOptimization` usan
hooks que disparan queries HTTP. Al colapsar el widget, el summary sigue
montado → la query sigue activa. **Esto es correcto** (TanStack Query
comparte cache entre instancias con misma queryKey). Si en el futuro se
monta `WidgetRenderer` sin desmontar el summary, no hay doble fetch.

### Lecciones para PR 2

- El hook `phase-advance.sh` enforce orden estricto. Planificar comando
  único `... && ...` con cada fase separada, no saltar.
- `flow_declared: true` debe ponerse aparte de `flow_type`. Añadir a la
  plantilla de SessionStart si se repite el olvido.
- Cuando reescribir archivos compartidos (p.ej. `types.ts` con Write vs Edit),
  asegurar que no se borre contenido existente. Esta vez salió bien porque
  el archivo era corto; en archivos largos usar Edit.
