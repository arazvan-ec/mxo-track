# Análisis Completo del Repositorio mxo-track

**Fecha:** 2026-03-11
**Objetivo:** Análisis del estado actual del repositorio, verificación del software y detección de áreas de mejora.

---

## 1. Resumen Ejecutivo

| Métrica | Valor |
|---------|-------|
| Archivos PHP fuente | ~331 |
| Líneas de código PHP | ~21,966 |
| Entidades Doctrine | 34 |
| Controladores | 38 |
| Servicios | 61+ |
| Tests | 92 (14 archivos) |
| Migraciones | 21 |
| Templates Twig | 47 |
| Servicios Docker | 9+ |

---

## 2. Estado de Salud del Software

### 2.1 Lint PHP: PASS
Todos los archivos en `backend/src/` pasan `php -l` sin errores de sintaxis.

### 2.2 Composer: PASS (con warnings menores)
- Falta `description` y `license` en composer.json (normal para proyecto privado)
- NelmioApiDocBundle tiene un problema de routing al ejecutar `cache:clear`

### 2.3 Tests: FAIL (25 errores, 7 fallos)

**92 tests totales, solo 60 pasan (~65%).**

#### Categoría 1: Errores por clases `final` no mockables (7 errores)
- **Afecta:** `ShipmentCsvImporterTest` (7 tests)
- **Causa:** `ImportRunTracker` fue marcado como `final`, PHPUnit no puede crear mock
- **Fix:** Extraer interfaz o usar mock library compatible con `final`

#### Categoría 2: Constructor desactualizado (6 errores)
- **Afecta:** `TraccarIngestionServiceTest` (6 tests)
- **Causa:** `TraccarIngestionService::__construct()` ahora requiere 3 argumentos, test pasa 2
- **Fix:** Actualizar setUp() del test para pasar el tercer argumento

#### Categoría 3: Tests funcionales con dependencias rotas (12 errores)
- **Afecta:** `CustomerTenantFilterTest` (1), `DriverApiTest` (11)
- **Causa:** Probablemente cambios en signatures de servicios o controladores
- **Fix:** Actualizar mocks y configuración de tests funcionales

#### Categoría 4: Tests de TopicResolver/MercureJwtFactory desincronizados (7 fallos)
- **Afecta:** `TopicResolverTest` (5), `MercureJwtFactoryTest` (2)
- **Causa:** Se añadieron nuevos topics a los resolvers pero los tests no se actualizaron
- **Fix:** Actualizar assertions para reflejar los topics actuales

---

## 3. Arquitectura - Puntos Fuertes

1. **Multi-tenancy bien implementado:** Doctrine SQL filter con `customer_id` es robusto
2. **Patrón PublicId:** Separación clara entre IDs internos (BIGINT) y públicos (ULID)
3. **Domain Events:** Buena separación de concerns con eventos de dominio
4. **Application Services:** Capa de aplicación (`Application/`) con servicios enfocados
5. **Integración VROOM/OSRM:** Abstracciones limpias con interfaces + implementaciones null
6. **Null Object Pattern:** Uso consistente de `NullPublisher`, `NullGpsProvider`, `NullGeocoder`, etc.
7. **Mercure para realtime:** SSE bien integrado con JWT y CORS configurados

---

## 4. Problemas Detectados

### 4.1 Tests Rotos (Crítico)
- 35% de los tests fallan
- Tests desincronizados con el código fuente → indica falta de CI/CD enforcement
- Sin pre-commit hooks que validen tests

### 4.2 Cobertura de Tests Muy Baja (Importante)
- Solo ~5% de archivos fuente tienen tests
- **Sin tests:** 25+ controladores, 9 comandos, 13 event subscribers, todos los repositorios
- Los services más críticos (RouteBuilder, DeliveryService, RoutePlanningService) no tienen tests

### 4.3 Bundle de Routing Roto (Moderado)
- `NelmioApiDocBundle` falla al cargar routing → `cache:clear` falla en post-install
- Puede afectar despliegues

### 4.4 Posible Complejidad Excesiva
- 331 archivos PHP para un sistema de tracking/logística → posible over-engineering
- Módulos AI/ML (9 archivos AI, 9 archivos Prediction, ML microservice) → ¿se usan realmente?
- Múltiples canales de notificación (SMS/WhatsApp/Email/Push/Webhook) → ¿todos implementados y usados?

### 4.5 Acoplamiento de Controladores
- 38 controladores, varios con lógica de negocio directa
- Algunos controladores duplican responsabilidades (ej: `ShipmentApiController` vs `Controller/Api/V1/ShipmentApiController`)

---

## 5. Cobertura de Tests por Módulo

| Módulo | Archivos | Tests | Cobertura |
|--------|----------|-------|-----------|
| Entity (dominio) | 34 | 2 archivos (Route, RouteStop) | ~6% |
| Service | 61+ | 5 archivos | ~8% |
| Controller | 38 | 1 archivo (DriverApi) | ~3% |
| Application | 13 | 0 | 0% |
| Command | 11 | 0 | 0% |
| DTO | 23 | 2 archivos | ~9% |
| EventSubscriber | 13 | 1 archivo | ~8% |
| Repository | 9 | 0 | 0% |
| Notification | 16 | 0 | 0% |
| RouteOptimization | 8 | 0 | 0% |
| Realtime | 6 | 1 archivo | ~17% |
| Security | 4 | 1 archivo | ~25% |

---

## 6. Documentación

### Fortalezas
- CLAUDE.md exhaustivo y bien mantenido
- FEATURES.md completo (692 líneas, v1.0.0)
- Múltiples documentos de diseño y planes en `docs/`

### Debilidades
- Algunos docs pueden estar desactualizados (planes de fases anteriores)
- No hay API docs generados (NelmioApiDoc roto)
- Falta CHANGELOG formal

---

## 7. Infraestructura

### Docker: Bien configurado
- 9+ servicios con docker-compose.local.yml
- Dockerfiles separados para cada servicio
- OSRM + VROOM para optimización de rutas

### CI/CD: Mínimo
- Solo `deploy.yml` en `.github/workflows/`
- No hay pipeline de tests automatizados
- No hay linting automático

---

## 8. Áreas Prioritarias para Mejora

### Prioridad 1: Arreglar Tests Rotos
1. Fix `ImportRunTracker` final → interfaz
2. Fix `TraccarIngestionService` constructor
3. Fix `TopicResolver`/`MercureJwtFactory` assertions
4. Fix tests funcionales (DriverApi, CustomerTenantFilter)

### Prioridad 2: CI/CD Pipeline
1. GitHub Actions para tests en cada PR
2. Lint check automatizado
3. Bloquear merge si tests fallan

### Prioridad 3: Aumentar Cobertura de Tests
1. Application services (DeliveryService, RoutePlanningService, RouteLifecycleService)
2. RouteBuilder + VROOM integration
3. Controladores API (V1)
4. Comandos console

### Prioridad 4: Fix NelmioApiDoc
1. Corregir routing del bundle
2. Generar documentación API actualizada

### Prioridad 5: Simplificación
1. Auditar módulos AI/ML → ¿se usan realmente?
2. Consolidar controladores duplicados
3. Evaluar si todos los canales de notificación están activos
