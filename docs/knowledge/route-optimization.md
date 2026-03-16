# Route Optimization

**Última actualización:** 2026-03-16
**Estado:** Vigente

## Providers de Optimización

| Provider | Tipo | Infraestructura | Descripción |
|----------|------|-----------------|-------------|
| **VROOM** | RouteOptimizer | Sí (servidor VROOM + OSRM) | VRP solver real con road distances |
| **Greedy** | RouteOptimizer | No | Nearest-neighbor heurístico, PHP puro |

## Providers de Routing

| Provider | Tipo | Infraestructura | Descripción |
|----------|------|-----------------|-------------|
| **OSRM** | RoutingEngine | Sí (servidor OSRM + mapa) | Road routing con distancias/duraciones reales |
| **Google Directions** | RoutingEngine | No (API key) | Google Maps Directions API |

## VROOM Integration

### Componentes

| Componente | Responsabilidad |
|------------|----------------|
| `RouteBuilder` | Construye rutas optimizadas via VROOM (multi-vehículo) |
| `VroomApiClient` | HTTP client para VROOM Express API (POST JSON) |
| `VroomRequestMapper` | Convierte entities → formato VROOM |
| `VroomResponseMapper` | Convierte respuesta VROOM → Route/RouteStop |
| `RouteOptimizationService` | Re-optimiza stop order de rutas existentes |
| `RouteCapacityValidator` | Valida restricciones de capacidad |

### Formato VROOM

- **Coordenadas**: `[longitude, latitude]` (orden invertido vs Google Maps)
- **Capacidad**: 3 dimensiones enteras: `[weight_grams, volume_cm3, parcels]`
- **Request**: POST JSON con arrays de `vehicles` y `jobs`
- **Response**: Array de `routes` con `steps` ordenados

### Greedy Optimizer

- Algoritmo nearest-neighbor en PHP puro
- Usa cálculo haversine interno (distancia en línea recta) para ordenar paradas
- Sin infraestructura requerida, ideal para deploy lite

## OSRM

### Preparación del Mapa

```bash
./docker/osrm/prepare-map.sh
```

Descarga mapa de Madrid (~75 MB) y genera ficheros `.osrm.*`. Solo una vez.

### Variables de Entorno

```env
OSRM_URL=http://osrm:5000                    # local
# OSRM_URL=http://osrm-mxo.railway.internal:5000  # Railway
VROOM_URL=http://vroom:3000                    # local
# VROOM_URL=http://vroom-mxo.railway.internal:3000  # Railway
```

## Google Directions

### Configuración

```env
DEFAULT_ROUTING_ENGINE=google_directions
GOOGLE_DIRECTIONS_API_KEY=<API key con Directions API habilitada>
```

`GoogleDirectionsFactory` inyecta `$defaultApiKey` desde env var. Si `CustomerIntegration.config` no trae `api_key`, usa el default global.

## Capacidad de Vehículos

Para optimización con VROOM, se necesita:

1. Configurar volumen y peso en cada **entrega** (Shipment: `weight_grams`, `volume_cm3`, `parcel_count`)
2. Configurar capacidad de cada **vehículo** (Vehicle: `max_weight_grams`, `max_volume_cm3`, `max_parcels`)

`RouteCapacityValidator` verifica que no se exceda la capacidad antes de confirmar rutas.

## Servicios de Análisis de Rutas

| Servicio | Propósito |
|----------|-----------|
| `RoutePlanningService` | Planificación de rutas (selección vehículos + optimización) |
| `RouteLifecycleService` | Transiciones de estado (PLANNED → ACTIVE → DONE) |
| `RouteOptimizationService` | Re-optimización de rutas existentes via VROOM |
| `RouteComparisonService` | Comparación planificado vs real (usa haversine interno) |
| `PostRouteAnalyzer` | Análisis de eficiencia post-ruta |

---

## Arquitectura del Dominio de Rutas

### Las 4 Capas

```
┌─────────────────────────────────────────────┐
│          RUTA PLANIFICADA (inmutable)         │
│  RouteSnapshot                               │
│  ├── originalStopOrder (JSON)                │
│  ├── originalPolyline (encoded)              │
│  ├── distanceBeforeKm / distanceAfterKm      │
│  ├── savingsPercent                          │
│  └── capacityValidation (JSON)               │
└──────────────────┬──────────────────────────┘
                   │ 1:1
┌──────────────────▼──────────────────────────┐
│          RUTA OPERATIVA (mutable)            │
│  Route + RouteStop[]                         │
│  ├── Route.status: PLANNED → ACTIVE → DONE   │
│  ├── RouteStop.sequence + status             │
│  ├── Vehicle asignado                        │
│  └── Driver asignado                         │
└──────────────────┬──────────────────────────┘
                   │ 1:N
┌──────────────────▼──────────────────────────┐
│       HISTORIAL DE EVENTOS (append-only)     │
│  RouteEvent[]                                │
│  ├── CREATED, OPTIMIZED, STARTED             │
│  ├── STOP_DELIVERED, STOP_EXCEPTION          │
│  ├── DEVIATION_DETECTED, ETA_CHANGED         │
│  └── REOPTIMIZED, STOPS_REORDERED            │
└──────────────────┬──────────────────────────┘
                   │ impacta
┌──────────────────▼──────────────────────────┐
│       ESTADO EN TIEMPO REAL                  │
│  RouteSnapshot (campos mutables)             │
│  ├── stopStates (JSON)                       │
│  ├── etas (JSON)                             │
│  ├── actualPolyline (encoded)                │
│  └── Mercure SSE → frontales                 │
└─────────────────────────────────────────────┘
```

