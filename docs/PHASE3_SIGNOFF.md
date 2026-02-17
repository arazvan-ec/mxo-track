# PHASE3_SIGNOFF (plantilla inicial)

Estado: **BORRADOR ACTIVO**
Fase objetivo: 3 — Asignaciones, visibilidad por rol y endurecimiento operativo.

## Objetivo
Definir desde el inicio el “Definition of Done” de Fase 3 para evitar deuda documental y alinear decisiones con Fase 2 cerrada.

## Dependencias de entrada (desde Fase 2)
- Fase 2 cerrada y congelada en `docs/PHASE2_SIGNOFF.md`.
- Reglas vigentes:
  - PK interna BIGINT + `public_id` ULID.
  - Topic Mercure por `public_id`.
  - Realtime mapa por SSE/Mercure JSON.
  - Desviación aceptada en `vehicle_last_position`.

## Scope previsto Fase 3 (borrador)

1. **Asignaciones de visibilidad reales**
   - Activar asignaciones CUSTOMER/DRIVER en `/api/vehicles` (dejar de devolver vacío temporal de Fase 2).
   - Consolidar y probar reglas de `VisibilityScopeService` con escenarios multi-tenant.

2. **Seguridad y contratos**
   - Revisar permisos por endpoint (API + admin/dev) para minimizar superficie.
   - Endurecer validaciones de entrada en endpoints sensibles (tracking, rutas, eventos).

3. **Tracking funcional ampliado**
   - Definir comportamiento de histórico por rol y ventanas temporales.
   - Alinear payloads y documentación con contratos definitivos de Fase 3.

4. **Documentación y validación de fase**
   - Actualizar `docs/FASE2_AUDIT.md` sólo como histórico; crear auditoría propia Fase 3.
   - Extender `scripts/phase_flow_validate.sh` con checks específicos de Fase 3.

## Definition of Done (pendiente de cierre)
- [ ] Asignaciones reales CUSTOMER/DRIVER implementadas y validadas.
- [ ] Reglas de visibilidad cubiertas por checks reproducibles de desarrollo.
- [ ] Contratos API Fase 3 documentados y consistentes con implementación.
- [ ] Flujo de validación transversal actualizado con criterios Fase 3.
- [ ] Signoff final Fase 3 marcado como **APROBADA**.

## Riesgos y decisiones a vigilar
- Evitar romper contratos públicos ya expuestos en Fase 2.
- Evitar mezclar IDs internos en payloads públicos.
- Mantener telemetría en SSE/Mercure JSON (sin Turbo Streams para tracking realtime).

## Próximo paso recomendado
Abrir checklist técnico por historias (backend/API/seguridad/docs) y asociarlo a este documento para seguimiento semanal.
