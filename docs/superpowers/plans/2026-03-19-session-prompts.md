# Prompts para sesiones paralelas de Claude

**Plan:** `docs/superpowers/plans/2026-03-19-technical-debt-elimination.md`
**Spec:** `docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md`

## Orden de lanzamiento

```
Inmediato:  Sesión A, Sesión B, Sesión C  (sin dependencias)
Después de A(F1): Sesión D
Después de B+C:   Sesión E
Después de A+E:   Sesión F
Después de F:     Sesión G
```

---

## Sesión A — Foundation + Event Sourcing + Route DDD (Fases 1 + 2)

```
Ejecuta las Fases 1 y 2 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (plan detallado)
- docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md (spec de diseño)
- docs/knowledge/architecture-ddd.md (reglas DDD)

Tu trabajo tiene dos partes secuenciales:

**Fase 1: Foundation (Tasks 1.0–1.8)**
Crear repository interfaces en src/Domain/Route/Repository/ e implementaciones Doctrine en src/Infrastructure/Route/Doctrine/. Migrar RouteOptimizationService, RouteBuilder y DeliveryService para que dependan de interfaces en vez de EntityManagerInterface o repos concretos. TDD obligatorio: test primero, implementar después.

**Fase 2: Event Sourcing + Route DDD (Tasks 2A.0–2D.8)**
Cuatro sub-fases estrictamente secuenciales:
- 2A: Completar los 6 event dispatches faltantes (ASSIGNED, CANCELLED, REOPTIMIZED, STOPS_REORDERED, STOP_SKIPPED, NOTE_ADDED) + crear 5 nuevos event types para mutations sin evento.
- 2B: Invertir patrón a events-first. Implementar Route::apply(RouteEvent) y Route::rebuildFromEvents(). Refactorizar RouteLifecycleService, DeliveryService, RouteOptimizationService, RouteBuilder y RouteAdminController.
- 2C: Crear projections (route_current_state, stop_current_status), ProjectionRebuilder command, migrar queries a usar projections.
- 2D: Migrar Route, RouteStop, RouteEvent a POPOs en src/Domain/Route/Model/ con Doctrine XML mapping en config/doctrine/. Añadir time-travel (stateAtTimestamp) y optimistic locking (version field).

Reglas:
- TDD obligatorio (Skill 7): test que falla → implementar → test verde
- Atomic commits después de cada task
- Push frecuente
- Verificar full test suite al final de cada sub-fase
- Seguir las convenciones de CLAUDE.md (declare strict_types, naming conventions, etc.)

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```

---

## Sesión B — Security + Cleanup (Fases 3 + 10)

```
Ejecuta las Fases 3 y 10 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (plan detallado)
- docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md (spec de diseño)

Tu trabajo:

**Fase 3: Security — Credential Encryption (Tasks 3.0–3.5)**
Encriptar credenciales en CustomerIntegration usando sodium_crypto_secretbox. Pasos:
1. Leer CustomerIntegration.php para entender la estructura del campo credentials
2. Crear CredentialEncryptor service con TDD (encrypt/decrypt round-trip, edge cases)
3. Integrar con Doctrine (custom type encrypted_json o lifecycle listener)
4. Migration para encriptar datos existentes
5. Verificar full test suite

**Fase 10: Cleanup (Tasks 10.1–10.3)**
1. Implementar PDF export en SlaReportController usando DomPDF
2. Documentar la decisión de NO refactorizar User.php SRP en docs/decisions/log.md
3. Evaluar si el provider framework codegen trigger se ha alcanzado (>6 proxies)

Reglas:
- TDD obligatorio
- Atomic commits después de cada task
- Push frecuente
- Esta sesión NO toca Route, RouteStop, RouteEvent ni sus servicios (eso es Sesión A)
- Seguir las convenciones de CLAUDE.md

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```

---

## Sesión C — SOLID GPS + Infrastructure (Fases 4 + 6)

