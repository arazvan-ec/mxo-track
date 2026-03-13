# Plan: Shipment CRUD Admin + Fixtures Fix

**Goal:** Fix demo fixtures to create 200 unassigned shipments and add a full CRUD admin for shipments.
**Spec:** `docs/superpowers/specs/2026-03-12-shipment-crud-and-fixtures-design.md`

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `src/Entity/Shipment.php` | Edit | Add `setReference()` setter (needed for form binding) |
| `src/DataFixtures/DemoRouteFixture.php` | Edit | Remove Route/RouteStop, create 200 unassigned shipments |
| `src/Form/ShipmentType.php` | Create | Form type for Shipment CRUD |
| `src/Controller/Admin/ShipmentAdminController.php` | Create | CRUD controller (index, new, edit, delete) |
| `templates/admin/shipment/index.html.twig` | Create | Paginated list with customer filter |
| `templates/admin/shipment/form.html.twig` | Create | Create/edit form |
| `templates/_sidebar_content.html.twig` | Edit | Add "Envios" link in Operations section |

## Tasks

### Task 1: Add `setReference()` to Shipment entity

Shipment's `reference` is set only in the constructor. The Symfony form needs a setter to bind data.

- [ ] Add `public function setReference(string $reference): void` to `src/Entity/Shipment.php`

### Task 2: Fix DemoRouteFixture — 200 unassigned shipments

- [ ] Edit `src/DataFixtures/DemoRouteFixture.php`:
  - Change `buildScenario()` to `buildScenario(200)`
  - Remove the Route + RouteStop creation block (lines 36-58)
  - Keep customer, warehouse, vehicles, drivers, customerUser persistence
- [ ] Verify: `php bin/console doctrine:fixtures:load -n` runs without error

### Task 3: Create ShipmentType form

- [ ] Create `src/Form/ShipmentType.php` with fields:
  - `reference` (TextType, required, empty_data='')
  - `customer` (EntityType, Customer::class, required)
  - `serviceType` (EnumType, ServiceType::class)
  - `priority` (EnumType, ShipmentPriority::class)
  - `recipientName` (TextType, optional)
  - `recipientPhone` (TextType, optional)
  - `address` (TextType, optional)
  - `latitude` (NumberType, optional)
  - `longitude` (NumberType, optional)
  - `totalWeightKg` (NumberType, optional, scale=2)
  - `totalVolumeM3` (NumberType, optional, scale=4)
  - `totalParcels` (IntegerType, default 1)
  - `estimatedDeliveryDate` (DateType, optional, widget=single_text)
  - `serviceTimeSeconds` (IntegerType, optional)
  - `notes` (TextareaType, optional)
  - `requiredSkills` (ChoiceType, multiple, expanded — same as VehicleType)
  - data_class: Shipment::class
- [ ] Use same Tailwind classes as VehicleType

### Task 4: Create ShipmentAdminController

- [ ] Create `src/Controller/Admin/ShipmentAdminController.php`:
  - Route prefix: `/admin/shipments` (note: must not conflict with existing `admin_shipments_import`)
  - `#[IsGranted('ROLE_ADMIN')]`
  - `index(Request)`: paginated list, 20/page, optional `?customer=publicId` filter, order by createdAt DESC
  - `new(Request)`: create form. Constructor needs `(reference, customer)` — handle via form event or manual instantiation
  - `edit(string $publicId, Request)`: edit form
  - `delete(string $publicId, Request)`: soft-delete via `$shipment->softDelete()`, CSRF validation

**Constructor handling for `new()`:**
Since Shipment requires `(reference, customer)` in constructor, create with dummy values then let form overwrite:
```php
$shipment = new Shipment('', $defaultCustomer ?? $customers[0]);
```
Or use `empty_data` callback in form options. Simplest: create a placeholder and let setters update.

Actually, better approach: add `setCustomer()` to entity if missing, or use form's `empty_data` factory.

### Task 5: Create list template

- [ ] Create `templates/admin/shipment/index.html.twig`:
  - Header: "Envios" + "Nuevo envio" button
  - Customer filter dropdown (optional, top of page)
  - Table columns: Referencia, Cliente, Destinatario, Direccion, Prioridad (badge), Peso/Vol/Bultos, Creado, Acciones
  - Priority badges: CRITICAL=red, HIGH=orange, URGENT=amber, NORMAL=blue, LOW=gray
  - Pagination (same pattern as vehicles)
  - Delete: inline form with confirm, CSRF token

### Task 6: Create form template

- [ ] Create `templates/admin/shipment/form.html.twig`:
  - Header: "Nuevo envio" / "Editar envio"
  - Sections:
    1. Datos basicos: reference, customer, serviceType, priority
    2. Destinatario: recipientName, recipientPhone
    3. Ubicacion: address, latitude, longitude (3-col grid for lat/lng)
    4. Carga: totalWeightKg, totalVolumeM3, totalParcels (3-col grid)
    5. Programacion: estimatedDeliveryDate, serviceTimeSeconds
    6. Adicional: notes, requiredSkills
  - Footer: Cancel + Submit buttons

### Task 7: Add sidebar link

- [ ] Edit `templates/_sidebar_content.html.twig`:
  - Add "Envios" link after "Rutas" (line ~106) and before "Planificador"
  - Route: `admin_shipments_index`
  - Active pattern: `starts with 'admin_shipments'`
  - Icon: package/box SVG

### Task 8: Verify

- [ ] `make lint` passes
- [ ] `php bin/console router:match /admin/shipments` resolves correctly
- [ ] No route name conflicts with existing `admin_shipments_import`
- [ ] Commit and push
