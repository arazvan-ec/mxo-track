# Planes de Ejecucion — Route Optimizer

## Contexto

En `docs/route-optimizer/` hay 6 planes de mejora del optimizador de rutas, todos en estado "Planificado". El sistema ya tiene la integracion VROOM+OSRM funcional (`VroomRequestMapper`, `VroomResponseMapper`, `VroomApiClient`, `RouteBuilder`). Los planes agregan funcionalidades que enriquecen la optimizacion: tiempos variables, prioridades, skills, max paradas, manifiesto LIFO y analisis historico.

**Nota**: Algunos planes documentan el estado como "VroomRequestMapper no existe todavia" pero ya esta implementado. Los planes se ejecutan sobre el codigo actual real.

## Orden de Implementacion Recomendado

Los planes se agrupan en 3 fases segun prioridad y dependencias:

### Fase 1 — Prioridad Alta (independientes entre si)
1. **PLAN_10: Max Paradas VROOM** — Mas rapido (~30 min), solo 2 cambios
2. **PLAN_05: Servicio Variable** — Entity + mapper + CSV + UI
3. **PLAN_B: Prioridad Envios** — Enum + entity + mapper + CSV + UI

### Fase 2 — Prioridad Media (independientes entre si)
4. **PLAN_C: Skills Vehiculo** — Enum + 2 entities + mapper + validator + UI
5. **PLAN_11: Carga LIFO** — Read-only, sin cambios de entidad

### Fase 3 — Prioridad Baja
6. **PLAN_12: Rutas Historicas** — Analisis, DTOs, recomendaciones

---

## PLAN_10: Max Paradas por Ruta (VROOM max_tasks)

**Complejidad**: S (Small) — ~30 min

### Archivos a modificar
- `backend/src/Service/VroomRequestMapper.php` — agregar `$maxTasks` a `mapVehicles()`
- `backend/src/Service/RouteBuilder.php` — pasar `$maxStopsPerRoute` al mapper

### Pasos
1. **VroomRequestMapper::mapVehicles()** — agregar parametro `?int $maxTasks = null`
   - Si `$maxTasks !== null`, incluir `'max_tasks' => $maxTasks` en cada vehiculo VROOM
   - Si null, omitir (VROOM default = ilimitado)
2. **RouteBuilder::buildRoutes()** — pasar `$maxStopsPerRoute` al mapper:
   ```php
   $vehicleData = $this->requestMapper->mapVehicles($vehicles, $origin, $maxStopsPerRoute);
   ```

### Verificacion
- Build ruta con maxStopsPerRoute=5, verificar que ninguna ruta tiene >5 paradas
- Build sin parametro, verificar que VROOM asigna libremente
- Verificar que envios excedentes aparecen en lista de unassigned

---

## PLAN_05: Tiempo de Servicio Variable

**Complejidad**: M (Medium)

### Archivos a crear
- Migration: `backend/migrations/VersionXXX.php`

### Archivos a modificar
- `backend/src/Entity/Shipment.php` — nueva propiedad `serviceTimeSeconds`
- `backend/src/Service/VroomRequestMapper.php` — usar tiempo por envio o fallback 300s
- `backend/src/Service/ShipmentCsvImporter.php` — columna opcional `service_time_seconds`
- Formulario admin de shipment (si existe) — dropdown con presets

### Pasos
1. **Shipment entity** — agregar propiedad:
   ```php
   #[ORM\Column(type: 'integer', nullable: true)]
   #[Assert\Range(min: 60, max: 1800)]
   private ?int $serviceTimeSeconds = null;
   ```
   + getter/setter
2. **Migration** — `ALTER TABLE shipment ADD service_time_seconds INT DEFAULT NULL`
3. **VroomRequestMapper::mapJobs()** — cambiar linea 75:
   ```php
   'service' => $shipment->getServiceTimeSeconds() ?? self::SERVICE_TIME_SECONDS,
   ```
   (Renombrar constante a `DEFAULT_SERVICE_TIME_SECONDS` para claridad)
4. **ShipmentCsvImporter** — agregar `service_time_seconds` como columna 13 opcional en `EXPECTED_COLUMNS`, parsear como int con validacion 60-1800
5. **UI admin** — agregar campo al formulario de shipment con presets: 120s, 300s, 480s, 900s

### Verificacion
- Crear shipment con serviceTimeSeconds=480, verificar que VROOM recibe `"service": 480`
- Crear shipment sin serviceTimeSeconds, verificar fallback a 300s
- Importar CSV con/sin columna service_time_seconds
- Validar rango: rechazar valores <60 o >1800

---

## PLAN_B: Prioridad de Envios

**Complejidad**: M (Medium)

### Archivos a crear
- `backend/src/Enum/ShipmentPriority.php`
- Migration: `backend/migrations/VersionXXX.php`