```
Ejecuta las Fases 4 y 6 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (plan detallado)
- docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md (spec de diseño)
- docs/knowledge/provider-framework.md (framework de providers)

Tu trabajo:

**Fase 4: SOLID — GpsProvider ISP/LSP Fix (Tasks 4.0–4.3)**
Split GpsDeviceProviderInterface (demasiado ancha, viola ISP/LSP) en dos interfaces cohesivas:
- GpsPositionProviderInterface: getPositions(), isAvailable() — implementada por AMBOS providers
- GpsDeviceManagerInterface: login(), getSessionCookie(), getDevices(), createDevice() — solo TraccarProvider
WebhookGpsProvider actualmente tiene stubs para los métodos de Traccar. Después del split, solo implementa GpsPositionProviderInterface y los stubs desaparecen.
Actualizar factories, proxies y consumidores del Provider Framework.

**Fase 6: Infrastructure — Mercure Abstraction (Tasks 6.1–6.4)**
Auditar qué event listeners usan HubInterface directamente vs RealtimePublisherInterface. Migrar todos los listeners para usar la abstracción. Auditar el estado del boilerplate del provider framework.

Reglas:
- TDD obligatorio
- Atomic commits después de cada task
- Push frecuente
- Esta sesión NO toca Route entities ni Shipment entities (esos son Sesión A y D)
- Seguir las convenciones de CLAUDE.md
- Al modificar constructores: buscar TODOS los call sites incluyendo Factories (CLAUDE.md: Constructor Signature Changes)

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```

---

## Sesión D — Delivery DDD (Fase 5)

**Prerequisito:** Sesión A debe haber completado Fase 1 y mergeado a main.

```
Ejecuta la Fase 5 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (plan detallado)
- docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md (spec de diseño)
- docs/knowledge/architecture-ddd.md (reglas DDD)

IMPORTANTE: Antes de empezar, verifica que Fase 1 está completada:
- src/Domain/Route/Repository/ debe tener RouteRepositoryInterface, RouteStopRepositoryInterface, RouteEventRepositoryInterface
- src/Infrastructure/Route/Doctrine/ debe tener las implementaciones Doctrine
- Si no existen, PARA y avisa que Fase 1 no está completa.

Tu trabajo:

**Fase 5: Delivery DDD — Shipment/Delivery Entities (Tasks 5.1–5.8)**
Migrar el segundo contexto crítico (Shipment/Delivery) a DDD puro. Sigue el mismo patrón establecido en Fase 1 para Route:
1. Crear ShipmentRepositoryInterface + DoctrineShipmentRepository (TDD)
2. Crear PodRepositoryInterface + DoctrinePodRepository (TDD)
3. Completar migración de DeliveryService a interfaces (parcialmente hecho en Fase 1)
4. Migrar Shipment, Parcel, Pod, ShipmentEvent a POPOs en src/Domain/Shipment/Model/
5. Crear Doctrine XML mappings en config/doctrine/
6. Migrar todos los imports, eliminar entidades de src/Entity/

Reglas:
- TDD obligatorio
- Atomic commits después de cada task
- Push frecuente
- Mira cómo Sesión A implementó Fase 1 (Route repositories) y replica el patrón para Shipment
- NO toques Route, RouteStop ni RouteEvent — eso es Sesión A
- Seguir las convenciones de CLAUDE.md
- Verificar doctrine:schema:validate después de crear XML mappings

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```

---

## Sesión E — User-Configurable Providers (Fase 7)

**Prerequisito:** Sesiones B (F3: encryption) y C (F4: GPS split) idealmente completadas.

```
Ejecuta la Fase 7 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (resumen de fase 7)
- docs/superpowers/plans/2026-03-11-user-configurable-providers.md (plan detallado original)
- docs/superpowers/specs/2026-03-11-user-configurable-providers-design.md (spec de diseño)
- docs/knowledge/provider-framework.md (framework existente)

IMPORTANTE: Verifica antes de empezar:
- Si Fase 4 (GPS split) está completada: usa GpsPositionProviderInterface para el proxy TenantAwareGpsProvider
- Si Fase 3 (encryption) está completada: integra CredentialEncryptor en CustomerIntegration para API keys
- Si NO están completadas: usa las interfaces actuales y deja TODOs para integrar después

Tu trabajo:

**Fase 7: User-Configurable Providers (Tasks 7.1–7.5)**
Implementar el Provider Framework completo siguiendo el plan detallado en docs/superpowers/plans/2026-03-11-user-configurable-providers.md. Este plan ya tiene tasks numeradas con código de ejemplo.

Resumen:
1. Foundation: ServiceType enum, provider enums, CustomerIntegration entity, ProviderFactoryInterface + Registry, TenantContext, ProviderResolver, CachedProviderResolver, FallbackChain
2. Transparent Proxies: TenantAwareRouteOptimizer, TenantAwareRoutingEngine, TenantAwareGpsProvider, TenantAwareRealtimePublisher
3. New Providers: GreedyOptimizer, HaversineEngine, GoogleDirectionsEngine, WebhookGpsProvider, HttpPollingPublisher (cada uno con Factory + Config DTO)
4. Admin UI: CustomerIntegrationController (CRUD), EventPollingController (polling API), DI config

Reglas:
- TDD obligatorio
- Atomic commits después de cada task
- Push frecuente
- Seguir las convenciones de CLAUDE.md
- Al crear proxies: implementar la misma interface que el servicio que envuelven (Transparent Proxy pattern)

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```