### Flujo End-to-End

1. **Optimizador crea ruta** → `RouteBuilder` usa VROOM con shipments, vehículos, ventanas, prioridades, skills
2. **Se captura snapshot inmutable** → `RouteSnapshot` con orden original, polyline, métricas de ahorro
3. **Ruta se activa** → Driver inicia ruta, GPS tracking comienza via Traccar
4. **Eventos ocurren** → `RouteEvent` registra entregas, excepciones, desviaciones (append-only)
5. **Estado se actualiza** → `RouteStop.status` cambia, ETAs se recalculan (`EtaRecalculationListener`)
6. **Frontales se actualizan** → Mercure publica cambios en realtime a admin, customer, driver, tracking público
7. **Post-análisis** → `PostRouteAnalyzer` + AI comparan planificado vs real

---

## Los Dos Ejes del Dominio

### Eje 1: Planificación con Múltiples Estrategias

La ruta se planifica con **estrategias** que compiten o se combinan según el contexto.

| Estrategia | Archivo | Tipo | Cuándo se usa |
|-----------|---------|------|--------------|
| VROOM (VRP solver) | `src/RouteOptimization/VroomRouteOptimizer.php` | Exacto / metaheurístico | Default — resuelve Vehicle Routing Problem con constraints |
| Greedy (nearest-neighbor) | `src/Provider/RouteOptimizer/GreedyOptimizer.php` | Heurístico simple | Fallback — sin infraestructura VROOM disponible |
| Google Directions | `src/Provider/Routing/GoogleDirectionsEngine.php` | Routing engine | Cálculo de distancias/tiempos reales |
| OSRM | `src/Routing/OsrmRoutingEngine.php` | Routing engine | Cálculo de distancias/tiempos reales (self-hosted) |

**Constraints soportados:**

- Capacidad 3D: peso (kg), volumen (m³), bultos — `RouteCapacityValidator`
- Ventanas de entrega (time windows) — `RouteStop.deliveryWindowStart/End`
- Skills de vehículo — `VehicleSkill` enum (refrigerado, carga pesada, acceso peatonal, hazmat, frágil)
- Prioridades de envío — `ShipmentPriority` (0-100) mapeado a VROOM
- Tiempo de servicio por parada — `Shipment.serviceTimeSeconds`

### Eje 2: Reactividad a Eventos

La ruta **vive** después de planificarse. Los eventos impactan el estado en tiempo real sin destruir el plan original.

| Componente | Archivo | Función |
|-----------|---------|---------|
| RouteEvent (15 tipos) | `src/Entity/RouteEvent.php` | Log inmutable append-only de todo lo que ocurre |
| RouteSnapshot | `src/Entity/RouteSnapshot.php` | Preserva plan original + refleja estado actual |
| EtaRecalculationListener | `src/EventListener/Domain/EtaRecalculationListener.php` | Recalcula ETAs cuando cambian posiciones o estados |
| RouteOptimizationService | `src/Service/RouteOptimizationService.php` | Re-optimiza paradas PENDING en ruta activa |
| Mercure SSE | `src/Realtime/MercurePublisher.php` | Publica cambios a frontales en tiempo real |

**Eventos y su impacto:**

| Evento | Impacto |
|--------|---------|
| `STOP_DELIVERED` | Actualiza estado parada, recalcula ETAs restantes |
| `STOP_EXCEPTION` | Marca excepción, puede disparar re-optimización |
| `STOP_SKIPPED` | Salta parada, re-optimiza restantes |
| `DEVIATION_DETECTED` | Alerta, recalcula ETAs |
| `ETA_CHANGED` | Notifica frontales |
| `REOPTIMIZED` | Nueva secuencia de paradas PENDING |
| `STOPS_REORDERED` | Cambio manual de orden |

### Cruce de los Dos Ejes

Un evento puede disparar una re-planificación con una estrategia diferente a la original. Los datos históricos (excepciones, tiempos reales, feedback de drivers) pueden alimentar futuras planificaciones.

---

## Gaps Conocidos

| ID | Gap | Descripción |
|----|-----|-------------|
| GAP-1.1 | UI de optimización | No hay UI interactiva para que el admin lance la optimización |
| GAP-1.2 | Preview visual | No hay preview de la ruta optimizada en mapa antes de confirmar |
| GAP-3.1 | Flujo de creación | No hay flujo UI "seleccionar shipments → preview → configurar → confirmar ruta" |
| GAP-6.1 | Event sourcing puro | Estado actual se muta directamente (pragmático, no defecto) |

**Nota:** GAP-1.1, GAP-1.2 y GAP-3.1 son facetas del mismo gap: falta un flujo UI completo para la creación interactiva de rutas. El backend ya soporta toda la funcionalidad.

---

## Decisiones Pendientes

| Decisión | Contexto | Trigger |
|----------|----------|---------|
| Selección de estrategia de optimización | Admin no tiene visibilidad de qué estrategia se usó ni puede comparar | Diseño del flujo UI de creación de rutas (GAP-3.1) |
| Re-optimización automática vs manual | `RouteOptimizationService` puede re-optimizar, pero no hay política definida | Definición de política de negocio |
| Datos históricos para planificación | Existen AddressRisk, DriverFeedback, RouteComparison, PostRouteAnalyzer | Diseño del módulo de mejora continua |

**Detalle completo:** `docs/analysis/2026-03-15-business-requirements-audit.md`

## Historial

- 2026-03-11: Creación inicial
- 2026-03-16: Enriquecido con arquitectura 4 capas, dos ejes del dominio, flujo end-to-end, gaps conocidos y decisiones pendientes (fuente: business-requirements-audit)
