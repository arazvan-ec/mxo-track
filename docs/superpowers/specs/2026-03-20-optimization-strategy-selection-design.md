# Spec: Selección de Estrategia de Optimización

**Fecha:** 2026-03-20
**Contexto:** Decisión 1 de `docs/analysis/2026-03-15-business-requirements-audit.md`
**Estado:** Diseño inicial

---

## Problema

Actualmente la estrategia de optimización se selecciona automáticamente via `CustomerIntegration` (Provider Framework). El admin no tiene visibilidad de qué estrategia se usó, no puede elegir entre opciones, y no puede comparar resultados entre estrategias.

## Restricciones

- El Provider Framework ya maneja resolución per-tenant (`ProviderFactoryRegistry` + `TenantAwareProxy`)
- Existen 2 optimizadores: VROOM (`VroomRouteOptimizer`) y Greedy fallback (`GreedyOptimizer`)
- `RoutePerformanceMetric` ya registra `optimizerUsed` por ruta completada
- El Route Planner (React SPA) ya tiene un flow de 4 pasos

## Opciones Evaluadas

### Opción A: Admin elige manualmente

**Flujo:** En Step 2 del Route Planner, un selector permite elegir "VROOM", "Greedy", etc.

**Pros:**
- Simple de implementar (pasar `optimizer` param al backend)
- Control total del admin

**Contras:**
- El admin necesita conocer las diferencias entre optimizadores
- No hay guía sobre cuál es mejor para el caso específico

### Opción B: Recomendación automática con override

**Flujo:** El sistema recomienda un optimizador basándose en el número de paradas, constraints, y disponibilidad de infra. El admin puede aceptar o cambiar.

**Pros:**
- Mejor UX: el sistema sugiere, el admin decide
- Escalable a más optimizadores sin abrumar al usuario

**Contras:**
- Necesita lógica de recomendación (moderada complejidad)

### Opción C: Ejecución paralela + comparación

**Flujo:** El sistema ejecuta N optimizadores en paralelo y presenta los resultados lado a lado (distancia total, duración estimada, distribución de paradas).

**Pros:**
- Máxima información para decidir
- Demuestra el valor de optimizadores premium

**Contras:**
- Mayor costo computacional (N optimizaciones por request)
- UX más compleja (comparar rutas en mapa)
- Puede confundir si las diferencias son mínimas

### Opción D: Recomendación + comparación bajo demanda (RECOMENDADA)

**Flujo:**
1. El sistema recomienda el mejor optimizador (como opción B)
2. El admin puede aceptar y generar preview directamente
3. Opcionalmente, el admin puede pedir "Comparar con otros" → ejecuta los demás y muestra tabla comparativa

**Pros:**
- Path rápido para el caso común (aceptar recomendación)
- Path completo para evaluación (comparar)
- Escalable: nuevos optimizadores se añaden al comparador automáticamente

**Contras:**
- Más código que A o B solos (pero incremental)

## Decisión

**Opción D: Recomendación + comparación bajo demanda.**

Implementar en fases:
1. **Fase 1 (MVP):** Selector manual en Step 2 (Opción A) — inmediato, desbloquea visibilidad
2. **Fase 2:** Lógica de recomendación automática (Opción B sobre A)
3. **Fase 3:** Comparación bajo demanda (Opción D completa)

## Cambios Necesarios (Fase 1 — MVP)

### Backend

- `RoutePlanningService.buildRoutes()` acepta `?string $optimizerName = null`
- Si `null`, usa el provider resuelto por tenant (comportamiento actual)
- Si especificado, resuelve via `ProviderFactoryRegistry` con ese nombre
- `BuildRoutesInput` gana campo `optimizerName`

### Frontend

- Step 2 del Route Planner: nuevo selector "Optimizador" con opciones:
  - "Automático (recomendado)" — default
  - "VROOM" — optimización completa
  - "Greedy" — rápido, sin infra externa
- El valor se envía en el payload de `/admin/route-planner/preview`

### API

- `POST /admin/route-planner/preview` acepta campo opcional `optimizer_name`
- `GET /admin/route-planner/optimizers` — nuevo endpoint que lista optimizadores disponibles

## Métricas de Éxito

- Admin puede ver qué optimizador se usó en cada ruta
- Admin puede elegir optimizador antes de generar preview
- `RoutePerformanceMetric.optimizerUsed` permite comparar rendimiento entre estrategias

## Siguiente Paso

Crear plan de implementación para Fase 1 (MVP) cuando el usuario lo solicite.
