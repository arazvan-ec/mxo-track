# Plan: Stop Details Expansion

**Spec:** `docs/superpowers/specs/2026-04-01-stop-details-expansion-design.md`

## Phase 1 (v0)

### Tarea 1: Backend — agregar recipientPhone a buildStopStates

**Archivo:** `backend/src/Service/RouteSnapshotManager.php`
**Cambio:** Agregar `'recipientPhone' => $stop->getRecipientPhone()` al array `$state` en `buildStopStates()`.
**Verificacion:** `php bin/console about` (no errores de sintaxis), tests existentes pasan.

### Tarea 2: Frontend — extender tipo FleetStop

**Archivo:** `frontend/src/api/types.ts`
**Cambio:** Agregar a `FleetStop`: `recipientPhone?`, `deliveredAt?`, `exceptionCode?`, `exceptionNotes?`
**Verificacion:** TypeScript compila sin errores.

### Tarea 3: Frontend — StopItem expandible con detalles y acciones

**Archivo:** `frontend/src/pages/admin/OperatorDashboardPage.tsx`
**Cambios:**
- `RouteListItem`: agregar estado `expandedStopKey` (string | null)
- `StopItem`: al hacer tap, toggle expansion en vez de llamar a `onStopClick`
- Area expandida muestra: telefono, hora entrega/ETA, excepcion, botones de accion
- Boton "Localizar" llama a `onStopClick` (vuelo al mapa)
- Boton "Copiar" copia direccion al clipboard
- Boton "Ver envio" si tiene shipmentPublicId
- Boton "Llamar" si tiene recipientPhone
**Verificacion:** TypeScript compila, build OK.

### Tarea 4: Verificacion final

- TypeScript: `npx tsc --noEmit`
- Build: `npm run build`
- Backend tests: `php vendor/bin/phpunit` (sin nuevos fallos)
