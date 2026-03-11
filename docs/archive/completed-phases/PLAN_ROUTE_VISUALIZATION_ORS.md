# Plan: Rutas de transporte reales en mapas (OpenRouteService self-hosted)

## Context

Los tres mapas (fleet `/fleet/map`, driver, customer) dibujan **líneas rectas** entre paradas consecutivas. El usuario quiere ver la **ruta real por carreteras**.

Se usa **OpenRouteService (ORS) self-hosted** en Docker — un solo contenedor que incluye routing, optimización (VROOM), perfiles para camiones, e instrucciones en español. Datos OSM de España (~1.3 GB).

## Architecture overview

```
Frontend (Leaflet)  →  ORS self-hosted (Docker)  →  Geometría por carreteras
     ↓ fallback
  Líneas rectas (si ORS no disponible)
```

- **Local dev**: contenedor `ors` en `docker-compose.local.yml`
- **Railway prod**: servicio separado (futuro, no incluido en este plan)
- **ORS API pública**: posible fallback (2000 req/día gratis, misma API)

## Files to create/modify

| File | Action | Description |
|------|--------|-------------|
| `docs/plans/routing-osrm-frontend.md` | **CREATE** | Guardar el plan anterior (OSRM público) |
| `docker-compose.local.yml` | **MODIFY** | Añadir servicio `ors` |
| `scripts/setup-ors-data.sh` | **CREATE** | Script para descargar PBF de España |
| `backend/public/js/ors-route.js` | **CREATE** | Utilidad JS para llamar ORS y dibujar rutas |
| `backend/templates/tracking/map.html.twig` | **MODIFY** | Integrar ors-route.js (líneas 259, 461-468) |
| `backend/templates/driver/routes/show.html.twig` | **MODIFY** | Integrar ors-route.js (líneas 600, 901-907) |
| `backend/templates/customer/route/show.html.twig` | **MODIFY** | Integrar ors-route.js (líneas 226, 301-307) |

## Step 1: Guardar plan anterior

Crear `docs/plans/routing-osrm-frontend.md` con el plan original de OSRM público (como referencia).

## Step 2: Setup ORS en Docker

### 2a. Script `scripts/setup-ors-data.sh`

Descarga el PBF de España de Geofabrik y lo coloca en `docker/ors/files/`:

```bash
#!/bin/bash
mkdir -p docker/ors/{files,graphs,elevation_cache}
wget -O docker/ors/files/spain-latest.osm.pbf \
  https://download.geofabrik.de/europe/spain-latest.osm.pbf
```

### 2b. Servicio `ors` en `docker-compose.local.yml`

```yaml
ors:
  image: openrouteservice/openrouteservice:v8.0.0
  ports:
    - "8085:8082"
  volumes:
    - ./docker/ors/graphs:/home/ors/graphs
    - ./docker/ors/elevation_cache:/home/ors/elevation_cache
    - ./docker/ors/files:/home/ors/files
  environment:
    XMS: 1g
    XMX: 4g
    REBUILD_GRAPHS: "False"
    ors.engine.profile_default.build.source_file: /home/ors/files/spain-latest.osm.pbf
    ors.engine.profiles.driving-car.enabled: "true"
    ors.engine.profiles.driving-hgv.enabled: "false"
    ors.engine.profiles.cycling-regular.enabled: "false"
    ors.engine.profiles.foot-walking.enabled: "false"
```

- Puerto 8085 (evitar conflicto con Traccar 8082)
- Solo perfil `driving-car` habilitado (minimiza RAM: ~4 GB)
- Primer arranque construye grafos (~30-90 min para España). Siguientes arranques son rápidos.

### 2c. Añadir `ORS_BASE_URL` al servicio `app`

```yaml
app:
  environment:
    ORS_BASE_URL: http://ors:8082
```

Esto permite pasar la URL al frontend via Twig.

## Step 3: Crear `backend/public/js/ors-route.js`

Utilidad global `window.MxoRoute` (~80 líneas):

**API ORS usada**: `POST /ors/v2/directions/driving-car/geojson`
- Request: `{ "coordinates": [[lng,lat], [lng,lat], ...] }`
- Response: GeoJSON `FeatureCollection` con `geometry.coordinates` y `properties.way_points` (índices donde cae cada waypoint en la geometría)

**Funciones**:

- `fetchRouteGeojson(waypoints)` — llama ORS, devuelve array de coordenadas + way_points
- `splitBySegments(coords, wayPointIndices, validStops)` — divide la geometría en segmentos usando `way_points`, asocia cada segmento al status de la parada destino
- `drawRoute(map, validStops, colorFn, opts)` — función principal:
  1. Dibuja líneas punteadas inmediatamente (feedback instantáneo)
  2. Llama ORS async
  3. Usa `way_points` para dividir la geometría en segmentos
  4. Dibuja cada segmento con el color del status de la parada destino
  5. Si ORS falla → fallback líneas rectas

