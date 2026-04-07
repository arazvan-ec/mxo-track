# Spec — Repository & Performance Improvements

**Date:** 2026-04-07
**Type:** bug fix + performance
**Branch:** `claude/analyze-repo-improvements-vqgSV`

## Problema

1. `AlertService::getOfflineVehicles()` ejecuta N+1 queries (1 por vehículo activo)
2. 14 repositories atrapan `\Throwable` cuando solo deberían atrapar `\InvalidArgumentException`

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| AlertService.getOfflineVehicles() | Transform | Replace N+1 with single JOIN query |
| AlertService.checkVehicleOffline() | Include | Keep as-is (single vehicle, no N+1) |
| AlertService.checkExcessiveExceptions() | Include | Keep as-is (already uses raw SQL) |
| 14x findOneByPublicId() in repositories | Transform | Narrow catch to InvalidArgumentException |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| PanelHeader extraction | Omit | Headers differ in content; abstraction forced |
| Controller tests (59 untested) | Omit | Separate initiative, documented as tech debt |
| 79 other catch(\Throwable) | Omit | Many legitimate (external APIs, commands) |

## Approach A (Recomendado): DQL LEFT JOIN + mechanical catch narrowing

**Ventaja:** Minimal change, maximum impact. Single DQL query replaces N+1. Catch narrowing is mechanical and safe.
**Desventaja:** Ninguna significativa — ambos cambios son de bajo riesgo.

## Alternativa B: Custom SQL + batch approach for AlertService

**Ventaja:** Slightly more control over query.
**Desventaja:** Bypasses Doctrine hydration, loses entity features. Overkill.

## Trade-off: InvalidArgumentException vs specific Ulid exception

`Ulid::fromString()` throws `\InvalidArgumentException`. Could catch `Symfony\Component\Uid\Exception\InvalidArgumentException` but the base PHP exception is sufficient and simpler.

## Design

### 1. AlertService N+1 Fix
Replace loop+findOneBy with single DQL LEFT JOIN between Vehicle and VehicleLastPosition.
VehicleLastPosition has `@OneToOne(targetEntity: Vehicle)` — the join is natural.
Return same shape `array<array{vehicle: Vehicle, minutesOffline: int}>`.

### 2. Repository catch narrowing (14 files)
Mechanical: `catch (\Throwable)` → `catch (\InvalidArgumentException)` in all 14 `findOneByPublicId()`.
DB errors, OOM, TypeErrors will now properly bubble up instead of returning null silently.
