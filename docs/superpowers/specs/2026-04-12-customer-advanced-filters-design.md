# Spec — Customer Advanced Filters

**Date:** 2026-04-12
**Branch:** `claude/add-customer-filters-ev8cG`
**Approved by user:** Yes (selected Enfoque A)

## Goal

Add advanced filters to the Customers admin list view: text search by name + frequency dropdown.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| Chips Todos/Activos/Inactivos | Include (keep) | Already functional |
| FilterBar with `advancedFilters` slot | Include (reuse) | Infrastructure ready |
| `useAdminCustomers` hook | Transform | Add search + frequency params |
| `CustomerListApiController` | Transform | Add backend filter logic + `/filters` endpoint |
| `Customer.frequency` (ClientFrequency enum) | Include | Natural filter dimension |
| `Customer.name` (string) | Include | Primary search field |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `Customer.address` | Omit | Low usage, adds noise per user choice (Enfoque A) |
| `Customer.contactPhone` | Omit | Rarely searched by phone |
| `Customer.webhookUrl` | Omit | Technical field, not user-facing filter |
| `Customer.notificationQuota` | Omit | Numeric range filter adds complexity without clear value |
| Date range filters | Omit | Entity lacks `createdAt` field |

## Design

### Backend

**File:** `backend/src/Controller/Api/Admin/CustomerListApiController.php`

1. Add `search` query param → `c.name LIKE :search` with `%query%` wrapping (case-insensitive via `LOWER()`)
2. Add `frequency` query param → `c.frequency = :frequency` via `ClientFrequency::tryFrom()`
3. Both filters applied to both `$qb` and `$countQb` (established pattern)
4. New `/filters` endpoint returning frequency enum values with labels

### Frontend

**File:** `frontend/src/api/hooks/useAdminCustomers.ts`
- Add `search` and `frequency` to `CustomerListParams`
- Add `useCustomerFilters()` hook fetching `/api/admin/customers/filters`

**File:** `frontend/src/pages/admin/AdminCustomersListPage.tsx`
- Add `search` and `frequency` state variables
- Build `advancedFilters` JSX: grid with text input + select dropdown
- Pass to FilterBar with `advancedFiltersOpen` auto-open when any filter active

### UI Layout

```
[Todos] [Activos] [Inactivos]           ← existing chips
🔍 Filtros avanzados ▼                  ← existing toggle
┌──────────────────────────────────────┐
│ [Buscar por nombre    ] [Frecuencia ▼] │  ← 2-col grid (1-col on mobile)
└──────────────────────────────────────┘
```

## Approach Selection

### Problema
La vista de Clientes solo tiene chips de estado (Todos/Activos/Inactivos) sin filtros avanzados, a diferencia de las demás vistas admin que ya los tienen.

### Opcion A (Seleccionada): Búsqueda por nombre + Frecuencia dropdown
- **Ventaja:** Cubre los 2 filtros de mayor valor de negocio sin agregar ruido
- **Ventaja:** Sigue el patrón exacto de Routes/Shipments (grid + dropdown + input)
- **Desventaja:** No incluye filtro por dirección
- **Trade-off:** Simplicidad vs completitud — 2 filtros cubren el 90% de casos de uso

### Alternativa B: Búsqueda + Frecuencia + Dirección
- **Ventaja:** Más completo
- **Desventaja:** Dirección es poco usada como criterio de búsqueda, agrega ruido visual
- **Descartada:** El usuario eligió Enfoque A por simplicidad

### Alternativa C: Solo búsqueda por nombre
- **Ventaja:** Implementación mínima
- **Desventaja:** Desaprovecha el campo `frequency` que tiene valor de negocio real
- **Descartada:** Insuficiente para la categorización que necesita el negocio