### Archivos a modificar
- `backend/src/Entity/Shipment.php` — nueva propiedad `priority`
- `backend/src/Service/VroomRequestMapper.php` — incluir `priority` en jobs
- `backend/src/Service/ShipmentCsvImporter.php` — columna opcional `priority`
- Formulario/vista admin de shipment — dropdown + badges de color

### Pasos
1. **ShipmentPriority enum** — crear en `backend/src/Enum/`:
   ```php
   enum ShipmentPriority: int {
       case LOW = 0; case NORMAL = 25; case HIGH = 50;
       case URGENT = 75; case CRITICAL = 100;
   }
   ```
   Con metodos `label(): string` (Baja/Normal/Alta/Urgente/Critica)
2. **Shipment entity** — agregar propiedad:
   ```php
   #[ORM\Column(type: 'smallint', enumType: ShipmentPriority::class)]
   private ShipmentPriority $priority = ShipmentPriority::NORMAL;
   ```
3. **Migration** — `ALTER TABLE shipment ADD priority SMALLINT DEFAULT 25 NOT NULL`
4. **VroomRequestMapper::mapJobs()** — agregar al array `$job`:
   ```php
   'priority' => $shipment->getPriority()->value,
   ```
5. **ShipmentCsvImporter** — columna opcional `priority`, acepta nombres (low/normal/high/urgent/critical), default NORMAL
6. **UI admin** — dropdown en formulario, badges coloreados en lista

### Verificacion
- Crear envios con prioridades mixtas, verificar que VROOM pone los urgentes primero
- Envios existentes reciben NORMAL via DEFAULT 25
- CSV sin columna priority funciona (default NORMAL)
- CSV con columna priority mapea correctamente

---

## PLAN_C: Skills y Restricciones de Vehiculo

**Complejidad**: L (Large)

### Archivos a crear
- `backend/src/Enum/VehicleSkill.php`
- Migration: `backend/migrations/VersionXXX.php`

### Archivos a modificar
- `backend/src/Entity/Vehicle.php` — nueva propiedad `skills` (JSON)
- `backend/src/Entity/Shipment.php` — nueva propiedad `requiredSkills` (JSON)
- `backend/src/Service/VroomRequestMapper.php` — mapear skills a vehiculos y jobs
- `backend/src/Service/RouteCapacityValidator.php` — validar match de skills
- Formularios admin de Vehicle y Shipment — multi-select checkboxes

### Pasos
1. **VehicleSkill enum** — crear:
   ```php
   enum VehicleSkill: int {
       case REFRIGERATED = 1; case HEAVY_LOAD = 2;
       case PEDESTRIAN_ACCESS = 3; case HAZMAT = 4; case FRAGILE = 5;
   }
   ```
2. **Vehicle entity** — agregar `skills` (JSON, default []):
   ```php
   #[ORM\Column(type: Types::JSON, nullable: true)]
   private ?array $skills = [];
   ```
   + getters/setters que convierten entre int[] y VehicleSkill[]
3. **Shipment entity** — agregar `requiredSkills` (JSON, default []):
   ```php
   #[ORM\Column(type: Types::JSON, nullable: true)]
   private ?array $requiredSkills = [];
   ```
   + getters/setters analogos
4. **Migration** — una sola:
   ```sql
   ALTER TABLE vehicle ADD skills JSON DEFAULT '[]';
   ALTER TABLE shipment ADD required_skills JSON DEFAULT '[]';
   ```
5. **VroomRequestMapper** — en `mapVehicles()`:
   ```php
   $skills = array_map(fn(VehicleSkill $s) => $s->value, $vehicle->getSkills());
   if ($skills !== []) { $vroomVehicle['skills'] = $skills; }
   ```
   En `mapJobs()`:
   ```php
   $skills = array_map(fn(VehicleSkill $s) => $s->value, $shipment->getRequiredSkills());
   if ($skills !== []) { $job['skills'] = $skills; }
   ```
6. **RouteCapacityValidator** — agregar metodo `validateSkillMatch(Vehicle, Shipment): bool`
   - Vehiculo debe tener TODOS los skills requeridos: `empty(array_diff($required, $vehicleSkills))`
7. **UI admin** — checkboxes multi-select en formularios de Vehicle y Shipment

### Verificacion
- Vehiculo con [REFRIGERATED] + envio con [REFRIGERATED] → asignado
- Vehiculo sin skills + envio con [REFRIGERATED] → no asignado (unassigned)
- Envio sin requiredSkills → cualquier vehiculo puede atenderlo
- Superset funciona: vehiculo [REFRIGERATED, HAZMAT] puede servir job [REFRIGERATED]

---

## PLAN_11: Manifiesto de Carga LIFO

**Complejidad**: S-M (Small-Medium)

