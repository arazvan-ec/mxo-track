# Customer Locations + Route Origin

## Problem

Routes currently have no origin point. A delivery route needs to start from the customer's warehouse/depot so drivers know where to begin and so the system can calculate proper routing from origin to first stop.

## Decision: Multiple Locations per Customer

Customers may operate from multiple warehouses (e.g., Madrid, Barcelona). A separate `CustomerLocation` entity allows each customer to have N named locations. When creating a route, the operator selects one as the origin.

## Design

### New Entity: `CustomerLocation`

| Field | Type | Notes |
|-------|------|-------|
| id | BIGINT (PK) | Internal, auto-increment |
| public_id | ULID | Via `PublicIdTrait` |
| customer | ManyToOne(Customer) | NOT NULL, CASCADE delete |
| name | string(150) | e.g., "Almacen Madrid" |
| address | string(255) | Full text address |
| latitude | float, nullable | Geocoordinates |
| longitude | float, nullable | Geocoordinates |
| is_default | bool | One default per customer |
| is_active | bool | Soft-deactivation |

Implements `CustomerScopedEntityInterface` for tenant filtering.

### Route Modification

Add `originLocation` (ManyToOne CustomerLocation, nullable) to `Route`. When a route has an `originLocation`, a `RouteStop` with `sequence=0` is auto-created with that location's address and coordinates. This stop represents the departure point, not a delivery.

### RouteStop Origin Marker

Add `isOrigin` boolean (default false) to `RouteStop`. When `sequence=0` and auto-generated from origin, `isOrigin=true`. This lets the UI render it differently (depot icon vs delivery pin) and skip it in delivery progress calculations.

### Geocoding (Hybrid)

- Latitude/longitude fields are manually editable in the `CustomerLocation` form.
- A "Geocodificar" button calls Nominatim (OpenStreetMap) via JavaScript fetch from the browser. No server-side geocoding service.
- If Nominatim fails or is rate-limited, the operator enters coordinates manually.

### Admin UI

- Customer edit page gets a "Ubicaciones" tab/section listing locations with add/edit/delete.
- `CustomerLocationAdminController` under `/admin/customers/{publicId}/locations`.
- `CustomerLocationType` form: name, address, latitude, longitude, isDefault, isActive.
- Route form gets an `originLocation` selector (EntityType filtered by route's customer).

### Fixtures

`DemoRouteFixture` updated:
- Create `CustomerLocation` "Almacen Villaverde" for "Mxo almacen #1" with coordinates (40.3460, -3.6970).
- Set as `originLocation` on the demo route.
- Auto-generate sequence=0 RouteStop.

## Out of Scope

- Server-side geocoding service (calls made from browser JS only).
- Lat/lng range validation (trust operator + Nominatim).
- Modifying existing `Customer.address` field (remains as fiscal/general address).
- Route optimization / distance calculation from origin.
