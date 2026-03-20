# Spec: Política de Re-optimización Automática vs Manual

**Fecha:** 2026-03-20
**Contexto:** Decisión 2 de `docs/analysis/2026-03-15-business-requirements-audit.md`
**Estado:** Diseño inicial

---

## Problema

`RouteOptimizationService.reoptimizePendingStops()` puede re-optimizar paradas PENDING en rutas activas, pero la política de cuándo hacerlo automáticamente es limitada: solo existe `ExceptionReoptimizationSubscriber` que re-optimiza si `Route.autoReoptimize == true` tras una excepción. No hay más triggers automáticos ni reglas configurables por customer.

## Infraestructura Existente

| Componente | Estado | Función |
|-----------|--------|---------|
| `Route.autoReoptimize` (bool) | Activo, default `false` | Flag global por ruta |
| `reoptimizePendingStops()` | Activo | Re-optimiza stops PENDING desde posición actual del driver |
| `ExceptionReoptimizationSubscriber` | Activo | Trigger: `StopExceptionReported` + autoReoptimize=true |
| `PushNotifyReoptimizeSubscriber` | Activo | Notifica driver vía push tras re-optimización |
| `POST /api/routes/{id}/reoptimize` | Activo | Trigger manual por operador |
| `RouteReoptimized` event | Activo | Domain event + RouteEventType::REOPTIMIZED |

## Opciones Evaluadas

### Opción A: Siempre manual

**Flujo:** Solo el operador puede re-optimizar via API/UI. `autoReoptimize` se elimina o se ignora.

**Pros:**
- Máximo control humano
- Zero riesgo de re-optimizaciones no deseadas
- Simple: no hay lógica de políticas

**Contras:**
- El operador debe monitorizar constantemente
- Latencia: el driver sigue una ruta subóptima hasta que alguien interviene
- No escala con muchas rutas simultáneas

### Opción B: Automática por defecto con toggle

**Flujo:** `autoReoptimize` default cambia a `true`. Re-optimización automática tras excepciones. Toggle para desactivar per-ruta.

**Pros:**
- Sin intervención humana para el caso común
- El operador puede desactivar si la ruta tiene restricciones especiales

**Contras:**
- Solo trigger de excepción — no cubre otros escenarios (retraso, nueva parada añadida)
- Binary: on/off sin granularidad

### Opción C: Reglas configurables por customer (RECOMENDADA)

**Flujo:** Cada customer tiene un `ReoptimizationPolicy` configurable con triggers y condiciones. Las rutas heredan la política del customer, con override per-ruta.

**Triggers disponibles:**
1. `on_exception` — tras excepción en parada (existente)
2. `on_delay` — si el retraso acumulado supera un umbral (ej: >30 min)
3. `on_skip` — tras skip de parada
4. `on_consecutive_exceptions` — tras N excepciones consecutivas (ej: ≥2)
5. `on_new_stop` — cuando se añade una parada a ruta activa

**Pros:**
- Granularity: cada customer define su nivel de automatización
- Extensible: añadir nuevos triggers sin cambiar la estructura
- Configurable por el admin en la UI de Customer settings

**Contras:**
- Mayor complejidad que A o B
- Necesita UI de configuración de políticas

### Opción D: Recomendación IA

**Flujo:** El sistema sugiere re-optimizar pero no actúa. El operador/driver acepta o rechaza.

**Pros:**
- Transparencia total
- El humano decide siempre

**Contras:**
- Requiere canal de comunicación bidireccional rápido (push notification con acción)
- Latencia: esperar aprobación puede costar minutos críticos
- Over-engineering para la etapa actual

## Decisión

**Opción C: Reglas configurables por customer.**

Implementar en fases:

### Fase 1: Expandir triggers automáticos

Mantener `Route.autoReoptimize` como está. Añadir nuevos subscribers:
- `SkipReoptimizationSubscriber` — trigger `on_skip`: re-optimiza tras `StopSkipped` si `autoReoptimize=true`
- `DelayReoptimizationSubscriber` — trigger `on_delay`: re-optimiza si retraso acumulado > 30 min (configurable)

Estos subscribers siguen el mismo patrón que `ExceptionReoptimizationSubscriber`.

### Fase 2: ReoptimizationPolicy entity + herencia Customer → Route

- Nueva entidad `ReoptimizationPolicy` (per customer):
  - `triggers`: JSON array de triggers habilitados (`on_exception`, `on_skip`, `on_delay`, `on_consecutive_exceptions`, `on_new_stop`)
  - `delayThresholdMinutes`: int (default 30)
  - `consecutiveExceptionThreshold`: int (default 2)
  - `cooldownMinutes`: int — mínimo entre re-optimizaciones automáticas (default 10)
  - `enabled`: bool (default true)
- `Route.autoReoptimize` se reemplaza por referencia a la policy del customer
- Subscribers consultan la policy en vez del bool

### Fase 3: Admin UI + override per-ruta

- Admin configura `ReoptimizationPolicy` en Customer settings
- Override per-ruta: al crear ruta, el operador puede desactivar auto-reoptimización o modificar thresholds
- Dashboard de re-optimizaciones: historial de auto-reopt por ruta con trigger, timestamp, resultado

## Cambios Necesarios (Fase 1)

### Backend

- `SkipReoptimizationSubscriber`: escucha `StopSkipped`, verifica `autoReoptimize=true` + ACTIVE, llama `reoptimizePendingStops()`
- `DelayReoptimizationSubscriber`: escucha `StopDelivered`, calcula retraso acumulado vs estimado, si > 30 min + `autoReoptimize=true`, re-optimiza
- Ambos despachan `RouteReoptimized` event con metadata del trigger
- `RouteReoptimized` event: añadir campo `trigger` (string: `exception`, `skip`, `delay`, `manual`)

### Tests

- Test `SkipReoptimizationSubscriber`: skip con autoReoptimize=true → re-optimiza, con false → no
- Test `DelayReoptimizationSubscriber`: retraso > umbral → re-optimiza, < umbral → no
- Test integración: verificar que `PushNotifyReoptimizeSubscriber` sigue funcionando tras los nuevos triggers

## Métricas de Éxito

- Número de re-optimizaciones automáticas por trigger type
- Reducción en distancia/tiempo tras auto-reoptimización
- % de re-optimizaciones automáticas que el operador habría hecho manualmente (encuesta)
- Cooldown effectiveness: re-optimizaciones evitadas por cooldown

## Siguiente Paso

Crear plan de implementación para Fase 1 (expandir triggers) cuando el usuario lo solicite.
