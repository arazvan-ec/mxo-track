# Feature 1.1 — Demo Setup Command

**Date:** 2026-03-11
**Status:** Approved
**Goal:** Comando Symfony que crea un escenario demo completo con datos realistas de Madrid

## Overview

Extender `DemoRouteFixture` existente y crear un command wrapper `app:demo:setup` que genere un escenario demo end-to-end configurable.

## Decisiones de Diseño

### Enfoque: Extender DemoRouteFixture

- Reutilizar la fixture existente (ya tiene 40 shipments Madrid)
- Ampliar: 3 vehicles, 2 drivers, rutas optimizadas via RoutePlanningService
- Command wrapper para ejecución y reset fácil

### Escala: Configurable

- `--shipments=N` (default: 40)
- El comando usa los services reales (RouteBuilder, RoutePlanningService)

## Componentes

### 1. DemoRouteFixture mejorada

**Archivo:** `src/DataFixtures/DemoRouteFixture.php`

Cambios:
- Crear **3 Vehicles** (no 1):
  - Furgoneta: maxWeight=1000kg, maxVolume=8m³, maxParcels=50, skills=[FRAGILE]
  - Camión refrigerado: maxWeight=3000kg, maxVolume=20m³, maxParcels=100, skills=[REFRIGERATED, HEAVY_LOAD]
  - Moto: maxWeight=30kg, maxVolume=0.5m³, maxParcels=5, skills=[PEDESTRIAN_ACCESS]
- Crear **2 Drivers** (no 1):
  - driver1@mxo.local (existente)
  - driver2@mxo.local (nuevo)
- Crear **CustomerLocation** warehouse (ya existe)
- Crear **N Shipments** con mix de:
  - Prioridades: 10% CRITICAL, 20% HIGH, 50% NORMAL, 20% LOW
  - Skills: algunos requieren REFRIGERATED, algunos FRAGILE
  - Pesos/volúmenes variados según el skill requerido
- **NO crear RouteStops directamente** — las rutas se construyen via RoutePlanningService

### 2. DemoSetupCommand

**Archivo:** `src/Command/DemoSetupCommand.php`

```
app:demo:setup [--fresh] [--shipments=40] [--simulate-gps]
```

- `--fresh`: Purga datos demo previos antes de crear
- `--shipments=N`: Número de shipments a crear (default: 40)
- `--simulate-gps`: Después de crear, lanza simulación GPS en la ruta activa

Flow:
1. Si `--fresh`: eliminar entidades con customer "Mxo almacen #1"
2. Ejecutar DemoRouteFixture (crea customer, vehicles, drivers, shipments)
3. Llamar RoutePlanningService::buildRoutes() para crear rutas optimizadas
4. Marcar primera ruta como ACTIVE
5. Si `--simulate-gps`: ejecutar SimulateGpsCommand internamente

### 3. DemoResetCommand

Alias simple: `app:demo:reset` = `app:demo:setup --fresh`

## Fallback sin VROOM

Si VROOM no está disponible, el comando debe crear rutas con stops asignados secuencialmente (round-robin entre vehicles) sin optimización. Mensaje de warning al usuario.

## Tests

- `DemoSetupCommandTest`: verifica que el comando crea las entidades esperadas
- Verifica: N shipments, 3 vehicles, 2 drivers, al menos 1 route
- Verifica: rutas tienen stops ordenados y capacity válida
- Verifica: `--fresh` limpia datos previos

## Fuera de Alcance

- UI para configurar demo
- Integración con Traccar real (solo simulación)
