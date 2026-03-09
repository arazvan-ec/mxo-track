# Plan: Trasladar Max Paradas por Ruta a VROOM

## Objetivo

The `maxStopsPerRoute` parameter (default 30) exists in RouteBuilder but is never passed to VROOM. VROOM supports `max_tasks` per vehicle which limits task assignments. This plan connects the two.

## Estado Actual

- RouteBuilder.php accepts maxStopsPerRoute but doesn't use it in VROOM request
- VroomRequestMapper doesn't include max_tasks in vehicle mapping
- The existing RouteOptimizationService uses a nearest-neighbor heuristic and has no VROOM integration yet
- VROOM docker service is configured on port 5100 (docker-compose.local.yml) but no PHP client exists

## Cambios Propuestos

### 1. VroomRequestMapper

- Add `maxTasks` parameter to `mapVehicles()` method
- Include `"max_tasks": $maxTasks` in each VROOM vehicle object
- If null, omit the field (VROOM default = unlimited)

```php
// In mapVehicles(), for each vehicle array:
if ($maxTasks !== null) {
    $vehicle['max_tasks'] = $maxTasks;
}
```

### 2. RouteBuilder

- Pass maxStopsPerRoute to VroomRequestMapper.mapVehicles()
- The parameter flows: API controller -> RouteBuilder -> VroomRequestMapper -> VROOM JSON

```php
$vroomVehicles = $this->vroomRequestMapper->mapVehicles(
    $vehicles,
    maxTasks: $maxStopsPerRoute,
);
```

### 3. API

- The max_stops_per_route parameter already exists in the API endpoint
- No API changes needed, just wiring

## VROOM max_tasks Reference

From VROOM docs, `max_tasks` is a vehicle-level integer constraint:

```json
{
  "vehicles": [
    {
      "id": 1,
      "start": [2.35044, 48.71764],
      "capacity": [100000, 500000, 30],
      "max_tasks": 5
    }
  ]
}
```

When a vehicle reaches its `max_tasks` limit, remaining jobs are left unassigned in the VROOM response.

## Verificacion

1. Build route with maxStopsPerRoute=5, verify no route has more than 5 stops
2. Build route without the parameter, verify VROOM assigns freely
3. Verify excess shipments appear in unassigned list
4. Check that the VROOM response `unassigned` array is properly mapped back to unassigned shipments in the UI
