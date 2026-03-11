# Plan Fase 3: Aumentar Cobertura de Tests

**Goal:** Llevar la cobertura de tests de ~5% a ~30%+ cubriendo servicios críticos
**Prerequisito:** Fase 1 (tests verdes) y Fase 2 (CI que los ejecute)

---

## Priorización por Criticidad de Negocio

### Tier 1 — Core Business (debe tener tests)
1. **DeliveryService** — orquesta entregas, el corazón del negocio
2. **RouteLifecycleService** — gestión del ciclo de vida de rutas
3. **RoutePlanningService** — planificación de rutas
4. **RouteBuilder** — construcción de rutas optimizadas con VROOM
5. **RouteCapacityValidator** — validación de capacidad de vehículos

### Tier 2 — Integración Externa
6. **VroomRequestMapper** — conversión dominio → VROOM
7. **VroomResponseMapper** — conversión VROOM → dominio
8. **TraccarApiClient** — comunicación con Traccar
9. **OsrmClient** — comunicación con OSRM

### Tier 3 — API y Seguridad
10. **ApiKeyAuthenticator** — autenticación API
11. **RouteApiController** (V1) — endpoints públicos de rutas
12. **ShipmentApiController** (V1) — endpoints públicos de shipments
13. **SecurityHeadersSubscriber** — headers de seguridad

### Tier 4 — Utilities
14. **CsvQualityAnalyzer** — análisis de calidad de CSV
15. **AuditLogger** — registro de auditoría
16. **Commands** — al menos smoke tests de los comandos principales

---

## Tareas

### Task 1: Tests de DeliveryService

**Archivo nuevo:** `backend/tests/Unit/DeliveryServiceTest.php`

- [ ] 1.1 Leer `src/Application/Delivery/DeliveryService.php` — documentar métodos públicos
- [ ] 1.2 Escribir test: entrega exitosa crea ShipmentEvent y marca RouteStop
- [ ] 1.3 Escribir test: entrega con excepción registra código y notas
- [ ] 1.4 Escribir test: entrega duplicada (idempotencia) no duplica evento
- [ ] 1.5 Escribir test: driver no owner lanza excepción
- [ ] 1.6 Escribir test: stop no encontrado lanza excepción
- [ ] 1.7 Verificar todos verdes
- [ ] 1.8 Commit: "test: add DeliveryService unit tests"

### Task 2: Tests de RouteLifecycleService

**Archivo nuevo:** `backend/tests/Unit/RouteLifecycleServiceTest.php`

- [ ] 2.1 Leer `src/Application/Route/RouteLifecycleService.php`
- [ ] 2.2 Test: start route transitions PLANNED → ACTIVE
- [ ] 2.3 Test: finish route transitions ACTIVE → DONE
- [ ] 2.4 Test: cancel route transitions to CANCELLED
- [ ] 2.5 Test: invalid transitions son no-op o excepción
- [ ] 2.6 Test: route not owned lanza excepción
- [ ] 2.7 Verificar + commit

### Task 3: Tests de RoutePlanningService

**Archivo nuevo:** `backend/tests/Unit/RoutePlanningServiceTest.php`

- [ ] 3.1 Leer `src/Application/Route/RoutePlanningService.php`
- [ ] 3.2 Test: planificación con shipments válidos crea rutas
- [ ] 3.3 Test: planificación sin vehículos disponibles falla gracefully
- [ ] 3.4 Test: validación de inputs
- [ ] 3.5 Verificar + commit

### Task 4: Tests de RouteBuilder + VROOM Mappers

- [ ] 4.1 Tests de `VroomRequestMapper`: conversión de Vehicle/Shipment a formato VROOM
- [ ] 4.2 Tests de `VroomResponseMapper`: conversión de respuesta VROOM a Route/RouteStop
- [ ] 4.3 Tests de `RouteCapacityValidator`: validación peso/volumen/parcels
- [ ] 4.4 Test de `RouteBuilder` con NullRouteOptimizer (sin llamada real a VROOM)
- [ ] 4.5 Verificar + commit

### Task 5: Tests de API Controllers (V1)

- [ ] 5.1 Tests de `RouteApiController`: GET routes, GET route by publicId
- [ ] 5.2 Tests de `ShipmentApiController`: POST shipment, GET shipments
- [ ] 5.3 Tests de `WebhookApiController`: CRUD webhooks
- [ ] 5.4 Tests de autenticación: request sin API key → 401
- [ ] 5.5 Verificar + commit

### Task 6: Tests de Seguridad

- [ ] 6.1 Tests de `ApiKeyAuthenticator`: key válida, key inválida, key expirada
- [ ] 6.2 Tests de `SecurityHeadersSubscriber`: verifica headers CSP, X-Frame-Options
- [ ] 6.3 Tests de `UserChecker`: usuario activo pasa, usuario inactivo falla
- [ ] 6.4 Verificar + commit

### Task 7: Tests de CsvQualityAnalyzer

- [ ] 7.1 Test: CSV válido retorna score alto
- [ ] 7.2 Test: CSV con problemas genera warnings apropiados
- [ ] 7.3 Test: CSV vacío
- [ ] 7.4 Verificar + commit

### Task 8: Smoke Tests de Comandos

- [ ] 8.1 Test: `SystemStatusCommand` se ejecuta sin errores
- [ ] 8.2 Test: `CreateAdminCommand` con inputs válidos
- [ ] 8.3 Test: `DatabaseMaintenanceCommand` basic execution
- [ ] 8.4 Verificar + commit

### Task 9: Verificación Global

- [ ] 9.1 Ejecutar suite completa: `php vendor/bin/phpunit`
- [ ] 9.2 Contar tests totales: objetivo >150
- [ ] 9.3 Verificar 0 errores, 0 fallos
- [ ] 9.4 Ejecutar `make lint`
