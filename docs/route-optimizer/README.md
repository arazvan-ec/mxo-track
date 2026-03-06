# Route Optimizer — Planes de Implementación

> Índice de planes de mejora del sistema de optimización de rutas de MXO Track.
> Cada plan detalla objetivo, estado actual, cambios propuestos, modelo de datos y verificación.

## Estado General

| # | Plan | Estado | Prioridad | Fichero |
|---|------|--------|-----------|---------|
| 5 | Tiempo de servicio variable | 📋 Planificado | Alta | [PLAN_05_SERVICIO_VARIABLE.md](PLAN_05_SERVICIO_VARIABLE.md) |
| 10 | Max paradas por ruta (VROOM max_tasks) | 📋 Planificado | Media | [PLAN_10_MAX_PARADAS_VROOM.md](PLAN_10_MAX_PARADAS_VROOM.md) |
| 11 | Manifiesto de carga LIFO | 📋 Planificado | Media | [PLAN_11_CARGA_LIFO.md](PLAN_11_CARGA_LIFO.md) |
| 12 | Análisis de rutas históricas | 📋 Planificado | Baja | [PLAN_12_RUTAS_HISTORICAS.md](PLAN_12_RUTAS_HISTORICAS.md) |
| B | Prioridad de envíos (VROOM priority) | 📋 Planificado | Alta | [PLAN_B_PRIORIDAD_ENVIOS.md](PLAN_B_PRIORIDAD_ENVIOS.md) |
| C | Skills/restricciones de vehículo | 📋 Planificado | Media | [PLAN_C_SKILLS_VEHICULO.md](PLAN_C_SKILLS_VEHICULO.md) |

## Leyenda de Estados

| Estado | Significado |
|--------|-------------|
| 📋 Planificado | Plan escrito, pendiente de implementación |
| 🚧 En progreso | Implementación iniciada |
| ✅ Completado | Implementado y verificado |
| ⏸️ Pausado | Pendiente de decisión o dependencia |

## Documentación Relacionada

- [Principios de Optimización de Rutas](../PRINCIPIOS_OPTIMIZACION_RUTAS.md) — Documento base con los 12 principios operativos + 6 avanzados
- [Presentación visual del optimizador](../route-optimizer-presentation.html) — HTML interactivo con arquitectura y flujo del sistema

## Stack Tecnológico

- **VROOM** v1.15 — Vehicle Routing Problem solver (optimiza duración total)
- **OSRM** — Open Source Routing Machine (distancias reales por carretera)
- **Symfony 7.4 LTS** — Backend PHP con Doctrine ORM
- **PostgreSQL 16** — Persistencia de rutas, paradas, envíos y vehículos
