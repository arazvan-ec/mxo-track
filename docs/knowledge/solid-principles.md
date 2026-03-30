# SOLID Principles — Detailed Reference

**Última actualización:** 2026-03-30
**Estado:** Vigente

Todo código nuevo debe cumplir los 5 principios. En code review, verificar cada uno.

## S — Single Responsibility

**Una clase debe tener una sola razón para cambiar.**

- Entidades: solo estado de dominio + transiciones de estado (`start()`, `finish()`, `markDelivered()`)
- Persistencia: en Infrastructure (mapping externo, repositories)
- Validación: en Value Objects (auto-validación en constructor) o Application layer (DTOs con Validator)
- Seguridad: en Security layer (voters, authenticators), no en la entidad

**Violación conocida:** `User.php` mezcla 5 responsabilidades (identidad, auth, roles, multi-tenancy, persistence lifecycle).
**Buen ejemplo:** `src/Domain/Event/StopDelivered.php` — POPO inmutable con un solo trabajo.

## O — Open/Closed

**Abierto para extensión, cerrado para modificación.**

- Múltiples implementaciones posibles → interface + registry o tagged services
- Nunca if/switch sobre tipos para seleccionar implementación → usar polimorfismo
- Nuevas funcionalidades se añaden con nuevas clases, no modificando las existentes

**Buen ejemplo:** Provider Framework — `ProviderFactoryInterface` + `#[AutoconfigureTag]` + `ProviderFactoryRegistry`. Añadir provider = nueva clase, cero cambios en código existente.

## L — Liskov Substitution

**Las implementaciones deben cumplir el contrato completo de su interface.**

- Si una implementación necesita stubs o no-ops → la interface es demasiado amplia → dividirla
- Nunca `throw new \RuntimeException('Not supported')` en un método de interface

**Violación conocida:** `WebhookGpsProvider` tiene stubs para `login()`, `getSessionCookie()`, `getDevices()` (deuda técnica documentada en backlog).

## I — Interface Segregation

**Los clientes no deben depender de interfaces que no usan.**

- Interfaces pequeñas y cohesivas (1-5 métodos relacionados)
- Si una implementación tiene stubs → ISP + LSP violados juntos
- Preferir composición de interfaces: `class X implements InterfaceA, InterfaceB`
- Interfaces marker (sin métodos) son aceptables

**Buen ejemplo:** `CustomerScopedEntityInterface` (1 método), `SoftDeletableInterface` (3 métodos cohesivos).

## D — Dependency Inversion

**Módulos de alto nivel dependen de abstracciones, no de módulos de bajo nivel.**

- Servicios de dominio y aplicación → dependen de interfaces definidas en Domain layer
- Infrastructure implementa las interfaces
- `EntityManagerInterface` directo → prohibido en contextos críticos. Usar `RepositoryInterface::save()`
- En contextos CRUD/pragmáticos → aceptable depender de repositorios concretos Symfony

```
Controller → Application Service → Domain Interface ← Infrastructure Implementation
```

**Violación conocida:** `DeliveryService` depende de `RouteStopRepository` y `ShipmentRepository` concretos.
**Buen ejemplo:** `RouteOptimizationService` depende de `RouteOptimizerInterface` y `RoutingEngineInterface`.
