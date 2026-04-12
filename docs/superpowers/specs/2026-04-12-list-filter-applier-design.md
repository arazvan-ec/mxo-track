# Spec — ListFilterApplier Service (Dual QueryBuilder Refactor)

**Date:** 2026-04-12
**Branch:** `claude/add-customer-filters-ev8cG`
**Approved by user:** Yes (selected Enfoque B — Service inyectable)

## Problema

5 admin list controllers repiten la mecánica de aplicar filtros a `$qb` + `$countQb` en paralelo. ~80 líneas de lógica duplicada. Riesgo: si alguien aplica un filtro solo a `$qb` y olvida `$countQb`, el count no coincide con los resultados paginados.

## Existing Functionality Inventory

| Controller | Filtros | Tipos usados | Complejidad join |
|-----------|---------|--------------|-----------------|
| CustomerListApi | active, search, frequency | bool, LIKE, enum | Ninguna |
| RouteListApi | status, date_from, date_to, driver, customer | enum, date×2, entity×2 | Alta (re-join con alias en countQb) |
| ShipmentListApi | customer, priority, date_from, date_to | entity lookup, enum, date×2 | Moderada |
| VehicleListApi | active, date_from, date_to | bool, date×2 | Ninguna |
| DriverListApi | active, date_from, date_to | bool, date×2 | Ninguna |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Pagination logic | Omit | Already clean, not duplicated in the same way |
| Response serialization | Omit | Each controller has unique DTO shapes |
| `/filters` endpoints | Omit | Each returns different data, no common pattern |

## Approach Selection

### Opcion A: Trait con métodos helper tipados
- **Ventaja:** Zero-cost DI, idiomático Symfony
- **Desventaja:** Viola SRP (mezcla filtrado en controller), no testable independientemente
- **Descartada:** No escala bien, no cumple SOLID

### Opcion B (Seleccionada): Service inyectable `ListFilterApplier`
- **Ventaja:** SRP — responsabilidad única de aplicar filtros
- **Ventaja:** OCP — nuevos tipos de filtro = extender, no modificar
- **Ventaja:** DIP — controllers dependen de abstracción
- **Ventaja:** Testable independientemente del controller
- **Trade-off:** Requiere inyección DI, pero Symfony autowiring lo resuelve automáticamente

### Alternativa C: AbstractAdminListController
- **Ventaja:** Herencia natural
- **Desventaja:** Symfony desaconseja herencia profunda, single inheritance limit
- **Descartada:** Inflexible

## Design

### Service: `ListFilterApplier`

**Location:** `backend/src/Service/Admin/ListFilterApplier.php` (pragmatic context, no DDD)

**Interface:**
```php
class ListFilterApplier
{
    /**
     * Apply filters to both data and count QueryBuilders.
     *
     * @param FilterDefinition[] $filters
     */
    public function apply(QueryBuilder $qb, QueryBuilder $countQb, array $filters): void;
}
```

### FilterDefinition: value object

**Location:** `backend/src/Service/Admin/FilterDefinition.php`

Static factory methods per filter type:
```php
class FilterDefinition
{
    public static function boolean(string $field, string $paramName, string $rawValue): self;
    public static function like(string $field, string $paramName, string $rawValue): self;
    public static function enum(string $field, string $paramName, string $rawValue, string $enumClass): self;
    public static function dateFrom(string $field, string $paramName, string $rawValue): self;
    public static function dateTo(string $field, string $paramName, string $rawValue): self;
    public static function entity(string $field, string $paramName, ?object $entity): self;
    
    // For Route's join re-aliasing in countQb
    public function withCountJoin(string $join, string $alias): self;
}
```

### Controller usage (after refactor):
```php
$this->filterApplier->apply($qb, $countQb, [
    FilterDefinition::boolean('c.isActive', 'active', $activeFilter),
    FilterDefinition::like('c.name', 'search', $searchFilter),
    FilterDefinition::enum('c.frequency', 'frequency', $frequencyFilter, ClientFrequency::class),
]);
```

### Route's special case (join re-aliasing):
```php
FilterDefinition::entity('d.id', 'driverId', $driverId)
    ->withCountJoin('r.driver', 'cd'),
```

## Files Affected

| File | Action |
|------|--------|
| `backend/src/Service/Admin/ListFilterApplier.php` | **New** — service |
| `backend/src/Service/Admin/FilterDefinition.php` | **New** — value object |
| `backend/src/Controller/Api/Admin/CustomerListApiController.php` | Refactor to use service |
| `backend/src/Controller/Api/Admin/RouteListApiController.php` | Refactor to use service |
| `backend/src/Controller/Api/Admin/ShipmentListApiController.php` | Refactor to use service |
| `backend/src/Controller/Api/Admin/VehicleListApiController.php` | Refactor to use service |
| `backend/src/Controller/Api/Admin/DriverListApiController.php` | Refactor to use service |
