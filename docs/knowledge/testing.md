# Testing

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Métricas

| Métrica | Valor |
|---------|-------|
| Archivos de test | 44 (39 Unit, 3 Functional, 2 Factory) |
| Tests totales | 249 |
| Assertions | 701 |

## Comandos

```bash
# Ejecutar todos los tests
cd backend && php vendor/bin/phpunit

# Test específico
php vendor/bin/phpunit tests/Unit/Provider/ProviderEnumsTest.php

# Con filtro
php vendor/bin/phpunit --filter=test_method_name

# PHP syntax lint
make lint
```

## Estructura

```
backend/tests/
├── Unit/
│   ├── Provider/          # 17 archivos — provider framework (mejor cubierto)
│   │   ├── Routing/       # OsrmEngine, GoogleDirections, ProviderEnums
│   │   ├── RouteOptimizer/# Vroom, Greedy
│   │   ├── Gps/           # Traccar, Webhook
│   │   ├── Realtime/      # Mercure, HttpPolling
│   │   └── ProviderFactoryRegistryTest.php
│   ├── Service/           # 8 archivos — servicios core
│   ├── Validation/        # 3 archivos
│   └── Dto/               # 5+ archivos
├── Functional/
│   ├── CustomerTenantFilterTest.php
│   ├── RouteLifecycleTest.php
│   └── IntegrationTest.php
└── Factory/
    └── TestEntityFactory.php  # Factory para crear entidades de test
```

## Patrones

### TestEntityFactory

Clase helper para crear entidades de test con valores sensatos por defecto:

```php
$vehicle = TestEntityFactory::createVehicle(name: 'Test Van');
$shipment = TestEntityFactory::createShipment(customer: $customer);
$route = TestEntityFactory::createRoute(vehicle: $vehicle, driver: $driver);
```

### Provider Tests

Cada provider tiene tests unitarios que verifican:
- Configuración correcta via Config DTO
- Comportamiento del engine con datos de ejemplo
- Factory crea instancias correctas
- Enum tiene los cases esperados

### Functional Tests

Requieren base de datos y servicios. Verifican:
- Multi-tenancy filter scoping
- Route lifecycle transitions (PLANNED → ACTIVE → DONE)
- Integration entre servicios

## Coverage

- **Bien cubierto**: Provider framework (factories, engines, resolvers, enums)
- **Parcialmente cubierto**: Servicios core (RouteBuilder, DeliveryService)
- **Poco cubierto**: Controllers, Analytics services, AI/ML services

## Convenciones

- Todos los test files usan `declare(strict_types=1)`
- Tests usan atributos `#[Test]` (no prefijo `test`)
- Tests unitarios no requieren servicios externos
- Mocks solo cuando es unavoidable (preferir implementaciones reales)

## Historial

- 2026-03-11: Creación inicial
