# Spec: Eliminación Completa de Deuda Técnica

**Fecha:** 2026-03-19
**Approach:** C — Por impacto de negocio (pragmático)
**Alcance:** Todo el backlog arquitectónico + violaciones SOLID + migración DDD

---

## Problema

El codebase tiene ~25 items de deuda técnica documentados en CLAUDE.md y descubiertos via auditoría:

| Tipo | Count | Severidad |
|------|-------|-----------|
| DDD migration (entidades críticas en src/Entity/) | 7 entidades | High |
| Repository interfaces faltantes | 7 interfaces | High |
| DIP violations (servicios con EntityManager/repos concretos) | ~10 servicios | High |
| SOLID: SRP — User.php (5 responsabilidades) | 1 | Medium |
| SOLID: ISP/LSP — GpsDeviceProviderInterface | 1 interface | Medium |
| SOLID: DIP — DeliveryService | 1 servicio | High |
| Mercure listeners sin abstracción | ~6 listeners | Medium |
| Seguridad: credenciales sin encriptar | 1 entidad | Critical |
| Business features pendientes | 3 decisiones | Business |
| TODO en código | 1 (PDF export) | Low |

**Impacto actual:** Los servicios no son testeables sin base de datos, las entidades críticas mezclan dominio con infraestructura, y hay una violación de seguridad bloqueante para producción.

---

## Decisiones de Diseño

### 1. Entidades DDD: POPOs + Doctrine XML mapping

Las entidades de contextos críticos (Route, RouteStop, RouteEvent, Shipment, Parcel, Pod, ShipmentEvent) migrarán a POPOs en `src/Domain/{Context}/Model/` con mapping externo en `config/doctrine/`.

**Formato de mapping:** XML (no PHP attributes en clase separada) porque:
- Separación total entre dominio y persistencia
- Symfony/Doctrine soporta XML mapping nativo
- No requiere cargar clases de mapping en runtime

### 2. Repository interfaces: métodos basados en uso real

Cada interface solo expondrá los métodos que los servicios actuales necesitan. No "por si acaso".

### 3. GpsDeviceProviderInterface: split en 2 interfaces

- `GpsPositionProviderInterface`: `getPositions()`, `isAvailable()` — ambos providers lo necesitan
- `GpsDeviceManagerInterface`: `login()`, `getSessionCookie()`, `getDevices()`, `createDevice()` — solo pull-based (Traccar)

### 4. User.php: no migrar a DDD, solo documentar

User.php está en contexto pragmático (Identity/Auth). La SRP violation es real pero el costo de refactorizarla (rompe Symfony Security integration) supera el beneficio. Se documenta como decisión consciente.

### 5. Credential encryption: Symfony Secrets o sodium

`CustomerIntegration.credentials` se encriptará con `sodium_crypto_secretbox` usando `APP_SECRET` como key derivation base.

### 6. Mercure: verificar que RealtimePublisherInterface ya se usa

El codebase ya tiene `RealtimePublisherInterface`. Verificar qué listeners la usan y cuáles van directamente a HubInterface.

---

## Fases

### Fase 1: Foundation — Repository Interfaces + DIP Fixes
**Objetivo:** Desbloquear testabilidad sin cambiar entidades
- Extraer repository interfaces para Route Planning context
- Crear implementaciones Doctrine
- Migrar servicios críticos (RouteOptimizationService, RouteBuilder, DeliveryService) a interfaces
- TDD: test unitario por cada servicio migrado

### Fase 2: Core DDD — Route Planning Entities → Domain POPOs
**Objetivo:** El corazón del negocio en DDD puro
- Route, RouteStop, RouteEvent → POPOs en src/Domain/Route/Model/
- Doctrine XML mapping en config/doctrine/
- Actualizar imports en todo el codebase

### Fase 3: Security — Credential Encryption
**Objetivo:** Producción-ready para clientes con API keys
- Encriptar CustomerIntegration.credentials
- Migration para encriptar datos existentes
- Tests de encrypt/decrypt

### Fase 4: SOLID — GpsProvider ISP/LSP
**Objetivo:** Contratos limpios para providers GPS
- Split GpsDeviceProviderInterface
- Actualizar TraccarProvider y WebhookGpsProvider
- Actualizar factories y proxies

### Fase 5: Delivery DDD — Shipment/Delivery Entities
**Objetivo:** Segundo contexto crítico en DDD puro
- Repository interfaces para Shipment context
- Shipment, Parcel, Pod, ShipmentEvent → POPOs
- Doctrine XML mapping
- Migrar DeliveryService y servicios dependientes

### Fase 6: Infrastructure — Mercure + Provider Cleanup
**Objetivo:** Consistencia en abstracciones
- Audit de Mercure listeners → migrar a RealtimePublisherInterface
- Provider framework: evaluar boilerplate actual

### Fase 7: Business Features — Decisiones Pendientes
**Objetivo:** Completar gaps de negocio
- Requiere brainstorming separado para cada decisión (strategy selection, re-optimization policy, historical data)
- Cada una es un proyecto en sí mismo — esta fase es un placeholder

---

## Dependencias entre fases

```
Fase 1 (Foundation) ──→ Fase 2 (Route DDD) ──→ Fase 5 (Delivery DDD)
                                                       │
Fase 3 (Security)     [independiente]                  │
                                                       │
Fase 4 (SOLID GPS)    [independiente]                  │
                                                       │
Fase 6 (Mercure)      [independiente]                  ▼
                                              Fase 7 (Business)
```

Fases 3, 4 y 6 son independientes entre sí y de las demás (pueden ejecutarse en paralelo).

---

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Romper tests existentes al mover entidades | TDD: tests antes del cambio, verificar green después |
| Doctrine mapping XML incorrecto | Migration + fixture load como smoke test |
| Imports rotos al mover clases | `grep -r "use App\\Entity\\Route"` antes/después |
| Constructor changes sin actualizar factories | Checklist mandatory de CLAUDE.md |

---

## Exclusiones

- **User.php SRP**: Decisión consciente de no refactorizar (contexto pragmático)
- **Event sourcing puro (GAP-6.1)**: Modelo híbrido es decisión de diseño, no deuda
- **SlaReportController PDF**: Baja prioridad, no bloquea arquitectura
- **Provider framework codegen**: Trigger no alcanzado (<6 servicios)
