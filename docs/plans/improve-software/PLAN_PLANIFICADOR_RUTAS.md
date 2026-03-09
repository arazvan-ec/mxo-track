# Plan: Planificador de Rutas con Preview

## Contexto

El flujo core del producto es: **CSV -> ver envios en mapa -> configurar vehiculos -> generar rutas optimizadas -> revisar y confirmar**. Actualmente existen las piezas de backend (RouteBuilder, VROOM, CSV importer) y la API (`POST /api/routes/build`), pero **no existe la interfaz visual integrada** que permita al operador realizar todo el flujo en una sola pantalla. Este es el requisito principal mencionado en CLAUDE.md para la demo a clientes.

## Alcance

Nueva pagina `/admin/route-planner` con flujo de 3 pasos:

1. **Paso 1 — Seleccionar envios**: Importar CSV o seleccionar envios pendientes existentes, verlos en mapa
2. **Paso 2 — Configurar vehiculos y parametros**: Elegir vehiculos, origen, max paradas, ver capacidades
3. **Paso 3 — Preview y confirmar**: Ejecutar optimizacion, ver rutas generadas en mapa con colores por vehiculo, revision de capacidad, aceptar o ajustar

## Archivos a crear

### 1. Controller: `backend/src/Controller/Admin/RoutePlannerController.php`

Nuevo controller con endpoints:

```
GET  /admin/route-planner              -> Vista principal (Twig con Alpine.js)
GET  /admin/route-planner/shipments    -> API JSON: envios sin ruta asignada (filtrable por cliente)
POST /admin/route-planner/preview      -> API JSON: ejecuta RouteBuilder en modo preview (sin persistir)
POST /admin/route-planner/confirm      -> Persiste las rutas generadas, redirect a listado
```

- Reutiliza: `RouteBuilder`, `RouteCapacityValidator`, `ShipmentRepository`, `VehicleRepository`
- El endpoint `/preview` llama a `RouteBuilder::buildRoutes()` pero NO hace `flush()` — devuelve JSON con las rutas propuestas
- El endpoint `/confirm` ejecuta el build real y persiste

### 2. Template: `backend/templates/admin/route_planner/index.html.twig`

Pagina completa con:

- **Layout**: Panel izquierdo (controles) + mapa Leaflet derecho (patron de `tracking/map.html.twig`)
- **Framework JS**: Alpine.js (consistente con el resto de la app)
- **Mapa**: Leaflet 1.9.4 con OpenStreetMap tiles (ya incluido en base)

#### Paso 1: Seleccion de envios
- Dropdown de cliente (filtra envios)
- Tabla de envios sin ruta con checkboxes (select all / individual)
- Boton "Importar CSV" que abre modal con el patron de `shipments_import.html.twig`
- Markers en mapa: circulos azules numerados para envios seleccionados
- Info popup en cada marker: referencia, destinatario, peso, volumen

#### Paso 2: Configuracion
- Lista de vehiculos disponibles con checkboxes + info de capacidad (kg, m3, parcels, skills)
- Selector de origen (CustomerLocation dropdown)
- Input: max paradas por ruta (default 30)
- Resumen: X envios seleccionados, Y vehiculos, capacidad total

#### Paso 3: Preview
- Boton "Optimizar" -> POST `/admin/route-planner/preview`
- Resultado en mapa: polylines coloreadas por ruta (colores distintos por vehiculo)
- Markers de paradas numerados por secuencia dentro de cada ruta
- Panel lateral: tarjetas por ruta con:
  - Vehiculo asignado, conductor (si hay)
  - Num paradas, distancia total, duracion estimada
  - Barra de capacidad (peso/volumen/bultos con % uso)
  - Envios no asignados (si los hay) con motivo
- Boton "Confirmar rutas" -> POST `/admin/route-planner/confirm`
- Boton "Volver a configurar" para ajustar parametros

### 3. API de envios pendientes

`GET /admin/route-planner/shipments?customer={id}`

Devuelve JSON con envios sin ruta (status CREATED, no asignados a ninguna RouteStop):

```json
[{
  "publicId": "01ABC...",
  "reference": "REF-001",
  "recipientName": "Juan Garcia",
  "address": "Calle Gran Via 42",
  "latitude": 40.42,
  "longitude": -3.70,
  "weightKg": 5.2,
  "volumeM3": 0.03,
  "parcels": 1,
  "priority": "NORMAL",
  "requiredSkills": ["REFRIGERATED"]
}]
```

### 4. Preview API response format

`POST /admin/route-planner/preview`

Input:
```json
{
  "shipment_ids": ["01ABC..."],
  "vehicle_ids": ["01DEF..."],
  "origin_id": "01GHI...",
  "max_stops_per_route": 30
}
```

