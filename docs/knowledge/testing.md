# Testing

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Métricas

| Métrica | Valor |
|---------|-------|
| Archivos de test | 53 (48 Unit, 3 Functional, 2 Factory) |
| Tests totales | 304 |
| Assertions | 886 |

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
│   ├── Dto/               # 5+ archivos
│   ├── ExceptionClassifierServiceTest.php     # 8 tests — clasificación NLP
│   ├── NlpClassificationHandlerTest.php       # 3 tests — handler async
│   ├── PostRouteAnalyzerTest.php              # 6 tests — análisis post-ruta
│   ├── PostRouteAnalysisHandlerTest.php       # 2 tests — handler async
│   ├── DeliveryRiskServiceTest.php            # 6 tests — predicción de riesgo
│   ├── AddressRiskServiceTest.php             # 6 tests — riesgo por dirección
│   ├── EmbeddingServiceTest.php               # 5 tests — embeddings vectoriales
│   ├── SearchServiceTest.php                  # 5 tests — búsqueda híbrida
│   ├── AiAssistantServiceTest.php             # 4 tests — asistente IA
│   ├── AiAssistantControllerTest.php          # 5 tests — controller chat
│   └── DeliveryNoteAiEnricherTest.php         # 5 tests — notas de entrega
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

- **Bien cubierto**: Provider framework (factories, engines, resolvers, enums), AI/ML services (clasificación, análisis, riesgo, embeddings, búsqueda, assistant, notas)
- **Parcialmente cubierto**: Servicios core (RouteBuilder, DeliveryService), Controllers
- **Poco cubierto**: Analytics services (AdminMetrics, SlaMetrics, DriverScoring), Domain event listeners

## Convenciones

- Todos los test files usan `declare(strict_types=1)`
- Tests usan atributos `#[Test]` (no prefijo `test`)
- Tests unitarios no requieren servicios externos
- Mocks solo cuando es unavoidable (preferir implementaciones reales)

## Historial

- 2026-03-11: Creación inicial
- 2026-03-11: Phase 2 — +55 tests AI/ML (249→304 tests, 701→886 assertions, 44→53 test files)
