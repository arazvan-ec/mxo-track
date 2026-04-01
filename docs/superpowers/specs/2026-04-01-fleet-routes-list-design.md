# Fleet Routes List — Design Spec

**Fecha:** 2026-04-01
**Tipo:** Enhancement
**Branch:** `claude/fleet-routes-list-C9ck2`

## Problema

En la vista Fleet Map (`/app/admin/fleet-map`), el bottom sheet muestra KPI pills
(VEHICLES, ROUTES, PENDING) pero no hay lista de rutas visible. El usuario no puede
ver ni interactuar con las rutas individuales desde el bottom sheet.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `RouteCardListWidget` | **Include** | Ya renderiza fleet routes con color, status, stops |
| `FleetMapPage.pageData` | **Include** | Ya pasa `routes` + `onSelectRoute` al widget system |
| `handleSelectRoute` | **Include** | Ya maneja selección + fly-to en mapa |
| `useFleetMapData` | **Include** | Ya trae routes completas del API |
| Widget layout system | **Include** | `WidgetRenderer` + `usePageLayout` funcionan |
| `RouteList` (FleetSidebar) | **Omit** | Duplicado, FleetSidebar no se usa en FleetMapPage |
| `FleetSidebar` | **Omit** | No se usa en la página actual |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `FleetSidebar` | Omit | FleetMapPage usa BottomSheet, no sidebar |
| `RouteList` | Omit | `RouteCardListWidget` es el equivalente en el widget system |
| Nuevo endpoint API | Omit | `/api/fleet/map-data` ya devuelve toda la data |
| Nuevo componente | Omit | `RouteCardListWidget` ya existe y funciona |

## Diseño

### Cambio único: agregar `route_card_list` al layout `fleet_map`

Layout actual:
- collapsed: `[kpi_pills]`
- half: `[kpi_pills, vehicle_info]`
- full: `[kpi_pills, vehicle_info, driver_info, map_legend]`

Layout nuevo:
- collapsed: `[kpi_pills]` (sin cambio)
- half: `[kpi_pills, route_card_list]` (reemplaza vehicle_info)
- full: `[kpi_pills, route_card_list, vehicle_info, driver_info, map_legend]`

### Justificación

- En `half`, la lista de rutas es más útil que vehicle_info porque permite navegar
  entre rutas. Vehicle_info se muestra al seleccionar un vehículo específico.
- En `full`, se agrega route_card_list antes de vehicle_info/driver_info para dar
  prioridad a la navegación de rutas.

### Implementación

Nueva migración SQL que actualiza los widgets del layout `fleet_map`:
1. Eliminar widgets actuales de `half` y `full`
2. Insertar nueva configuración con `route_card_list` incluido

No se requieren cambios en frontend — el widget ya existe y recibe la data correcta.