### Archivos a crear
- `backend/src/Dto/LoadingManifestItem.php`
- `backend/src/Service/LoadingManifestGenerator.php`
- `backend/src/Controller/Api/LoadingManifestApiController.php` (o agregar endpoint en `RouteOptimizationApiController`)

### Pasos
1. **LoadingManifestItem DTO**:
   ```php
   final class LoadingManifestItem {
       public function __construct(
           public readonly int $loadingOrder,
           public readonly int $deliverySequence,
           public readonly string $shipmentPublicId,
           public readonly string $shipmentReference,
           public readonly ?string $recipientName,
           public readonly string $address,
           public readonly ?string $recipientPhone,
       ) {}
   }
   ```
2. **LoadingManifestGenerator** — servicio que:
   - Consulta RouteStops ordenados por sequence ASC
   - Filtra origin stops y stops sin shipment
   - Invierte el orden (array_reverse)
   - Mapea a LoadingManifestItem con loadingOrder 1, 2, 3...
   - Reutilizar patron de query de `RouteCapacityValidator::getDeliveryStops()`
3. **API endpoint** — `GET /api/routes/{publicId}/loading-manifest`
   - Acceso: ROLE_ADMIN, ROLE_OPERATOR, ROLE_CUSTOMER
   - Devuelve JSON array de LoadingManifestItem
   - Agregar al `RouteOptimizationApiController` existente (mantener controladores agrupados)
4. **UI admin** — boton "Ver manifiesto de carga" en vista de ruta, tabla imprimible

### Verificacion
- Ruta con 5 paradas: loading order 1 = ultima entrega, loading order 5 = primera entrega
- Parada origen excluida
- Paradas sin shipment excluidas
- Ruta sin paradas devuelve []
- Ruta inexistente devuelve 404

---

## PLAN_12: Analisis de Rutas Historicas

**Complejidad**: L (Large) — Fase 1 solamente

### Archivos a crear
- `backend/src/Dto/RouteAnalysisResult.php`
- `backend/src/Dto/StopAnalysis.php`
- `backend/src/Service/RouteAnalysisService.php`
- `backend/src/Controller/Api/RouteAnalysisController.php` (o agregar en controller existente)

### Pasos (Fase 1 unicamente)
1. **StopAnalysis DTO** — metricas por parada:
   - plannedSequence, actualOrder, address, status, deliveredAt, actualServiceTimeSeconds, sequenceDeviation, exceptionCode, exceptionNotes
2. **RouteAnalysisResult DTO** — metricas de ruta:
   - routePublicId, routeName, vehicleName, driverName, actualDurationMinutes, sequenceAdherence, avgActualServiceTimeSeconds, stops[], recommendations[]
3. **RouteAnalysisService**:
   - `analyzeRouteExecution(Route): RouteAnalysisResult`
   - Validar Route.status === DONE
   - Cargar RouteStops, filtrar origin, ordenar por deliveredAt para orden real
   - Calcular: adherencia de secuencia, tiempos de servicio reales, desviaciones
   - Generar recomendaciones (umbrales: >6min servicio, <70% adherencia, >10min en parada, >20% excepciones)
4. **API endpoint** — `GET /api/routes/{publicId}/analysis`
   - Acceso: ROLE_ADMIN, ROLE_OPERATOR
   - 422 si ruta no completada (status != DONE)
5. **Fase 2 se deja para futuro**: agregacion por zona, patrones de conductor, cache

### Verificacion
- Ruta completada con entregas en orden diferente al planificado → adherencia < 100%
- Ruta con entregas en orden → adherencia = 100%
- Ruta no completada → 422
- Recomendaciones generadas correctamente segun umbrales
- Paradas SKIPPED/EXCEPTION excluidas del calculo de adherencia

---

## Migracion Combinada

Se puede crear una **unica migracion** para los planes 05, B y C (los que modifican entidades):

```sql
-- PLAN_05: Service time variable
ALTER TABLE shipment ADD service_time_seconds INT DEFAULT NULL;

-- PLAN_B: Priority
ALTER TABLE shipment ADD priority SMALLINT DEFAULT 25 NOT NULL;

-- PLAN_C: Skills
ALTER TABLE vehicle ADD skills JSON DEFAULT '[]';
ALTER TABLE shipment ADD required_skills JSON DEFAULT '[]';
```

O migraciones separadas por plan para poder implementar incrementalmente.

## Verificacion Global

1. `make lint` — sin errores de sintaxis despues de cada plan
2. `php bin/console doctrine:schema:validate` — esquema sincronizado
3. `php bin/console doctrine:migrations:migrate -n` — migraciones aplicables
4. Test manual: crear shipments con CSV → build rutas con VROOM → verificar que todos los nuevos campos se pasan correctamente en el payload VROOM
