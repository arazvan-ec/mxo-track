# PHASE2_SIGNOFF

Fecha de cierre: 2026-02-17
Estado: **APROBADA (desarrollo)**

## Objetivo de este documento
Congelar la definición de “Done” de Fase 2 para evitar reabrir decisiones en Fase 3 y mantener trazabilidad técnica.

## Definition of Done (Fase 2)

1. **Stack y dependencias**
   - Symfony 7.4 LTS mantenido.
   - Doctrine ORM + Migrations presentes.
   - Mercure bundle operativo en backend.

2. **Modelo de datos**
   - Regla arquitectónica: PK interna BIGINT + `public_id` ULID.
   - Aplicada también en tablas técnicas 1:1.
   - Desviación aceptada: `vehicle_last_position` con `id` PK y `vehicle_id` UNIQUE.

3. **API mínima de tracking**
   - `GET /api/vehicles` activo.
   - `GET /api/vehicles/{publicId}/last-position` activo.
   - En Fase 2, CUSTOMER/DRIVER devuelven vacío en `/api/vehicles`.

4. **Realtime map**
   - Ruta `GET /fleet/map` activa.
   - Turbo desactivado solo en esa vista (`data-turbo="false"`).
   - Consumo realtime por Mercure SSE con JSON.
   - Cambio de vehículo con cierre limpio de `EventSource`.

5. **Mercure y telemetría**
   - Topic estándar vehículo: `/vehicles/{vehicle_public_id}/position`.
   - Publisher token sólo backend.
   - `GET /api/mercure-token` con validación estricta ULID para `vehicle_ids`.
   - Endpoint de simulación admin-only:
     - `POST /admin/dev/push-position`.

6. **Documentación operativa**
   - `docs/REALTIME_MAP.md` disponible con payload, topics y pasos de prueba.
   - `docs/PHASE_FLOW_VALIDATION.md` disponible para validar continuidad entre fases.

## Decisiones congeladas para Fase 3

- No se reabre en Fase 3 el debate BIGINT+ULID salvo incidencia crítica documentada.
- No se introduce Turbo Streams para telemetría de mapa; se mantiene SSE Mercure JSON.
- Asignaciones reales CUSTOMER/DRIVER se activan en Fase 3 sobre el contrato ya fijado.

## Checklist de cierre

- [x] Requisitos funcionales de Fase 2 implementados.
- [x] Decisiones arquitectónicas registradas en `docs/DECISIONS.md`.
- [x] Flujo de validación transversal disponible.
- [x] Auditoría de fase actualizada (`docs/FASE2_AUDIT.md`).