**Ventaja del endpoint GeoJSON**: No necesitamos decodificar polylines. Las coordenadas vienen en formato `[lng, lat]`, solo hay que invertir a `[lat, lng]` para Leaflet.

**URL configurable**: `MxoRoute.ORS_BASE_URL` se puede setear desde Twig. En local: `http://localhost:8085`. Para fallback público: `https://api.openrouteservice.org` (con API key).

## Step 4: Integrar en Fleet map (`tracking/map.html.twig`)

1. **Después de línea 259** (script Leaflet): añadir `<script src="/js/ors-route.js"></script>`
2. **Antes del script principal**: pasar URL desde Twig: `MxoRoute.ORS_BASE_URL = '{{ ors_public_url }}';`
3. **Líneas 461-468**: reemplazar loop de `L.polyline` rectas por:
```javascript
MxoRoute.drawRoute(this.map, validStops, this.segmentColor, { weight: 4 })
    .then((polylines) => {
        this.routePolylines = this.routePolylines.concat(polylines);
    });
```

`clearRouteOverlay()` (línea 471) ya hace `this.routePolylines.forEach(p => p.remove())` — funciona sin cambios.

### Cambio en `FleetMapController.php`

Pasar `ors_public_url` al template:

```php
'ors_public_url' => $_ENV['ORS_PUBLIC_URL'] ?? 'http://localhost:8085',
```

## Step 5: Integrar en Driver route (`driver/routes/show.html.twig`)

1. **Después de línea 600**: añadir script tag
2. **Líneas 901-907**: reemplazar por `MxoRoute.drawRoute(map, validStops, statusColor, { weight: 4 });`

### Cambio en `DriverWebController.php`

Pasar `ors_public_url` al template.

## Step 6: Integrar en Customer route (`customer/route/show.html.twig`)

1. **Después de línea 226**: añadir script tag
2. **Líneas 301-307**: reemplazar por `MxoRoute.drawRoute(map, validStops, statusColor, { weight: 4 });`

### Cambio en `CustomerRouteController.php`

Pasar `ors_public_url` al template.

## Step 7: Configuración de env vars

En `.env`:
```
ORS_PUBLIC_URL=http://localhost:8085
```

En `docker-compose.local.yml` servicio `app`:
```
ORS_PUBLIC_URL: http://ors:8082
```

**Nota**: `ORS_PUBLIC_URL` es la URL accesible desde el **navegador** (localhost:8085), no la URL interna Docker (ors:8082). Para el frontend la URL debe ser accesible desde el browser.

## UX del loading

1. **Instantáneo**: Líneas punteadas semitransparentes entre paradas
2. **~200-500ms después**: Se reemplazan por polilíneas sólidas siguiendo carreteras
3. **Si falla ORS**: Se reemplazan por líneas rectas sólidas (comportamiento actual)

## Error handling

- Timeout 8s con `AbortController`
- Catch errores de red → fallback líneas rectas
- Menos de 2 paradas → no llama ORS
- Respuesta sin geometry → fallback completo
- Segmento sin coordenadas suficientes → fallback recta solo para ese segmento

## Verification

1. Ejecutar `scripts/setup-ors-data.sh` para descargar datos
2. `docker compose -f docker-compose.local.yml up -d --build` — esperar a que ORS construya grafos
3. Verificar ORS health: `curl http://localhost:8085/ors/v2/health` → `{"status":"ready"}`
4. Crear ruta con paradas en España
5. Abrir `/fleet/map` → polilíneas por carreteras visibles
6. Abrir vista driver → lo mismo
7. Abrir vista customer → lo mismo
8. Verificar colores por status (azul=PENDING, verde=DELIVERED, rojo=EXCEPTION, gris=SKIPPED)
9. Verificar fallback: parar contenedor `ors` → deben aparecer líneas rectas
10. Fleet map: cambiar de ruta → polylines se limpian y redibujan correctamente

## Future enhancements (no incluidos ahora)

- **Backend caching**: guardar geometría en Route entity para no llamar ORS en cada page view
- **Railway deploy**: añadir ORS como servicio separado en Railway
- **VROOM optimization**: usar `/v2/optimization` para el flujo CSV → crear rutas óptimas
- **HGV profile**: activar perfil para camiones con restricciones de peso/altura
- **ETA mejorado**: usar distancia por carretera de ORS en vez de Haversine
- **API pública como fallback**: usar `https://api.openrouteservice.org` si ORS self-hosted no responde
