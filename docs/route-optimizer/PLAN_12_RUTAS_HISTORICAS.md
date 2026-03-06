# Plan: Analisis de Rutas Historicas (Planificadas vs Ejecutadas)

## Objetivo

Compare planned delivery routes (optimized sequence) with actual driver execution (real delivery timestamps from ShipmentEvents). Identify recurring deviations to improve future optimization: adjusted service times per zone, problematic addresses, driver-specific patterns.

## Estado Actual

- `ShipmentEvent` tracks delivery lifecycle events with timestamps (`CREATED`, `PICKED_UP`, `IN_TRANSIT`, `OUT_FOR_DELIVERY`, `DELIVERED`, `EXCEPTION`) — each event has `createdAt` (DateTimeImmutable) and a JSON `payload`.
- `RouteStop` has `sequence` (planned order), `deliveredAt` (actual delivery timestamp), `status` (PENDING/DELIVERED/EXCEPTION/SKIPPED), and a nullable `shipment` relation.
- `Route` has `startAt`/`endAt` timestamps and `status` (PLANNED/ACTIVE/DONE/CANCELLED). The `finish()` method sets status to `DONE` and records `endAt`.
- `ReportingService` provides delivery/exception counts, driver performance (stops per hour), trend data, and driver ranking — all using DQL queries joining `RouteStop` with `Route`.
- VROOM integration (`RouteBuilder`, `VroomRequestMapper`) builds optimized routes with capacity constraints (weight, volume, parcels) — but currently uses no per-zone service time adjustments.
- **No comparison between planned route sequence and actual execution order exists.**

## Cambios Propuestos

### 1. New Service: RouteAnalysisService

- **File**: `backend/src/Service/RouteAnalysisService.php`
- **Dependencies**: `EntityManagerInterface`, `RouteStopRepository`
- **Method**: `analyzeRouteExecution(Route $route): RouteAnalysisResult`
  - Validate route status is `DONE` (throw if not completed).
  - Load all `RouteStop` entities for the route, ordered by `sequence`.
  - Filter out origin stops (`isOrigin = true`).
  - For each stop: use `deliveredAt` timestamp directly from `RouteStop` (no need to query `ShipmentEvent` — `RouteStop.deliveredAt` is set by `markDelivered()`).
  - Optionally enrich with `ShipmentEvent` data: find DELIVERED/EXCEPTION events for the stop's shipment to get additional payload details (e.g., driver notes, exception codes).
  - Determine **actual delivery order** by sorting non-origin stops by `deliveredAt` ASC (nulls last for undelivered).
  - Compare planned sequence vs actual order.
  - Calculate per-stop metrics:
    - **Actual service time**: time between arriving at stop N and arriving at stop N+1 (derived from consecutive `deliveredAt` timestamps). For the first stop, use `Route.startAt` as the start reference.
    - **Deviation from plan**: difference between planned sequence position and actual delivery position.
  - Calculate route-level metrics:
    - **Planned duration**: estimated from VROOM (if stored) or fallback to null.
    - **Actual duration**: `Route.endAt - Route.startAt` in minutes.
    - **Sequence adherence**: percentage of stops delivered in the exact planned order.
    - **Average actual service time**: mean of per-stop service times.

### 2. New DTO: RouteAnalysisResult

- **File**: `backend/src/Dto/RouteAnalysisResult.php`
- **Properties**:

```php
final class RouteAnalysisResult
{
    public function __construct(
        public readonly string $routePublicId,
        public readonly string $routeName,
        public readonly ?string $vehicleName,
        public readonly ?string $driverName,
        public readonly ?int $actualDurationMinutes,       // Route.endAt - Route.startAt
        public readonly float $sequenceAdherence,           // 0.0–100.0 (% stops in planned order)
        public readonly ?float $avgActualServiceTimeSeconds, // mean service time across stops
        /** @var list<StopAnalysis> */
        public readonly array $stops,
        /** @var list<string> */
        public readonly array $recommendations,
    ) {}
}
```

- **File**: `backend/src/Dto/StopAnalysis.php`

```php
final class StopAnalysis
{
    public function __construct(
        public readonly int $plannedSequence,              // RouteStop.sequence
        public readonly ?int $actualOrder,                 // position when sorted by deliveredAt
        public readonly string $address,
        public readonly string $status,                    // DELIVERED, EXCEPTION, PENDING, SKIPPED
        public readonly ?string $deliveredAt,              // ISO 8601
        public readonly ?float $actualServiceTimeSeconds,  // time spent at this stop
        public readonly ?int $sequenceDeviation,           // actualOrder - plannedSequence
        public readonly ?string $exceptionCode,
        public readonly ?string $exceptionNotes,
    ) {}
}
```

### 3. API Endpoint

