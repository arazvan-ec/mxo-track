# Design Spec — PR 2 + PR 3 en paralelo

**Fecha:** 2026-04-14
**Branch principal:** `claude/enhance-dashboard-widgets-sxseH`
**Estrategia:** 2 agentes background con worktree aislada, trabajando en paralelo
sobre ramas hijas. Merge-back secuencial al terminar.

## Problema

Cerrar las dos fases pendientes del roadmap (PR 2: migración al widget-registry;
PR 3: preferencias de usuario) con el mayor grado de paralelismo posible, dado
que los archivos relevantes son disjuntos en ~95%.

## Archivos y ownership

## Existing Functionality Inventory

Elementos existentes relevantes para ambos agentes (no son targets, son contexto):

| Elemento | Decisión | Justificación |
|---|---|---|
| `PageLayout`, `PageLayoutWidget`, `WidgetDefinition` entities + migración `Version20260323000100` | Incluir (reusar) — Agente A | Ya pueblan 10 widgets y 8 layouts; admin_dashboard falta |
| `PageKey::ADMIN_DASHBOARD` enum value | Incluir (reusar) — Agente A | Ya existe, sólo falta layout que lo use |
| `usePageLayout` React hook | Incluir (reusar) — Agente A | Usado por `OperatorDashboardPage` — patrón a replicar |
| `WidgetRenderer` en `mode='page'` | Incluir (reusar) — Agente A | Renderer auto-wrap en `CollapsibleWidget` ya implementado |
| Widget registry (`DashboardKpisWidget`, `SystemHealthWidget`, `InfrastructureMetricsWidget`, `MiniReportsWidget`, `ReportsBannerWidget`) + summaryComponents | Incluir (reusar) — Agente A | Todos colapsables + summary |
| `CollapsibleWidget.tsx` con prop `summary` y persistencia localStorage | Transformar aditivamente — Agente B | Añadir prop opcional `initialMode` + lectura de Context |
| `User` entity (`backend/src/Entity/User.php`) | Transformar — Agente B | Añadir relación con `UserPreference` O columna JSON |
| `NotificationPreference` entity (patrón customer-scoped) | Incluir (referencia) — Agente B | Modelo a seguir para `UserPreference` |
| `ApiErrorResponder` + DTOs con validación Symfony | Incluir (reusar) — Agente B | Error handling + validation boundary |
| `frontend/src/router.tsx` | Transformar — Agente B | +1 ruta `/profile` |
| `NavigationSidebar` | Transformar (opcional) — Agente B | +1 enlace a Profile |
| `frontend/src/api/client.ts` | Incluir (reusar) — Agente B | Cliente HTTP existente |

## Omission Decisions

Al principio del file:

## Archivos y ownership

### Agente A — PR 2 "Registry migration" (rama `.../pr2-registry`)

| Archivo | Cambio |
|---|---|
| `backend/migrations/VersionYYYYMMDD_admin_dashboard_layout.php` | Nuevo — seed `PageLayout` para `admin_dashboard` + `PageLayoutWidget` para 5-6 widgets |
| `frontend/src/pages/admin/AdminDashboardPage.tsx` | Refactor completo: `usePageLayout('admin_dashboard')` + `WidgetRenderer` en `mode='page'` |
| `frontend/src/widgets/registry.ts` | (solo si hace falta) — confirmar que los widgets usados existen con metadata |
| Docs: `docs/superpowers/{specs,plans,execution-logs}/2026-04-14-pr2-*.md` | Nuevos |

**No debe tocar:**
- `frontend/src/components/widgets/CollapsibleWidget.tsx`
- `frontend/src/widgets/bottom-sheet/WidgetRenderer.tsx` salvo para añadir soporte de layoutWaves si fuera imprescindible (avisar antes)
- Nada bajo `backend/src/Controller/Api/UserPreference*` (propiedad de Agente B)
- `ProfilePage.tsx` o hooks de preferencias

### Agente B — PR 3 "User preferences" (rama `.../pr3-prefs`)

