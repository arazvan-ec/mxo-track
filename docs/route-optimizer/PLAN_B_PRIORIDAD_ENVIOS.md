# Plan: Peso de Prioridad en Envios

## Objetivo

VROOM supports a `priority` field (integer 0-100) on jobs. Higher priority jobs are scheduled first in the route. This plan adds a priority level to Shipment and maps it to VROOM's priority field, ensuring urgent/high-value shipments are delivered earlier in the route.

## Estado Actual

- **Shipment entity** (`backend/src/Entity/Shipment.php`): No `priority` field exists. The entity tracks weight, volume, parcels, time windows, and service type, but has no concept of delivery priority.
- **VroomRequestMapper** (`backend/src/Service/VroomRequestMapper.php`): The `mapJobs()` method builds VROOM jobs with `id`, `location`, `service`, `amount`, and optional `time_windows`. No `priority` key is included in the job payload.
- **VROOM priority semantics**: Integer 0-100. Jobs with higher priority values are scheduled first. Default is 0 (lowest).

## Cambios Propuestos

### 1. Enum: ShipmentPriority

- **File**: `backend/src/Enum/ShipmentPriority.php`
- **Values**:
  - `LOW` = 0
  - `NORMAL` = 25
  - `HIGH` = 50
  - `URGENT` = 75
  - `CRITICAL` = 100
- **Method**: `toVroomPriority(): int` — returns the integer value for VROOM's priority field.
- **Method**: `label(): string` — returns a human-readable label for the UI.

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum ShipmentPriority: int
{
    case LOW = 0;
    case NORMAL = 25;
    case HIGH = 50;
    case URGENT = 75;
    case CRITICAL = 100;

    public function toVroomPriority(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baja',
            self::NORMAL => 'Normal',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
            self::CRITICAL => 'Critica',
        };
    }
}
```

### 2. Entity: Shipment

- Add `priority` property with `ShipmentPriority` enum type, default `NORMAL`.
- Doctrine column: `priority` (smallint), not nullable.

```php
#[ORM\Column(type: 'smallint', enumType: ShipmentPriority::class)]
private ShipmentPriority $priority = ShipmentPriority::NORMAL;

public function getPriority(): ShipmentPriority { return $this->priority; }
public function setPriority(ShipmentPriority $priority): void { $this->priority = $priority; }
```

### 3. Migration

- Add `priority` column (smallint, NOT NULL, DEFAULT 25) to the `shipment` table.
- The DEFAULT 25 ensures existing rows get `NORMAL` priority.

```sql
ALTER TABLE shipment ADD priority SMALLINT DEFAULT 25 NOT NULL;
```

### 4. VroomRequestMapper

- In `mapJobs()`, add `priority` to each job array:

```php
$job = [
    'id' => $index,
    'location' => [$shipment->getLongitude(), $shipment->getLatitude()],
    'service' => self::SERVICE_TIME_SECONDS,
    'amount' => [
        $this->kgToGrams($shipment->getTotalWeightKg()),
        $this->m3ToCm3($shipment->getTotalVolumeM3()),
        $shipment->getTotalParcels(),
    ],
    'priority' => $shipment->getPriority()->toVroomPriority(),
];
```

- Backwards compatible: if `priority` is null for some reason, default to 0.

### 5. DTO / API

- Add `priority` field to shipment creation/update DTOs.
- Accept string values: `"low"`, `"normal"`, `"high"`, `"urgent"`, `"critical"`.
- Validate that the value is a valid `ShipmentPriority` case name.
- **CSV import**: new optional `priority` column. If absent, defaults to `NORMAL`.

### 6. Admin UI

- **Shipment form**: Add priority dropdown selector (`<select>`) with all enum values.
- **Shipment list**: Show priority as a colored badge (e.g., green=low, blue=normal, orange=high, red=urgent, dark-red=critical).
- **Sort**: Allow sorting shipment list by priority (descending = most urgent first).

## Modelo de Datos

```php
// Shipment entity — Doctrine attribute mapping
#[ORM\Column(type: 'smallint', enumType: ShipmentPriority::class)]
private ShipmentPriority $priority = ShipmentPriority::NORMAL;
```

PostgreSQL column:

```
Column   | Type     | Default | Nullable
---------|----------|---------|--------
priority | smallint | 25      | NOT NULL
```

## Verificacion

1. **Create shipments with different priorities** — Verify enum values persist correctly in the database.
2. **Build route** — Send shipments with mixed priorities to VROOM and verify that higher-priority shipments are assigned to earlier positions in the route.
3. **Critical shipment scheduling** — Verify a CRITICAL shipment is never left unassigned if vehicle capacity allows it.
4. **Backwards compatibility** — Existing shipments (without explicit priority) receive `NORMAL` (25) via the database default. No data migration beyond the ALTER TABLE is needed.
5. **CSV import** — Import a CSV without the `priority` column and verify shipments default to NORMAL. Import with the column and verify values map correctly.
6. **Admin UI** — Verify the priority selector appears in the form, and badges render correctly in the list view.
