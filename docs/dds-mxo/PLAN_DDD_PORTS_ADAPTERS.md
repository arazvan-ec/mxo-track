# Plan: Refactorización DDD — Ports & Adapters para todo el dominio

## Progreso

| Fase | Estado | Fecha |
|------|--------|-------|
| 1 — Routing (OSRM) | ✅ Completada | 2026-03-08 |
| 2 — Route Optimization (VROOM) | Pendiente | — |
| 3 — GPS Tracking (Traccar) | Pendiente | — |
| 4 — AI/LLM (Claude+OpenAI) | Pendiente | — |
| 5 — Realtime (Mercure) | Pendiente | — |
| 6 — ML Prediction | Pendiente | — |
| 7 — Repositories (incremental) | Pendiente | — |

---

## Contexto

El proyecto mxo-track tiene **28 features implementadas** pero el dominio está acoplado a la infraestructura. Solo `Geocoding` y `Notification` siguen Ports & Adapters correctamente. El resto de servicios externos (Traccar, VROOM, OSRM, Mercure, Claude, OpenAI, ML sidecar) son clases concretas sin interfaz — imposible cambiar proveedor sin reescribir.

**Diagnóstico**: Madurez DDD 5/10. Buen modelo de entidades y enums, pero falta separación de capas en infraestructura.

**Objetivo**: Que cada dependencia externa tenga un **puerto (interfaz)** con su **adaptador (implementación)**, siguiendo el patrón ya existente en `GeocoderInterface`. Cada fase es un PR independiente que no rompe funcionalidad.

**Patrón de referencia**: `src/Geocoding/GeocoderInterface.php` — puerto con value objects, adaptador concreto, decorador cache, null adapter, alias en `services.yaml`.

---

## Fase 1: Routing Port (OSRM)

**Por qué primero**: Superficie mínima (1 clase, 2 consumidores), establece el patrón para el equipo.

### Crear
| Archivo | Rol |
|---------|-----|
| `src/Routing/RoutingEngineInterface.php` | **Puerto** — `route(fromLat, fromLng, toLat, toLng): RouteResult`, `routeWithWaypoints(list<Coordinate>): MultiWaypointRouteResult` |
| `src/Routing/Coordinate.php` | Value object — `readonly class { float $lat, float $lng }` |
| `src/Routing/RouteResult.php` | Value object — `distanceKm`, `durationSeconds` |
| `src/Routing/MultiWaypointRouteResult.php` | Value object — `totalDistanceKm`, `totalDurationSeconds`, `legs: list<RouteResult>` |
| `src/Routing/OsrmRoutingEngine.php` | **Adaptador** — encapsula `[lng,lat]` order y endpoint `/route/v1/driving/` |
| `src/Routing/NullRoutingEngine.php` | **Null adapter** — devuelve ceros para tests |

### Modificar
- `src/Service/EtaService.php` — depender de `RoutingEngineInterface` en vez de `OsrmClient`
- `src/Service/RouteOptimizationService.php` — ídem
- `config/services.yaml` — alias `RoutingEngineInterface → OsrmRoutingEngine`

### Deprecar
- `src/Service/OsrmClient.php` — marcar `@deprecated`, proxy temporal

---

## Fase 2: Route Optimization Port (VROOM)

**Por qué segundo**: VROOM-specific formats (`[lng,lat]`, `[grams,cm³,parcels]`) están hardcodeados en 3 mappers + `RouteBuilder`.

### Crear
| Archivo | Rol |
|---------|-----|
| `src/RouteOptimization/RouteOptimizerInterface.php` | **Puerto** — `optimize(list<OptimizableVehicle>, list<OptimizableJob>): OptimizationResult` |
| `src/RouteOptimization/OptimizableVehicle.php` | VO — `startLocation: ?Coordinate`, `maxWeightKg`, `maxVolumeM3`, `maxParcels`, `skills` |
| `src/RouteOptimization/OptimizableJob.php` | VO — `location: Coordinate`, `weightKg`, `volumeM3`, `parcels`, `priority`, `requiredSkills` |
| `src/RouteOptimization/OptimizationResult.php` | VO — `routes: list<OptimizedRoute>`, `unassignedJobIds` |
| `src/RouteOptimization/OptimizedRoute.php` | VO — `vehicleId`, `steps: list<OptimizedStep>`, `distanceMeters`, `durationSeconds` |
| `src/RouteOptimization/OptimizedStep.php` | VO — `jobId`, `type` (job/start/end) |
| `src/RouteOptimization/VroomRouteOptimizer.php` | **Adaptador** — absorbe `VroomApiClient` + `VroomRequestMapper` + conversiones de unidades |