| Archivo | Cambio |
|---|---|
| `backend/migrations/VersionYYYYMMDD_user_preferences.php` | Nuevo — tabla `user_preference` o columna JSON en `user` |
| `backend/src/Entity/UserPreference.php` o extensión en `User.php` | Nuevo |
| `backend/src/Controller/Api/UserPreferenceController.php` | Nuevo — GET/PATCH `/api/me/preferences` |
| `backend/src/Dto/UserPreferencesDto.php` | Nuevo — valida payload |
| `frontend/src/api/hooks/useUserPreferences.ts` | Nuevo |
| `frontend/src/contexts/UserPreferencesContext.tsx` | Nuevo |
| `frontend/src/pages/ProfilePage.tsx` | Nuevo — selector modo b vs c |
| `frontend/src/router.tsx` | +1 ruta `/profile` |
| `frontend/src/components/widgets/CollapsibleWidget.tsx` | **Cambio aditivo** — prop opcional `initialMode?: 'expanded' \| 'collapsed' \| 'summary-visible'` + lee `UserPreferencesContext` si prop se omite |
| Tests unitarios nuevos | Donde aplique |
| Docs: `docs/superpowers/{specs,plans,execution-logs}/2026-04-14-pr3-*.md` | Nuevos |

**No debe tocar:**
- `AdminDashboardPage.tsx`, `registry.ts`, `WidgetRenderer.tsx` (propiedad de Agente A)
- Migraciones de `PageLayout` (propiedad de Agente A)

**Cambio en `CollapsibleWidget` debe ser 100% aditivo:**
- Nueva prop `initialMode` es **opcional**, default `undefined`
- Si ausente y `UserPreferencesContext` existe → usar preferencia
- Si ambos ausentes → comportamiento actual (localStorage + `defaultExpanded`)
- Tests existentes siguen pasando

## Coordinación

### Worktree isolation

Agentes usan `isolation: "worktree"` del tool Agent. Cada uno obtiene:
- Clon aislado del repo
- Rama nueva (`claude/enhance-dashboard-widgets-sxseH` base)
- No pueden pisar archivos del otro aunque se equivoquen

### Merge-back (tarea del main agent)

Tras completar ambos:
1. `git fetch origin` para traer las dos ramas hijas pusheadas
2. `git merge origin/claude/.../pr2-registry --no-ff`
3. `git merge origin/claude/.../pr3-prefs --no-ff` (resolver conflicto en manifest si ocurre)
4. Re-run `make manifest`
5. Preflight final + push

### Protocolo si un agente falla

- Si A falla y B pasa → hago merge de B, reporto A al usuario
- Si ambos fallan → reporto ambos errores
- Si A modifica un archivo que "no debe tocar" → rechazo el merge y pido re-trabajo

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Ejecutar los dos PRs secuencialmente | Omitir | El usuario eligió paralelo; archivos disjuntos |
| Launch en foreground | Omitir | Se bloquearía el main thread sin valor; los agentes no necesitan interacción |
| Unificar ambos en un solo agente | Omitir | Perderíamos paralelismo y el contexto del agente sería demasiado grande |
| Crear sub-PRs reales en GitHub | Omitir | Usuario no pidió PRs; rama única con commits ordenados |
| Tests e2e completos | Omitir | Cada agente hace preflight; e2e manual queda para sesión futura |

## Criterios de aceptación

Por agente:
1. Su preflight (7/7) pasa
2. `npm run build` verde
3. `phpunit` ≤ baseline
4. Commit con mensaje descriptivo + push a su rama
5. No modificó archivos fuera de su ownership
6. No usó `--no-verify` ni bypass de ganchos

Globales (post-merge):
1. Preflight principal 7/7
2. `npm run build` verde tras el merge
3. Dashboard admin sigue renderizando correctamente (registry-driven con widgets colapsables, con preferencia de usuario aplicable)
4. `ProfilePage` accesible y funcional
5. Zero regresiones en `test-phase-advance.sh` / `test-workflow-engine.sh`
