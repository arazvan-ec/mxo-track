# Spec: Session B — Security + Cleanup

**Fecha:** 2026-03-20
**Bounded context:** Pragmático (Tenant Management, Admin)

## Fase 3: Credential Encryption

**Problema:** `CustomerIntegration.config` almacena API keys como JSON plaintext en PostgreSQL.

**Approach elegido:** B — Doctrine Custom Type `encrypted_json`

- `CredentialEncryptor` service: `sodium_crypto_secretbox` con key derivada de `APP_SECRET`
- `EncryptedJsonType` Doctrine type: encripta en `convertToDatabaseValue`, desencripta en `convertToPHPValue`
- `EncryptedJsonTypeInitializer`: bridge entre Symfony DI y el singleton de Doctrine
- Cambio en entity: `Types::JSON` → `'encrypted_json'` (1 línea)
- Migration: convierte datos existentes de plaintext JSON a encrypted TEXT
- Zero cambios en los 11+ consumidores (factories, controllers)

## Fase 10.1: SLA PDF Export

**Approach elegido:** A — DomPDF

- `composer require dompdf/dompdf`
- Reutiliza template `sla_export.html.twig` existente
- Renderiza HTML → PDF → Response con `Content-Type: application/pdf`

## Fase 10.2-10.3: Documentation

- Entrada en `docs/decisions/log.md` para User.php SRP (decisión de NO refactorizar)
- Entrada para codegen trigger evaluation (5 proxies ≤ 6, no alcanzado)
