# Plan — Dashboard Widget Expansion (PR 1 of 3)

**Spec:** `docs/superpowers/specs/2026-04-13-dashboard-widget-expansion-design.md`
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Estrategia:** parallel-first. 3 waves.

## Fase 1 (v0): Implementación mínima funcional

### [parallel] Wave 1 — Backend métricas + tipos frontend

Los tres no dependen entre sí y tocan archivos disjuntos.

**1a — Backend: extender `AdminMetricsService`**
- Archivo: `backend/src/Service/AdminMetricsService.php`
- Añadir métodos `countAll`, `countByStatusGroup`, `countByStatusAndSince`
- Extender `collect()` con los 8 campos nuevos (ver spec §Backend)
- → produce: diccionario extendido con `total_routes`, `total_stops`,
  `route_status_breakdown`, `stop_status_breakdown`, `deliveries_today`,
  `failed_today`, `import_runs_last_7d`, `positions_last_24h`

**1b — Tests: `AdminMetricsServiceTest`**
- Archivo nuevo: `backend/tests/Service/AdminMetricsServiceTest.php`
  (si no existe). Si existe, extender.
- TDD: escribir test para cada campo nuevo usando `Connection` mock o `KernelTestCase`
  con fixtures de `route_plan`, `route_stop`, `csv_import_run`, `vehicle_positions`
- → produce: tests verdes que prueban los nuevos campos
- **NOTA TDD:** este test se escribe ANTES de 1a. El orden lógico es test → red →
  implementación → green. En la ejecución paralela, el agente de 1a escribe el test
  primero, lo ve fallar, y luego implementa.

**1c — Frontend: extender `DashboardMetrics` type**
- Archivo: `frontend/src/api/types.ts`
- Añadir los 8 campos nuevos al interface `DashboardMetrics`
- → produce: tipo TypeScript actualizado

### Wave 2 — Refactor de la página (depende de Wave 1)

**2 — `AdminDashboardPage.tsx`: envolver widgets en `CollapsibleWidget`**
- Archivo: `frontend/src/pages/admin/AdminDashboardPage.tsx`
- Import: `CollapsibleWidget` desde `@/components/widgets/CollapsibleWidget`
- Envolver los 7 widgets visibles con `CollapsibleWidget`:
  1. Cada uno de los 4 KPIs (Rutas, Paradas, Imports, Posiciones)
  2. SISTEMA
  3. Entregas (7 días)
  4. Top transportistas
  5. Fila infraestructura (como un único widget con 3 sub-cards internas)
- Para KPIs con desglose (Rutas, Paradas), añadir dentro del cuerpo expandido
  una lista `<dl>` que mapea `metrics.route_status_breakdown` etc.
- Para KPIs con secundario temporal (Imports, Posiciones), mostrar el extra
  (`import_runs_last_7d`, `positions_last_24h`) como sub-métrica.
- Conservar animaciones `animate-fade-in-up` en el contenedor que envuelve cada
  `CollapsibleWidget`, no dentro.
- Conservar el bento grid layout actual.
- Conservar el banner de Reportes al final (no colapsable — es link de navegación).
- → produce: dashboard con todos los widgets colapsables y contenido enriquecido.

**Nota:** Wave 2 es tarea única porque todo el cambio ocurre en un solo archivo
(`AdminDashboardPage.tsx`). Dos agentes editando el mismo archivo generan conflictos.

### Wave 3 — Verificación (depende de Wave 2)

**3a — Lint + tests backend**
```
cd backend && make lint
cd backend && php vendor/bin/phpunit --filter AdminMetrics
```

**3b — Build frontend**
```
cd frontend && npm run build
```

**3c — Update manifest**
```
make manifest
```

**3d — Commit + push**
- Mensaje: `feat: dashboard widgets collapsible con métricas enriquecidas`
- Push a `claude/enhance-dashboard-widgets-sxseH`

## Fase 2 (Mature): omitida

No aplica en PR 1. El código ya queda en estado "production-ready" en Fase 1 —
no se introduce deuda técnica que requiera segunda pasada.

**Lo que se reserva para PR 2/3 (siguientes sesiones):**
- Migración a widget-registry
- Infraestructura de user preferences

## Criterios de éxito (copia de spec)

1. `GET /api/admin/dashboard` devuelve los 8 campos nuevos en `metrics`.
2. Cada uno de los 7 widgets visibles está envuelto en `CollapsibleWidget`.
3. Click en header colapsa/expande con persistencia localStorage.
4. Desglose por status visible en cuerpos expandidos de Rutas y Paradas.
5. `make lint` verde.
6. `php vendor/bin/phpunit` verde.
7. `cd frontend && npm run build` verde.
