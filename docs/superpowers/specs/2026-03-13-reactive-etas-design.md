# Fase 2: ETAs Reactivas

**Fecha:** 2026-03-13
**Estado:** Pendiente de aprobación
**Objetivo:** Recalcular ETAs automáticamente cuando cambia la posición del vehículo, persistirlas, propagarlas vía Mercure a todos los roles, y registrar cambios significativos como RouteEvent.

---

## Problema

`EtaService.calculateEtas()` solo se ejecuta **on-demand** cuando el conductor llama a `GET /api/routes/{id}/etas`. Los ETAs no se persisten, no se propagan por Mercure, no aparecen en las vistas de admin/customer/tracking, y no hay forma de saber si un ETA cambió significativamente.

**Consecuencias:**
- Admin no ve ETAs en tiempo real en la vista de ruta
- Customer no sabe cuándo llegará su entrega
- Public tracking no muestra ETA al destinatario
- No hay alertas de "ETA changed significantly" ni violaciones de ventana horaria
- No se puede medir accuracy de ETAs (estimado vs real)

---

## Diseño

### 1. Incluir ETAs en `StopViewData` y el flujo de Mercure existente

**Cambio clave:** Añadir campos ETA a `StopViewData` para que fluyan por el pipeline existente:

```
StopViewData (añadir)
├── etaMinutes (int, nullable) — minutos hasta llegada
├── etaTime (string, nullable) — hora estimada formateada "HH:MM"
├── etaDistanceKm (float, nullable) — distancia restante en km
```

Cuando `RouteSnapshotListener` publica MapViewData, los ETAs viajan con cada stop. Los frontends que ya escuchan `mxo:route-updated` obtienen ETAs automáticamente sin cambios adicionales.

### 2. Persistir ETAs en RouteSnapshot (campo JSON)

Añadir campo `etas` (JSON) a `RouteSnapshot`:

```
route_snapshot.etas → JSON
{
  "stop_public_id_1": {"eta": "2026-03-13T10:30:00+00:00", "minutes": 15, "distance_km": 3.2},
  "stop_public_id_2": {"eta": "2026-03-13T10:45:00+00:00", "minutes": 30, "distance_km": 7.8}
}
```

Se actualiza cada vez que se recalculan ETAs. Permite que `RouteViewService` lea ETAs del snapshot sin llamar a OSRM en cada request.

### 3. Nuevo listener: `EtaRecalculationListener`

Escucha `VehiclePositionReceived` y recalcula ETAs para la ruta activa del vehículo.

**Trigger:** `VehiclePositionReceived` (cada vez que llega una posición GPS)

**Lógica:**
1. Buscar la ruta ACTIVE del vehículo (`Route` con status=ACTIVE y vehicle=evento.vehicle)
2. Llamar a `EtaService.calculateEtas($route)`
3. Comparar con ETAs anteriores (del snapshot)
4. Si hay cambio significativo (>= 5 min en cualquier stop):
   - Dispatch `EtaChanged` domain event
   - Esto genera un `RouteEvent` tipo `ETA_CHANGED` vía `RouteEventLogListener`
5. Persistir nuevos ETAs en `RouteSnapshot.etas`
6. Disparar actualización de `MapViewData` vía Mercure (reutilizar `RouteSnapshotListener.publishRouteViewUpdate()`)

**Throttling:** No recalcular si la última recalculación fue hace <30 segundos (evitar saturar OSRM).

### 4. Nuevo domain event: `EtaChanged`

```php
final readonly class EtaChanged
{
    public function __construct(
        public string $routePublicId,
        public array $previousEtas,  // stop_id => minutes
        public array $currentEtas,   // stop_id => minutes
        public int $maxDeltaMinutes, // mayor cambio absoluto
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
```

Solo se dispatcha cuando `maxDeltaMinutes >= 5` (umbral configurable).

### 5. Modificar `RouteSnapshotManager` para incluir ETAs

El método `updateStopStates()` ya actualiza el snapshot cuando hay progreso. Añadir método `updateEtas()` que:
1. Recibe el resultado de `EtaService.calculateEtas()`
2. Almacena en `RouteSnapshot.etas` (JSON)
3. También inyecta ETAs en `stopStates` para que `buildStopViews()` pueda leerlos

### 6. Modificar `RouteViewService.buildStopViews()` para leer ETAs

Actualmente construye `StopViewData` desde `snapshot.stopStates`. Debe leer también `snapshot.etas` y mapear a los nuevos campos de `StopViewData`.

