# Fleet Routes List — Plan

**Fecha:** 2026-04-01
**Spec:** `docs/superpowers/specs/2026-04-01-fleet-routes-list-design.md`
**Branch:** `claude/fleet-routes-list-C9ck2`

## Phase 1 (v0): Agregar route_card_list al layout fleet_map

### Tarea 1: Migración SQL para actualizar layout fleet_map
- Crear migración `Version20260401000100.php`
- DELETE widgets de `fleet_map` para estados `half` y `full`
- INSERT nuevos widgets: half=[kpi_pills, route_card_list], full=[kpi_pills, route_card_list, vehicle_info, driver_info, map_legend]
- Test: ejecutar migración sin errores

### Tarea 2: Verificación
- Ejecutar `php bin/console doctrine:migrations:migrate -n`
- Verificar que TypeScript compila sin errores
- Verificar que tests existentes pasan

## Phase 2: N/A
No se requiere refactor — el cambio es solo configuración en BD.

## Archivos afectados
- `backend/migrations/Version20260401000100.php` (nuevo)
