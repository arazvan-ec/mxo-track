# Plan: Peso de Prioridad en Envios

## Objetivo

VROOM supports a `priority` field (integer 0-100) on jobs. Higher priority jobs are scheduled first in the route. This plan adds a priority level to Shipment and maps it to VROOM's priority field, ensuring urgent/high-value shipments are delivered earlier in the route.

## Estado Actual

- **Shipment entity** (`backend/src/Entity/Shipment.php`): No `priority` field exists. The entity has basic fields (reference, customer, recipient info, coordinates, notes, tracking token) but no concept of priority.
- **VroomRequestMapper** (`backend/src/Service/VroomRequestMapper.php`): Does not exist yet. The VROOM integration (request/response mapping) has not been implemented in this branch.
- **VROOM priority**: 0 = lowest, 100 = highest. Jobs with higher priority are handled first by the solver.

## Cambios Propuestos

### 1. Enum: ShipmentPriority

- File: `backend/src/Enum/ShipmentPriority.php`
- Values:
  - `LOW` = 0
  - `NORMAL` = 25
  - `HIGH` = 50
  - `URGENT` = 75
  - `CRITICAL` = 100
- Method: `toVroomPriority(): int` — returns the integer value for VROOM's job priority field.
- Method: `label(): string` — returns a human-readable label for the UI.

```php
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
- Doctrine column: `priority` (smallint), not nullable, default 25.

```php
#[ORM\Column(type: 'smallint', options: ['default' => 25])]
private ShipmentPriority $priority = ShipmentPriority::NORMAL;

public function getPriority(): ShipmentPriority { return $this->priority; }
public function setPriority(ShipmentPriority $priority): void { $this->priority = $priority; }
```

> **Nota sobre Doctrine enum mapping**: Se almacena como `smallint` (el valor backed del enum) en lugar de `string` para permitir ordenacion por prioridad a nivel SQL (`ORDER BY priority DESC`).

### 3. Migration

- Add `priority` column to `shipment` table.
- Default value `25` (NORMAL) para que los envios existentes no se rompan.

```sql
ALTER TABLE shipment ADD priority SMALLINT DEFAULT 25 NOT NULL;
```

### 4. VroomRequestMapper

- When `VroomRequestMapper` is implemented, include priority in the job mapping:

```php
$job = [
    'id' => $shipment->getId(),
    'location' => [$shipment->getLongitude(), $shipment->getLatitude()],
    'priority' => $shipment->getPriority()->toVroomPriority(),
    // ... other fields (delivery amounts, skills, etc.)
];
```

- Default to `0` if priority is null (backwards compatible, though with the non-nullable column + default this should not happen).

### 5. DTO / API

- Add `priority` field to shipment creation/update DTOs.
- Accept string values: `"low"`, `"normal"`, `"high"`, `"urgent"`, `"critical"`.
- Validation: must be one of the valid enum cases.
- CSV import: new optional `priority` column. If omitted, defaults to `NORMAL`.

```php
// In CreateShipmentDto or similar
#[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent', 'critical'])]
public ?string $priority = 'normal';
```

### 6. Admin UI

- **Shipment form**: Priority selector (dropdown) with colored labels.
- **Shipment list**: Priority badge/icon next to reference.
  - CRITICAL: red badge
  - URGENT: orange badge
  - HIGH: yellow badge
  - NORMAL: gray/default
  - LOW: muted/light
- **Sort by priority**: Option in shipment list table headers.
- **Route builder preview**: Show priority indicators on shipments before building the route, so the operator can see which shipments are high priority.

## Modelo de Datos

```php
#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
class Shipment implements CustomerScopedEntityInterface, SoftDeletableInterface
{
    use PublicIdTrait;
    use SoftDeleteTrait;

    // ... existing fields ...

    #[ORM\Column(type: 'smallint', options: ['default' => 25])]
    private ShipmentPriority $priority = ShipmentPriority::NORMAL;

    // ... existing constructor ...

    public function getPriority(): ShipmentPriority
    {
        return $this->priority;
    }

    public function setPriority(ShipmentPriority $priority): void
    {
        $this->priority = $priority;
    }
}
```

## Verificacion

1. **Create shipments with different priorities** — Verify each priority level is stored correctly in the database as the expected smallint value.
2. **Build route** — Verify VROOM assigns higher priority shipments to earlier positions in the optimized route.
3. **Critical shipment handling** — Verify a CRITICAL shipment is never left unassigned if vehicle capacity allows it.
4. **Backwards compatibility** — Verify existing shipments (created before migration) receive `NORMAL` (25) as default priority.
5. **CSV import** — Verify CSV import works with and without the priority column.
6. **API round-trip** — Create via API with priority, read back, confirm value matches.
