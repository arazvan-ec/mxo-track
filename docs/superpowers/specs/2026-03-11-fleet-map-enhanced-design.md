# Feature 1.5: Mapa Flota Interactivo Mejorado — Design Spec

## Goal

Enhance the fleet map with skill-differentiated vehicle icons, click popups with detailed info, and a demo mode button for quick demo setup.

## Current State

- `tracking/map.html.twig`: Full-screen Leaflet map, sidebar with vehicle/route lists, SSE position updates
- All vehicle markers identical (blue circle with truck SVG)
- Hover tooltips show name + speed only
- No vehicle skill/route/driver info in map data
- No demo mode shortcut

## Design

### 1. Enrich Vehicle Map Data

Add to `FleetOverviewService.getFleetMapData()` vehicle array:
- `skills`: array of VehicleSkill names (e.g., ['REFRIGERATED', 'HEAVY_LOAD'])
- `route_name`: name of assigned active route (or null)
- `driver_name`: name of driver on assigned route (or null)

### 2. Skill-Based Vehicle Markers

Map primary skill (first in array) to marker color:
- REFRIGERATED → light blue (#0ea5e9)
- HEAVY_LOAD → orange (#f97316)
- HAZMAT → red (#ef4444)
- FRAGILE → pink (#ec4899)
- PEDESTRIAN_ACCESS → green (#22c55e)
- No skills / default → indigo (#6366f1)

Keep the circle+truck icon shape but change border/background color.

### 3. Click Popups

On vehicle marker click, show Leaflet popup with:
- Vehicle name (bold)
- Speed + bearing
- Assigned route name (or "Sin ruta")
- Driver name (or "Sin conductor")
- Skills badges
- Last update time

### 4. Demo Mode Button

Add a button in the map header: "Modo Demo".
- Links to `app:demo:setup` documentation or triggers a fetch to a demo endpoint
- For now: simple link to `/admin/routes` with a tooltip explaining the demo command

## Out of Scope

- Vehicle type enum (doesn't exist; using skills instead)
- GPS trail enhancement (already functional)
- Route optimization from map
