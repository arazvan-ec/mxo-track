# Componentes Reutilizables del Codebase Actual

> Fecha: 2026-02-27
> Análisis de qué podemos reutilizar de mxo-track para el sistema agent-native

---

## Patrones Arquitectónicos (REUTILIZAR)

### 1. PublicIdTrait — Identidad dual
- **Qué es**: PK interno `BIGINT` auto-increment + public_id `ULID` para APIs
- **Por qué reutilizar**: Fundamental para agent-native — los agentes nunca ven IDs internos
- **Ubicación**: `backend/src/Entity/Concerns/PublicIdTrait.php`

### 2. Multi-Tenant via Doctrine SQL Filter
- **Qué es**: `CustomerTenantFilter` filtra automáticamente por `customer_id`
- **Por qué reutilizar**: Aislamiento de datos por cliente B2B — cada agente opera dentro de su tenant
- **Componentes**: `CustomerScopedEntityInterface`, `DoctrineCustomerFilterSubscriber`

### 3. SoftDelete
- **Qué es**: `SoftDeleteTrait` con campo `deleted_at`
- **Por qué reutilizar**: Los agentes no deben borrar datos permanentemente

### 4. Sistema de Roles
- **Roles actuales**: `ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER > ROLE_DRIVER`
- **Para agent-native**: Añadir `ROLE_AGENT` con permisos específicos por tool

---

## Entidades Existentes (REUTILIZAR y EXTENDER)

### Customer
- **Reutilizable**: Sí, como base
- **Extender con**:
  - Categoría de frecuencia (no_frecuente, frecuente, muy_frecuente, super_frecuente)
  - Preferencias de franja horaria (mañana/tarde)
  - Email de contacto
  - Configuración de notificaciones

### Vehicle
- **Reutilizable**: Sí, como base
- **Extender con**:
  - `maxWeightKg` (peso máximo en kg)
  - `maxVolumeM3` (volumen máximo en m³)
  - `licensePlate` (matrícula)
  - `vehicleType` (furgoneta, camión, etc.)

### Route / RouteStop
- **Reutilizable**: Sí, la estructura base es buena
- **Extender con**:
  - `estimatedDurationMinutes` en Route
  - `estimatedDeliveryTimeMinutes` en RouteStop
  - `totalWeightKg`, `totalVolumeM3` en Route (pre-calculados)
  - Validación pre-ruta de capacidad del vehículo

### Shipment
- **Reutilizable**: Sí, pero necesita una reestructuración significativa
- **Cambios necesarios**:
  - Concepto de "Bulto/Parcel" como entidad separada (relación 1:N con Shipment)
  - `totalParcels` en Shipment
  - Cada Parcel: peso, volumen, EAN, descripción

### ShipmentEvent
- **Reutilizable**: Sí, el patrón de eventos es perfecto para agent-native
- **Extender tipos**:
  - LOADED (cargado)
  - IN_ROUTE (en ruta)
  - ABSENCE (ausencia)
  - RETURN (devolución)
  - PICKUP (recogida)

---

## Servicios Existentes (REUTILIZAR y EXTENDER)

### RouteOptimizationService ⭐
- **Reutilizable**: Sí — algoritmo nearest-neighbor ya implementado
- **Extender con**:
  - Algoritmo "empezar desde el punto más alejado"
  - Integración con isócronas
  - Validación de capacidad (peso/volumen)
  - Ventanas de tiempo de entrega
  - OpenRouteService para distancias reales (ya integrado en el mapa)

### ShipmentCsvImporter ⭐
- **Reutilizable**: Sí — estructura base para importación CSV
- **Extender con**:
  - Columnas de peso y volumen por bulto
  - Columna de tipo de servicio
  - Generación automática de rutas post-importación
  - Validación más robusta

### TraccarApiClient / TraccarIngestionService
- **Reutilizable**: Completamente — GPS tracking es core
- **Notas**: Ya funciona con Traccar para posiciones en tiempo real

### NotificationService / WebhookNotificationService
- **Reutilizable**: Sí — base para sistema de notificaciones
- **Extender con**: Notificaciones por estado de bulto/entrega al cliente B2B

### MercureJwtFactory / Mercure
- **Reutilizable**: Completamente — realtime SSE para dashboards
- **Notas**: Perfecto para dashboards agent-native en tiempo real

### EtaService
- **Reutilizable**: Sí — estimación de tiempos de llegada

### BillingService
- **Reutilizable**: Base para métricas €/ruta, €/bulto

---

## Infraestructura (REUTILIZAR)

| Componente | Estado | Notas |
|-----------|--------|-------|
| Docker setup | ✅ Funciona | PHP 8.4, PostgreSQL 16, Redis 7, Mercure |
| Doctrine ORM 3.x | ✅ Funciona | Migraciones, attributes, SQL filters |
| Twig + Turbo | ✅ Funciona | Frontend reactivo sin SPA |
| Traccar integration | ✅ Funciona | GPS tracking completo |
| OpenRouteService | ✅ Integrado | Para routing real (no solo Haversine) |
| Railway deploy | ✅ Configurado | Producción |

---

## Lo que NO existe y hay que crear

| Componente | Prioridad | Descripción |
|-----------|-----------|-------------|
| Entidad `Parcel` (Bulto) | Alta | peso, volumen, EAN, descripción, estado |
| Entidad `ServiceType` | Alta | Enum: entrega, entrega+recogida, devolución |
| Capacidad de vehículo | Alta | peso_max, volumen_max en Vehicle |
| Validación pre-ruta | Alta | ¿cabe todo en el camión? |
| Generador de albaranes | Media | PDF generation |
| Sistema de isócronas | Media | OpenRouteService isochrones API |
| RGU (Ruta Geográfica Unitaria) | Media | Agrupación de zonas por tiempo |
| Categorización de clientes | Media | Frecuencia + preferencias |
| Dashboard de productividad | Media | Métricas por transportista |
| Agent Tools API | Alta | Capa de tools atómicos para agentes |
| Agent Context System | Alta | Context.md pattern para cada agente |
| Costes y métricas | Media | €/ruta, €/bulto |