- **File**: `backend/src/Controller/Api/RouteAnalysisController.php`
- **Route**: `GET /api/routes/{publicId}/analysis`
- **Access**: `ROLE_ADMIN` or `ROLE_OPERATOR` (via `#[IsGranted]`)
- **Logic**:
  1. Load `Route` by `publicId`.
  2. Verify `Route.status === RouteStatus::DONE`.
  3. Call `RouteAnalysisService::analyzeRouteExecution()`.
  4. Return JSON response with `RouteAnalysisResult`.
- **Error handling**: Use `ApiErrorResponder` for consistent error format (404 if route not found, 422 if route not completed).

### 4. Recommendation Engine (within RouteAnalysisService)

- **Method**: `private generateRecommendations(array $stopAnalyses, Route $route): array`
- Rules (Phase 1):
  - If `avgActualServiceTime > 360s` (6 min): "El tiempo medio de servicio real ({X}min) supera los 5min asumidos. Considerar ajustar el tiempo de servicio en el optimizador."
  - If `sequenceAdherence < 70%`: "Solo el {X}% de las paradas se entregaron en el orden planificado. Revisar restricciones de ventanas horarias o accesibilidad de direcciones."
  - If any stop has `actualServiceTime > 600s` (10 min): "La parada '{address}' requirio {X}min. Verificar accesibilidad de esta direccion."
  - If exception rate > 20%: "La tasa de excepciones ({X}%) es alta. Revisar las direcciones problematicas."

### 5. Aggregate Analysis (Phase 2)

- **Method**: `getZoneServiceTimeAverages(Customer $customer, \DateTimeInterface $from, \DateTimeInterface $to): array`
- Groups completed route stops by geographic zone (postal code prefix or configurable grid).
- Calculates average actual service time per zone from `deliveredAt` timestamps.
- Output format:
  ```php
  [
      ['zone' => '28001', 'avg_service_time_seconds' => 420, 'sample_size' => 34],
      ['zone' => '28013', 'avg_service_time_seconds' => 310, 'sample_size' => 12],
  ]
  ```
- This data can feed back into `VroomRequestMapper` to set per-job `service` times instead of a global default, improving route time estimates.

- **Method**: `getDriverDeviationPatterns(User $driver, \DateTimeInterface $from, \DateTimeInterface $to): array`
- Analyzes a driver's historical routes to identify:
  - Average sequence adherence across routes.
  - Addresses or zones where the driver consistently deviates from plan.
  - Typical service time compared to other drivers.

### 6. Admin Dashboard Widget

- **Route detail page** (`/admin/routes/{publicId}`): Add "Analisis de ruta" section:
  - Sequence adherence percentage (badge: green >90%, yellow 70-90%, red <70%).
  - "Tiempo real vs planificado" bar comparison.
  - Table of stops showing planned vs actual order with deviation highlighting.
  - List of recommendations.
- **Implementation**: Turbo Frame loading analysis data via AJAX call to the API endpoint, rendered with Twig partial.

## Modelo de Datos

No new entities initially. Analysis is computed on-the-fly from existing data:
- `RouteStop.sequence` = planned order
- `RouteStop.deliveredAt` = actual completion timestamp
- `RouteStop.status` = outcome (DELIVERED/EXCEPTION/SKIPPED/PENDING)
- `Route.startAt` / `Route.endAt` = route time bounds

**Phase 2**: Consider adding a `RouteAnalysisCache` entity to store computed results for completed routes, avoiding recalculation:

```
route_analysis_cache
├── id (BIGINT PK)
├── public_id (ULID)
├── route_id (FK → route_plan)
├── sequence_adherence (FLOAT)
├── actual_duration_minutes (INT)
├── avg_service_time_seconds (FLOAT)
├── recommendations (JSON)
├── stop_analyses (JSON)
├── computed_at (DATETIME)
```

## Dependencias con Otros Planes

- **VroomRequestMapper**: Phase 2 zone service times feed into VROOM job `service` parameter for more accurate optimization.
- **ReportingService**: Could add route analysis aggregates to existing delivery reports (e.g., fleet-wide average adherence).
- **RouteBuilder**: Could use historical data to set realistic per-shipment service times during route building.

## Verificacion

1. Create a route with multiple stops (via fixture or route builder).
2. Start the route (`Route.start()`), deliver stops in a different order than planned (with varying timestamps).
3. Complete the route (`Route.finish()`).
4. `GET /api/routes/{publicId}/analysis` — verify:
   - `sequenceAdherence` reflects actual reordering.
   - `actualDurationMinutes` matches `endAt - startAt`.
   - Per-stop `actualOrder` is correctly derived from `deliveredAt` sort.
   - Per-stop `actualServiceTimeSeconds` is computed from consecutive delivery timestamps.
   - Recommendations are generated based on thresholds.
5. Test edge cases:
   - Route with all stops delivered in planned order (100% adherence).
   - Route with skipped/exception stops (excluded from sequence calculation).
   - Route with single stop.
   - Route not yet completed (should return 422 error).
6. Phase 2: Verify zone aggregation produces correct averages across multiple completed routes.
