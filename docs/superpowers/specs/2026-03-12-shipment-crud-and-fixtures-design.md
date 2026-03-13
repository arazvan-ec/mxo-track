# Shipment CRUD Admin + Fixtures Fix

**Date:** 2026-03-12
**Status:** Draft

## Problem

1. **Fixtures create 40 shipments but assign ALL to a route** — the route planner's "Cargar envios" shows nothing because it filters for unassigned shipments only.
2. **No admin UI to create/edit/delete shipments manually** — only CSV import exists. Need a full CRUD for manual management.

## Design

### Part 1: Fix Fixtures (200 unassigned shipments)

**Change:** Modify `DemoRouteFixture` to create 200 shipments WITHOUT assigning them to any route. Remove the Route/RouteStop creation from fixtures entirely.

- Call `buildScenario(200)` instead of `buildScenario()`
- Remove the Route + RouteStop creation block (lines 36-58 of DemoRouteFixture)
- The 40 STOPS array in DemoScenarioBuilder cycles via `$i % $stopCount`, so 200 shipments will reuse the 40 addresses ~5 times each with unique references (DEMO-SHP-0001 through DEMO-SHP-0200)

**No changes needed to DemoScenarioBuilder** — it already accepts a `$shipmentCount` parameter and cycles addresses.

### Part 2: Shipment CRUD Admin

Follow the exact same pattern as `VehicleAdminController` / `VehicleType` / vehicle templates.

#### Controller: `ShipmentAdminController`

Route prefix: `/admin/shipments`
Methods:
- `index(Request)` — paginated list (20 per page), filterable by customer
- `new(Request)` — create form, requires selecting a customer
- `edit(string $publicId, Request)` — edit form
- `delete(string $publicId, Request)` — soft-delete via SoftDeleteTrait (already on Shipment entity)

#### Form Type: `ShipmentType`

Fields grouped in sections:

**Basic Info:**
- `reference` (TextType, required) — unique identifier
- `customer` (EntityType, Customer, required) — dropdown of customers
- `serviceType` (EnumType, ServiceType) — delivery/collection/etc.
- `priority` (EnumType, ShipmentPriority)

**Recipient:**
- `recipientName` (TextType, optional)
- `recipientPhone` (TextType, optional)

**Location:**
- `address` (TextType, optional)
- `latitude` (NumberType, optional)
- `longitude` (NumberType, optional)

**Cargo:**
- `totalWeightKg` (NumberType, optional)
- `totalVolumeM3` (NumberType, optional)
- `totalParcels` (IntegerType, default 1)

**Scheduling:**
- `estimatedDeliveryDate` (DateType, optional)
- `preferredWindowStart` (TimeType, optional)
- `preferredWindowEnd` (TimeType, optional)
- `serviceTimeSeconds` (IntegerType, optional)

**Additional:**
- `notes` (TextareaType, optional)
- `requiredSkills` (ChoiceType, multiple, expanded — same pattern as Vehicle skills)

**NOT in form** (auto-generated): publicId, trackingToken, createdAt, parcels

#### Templates

**`admin/shipment/index.html.twig`** — Table columns:
- Reference
- Customer name
- Recipient
- Address (truncated)
- Priority (colored badge)
- Weight/Volume/Parcels
- Created date
- Actions (Edit, Delete)

Filter by customer dropdown at the top (optional enhancement).

**`admin/shipment/form.html.twig`** — Grouped sections matching the form type above.

#### Sidebar Navigation

Add "Envios" link in the OPERATIONS section, between "Rutas" and "Planificador". Use the box/package icon (same as customer shipments sidebar).

#### Notes

- Shipment constructor requires `(reference, customer)` — the form needs special handling: `reference` as form field with `empty_data`, and `customer` as EntityType
- Soft delete via `SoftDeleteTrait` already on entity — delete action calls `$shipment->softDelete()` or equivalent
- No need for `isActive` field — use soft delete instead
- The `reference` field has a unique constraint — form validation will catch duplicates via Doctrine exception or Symfony UniqueEntity constraint