Output (sin persistir):
```json
{
  "routes": [{
    "vehicleName": "Furgoneta 1",
    "vehiclePublicId": "01DEF...",
    "distanceKm": 42.5,
    "durationMinutes": 85,
    "capacity": {
      "weightKg": 120.5, "maxWeightKg": 500, "weightPct": 24.1,
      "volumeM3": 1.2, "maxVolumeM3": 5.0, "volumePct": 24.0,
      "parcels": 15, "maxParcels": 50, "parcelPct": 30.0,
      "valid": true
    },
    "stops": [{
      "sequence": 1,
      "shipmentPublicId": "01ABC...",
      "recipientName": "Juan Garcia",
      "address": "Calle Gran Via 42",
      "latitude": 40.42,
      "longitude": -3.70,
      "weightKg": 5.2
    }]
  }],
  "unassigned": [{
    "shipmentPublicId": "01XYZ...",
    "reason": "No vehicle with required skill REFRIGERATED"
  }]
}
```

## Servicios existentes a reutilizar (NO modificar)

| Archivo | Uso |
|---------|-----|
| `Service/RouteBuilder.php` | Logica de optimizacion VROOM — llamar `buildRoutes()` |
| `Service/RouteCapacityValidator.php` | Validacion de capacidad post-build |
| `Service/VroomApiClient.php` | Cliente HTTP VROOM (usado internamente por RouteBuilder) |
| `Service/OsrmClient.php` | Distancias reales (usado por RouteBuilder) |
| `Service/ShipmentCsvImporter.php` | Import CSV (reutilizar desde modal) |
| `Service/CsvQualityAnalyzer.php` | Calidad CSV |

## Templates de referencia

| Archivo | Patron a copiar |
|---------|-----------------|
| `templates/tracking/map.html.twig` | Mapa Leaflet + Alpine.js |
| `templates/admin/shipments_import.html.twig` | Import CSV drag&drop |
| `templates/admin/route/form.html.twig` | Modal de optimizacion |

## Archivo existente a modificar

| Archivo | Cambio |
|---------|--------|
| `templates/base.html.twig` | Agregar link "Planificador" en sidebar nav (seccion Rutas) |

## Implementacion paso a paso

### Paso 1: Controller con endpoints
1. Crear `RoutePlannerController` con las 4 rutas
2. Endpoint `shipments`: query Shipments WHERE no RouteStop exists + filtro customer
3. Endpoint `preview`: instanciar RouteBuilder, buildRoutes() sin flush, serializar resultado a JSON
4. Endpoint `confirm`: buildRoutes() + flush() + redirect

### Paso 2: Template base con mapa
1. Crear `admin/route_planner/index.html.twig` extendiendo `base.html.twig`
2. Layout: grid 2 columnas (panel izquierdo 400px + mapa flex-1)
3. Inicializar Leaflet map centrado en Madrid (40.4168, -3.7038)
4. Alpine.js component `routePlanner()` con estado del wizard

### Paso 3: Paso 1 del wizard — Seleccion envios
1. Dropdown de clientes (cargado server-side via Twig)
2. Fetch envios al cambiar cliente -> `GET /admin/route-planner/shipments?customer=X`
3. Tabla con checkboxes, select all, filtro de texto
4. Markers en mapa sincronizados con seleccion
5. Click en marker -> selecciona/deselecciona en tabla

### Paso 4: Paso 2 del wizard — Vehiculos
1. Fetch vehiculos activos (server-side o API)
2. Tarjetas por vehiculo con capacidad visual (barras)
3. Checkboxes para seleccionar vehiculos
4. Selector de origen (CustomerLocation)
5. Input max paradas

### Paso 5: Paso 3 del wizard — Preview
1. Boton "Optimizar" -> POST preview con IDs seleccionados
2. Pintar resultado: polylines coloreadas + markers numerados por ruta
3. Panel con tarjetas de rutas y resumen de capacidad
4. Indicar envios no asignados con icono de alerta
5. Botones: "Confirmar" o "Volver"

### Paso 6: Link en sidebar
1. Agregar entrada en el menu lateral de admin

## Verificacion

1. **Test manual del flujo completo**:
   - Cargar fixtures (`doctrine:fixtures:load`)
   - Navegar a `/admin/route-planner`
   - Seleccionar cliente -> ver envios en mapa
   - Seleccionar vehiculos -> configurar
   - Click "Optimizar" -> ver preview con rutas coloreadas
   - Click "Confirmar" -> verificar rutas creadas en `/admin/routes`

2. **Validaciones a verificar**:
   - Sin envios seleccionados: boton deshabilitado
   - Sin vehiculos: boton deshabilitado
   - Envios sin coordenadas: warning visible
   - Capacidad excedida: alerta roja en tarjeta de ruta
   - Envios no asignables: listados con motivo

3. **Lint**: `make lint` sin errores
