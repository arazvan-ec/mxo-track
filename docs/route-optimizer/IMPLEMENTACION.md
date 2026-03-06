# Implementación: 6 Planes de Optimización de Rutas

> Estado de implementación de los planes definidos en esta carpeta.

## Estado General

| # | Plan | Estado | Agente | Archivos clave |
|---|------|--------|--------|----------------|
| 5 | Servicio Variable | 🔲 Pendiente | Worktree 1 | Shipment.php, VroomRequestMapper.php |
| 10 | Max Paradas VROOM | 🔲 Pendiente | Worktree 2 | VroomRequestMapper.php, RouteBuilder.php |
| 11 | Carga LIFO | 🔲 Pendiente | Worktree 3 | LoadingManifestGenerator.php (nuevo) |
| 12 | Rutas Históricas | 🔲 Pendiente | Worktree 4 | RouteAnalysisService.php (nuevo) |
| B | Prioridad Envíos | 🔲 Pendiente | Worktree 5 | ShipmentPriority.php (nuevo), Shipment.php |
| C | Skills Vehículo | 🔲 Pendiente | Worktree 6 | VehicleSkill.php (nuevo), Vehicle.php, Shipment.php |

## Leyenda

| Estado | Significado |
|--------|-------------|
| 🔲 Pendiente | No iniciado |
| 🚧 En progreso | Agente trabajando |
| ✅ Completado | Implementado, commitado y pushed |
| ❌ Error | Falló, requiere intervención |

---

## Estrategia de Ejecución

### Paralelización con Git Worktrees
Cada plan se implementa en un **git worktree aislado** para evitar conflictos. Todos trabajan sobre el mismo branch base `claude/evaluate-tech-stack-ofYNU`.

### Archivos compartidos (riesgo de conflicto)
- `Shipment.php` → tocado por planes #5, B, C (propiedades distintas)
- `VroomRequestMapper.php` → tocado por planes #5, #10, B, C (zonas distintas del código)
- `ShipmentCsvImporter.php` → tocado por planes #5, B

### Orden de merge
1. Planes que **solo crean archivos nuevos**: #11 (LIFO), #12 (Históricas) — sin conflicto
2. Planes que **modifican entidades**: #5, B, C — propiedades distintas, merge manual si es necesario
3. Plan que **modifica mapper + builder**: #10 — merge último por tocar VroomRequestMapper

---

## Detalle por Plan

### Plan #5: Servicio Variable
- **Entity**: `Shipment.serviceTimeSeconds` (int, nullable, default null = 300s)
- **Mapper**: `VroomRequestMapper` usa `$shipment->getServiceTimeSeconds() ?? 300`
- **CSV**: nueva columna opcional `service_time_seconds`
- **Migración**: `ALTER TABLE shipment ADD service_time_seconds INT DEFAULT NULL`

### Plan #10: Max Paradas VROOM
- **Mapper**: `VroomRequestMapper.mapVehicles()` acepta `?int $maxTasks`, incluye `max_tasks` en JSON
- **Builder**: `RouteBuilder.buildRoutes()` pasa `$maxStopsPerRoute` al mapper
- Sin cambios de entidad

### Plan #11: Carga LIFO
- **DTO nuevo**: `LoadingManifestItem.php`
- **Service nuevo**: `LoadingManifestGenerator.php` — reverse de delivery stops
- **Controller nuevo**: `GET /api/routes/{publicId}/loading-manifest`
- Sin cambios de entidad

### Plan #12: Rutas Históricas
- **DTOs nuevos**: `RouteAnalysisResult.php`, `StopAnalysis.php`
- **Service nuevo**: `RouteAnalysisService.php` — planned vs actual comparison
- **Controller nuevo**: `GET /api/routes/{publicId}/analysis`
- Sin cambios de entidad

### Plan B: Prioridad Envíos
- **Enum nuevo**: `ShipmentPriority.php` (LOW=0, NORMAL=25, HIGH=50, URGENT=75, CRITICAL=100)
- **Entity**: `Shipment.priority` (ShipmentPriority, default NORMAL)
- **Mapper**: job VROOM incluye `priority`
- **CSV**: nueva columna opcional `priority`
- **Migración**: `ALTER TABLE shipment ADD priority SMALLINT DEFAULT 25 NOT NULL`

### Plan C: Skills Vehículo
- **Enum nuevo**: `VehicleSkill.php` (REFRIGERATED=1, HEAVY_LOAD=2, PEDESTRIAN_ACCESS=3, HAZMAT=4, FRAGILE=5)
- **Entity Vehicle**: `skills` (JSON, default [])
- **Entity Shipment**: `requiredSkills` (JSON, default [])
- **Mapper**: vehicles y jobs incluyen `skills` array
- **Migración**: `ALTER TABLE vehicle ADD skills JSON DEFAULT '[]'` + `ALTER TABLE shipment ADD required_skills JSON DEFAULT '[]'`

---

## Verificación Post-Implementación

1. `make lint` — sin errores de sintaxis PHP
2. Verificar nuevas propiedades en entidades
3. Verificar VroomRequestMapper incluye: service time variable, max_tasks, priority, skills
4. Verificar endpoints nuevos responden correctamente
5. Verificar enums y DTOs están bien formados
