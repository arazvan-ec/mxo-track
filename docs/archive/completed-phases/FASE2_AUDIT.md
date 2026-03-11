# Auditoría de cumplimiento — FASE 2 (Tracking Core)

Fecha: 2026-02-17

## Veredicto rápido

**Estado general: CUMPLE FASE 2 (con desviación explícitamente aceptada).**

## Decisión arquitectónica aplicada

- Se mantiene regla rígida: **PK interna BIGINT + `public_id` ULID** en entidades del dominio.
- Para tablas técnicas 1:1 también se prioriza consistencia del patrón anterior.
- **Desviación aceptada** respecto al prompt literal: `vehicle_last_position` usa `id` BIGSERIAL como PK + `vehicle_id` UNIQUE (en lugar de `vehicle_id` como PK).

## Checklist resumido

1. Dependencias Symfony 7.4 / Doctrine / Mercure: ✅
2. Entidades y migración base con BIGINT interno + ULID público: ✅
3. Endpoints base (`/api/vehicles`, `/api/vehicles/{publicId}/last-position`): ✅
4. Mapa `/fleet/map` con Turbo desactivado solo en esa vista + SSE + cierre limpio de EventSource: ✅
5. Endpoint admin fake push (`POST /admin/dev/push-position`) + payload JSON esperado: ✅
6. Telemetría por Mercure (sin Turbo Streams): ✅
7. Documentación realtime (`docs/REALTIME_MAP.md`): ✅

## Nota final

La desviación de `vehicle_last_position` se considera **aceptada por decisión de arquitectura** para preservar uniformidad del modelo interno.