### 7. Frontend: mostrar ETAs en todas las vistas

Las vistas ya reciben `StopViewData` vía Mercure. Solo falta renderizar los nuevos campos:

**Admin route show** (`_stop_list.html.twig`):
- Mostrar badge "ETA: HH:MM (~X min)" en cada stop PENDING

**Customer route show** (`_stop_list.html.twig`):
- Mostrar "Llegada estimada: HH:MM" en stops PENDING

**Driver route show** (ya tiene ETAs parcialmente):
- Actualizar para usar ETAs del Mercure stream en lugar de polling manual

**Public tracking** (`tracking/public.html.twig`):
- Mostrar "Tu entrega llegará aproximadamente a las HH:MM"

### 8. Endpoint ETA actualizado

El endpoint existente `GET /api/routes/{publicId}/etas` sigue funcionando pero ahora también puede leer del snapshot (más rápido, sin OSRM call) cuando los ETAs están frescos.

---

## Archivos a crear/modificar

### Nuevos archivos
1. `src/Domain/Event/EtaChanged.php` — Domain event
2. `src/EventListener/Domain/EtaRecalculationListener.php` — Listener que recalcula ETAs en cada posición

### Archivos a modificar
1. `src/View/StopViewData.php` — Añadir `etaMinutes`, `etaTime`, `etaDistanceKm`
2. `src/Entity/RouteSnapshot.php` — Añadir campo `etas` (JSON)
3. `src/Service/RouteSnapshotManager.php` — Añadir `updateEtas()` y enriquecer `stopStates` con ETAs
4. `src/View/RouteViewService.php` — Leer ETAs del snapshot en `buildStopViews()`
5. `src/EventListener/Domain/RouteEventLogListener.php` — Añadir handler `onEtaChanged()`
6. `templates/components/route/_stop_list.html.twig` — Mostrar ETA badge
7. `templates/driver/routes/show.html.twig` — Usar ETAs de Mercure stream
8. `templates/tracking/public.html.twig` — Mostrar ETA al destinatario
9. `migrations/VersionXXX.php` — Añadir columna `etas` a `route_snapshot`

---

## Decisiones de diseño

### ¿Por qué en RouteSnapshot y no en una tabla separada?

RouteSnapshot ya es la "cache desnormalizada" de la ruta. Los ETAs son parte de ese estado actual. Usar JSON evita N queries por stop y se alinea con el patrón existente de `stopStates` JSON.

### ¿Por qué throttle de 30s?

Las posiciones GPS llegan cada 5-15 segundos. Recalcular ETAs con OSRM en cada una sería ~4-12 requests/minuto/ruta. Con 50 rutas activas = 200-600 OSRM calls/minuto. El throttle reduce a ~2/minuto/ruta = 100 calls/minuto máximo.

### ¿Por qué umbral de 5 minutos para ETA_CHANGED?

Cambios menores (1-2 min) son ruido normal de tráfico. Solo queremos alertar y registrar cuando hay un cambio significativo que afecta la operación. El umbral es configurable.

### ¿Por qué no usar el ML predictor todavía?

La infraestructura existe (`EtaPredictorInterface`, `HttpMlServiceClient`) pero no hay modelo entrenado. OSRM proporciona ETAs road-based precisos para esta fase. ML se activa cuando haya datos históricos suficientes.

---

## Orden de implementación (TDD)

1. Crear domain event `EtaChanged`
2. Migration: añadir `etas` JSON a `route_snapshot`
3. Modificar `RouteSnapshot` entity — getter/setter para `etas`
4. Modificar `StopViewData` — añadir campos ETA
5. Modificar `RouteSnapshotManager.updateEtas()` — persistir ETAs
6. Modificar `RouteViewService.buildStopViews()` — incluir ETAs del snapshot
7. Crear `EtaRecalculationListener` — test + implementación
8. Añadir handler `onEtaChanged()` a `RouteEventLogListener`
9. Modificar `_stop_list.html.twig` — mostrar ETA badge
10. Modificar driver show — usar ETAs reactivos
11. Modificar public tracking — mostrar ETA
12. Actualizar documentación

---

## Fuera de alcance (fases posteriores)

- ML-based ETA predictions (requiere datos históricos)
- Notificaciones push/SMS cuando ETA cambia (Fase 4: Notifications)
- Detección de ventana horaria violada (Fase 3: Deviation Detection — usa ETAs de esta fase)
- Dashboard de accuracy de ETAs (analytics posterior)