---

## Sesión F — Route Creation UI (Fase 8)

**Prerequisito:** Sesión A (F2: event sourcing) y Sesión E (F7: providers) completadas.

```
Ejecuta la Fase 8 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (plan detallado)
- docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md (spec)
- docs/analysis/2026-03-15-business-requirements-audit.md (gaps GAP-1.1, GAP-1.2, GAP-3.1)

IMPORTANTE: Verifica antes de empezar:
- El event sourcing para Route debe estar implementado (Fase 2)
- Los user-configurable providers deben estar implementados (Fase 7)
- Revisa el frontend React existente en la app (busca /app/* routes, MapLibre components)

Tu trabajo:

**Fase 8: Route Creation UI (Tasks 8.0–8.6)**
Cubre 3 gaps de negocio que son facetas del mismo flujo:
- GAP-1.1: UI interactiva para lanzar optimización
- GAP-1.2: Preview visual de ruta optimizada en mapa
- GAP-3.1: Flujo "seleccionar shipments → preview → configurar → confirmar"

Pasos:
1. PRIMERO: Brainstorming (Skill 2) para diseñar el flujo completo — wireframes, API endpoints necesarios, interacciones con mapa. Escribir spec.
2. Backend: crear endpoint POST /api/v1/routes/preview (optimización sin persistir)
3. React: ShipmentSelector (listado filtrable con selección múltiple)
4. React: RouteConfigurator (selección de vehículo, driver, origin, strategy)
5. React: RoutePreviewMap (MapLibre con polyline, markers draggable, métricas)
6. React: Wizard integrado (3 steps → confirm)

Stack frontend: React SPA en /app/*, MapLibre GL JS, TypeScript, Protomaps vector tiles (dark theme).

Reglas:
- TDD obligatorio (backend), component tests para React
- Atomic commits
- Push frecuente
- No romper los frontales Twig existentes (coexisten con React SPA)
- Seguir las convenciones de CLAUDE.md

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```

---

## Sesión G — Business Decisions (Fase 9)

**Prerequisito:** Sesión F (F8: Route Creation UI) completada.

```
Ejecuta la Fase 9 del plan de eliminación de deuda técnica.

Lee primero estos archivos para contexto completo:
- CLAUDE.md (instrucciones del proyecto)
- docs/superpowers/plans/2026-03-19-technical-debt-elimination.md (plan detallado)
- docs/analysis/2026-03-15-business-requirements-audit.md (decisiones pendientes)
- docs/knowledge/route-optimization.md (contexto de optimización)

Tu trabajo:

**Fase 9: Business Decisions (Tasks 9.1–9.3)**
Resolver 3 decisiones de negocio pendientes. Cada una requiere brainstorming completo (Skill 2) seguido de spec y plan.

**Decisión 1: Selección de estrategia de optimización**
- Contexto: con providers configurables (Fase 7), el admin configura strategy per-tenant. ¿Necesita también selección per-route en el flujo de creación (Fase 8)?
- Opciones: manual, recomendación automática, ejecución paralela + comparación, combinación
- Output: spec + plan de implementación

**Decisión 2: Política de re-optimización automática vs manual**
- Contexto: RouteOptimizationService puede re-optimizar PENDING stops. Route.autoReoptimize existe. Con event sourcing (Fase 2), cada re-opt es traceable.
- Opciones: siempre manual, automática por defecto, reglas configurables per-customer, recomendación IA
- Output: spec + plan

**Decisión 3: Datos históricos alimentando planificación futura**
- Contexto: existen AddressRisk, DriverFeedback, RouteComparison, PostRouteAnalyzer. Con event sourcing, historical data es más rico.
- Opciones: solo analytics/reporting, auto-feedback al optimizador, sistema de recomendaciones, híbrido
- Output: spec + plan

Reglas:
- Cada decisión empieza con brainstorming (Skill 2 de CLAUDE.md) — proponer 2-3 approaches con trade-offs
- Seguir el Full-flow de CLAUDE.md: consultar → brainstorm → plan
- Consultar docs/decisions/log.md antes de proponer
- Escribir specs en docs/superpowers/specs/ y plans en docs/superpowers/plans/
- Estas decisiones NO requieren implementación de código — solo diseño y planificación
- Atomic commits de cada spec y plan

Trabaja en un feature branch propio. Cuando termines, pushea y crea un PR.
```
