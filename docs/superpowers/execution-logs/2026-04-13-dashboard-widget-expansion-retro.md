# Retrospective — Dashboard Widget Expansion PR 1

## Qué funcionó

- **Dos subagents Explore consecutivos** redujeron el ruido de contexto. El primero
  descubrió que el dashboard visible era React (no Twig), el segundo encontró
  `CollapsibleWidget` ya existente + `WidgetRenderer` + falta de infra de user prefs.
- **TDD estricto en `AdminMetricsService`** — el test RED capturó exactamente los 8
  campos que faltaban, y la implementación fue mecánica después. Zero iteración.
- **Push-back de alcance** — cuando el usuario pidió "migración completa + todos los
  widgets + user prefs en perfil", calculé ~1200 líneas y propuse faseo en 3 PRs.
  El usuario aceptó opción conservadora. Esto evitó un plan >15 pasos que no
  sobreviviría a una compactación.

## Qué salió mal

- **Verificación inicial falló por ambiente no preparado:** `make lint` se intentó
  desde `backend/` en vez de raíz, y `npm run build` falló porque `node_modules` no
  existía. Tuve que lanzar `composer install` y `npm install` mid-verificación. No es
  culpa del código pero sí del proceso — en siguiente sesión, verificar dependencias
  antes de la fase de implementación.
- **Deviación de spec sin preguntar:** añadí el prop `summary` a `CollapsibleWidget`
  durante implementación porque identifiqué que el spec original contradecía el
  requisito del usuario ("info visible siempre"). Documentado en execution log, pero
  idealmente debería haberse detectado en brainstorming.

## Gap de proceso detectado

Mi spec inicial decía "el número grande pasa al cuerpo para no duplicar información".
Esto es un **anti-patrón** cuando el widget está colapsado — la UX queda peor que
antes del cambio (el usuario perdía info al colapsar). El checklist de brainstorming
no tiene un paso explícito de "probar mentalmente el estado COLAPSADO de cada widget",
solo el expandido.

**Acción:** para futuras features con componentes colapsables, añadir a la revisión
del spec una pregunta explícita: "¿qué información se pierde al colapsar? ¿es
aceptable?"

No amerita modificar `CLAUDE.md` todavía (incidente único), pero si reaparece en otro
execution log → graduar a regla.

## Pattern-wide check

- ¿Hay otros widgets en la codebase que usen `CollapsibleWidget` y sufran la misma
  pérdida de info al colapsar?
- Consumidor único: `WidgetRenderer.tsx:30-36`. Los widgets colapsables allí
  (`DashboardKpisWidget`, `SystemHealthWidget`, etc.) renderizan su contenido
  completo dentro del cuerpo — cuando están colapsados, el usuario ve solo el título.
  **Mismo problema potencial.** Cuando migremos en PR 2, evaluar pasar `summary` a
  través del registry (p.ej. un render-prop en `WidgetDefinition`).

## Backlog derivado

- PR 2: seed `admin_dashboard` layout + refactor a `usePageLayout` + `WidgetRenderer`.
  Considerar cómo exponer el nuevo prop `summary` al registry.
- PR 3: `UserPreference` entity + endpoint + `ProfilePage` con selector b/c.
- Fallo pre-existente `GitLogReaderTest::testGetCommitsReturnsStructuredArray` — sigue
  fallando en `main`. Documentar como known-issue y fix en PR independiente si
  bloquea CI futuro.