### Modificar
- `src/Service/RouteBuilder.php` — convertir entidades → `OptimizableVehicle`/`OptimizableJob`, llamar `RouteOptimizerInterface`, usar `VroomResponseMapper` refactorizado para `OptimizationResult`
- `src/Service/RouteOptimizationService.php` — usar `RouteOptimizerInterface`
- `config/services.yaml` — alias

### Deprecar/absorber
- `src/Service/VroomApiClient.php`, `VroomRequestMapper.php`, `VroomResponseMapper.php`

---

## Fase 3: GPS Tracking Port (Traccar)

**Por qué tercero**: 4 commands + `SystemHealthService` dependen del concreto `TraccarApiClient`. Traccar es el proveedor más probable de cambiar.

### Crear
| Archivo | Rol |
|---------|-----|
| `src/Tracking/GpsDeviceProviderInterface.php` | **Puerto** — `getDevices(): list<DeviceInfo>`, `createDevice(name, uniqueId): DeviceInfo`, `getPositions(deviceId, ?since): list<DevicePosition>`, `isAvailable(): bool` |
| `src/Tracking/DeviceInfo.php` | VO — `id`, `name`, `uniqueId` |
| `src/Tracking/DevicePosition.php` | VO — `latitude`, `longitude`, `speed`, `course`, `accuracy`, `deviceTime`, `serverTime` |
| `src/Tracking/TraccarGpsProvider.php` | **Adaptador** — absorbe `TraccarApiClient` (HTTP/cookie login, API calls) |

### Modificar
- `src/Service/TraccarIngestionService.php` — aceptar `list<DevicePosition>` en vez de `list<array>`
- `src/Command/TraccarStreamCommand.php` — depender de `GpsDeviceProviderInterface`
- `src/Command/TraccarSyncDevicesCommand.php` — ídem
- `src/Command/SimulateGpsCommand.php` — ídem
- `src/Command/SmokeTraccarOnceCommand.php` — ídem
- `src/Service/SystemHealthService.php` — ídem
- `config/services.yaml` — alias

### Deprecar
- `src/Service/TraccarApiClient.php`

---

## Fase 4: AI/LLM Port (Claude + OpenAI)

**Por qué cuarto**: 7+ servicios dependen de `ClaudeApiClient` concreto. Modelos y URLs hardcodeados.

### Crear
| Archivo | Rol |
|---------|-----|
| `src/Ai/LlmClientInterface.php` | **Puerto** — `complete(LlmRequest): LlmResponse`, `completeWithToolLoop(messages, system, tools, executor): LlmToolLoopResponse` |
| `src/Ai/EmbeddingClientInterface.php` | **Puerto** — `embed(text): ?list<float>`, `embedBatch(texts): list<list<float>>` |
| `src/Ai/LlmRequest.php` | VO — `systemPrompt`, `userMessage`, `model`, `maxTokens`, `temperature` |
| `src/Ai/LlmResponse.php` | VO — `content`, `model`, `inputTokens`, `outputTokens`, `stopReason` |
| `src/Ai/LlmToolLoopResponse.php` | VO — `finalContent`, `toolCallCount`, `totalTokens` |
| `src/Ai/ToolDefinition.php` | VO — `name`, `description`, `inputSchema` |
| `src/Ai/ClaudeLlmClient.php` | **Adaptador Claude** — absorbe `ClaudeApiClient` |
| `src/Ai/OpenAiEmbeddingClient.php` | **Adaptador OpenAI** — absorbe `OpenAiApiClient` |
| `src/Ai/NullLlmClient.php` | Null adapter |
| `src/Ai/NullEmbeddingClient.php` | Null adapter |

### Modificar (7 consumidores de ClaudeApiClient)
- `src/Service/AiAssistantService.php`
- `src/Service/PostRouteAnalyzer.php`
- `src/Service/ShipmentSkillDetector.php`
- `src/Service/WebhookMessageEnricher.php`
- `src/Service/DeliveryNoteAiEnricher.php`
- `src/Service/DriverBriefingService.php`
- `src/Service/ExceptionClassifierService.php`
- `src/Service/EmbeddingService.php` (para `EmbeddingClientInterface`)
- `config/services.yaml`

### Deprecar
- `src/Service/ClaudeApiClient.php`, `src/Service/OpenAiApiClient.php`

---

## Fase 5: Realtime Publishing Port (Mercure)

**Por qué quinto**: Solo 3 archivos usan `HubInterface` directamente, pero acoplan el formato de topics y `Update` objects.

### Crear
| Archivo | Rol |
|---------|-----|
| `src/Realtime/RealtimePublisherInterface.php` | **Puerto** — `publishVehiclePosition(vehiclePublicId, positionData)`, `publishNotificationCount(userId, unreadCount)` |
| `src/Realtime/MercureRealtimePublisher.php` | **Adaptador** — encapsula topic naming y `HubInterface` |
| `src/Realtime/NullRealtimePublisher.php` | Null adapter |

