# Visión de Dominio: La Ruta como Núcleo del Negocio

**Fecha:** 2026-03-15
**Estado:** Validada (consenso usuario + auditoría técnica)
**Documento previo:** `docs/analysis/2026-03-15-business-requirements-audit.md`

---

## Punto Central del Negocio

El valor del software no está en el CRUD (cualquier sistema gestiona vehículos, clientes, envíos) sino en **convertir un conjunto desordenado de entregas en la secuencia óptima de paradas**, ejecutarla con visibilidad total, y aprender de los resultados.

**El negocio vende kilómetros y tiempo ahorrados.**

Todo lo demás — gestión de flota, multi-tenancy, portales, tracking — es infraestructura al servicio de ese punto central.

---

## Dos Ejes del Dominio

### Eje 1: Planificación con Múltiples Estrategias

La ruta no se optimiza con "un algoritmo". Se planifica con **estrategias** que compiten o se combinan según el contexto.

#### Estado actual

| Estrategia | Archivo | Tipo | Cuándo se usa |
|-----------|---------|------|--------------|
| VROOM (VRP solver) | `src/RouteOptimization/VroomRouteOptimizer.php` | Exacto / metaheurístico | Default — resuelve Vehicle Routing Problem con constraints |
| Greedy (nearest-neighbor) | `src/Provider/RouteOptimizer/GreedyOptimizer.php` | Heurístico simple | Fallback — sin infraestructura VROOM disponible |
| Google Directions | `src/Provider/Routing/GoogleDirectionsEngine.php` | Routing engine | Cálculo de distancias/tiempos reales |
| OSRM | `src/Routing/OsrmRoutingEngine.php` | Routing engine | Cálculo de distancias/tiempos reales (self-hosted) |

#### Constraints ya soportados

- Capacidad 3D: peso (kg), volumen (m³), bultos — `RouteCapacityValidator`
- Ventanas de entrega (time windows) — `RouteStop.deliveryWindowStart/End`
- Skills de vehículo — `VehicleSkill` enum (refrigerado, carga pesada, acceso peatonal, hazmat, frágil)
- Prioridades de envío — `ShipmentPriority` (0-100) mapeado a VROOM
- Tiempo de servicio por parada — `Shipment.serviceTimeSeconds`

#### Visión

No solo "un optimizador" sino un sistema donde:
- Múltiples estrategias pueden ejecutarse sobre los mismos datos
- Los resultados se pueden comparar antes de confirmar
- El sistema puede recomendar la mejor estrategia según el tipo de ruta
- Datos históricos (excepciones, tiempos reales, feedback de drivers) alimentan futuras planificaciones

---

### Eje 2: Reactividad a Eventos

La ruta no muere cuando se planifica. **Vive.** Los eventos impactan el estado en tiempo real sin destruir el plan original.

#### Estado actual

| Componente | Archivo | Función |
|-----------|---------|---------|
| RouteEvent (15 tipos) | `src/Entity/RouteEvent.php` | Log inmutable append-only de todo lo que ocurre |
| RouteSnapshot | `src/Entity/RouteSnapshot.php` | Preserva plan original + refleja estado actual |
| EtaRecalculationListener | `src/EventListener/Domain/EtaRecalculationListener.php` | Recalcula ETAs cuando cambian posiciones o estados |
| RouteOptimizationService | `src/Service/RouteOptimizationService.php` | Re-optimiza paradas PENDING en ruta activa |
| Mercure SSE | `src/Realtime/MercurePublisher.php` | Publica cambios a frontales en tiempo real |
| HTTP Polling fallback | `RealtimeEvent` entity | Para clientes sin soporte SSE |

#### Tipos de evento y su impacto

| Evento | Impacto |
|--------|---------|
| `STOP_DELIVERED` | Actualiza estado parada, recalcula ETAs restantes |
| `STOP_EXCEPTION` | Marca excepción, puede disparar re-optimización |
| `STOP_SKIPPED` | Salta parada, re-optimiza restantes |
| `DEVIATION_DETECTED` | Alerta, recalcula ETAs |
| `ETA_CHANGED` | Notifica frontales |
| `REOPTIMIZED` | Nueva secuencia de paradas PENDING |
| `STOPS_REORDERED` | Cambio manual de orden |

