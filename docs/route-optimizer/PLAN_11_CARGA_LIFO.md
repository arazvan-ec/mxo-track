# Plan: Manifiesto de Carga LIFO

## Objetivo

After route optimization, generate a "loading manifest" that tells warehouse workers the order to load packages into the vehicle. The loading order is the REVERSE of the delivery sequence (LIFO: Last In, First Out). The first delivery stop's packages are loaded last (most accessible), the last delivery stop's packages are loaded first (deepest in vehicle).

## Estado Actual

- Routes have RouteStops with `sequence` numbers (0 = origin, 1+ = delivery stops)
- Each RouteStop links to a `Shipment` (nullable)
- RouteStop has: `address`, `recipientName`, `recipientPhone`, `isOrigin`, `sequence`
- Shipment has: `reference`, `recipientName`, `address`, `recipientPhone`
- **Shipment does NOT currently have weight, volume, or parcels fields** -- these will need to be added separately (see note below)
- `RouteOptimizationService` already queries stops ordered by sequence and separates origin from delivery stops
- No loading manifest or reverse-order projection exists

## Cambios Propuestos

### 1. New DTO: `LoadingManifestItem`

- File: `backend/src/Dto/LoadingManifestItem.php`
- Read-only DTO representing one item in the loading manifest

```php
final class LoadingManifestItem
{
    public function __construct(
        public readonly int $loadingOrder,        // 1 = load first (goes deepest in vehicle)
        public readonly int $deliverySequence,    // original stop sequence number
        public readonly string $shipmentPublicId, // Shipment ULID
        public readonly string $shipmentReference,// e.g. "SHP-001"
        public readonly ?string $recipientName,
        public readonly string $address,
        public readonly ?string $recipientPhone,
        // Future: parcels, weightKg, volumeM3 (pending Shipment entity expansion)
    ) {}
}
```

**Note on weight/volume/parcels**: The current `Shipment` entity has no `weightGrams`, `volumeCm3`, or `parcels` columns. The CLAUDE.md mentions VROOM capacity dimensions `[weight_grams, volume_cm3, parcels]` but these fields are not yet on the entity. The DTO should be designed to accommodate these fields once they are added -- for now they can be omitted or set to null.

### 2. New Service: `LoadingManifestGenerator`

- File: `backend/src/Service/LoadingManifestGenerator.php`
- Single responsibility: given a Route, produce an ordered loading manifest

```php
final class LoadingManifestGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return list<LoadingManifestItem> */
    public function generateManifest(Route $route): array
    {
        // 1. Query RouteStops for this route, ordered by sequence ASC
        // 2. Filter out origin stops (isOrigin = true) and stops without shipment
        // 3. Reverse the order (last delivery = loading order 1)
        // 4. Map each stop to LoadingManifestItem with loadingOrder 1, 2, 3...
    }
}
```

Key logic: the stop query can reuse the same pattern as `RouteOptimizationService::getStopsForRoute()`. The reversal is simply `array_reverse()` on the delivery stops sorted by sequence ASC.

### 3. API Endpoint

- File: `backend/src/Controller/LoadingManifestApiController.php`
- Route: `GET /api/routes/{publicId}/loading-manifest`
- Access: `ROLE_ADMIN`, `ROLE_OPERATOR`, `ROLE_CUSTOMER`
- Returns JSON array of `LoadingManifestItem`

Response example:
```json
[
    {
        "loadingOrder": 1,
        "deliverySequence": 5,
        "shipmentPublicId": "01JXYZ...",
        "shipmentReference": "SHP-005",
        "recipientName": "Maria Garcia",
        "address": "Calle Gran Via 42, Madrid",
        "recipientPhone": "+34600123456"
    },
    {
        "loadingOrder": 2,
        "deliverySequence": 4,
        "shipmentPublicId": "01JXYW...",
        "shipmentReference": "SHP-004",
        "recipientName": "Carlos Lopez",
        "address": "Calle Alcala 15, Madrid",
        "recipientPhone": null
    }
]
```

Error cases:
- Route not found: 404 via `ApiErrorResponder`
- Route has no delivery stops: return empty array `[]`

### 4. Admin UI (future)

- Add "Ver manifiesto de carga" button in route detail view
- Show loading order table with columns: #Carga, #Entrega, Referencia, Destinatario, Direccion
- Printable version for warehouse use (label generation future enhancement)

## Modelo de Datos

**No entity changes required.** This is a read-only projection of existing data in reverse order.

When `Shipment` gains weight/volume/parcels fields (planned for route optimization capacity work), the DTO and service should be updated to include:
- `parcels: ?int`
- `weightKg: ?float` (converted from `weightGrams`)
- `volumeM3: ?float` (converted from `volumeCm3`)

## Dependencias

- `RouteStop` entity (exists)
- `Route` entity (exists)
- `Shipment` entity (exists)
- `RouteStopRepository` (exists)
- `ApiErrorResponder` (exists, for consistent error responses)
- `PublicIdTrait` on RouteStop and Shipment (exists, for public ID exposure)

## Verificacion

1. Create a route with 5 delivery stops (sequence 1-5) + 1 origin stop (sequence 0)
2. `GET /api/routes/{publicId}/loading-manifest`
3. Verify loading order is reversed: stop with sequence 5 has `loadingOrder: 1`, stop with sequence 1 has `loadingOrder: 5`
4. Verify origin stop (sequence 0, `isOrigin: true`) is excluded from manifest
5. Verify stops without a linked shipment are excluded
6. Verify all shipment details (publicId, reference, recipient, address) are present
7. Verify empty route returns `[]`
8. Verify 404 for non-existent route publicId

## Estimacion

- DTO: ~30 min
- Service: ~1 hour
- Controller + routing: ~1 hour
- Tests: ~1 hour
- Total: ~3.5 hours