### Modificar
- `src/Service/TraccarIngestionService.php` — usar `RealtimePublisherInterface`
- `src/Service/NotificationService.php` — ídem
- `config/services.yaml`

---

## Fase 6: ML Prediction Port

### Crear
| Archivo | Rol |
|---------|-----|
| `src/Ml/PredictionClientInterface.php` | **Puerto** — `predict(model, features): array`, `train(model, params): array`, `isHealthy(): bool` |
| `src/Ml/HttpPredictionClient.php` | **Adaptador** — absorbe `MlApiClient` |
| `src/Ml/NullPredictionClient.php` | Null adapter |

### Modificar (6 consumidores)
- `DriverAffinityService`, `FleetAnomalyService`, `DeliveryRiskService`, `DeliveryZoneService`, `DemandForecastService`, `ServiceTimePredictionService`
- `config/services.yaml`

### Deprecar
- `src/Service/MlApiClient.php`

---

## Fase 7: Repositories — Inyección directa (incremental)

**Estrategia**: NO crear interfaces abstractas para cada repositorio. En Symfony/Doctrine los `ServiceEntityRepository` concretos ya son la capa de repositorio. Lo que hay que corregir: **reemplazar `$em->getRepository(Foo::class)` por inyección tipada del repositorio**.

Se hace incrementalmente al tocar cada servicio en fases 1-6:
- Fase 3: `TraccarIngestionService` → inyectar `RouteRepository`, crear `VehicleLastPositionRepository` si no existe
- Fase 5: `NotificationService` → inyectar `UserRepository`
- Resto: boy scout rule, al tocar servicio → reemplazar `getRepository()`

---

## Lo que NO hacemos (decisiones explícitas)

1. **NO reorganizar en `src/Domain/` + `src/Infrastructure/`** — la estructura plana con namespaces (`App\Routing`, `App\Tracking`) funciona bien y es idiomática de Symfony
2. **NO introducir CQRS / command bus** — sobreingeniería para este tamaño de proyecto
3. **NO crear interfaces para servicios internos** — solo para fronteras de infraestructura externa
4. **NO quitar atributos Doctrine de entidades** — es el approach estándar de Symfony, aceptable en DDD pragmático
5. **NO domain events en esta iteración** — es Fase 8 futura, no afecta la swappability de infraestructura

---

## Verificación

Para cada fase:

1. **Lint**: `cd backend && find src/Routing -name '*.php' -exec php -l {} \;` (adaptar namespace)
2. **Symfony container**: `php bin/console debug:container --tag=app.* 2>&1` — verifica que los aliases se resuelven
3. **Autowiring**: `php bin/console debug:autowiring RoutingEngineInterface` — verifica que la interfaz es inyectable
4. **Grep deprecaciones**: `grep -r 'OsrmClient\|VroomApiClient\|TraccarApiClient' src/Service/ src/Command/` — debe dar 0 resultados (excepto clases deprecadas)
5. **Funcionalidad**: El servicio existente sigue funcionando porque el adaptador implementa la misma lógica, solo detrás de una interfaz

### Test manual de smoke por fase:
- Fase 1: `php bin/console debug:autowiring Routing` muestra `RoutingEngineInterface → OsrmRoutingEngine`
- Fase 2: `php bin/console debug:autowiring RouteOptimizer` muestra `RouteOptimizerInterface → VroomRouteOptimizer`
- Fase 3: `php bin/console debug:autowiring GpsDevice` muestra `GpsDeviceProviderInterface → TraccarGpsProvider`
- Fase 4: `php bin/console debug:autowiring LlmClient` muestra `LlmClientInterface → ClaudeLlmClient`
- Fase 5: `php bin/console debug:autowiring RealtimePublisher` muestra alias correcto
- Fase 6: `php bin/console debug:autowiring PredictionClient` muestra alias correcto

---

## Resumen de esfuerzo

| Fase | Puerto | Archivos nuevos | Archivos modificados | Impacto |
|------|--------|----------------|---------------------|---------|
| 1 | Routing (OSRM) | 6 | 3 | Establece patrón |
| 2 | Route Optimization (VROOM) | 7 | 3 | Core domain |
| 3 | GPS Tracking (Traccar) | 4 | 7 | Más acoplado |
| 4 | AI/LLM (Claude+OpenAI) | 10 | 9 | Más consumidores |
| 5 | Realtime (Mercure) | 3 | 3 | Quick win |
| 6 | ML Prediction | 3 | 7 | Quick win |
| 7 | Repositories | 0 | incremental | Boy scout |
| **Total** | **6 puertos** | **~33** | **~32** | — |