#### Visión

Los eventos no solo registran lo que pasó — **disparan reacciones**:
- Un evento puede provocar re-planificación usando una estrategia diferente a la original
- El histórico de eventos alimenta el aprendizaje del sistema
- Los frontales reaccionan en tiempo real a cada cambio

---

## Cruce de los Dos Ejes

Los ejes se cruzan: **un evento puede disparar una re-planificación con una estrategia diferente a la original.**

```
Eje 1 (Planificación)          Eje 2 (Reactividad)
         │                              │
         │    ┌──────────────────┐      │
         ├───→│  RUTA PLANIFICADA │←────┤  Evento dispara
         │    │  (snapshot)       │      │  re-planificación
         │    └────────┬─────────┘      │
         │             │                │
         │    ┌────────▼─────────┐      │
         │    │  RUTA OPERATIVA  │      │
         │    │  (estado actual)  │←────┤  Eventos actualizan
         │    └────────┬─────────┘      │  estado
         │             │                │
         │    ┌────────▼─────────┐      │
         └───→│  DATOS HISTÓRICOS │←────┘  Eventos alimentan
              │  (aprendizaje)    │         futuras planificaciones
              └──────────────────┘
```

---

## Decisiones Pendientes (para futuro brainstorming)

### Decisión 1: ¿Cómo se selecciona la estrategia de optimización?

**Contexto:** Actualmente la estrategia se selecciona por provider configuration (`CustomerIntegration`). El admin no tiene visibilidad de "qué estrategia se usó" ni puede comparar.

**Opciones a evaluar (cuando se diseñe):**
- A) El admin elige manualmente la estrategia
- B) El sistema recomienda basándose en el tipo de ruta
- C) Se ejecutan varias en paralelo y se presentan para comparar
- D) Combinación: recomendación + override manual

**Trigger:** Cuando se diseñe el flujo UI de creación de rutas (GAP-3.1 de la auditoría).

---

### Decisión 2: ¿Qué eventos disparan re-optimización automática vs manual?

**Contexto:** `RouteOptimizationService` puede re-optimizar paradas PENDING, pero actualmente no hay política definida de cuándo hacerlo automáticamente.

**Opciones a evaluar:**
- A) Siempre manual (admin o driver solicita)
- B) Automática por defecto, con override para desactivar (`Route.autoReoptimize` ya existe)
- C) Reglas configurables por customer (ej: "re-optimizar si >2 excepciones consecutivas")
- D) Recomendación IA: el sistema sugiere re-optimizar pero no lo hace sin confirmación

**Trigger:** Cuando se defina la política de negocio de re-optimización.

---

### Decisión 3: ¿Qué datos históricos alimentan futuras planificaciones?

**Contexto:** Ya existen datos históricos que podrían mejorar la planificación:

| Dato | Entidad | Uso potencial |
|------|---------|--------------|
| Tasa de excepciones por dirección | `AddressRisk` | Ajustar prioridad/orden de paradas en zonas problemáticas |
| Feedback de drivers | `DriverFeedback` | Coordenadas corregidas, notas de acceso, tiempos reales |
| Tiempos reales vs estimados | `RouteSnapshot` + `RouteComparison` | Calibrar estimaciones del optimizador |
| Análisis post-ruta | `PostRouteAnalyzer` + `Route.aiAnalysis` | Patrones de desviación, causas de retraso |

**Opciones a evaluar:**
- A) Solo analytics/reporting (no impactan planificación)
- B) Alimentan automáticamente el optimizador (service times ajustados, risk scoring)
- C) Sistema de recomendaciones: sugiere ajustes pero no los aplica automáticamente

**Trigger:** Cuando se diseñe el módulo de aprendizaje/mejora continua.

---

## Referencia

- Auditoría completa: `docs/analysis/2026-03-15-business-requirements-audit.md`
- Modelo de dominio: `docs/knowledge/domain-model.md`
- Optimización de rutas: `docs/knowledge/route-optimization.md`
- Framework de providers: `docs/knowledge/provider-framework.md`
