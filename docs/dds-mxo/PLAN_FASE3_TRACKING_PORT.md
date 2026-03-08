# Plan de Ejecución: Fase 3 — GPS Tracking Port (Traccar)

## Contexto

Extraer `TraccarApiClient` en un puerto `GpsDeviceProviderInterface`. 6 consumidores: TraccarStreamCommand, TraccarSyncDevicesCommand, SimulateGpsCommand, SmokeTraccarOnceCommand, SystemHealthService, TraccarIngestionService (indirectamente via arrays de posiciones).

## Archivos críticos

| Archivo | TraccarApiClient methods used |
|---------|------------------------------|
| `TraccarStreamCommand` | `getPositions()` |
| `TraccarSyncDevicesCommand` | `getDevices()` |
| `SimulateGpsCommand` | `login()`, `getDevices()`, `createDevice()`, `getPositions()` |
| `SmokeTraccarOnceCommand` | `getDevices()`, `getPositions()` |
| `SystemHealthService` | `canConnect()` |
| `TraccarIngestionService` | Consume `list<array>` positions (no direct TraccarApiClient dep) |

## Commits

### Commit 1: Value Objects
- `src/Tracking/DeviceInfo.php` — `readonly class { int $id, string $name, string $uniqueId }`
- `src/Tracking/DevicePosition.php` — `readonly class { float $latitude, $longitude, $speed, $course, $accuracy, DateTimeImmutable $deviceTime, $serverTime, ?int $rawId }`

### Commit 2: Port Interface
- `src/Tracking/GpsDeviceProviderInterface.php` — `getDevices(): list<DeviceInfo>`, `createDevice(name, uniqueId): DeviceInfo`, `getPositions(deviceId, ?since): list<DevicePosition>`, `isAvailable(): bool`

### Commit 3: Adapters
- `src/Tracking/TraccarGpsProvider.php` — absorbe TraccarApiClient HTTP/cookie logic
- `src/Tracking/NullGpsProvider.php` — returns empty arrays

### Commit 4: Migrate consumers
- 5 commands/services migrados a GpsDeviceProviderInterface
- TraccarIngestionService → acepta `list<DevicePosition>` en vez de `list<array>`

### Commit 5: Wire + deprecate
- services.yaml: alias GpsDeviceProviderInterface → TraccarGpsProvider
- TraccarApiClient: @deprecated
