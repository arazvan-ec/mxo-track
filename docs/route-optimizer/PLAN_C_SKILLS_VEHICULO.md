# Plan: Skills y Restricciones de Vehiculo

## Objetivo

Not every vehicle can deliver every shipment. VROOM supports `skills` matching: a vehicle has a set of skills (integers), and a job requires a set of skills. A job can only be assigned to a vehicle that has ALL the required skills. This enables: refrigerated transport, heavy-load vehicles, pedestrian-zone access, hazmat certification, etc.

## Estado Actual

### Vehicle (`backend/src/Entity/Vehicle.php`)
- Properties: `name`, `traccarDeviceId`, `isActive`, `createdAt`, `updatedAt`
- Uses `PublicIdTrait` and `SoftDeleteTrait`
- **No skills field exists**

### Shipment (`backend/src/Entity/Shipment.php`)
- Properties: `reference`, `customer`, `recipientName`, `recipientPhone`, `address`, `latitude`, `longitude`, `notes`, `trackingToken`, `createdAt`
- Uses `PublicIdTrait` and `SoftDeleteTrait`, implements `CustomerScopedEntityInterface`
- **No required skills field exists**

### VroomRequestMapper
- **Does not exist yet.** The route optimization currently uses `RouteOptimizationService` which implements a nearest-neighbor heuristic (Haversine distance). The VROOM integration described in CLAUDE.md (`VroomRequestMapper`, `VroomResponseMapper`, `VroomApiClient`, `RouteBuilder`) is planned but not yet implemented.
- When `VroomRequestMapper` is created, it will need to include skills in both vehicle and job mappings.

## Cambios Propuestos

### 1. Enum: VehicleSkill

- File: `backend/src/Enum/VehicleSkill.php`
- Backed enum (`int`):

```php
enum VehicleSkill: int
{
    case REFRIGERATED      = 1; // Transporte refrigerado
    case HEAVY_LOAD        = 2; // Carga pesada, camion con rampa
    case PEDESTRIAN_ACCESS = 3; // Acceso a zonas peatonales, vehiculo pequeno
    case HAZMAT            = 4; // Materiales peligrosos
    case FRAGILE           = 5; // Equipamiento especial para fragiles
}
```

- Extensible: new cases can be added without migration (stored as JSON array of ints)
- Each case maps directly to a VROOM skill integer

### 2. Entity: Vehicle

Add `skills` property:

```php
#[ORM\Column(type: Types::JSON, nullable: true)]
private ?array $skills = [];
```

- Doctrine column: `skills` (json, nullable, default `[]`)
- Getter returns `VehicleSkill[]`:

```php
/** @return VehicleSkill[] */
public function getSkills(): array
{
    return array_filter(
        array_map(
            static fn (int $v): ?VehicleSkill => VehicleSkill::tryFrom($v),
            $this->skills ?? [],
        ),
    );
}

/** @param VehicleSkill[] $skills */
public function setSkills(array $skills): void
{
    $this->skills = array_map(
        static fn (VehicleSkill $s): int => $s->value,
        $skills,
    );
}
```

### 3. Entity: Shipment

Add `requiredSkills` property:

```php
#[ORM\Column(type: Types::JSON, nullable: true)]
private ?array $requiredSkills = [];
```

- Doctrine column: `required_skills` (json, nullable, default `[]`)
- If empty, any vehicle can deliver it (VROOM default behavior)
- Getter/setter pattern identical to Vehicle:

```php
/** @return VehicleSkill[] */
public function getRequiredSkills(): array
{
    return array_filter(
        array_map(
            static fn (int $v): ?VehicleSkill => VehicleSkill::tryFrom($v),
            $this->requiredSkills ?? [],
        ),
    );
}

/** @param VehicleSkill[] $requiredSkills */
public function setRequiredSkills(array $requiredSkills): void
{
    $this->requiredSkills = array_map(
        static fn (VehicleSkill $s): int => $s->value,
        $requiredSkills,
    );
}
```

### 4. Migrations

Single migration adding both columns:

```sql
ALTER TABLE vehicle ADD skills JSON DEFAULT '[]';
ALTER TABLE shipment ADD required_skills JSON DEFAULT '[]';
```

### 5. VroomRequestMapper (when created)

Vehicle mapping must include skills:

```php
// Vehicle -> VROOM vehicle
$vroomVehicle = [
    'id'       => $vehicle->getId(),
    'start'    => [$depot->getLongitude(), $depot->getLatitude()],
    'capacity' => [$weightGrams, $volumeCm3, $parcels],
    'skills'   => array_map(fn (VehicleSkill $s) => $s->value, $vehicle->getSkills()),
    // ...
];
```

Job mapping must include required skills:

```php
// Shipment -> VROOM job
$vroomJob = [
    'id'       => $shipment->getId(),
    'location' => [$shipment->getLongitude(), $shipment->getLatitude()],
    'delivery' => [$weightGrams, $volumeCm3, 1],
    'skills'   => array_map(fn (VehicleSkill $s) => $s->value, $shipment->getRequiredSkills()),
    // ...
];
```

- If `skills` array is empty, omit the field entirely (VROOM treats missing `skills` as "no requirements" for jobs and "no capabilities" for vehicles)
- **Important**: a vehicle with no skills cannot serve a job that requires skills. A job with no skills can be served by any vehicle.

### 6. RouteCapacityValidator (when created)

Add skill validation alongside weight/volume/parcels:

```php
public function validateSkillMatch(Vehicle $vehicle, Shipment $shipment): bool
{
    $vehicleSkills = array_map(fn (VehicleSkill $s) => $s->value, $vehicle->getSkills());
    $requiredSkills = array_map(fn (VehicleSkill $s) => $s->value, $shipment->getRequiredSkills());

    // Vehicle must have ALL required skills
    return empty(array_diff($requiredSkills, $vehicleSkills));
}
```

### 7. Admin UI

- **Vehicle form**: multi-select checkboxes for available skills (ChoiceType with `multiple: true`, `expanded: true`)
- **Shipment form**: multi-select for required skills
- **Route view**: visual indicators (badges/icons) showing skill requirements per stop and vehicle capabilities
- **Route builder preview**: warn if a shipment's required skills are not met by any available vehicle

## Modelo de Datos

```
Vehicle
+------------------+----------+----------------------------+
| Column           | Type     | Notes                      |
+------------------+----------+----------------------------+
| id               | BIGINT   | PK auto-increment          |
| public_id        | ULID     | Public identifier          |
| name             | VARCHAR  | Vehicle name               |
| skills           | JSON     | [1, 3] (VehicleSkill vals) |
| ...              |          |                            |
+------------------+----------+----------------------------+

Shipment
+------------------+----------+----------------------------+
| Column           | Type     | Notes                      |
+------------------+----------+----------------------------+
| id               | BIGINT   | PK auto-increment          |
| public_id        | ULID     | Public identifier          |
| reference        | VARCHAR  | Shipment reference         |
| required_skills  | JSON     | [1] (VehicleSkill vals)    |
| ...              |          |                            |
+------------------+----------+----------------------------+

VehicleSkill (enum, not a table)
+----+-------------------+
| 1  | REFRIGERATED      |
| 2  | HEAVY_LOAD        |
| 3  | PEDESTRIAN_ACCESS |
| 4  | HAZMAT            |
| 5  | FRAGILE           |
+----+-------------------+
```

VROOM mapping:

```
Vehicle.skills [REFRIGERATED, PEDESTRIAN_ACCESS] -> VROOM vehicle.skills [1, 3]
Shipment.requiredSkills [REFRIGERATED]           -> VROOM job.skills [1]
```

VROOM will only assign the shipment to a vehicle whose skills are a superset of the job's skills.

## Verificacion

1. Create vehicle with skills `[REFRIGERATED]`
2. Create vehicle without skills `[]`
3. Create shipment requiring `[REFRIGERATED]`
4. Build route -> verify refrigerated shipment assigned only to refrigerated vehicle
5. Create shipment with no required skills -> verify assigned to any vehicle
6. Verify unassigned list shows shipment if no vehicle has required skill
7. Test edge cases:
   - Vehicle with `[REFRIGERATED, HAZMAT]` can serve job requiring `[REFRIGERATED]` (superset)
   - Vehicle with `[REFRIGERATED]` cannot serve job requiring `[REFRIGERATED, HAZMAT]` (subset)
   - Adding a new enum value does not require migration (JSON storage)

## Orden de Implementacion

1. Create `VehicleSkill` enum
2. Add `skills` to `Vehicle` entity + migration
3. Add `requiredSkills` to `Shipment` entity (same migration)
4. Update admin forms (Vehicle, Shipment)
5. Integrate into `VroomRequestMapper` (when VROOM integration is built)
6. Add skill validation to `RouteCapacityValidator`
7. Update route builder UI to show skill warnings
