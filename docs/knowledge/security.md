# Security

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Autenticación

| Método | Área | Mecanismo |
|--------|------|-----------|
| **Form Login** | `/login` (Admin, Customer) | CSRF + rate limiting (5 intentos/min) |
| **API Key** | `/api/v1/*` | Header `X-Api-Key`, stateless, SHA-256 hash |
| **Session Auth** | Admin, Driver web | Redis sessions (prefijo `sess:transporte:`, TTL 12h) |

## Roles y Jerarquía

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

Definidos en `UserRole` enum.

### Access Control

| Path | Roles Requeridos |
|------|-----------------|
| `/admin/*` | ADMIN, OPERATOR |
| `/operator/*` | ADMIN, OPERATOR |
| `/driver/*`, `/api/driver/*` | ADMIN, DRIVER |
| `/api/v1/*` | API key o sesión |
| `/track/*` | Público |

## Multi-Tenancy

- `CustomerTenantFilter`: Doctrine SQL filter que añade `WHERE customer_id = ?`
- `CustomerScopedEntityInterface`: Entidades opt-in al filtro
- `DoctrineCustomerFilterSubscriber`: Activa filtro para ROLE_CUSTOMER y ROLE_DRIVER con customer asociado
- Admin/Operator bypasean el filtro

## Security Headers

`SecurityHeadersSubscriber` añade en cada response:
- `Content-Security-Policy` (CSP)
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`

## Componentes de Seguridad

| Componente | Responsabilidad |
|------------|----------------|
| `UserChecker` | Valida que el usuario está activo antes de autenticar |
| `ApiRateLimitSubscriber` | Rate limiting por API key |
| `CsrfApiSubscriber` | CSRF en APIs con sesión |
| `LoginAuditSubscriber` | Auditoría de login (éxito/fallo) |
| `AuditLog` entity | Trail de auditoría estructurado |
| `ApiKey` entity | API keys (hash SHA-256, nunca almacenadas en plain) |

## Auditoría

`AuditLog` entity registra operaciones sensibles:
- Login/logout
- Cambios en usuarios
- Operaciones críticas de negocio

Listeners: `LoginAuditSubscriber`, `AuditDeliveryListener`.

## Historial

- 2026-03-11: Creación inicial
