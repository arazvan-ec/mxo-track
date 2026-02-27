# Análisis SGA: Registro Simple vs Gestión Completa de Almacén

> Fecha: 2026-02-27
> Contexto: Respuesta a Q5 — El cliente prefiere gestión completa

---

## Resumen Ejecutivo

**Recomendación: Empezar con Fase 0 (registro simple, 3 semanas) e iterar hacia SGA completo.**

El SGA completo requiere 15-20 entidades nuevas y ~6 meses de desarrollo. Pero la arquitectura permite un enfoque incremental donde cada fase entrega valor independiente.

---

## Estado Actual de la Plataforma

Lo que ya existe y es relevante para el SGA:

- `Shipment` — envío con tracking, ya tiene evento `IN_HUB`
- `ShipmentEvent` — timeline con tipo `IN_HUB` (paso por almacén)
- `Route` / `RouteStop` — rutas con paradas, vínculo con Shipment
- `CustomerLocation` — ubicaciones del cliente (potenciales almacenes) con coordenadas
- `Pod` — prueba de entrega
- `CsvImportRun` — importación masiva

**Punto clave:** `IN_HUB` ya existe como evento pero no se gestiona activamente. `CustomerLocation` ya modela almacenes.

---

## Opción A: Registro Simple de Entrada/Salida

### Alcance

Control de flujo de paquetes dentro/fuera del almacén, sin ubicaciones internas ni stock.

### Modelo de Datos (2-3 entidades)

**`WarehouseEntry`** (movimiento)

| Campo | Tipo |
|-------|------|
| publicId | ULID |
| shipment | FK → Shipment |
| location | FK → CustomerLocation |
| type | Enum: ENTRY, EXIT |
| operatorUser | FK → User |
| reference | string |
| notes | text |
| createdAt | DateTimeImmutable |

### Esfuerzo: ~2-3 semanas

| Componente | Tiempo |
|------------|--------|
| Entidades + migraciones | 1-2 días |
| Servicios | 2-3 días |
| Controladores admin | 2-3 días |
| API para escaneo móvil | 1-2 días |
| Templates Twig | 2-3 días |
| Tests | 1-2 días |

### Valor

- Trazabilidad básica: ¿el bulto pasó por el almacén?
- Vincular eventos de almacén con timeline del envío
- Control de carga/descarga
- Baja barrera de adopción para operarios

### Limitaciones

- No sabe DÓNDE está el bulto dentro del almacén
- No gestiona stock ni inventario
- No detecta bultos perdidos internamente
- Dependencia total del operario para registrar

---

## Opción B: SGA Completo

### Alcance — Módulos

| Módulo | Descripción |
|--------|-------------|
| **Recepción** | Registro de llegada, verificación vs albarán, inspección, discrepancias |
| **Put-away** | Asignación de ubicación sugerida, confirmación por escaneo |
| **Inventario** | Stock en tiempo real por ubicación, reservas, alertas min/max, bloqueos |
| **Picking** | Generación de órdenes desde rutas, secuencia optimizada, escaneo |
| **Packing** | Verificación, pesaje, embalaje, etiquetado |
| **Expedición** | Agrupación por ruta, verificación de carga, albarán de salida |
| **Devoluciones** | Recepción, inspección, reintegración a stock o descarte |
| **Zonas/Ubicaciones** | Pasillos, estanterías, niveles (A-01-03-02), mapa visual |
| **Inventario cíclico** | Conteos planificados, discrepancias, ajustes |
| **Multi-almacén** | Varios almacenes por cliente, transferencias, stock consolidado |

### Modelo de Datos (15-20 entidades nuevas)

#### Entidades principales

**`Warehouse`** — Almacén
- publicId, customer, name, code (MAD-01), address, lat/lng, isActive

**`WarehouseZone`** — Zona del almacén
- publicId, warehouse, name, code (REC, ALM-A, EXP), zoneType (RECEIVING, STORAGE, PICKING, PACKING, SHIPPING, RETURNS)

**`StorageLocation`** — Ubicación de almacenaje
- publicId, zone, code (A-01-03-02), aisle, rack, level, position
- locationType (PALLET, SHELF, FLOOR, COLD, HAZMAT)
- maxWeight, maxVolume, isOccupied

**`Product`** — Referencia / SKU
- publicId, customer, sku, name, barcode (EAN), weight, volume, dimensions
- minStock, maxStock

**`InventoryItem`** — Stock en ubicación
- publicId, product, location, warehouse, quantity, reservedQuantity
- lotNumber, expiresAt, status (AVAILABLE, RESERVED, BLOCKED, DAMAGED)

**`StockMovement`** — Movimiento de stock
- publicId, warehouse, product, movementType (INBOUND, OUTBOUND, TRANSFER, ADJUSTMENT, RETURN)
- quantity, fromLocation, toLocation, relatedShipment, relatedRoute
- performedBy, reason

**`ReceivingOrder` + `ReceivingOrderLine`** — Recepción
- Referencia, proveedor, líneas con cantidad esperada/recibida/dañada

**`PickingOrder` + `PickingOrderLine`** — Picking
- Vinculada a Route, líneas con ubicación origen, cantidad solicitada/cogida

**`PackingRecord`** — Embalaje
- Vinculado a Shipment, tipo embalaje, peso/volumen final, código de barras

**`CycleCount` + `CycleCountLine`** — Inventario cíclico
- Planificación, conteo por ubicación, discrepancias, ajustes

**`ReturnOrder`** — Devoluciones
- Vinculada a Shipment original, motivo, estado (PENDING → RECEIVED → INSPECTED → RESTOCKED/DISCARDED)

#### Diagrama de relaciones

```
Customer ─── Warehouse ─── WarehouseZone ─── StorageLocation
    │            │                                  │
    │            ├── ReceivingOrder ── Lines         │
    │            ├── PickingOrder ──── Lines ────────┘
    │            ├── CycleCount ────── Lines
    │            ├── ReturnOrder
    │            └── StockMovement
    │
    └── Product ── InventoryItem ── StorageLocation

Shipment ── PackingRecord
         ── StockMovement (relatedShipment)
         ── PickingOrderLine

Route ── PickingOrder (relatedRoute)
      ── StockMovement (relatedRoute)
```

### Esfuerzo: ~4-6 meses (1 desarrollador)

| Componente | Tiempo |
|------------|--------|
| Entidades + migraciones | 2-3 semanas |
| Enums y value objects | 3-4 días |
| Servicios core | 3-4 semanas |
| Controladores admin | 3-4 semanas |
| API para operaciones móviles | 2-3 semanas |
| Templates Twig + dashboards | 3-4 semanas |
| Integración con TMS | 1-2 semanas |
| Mapa visual del almacén | 1-2 semanas |
| Etiquetas | 1 semana |
| Tests | 2-3 semanas |

### Valor

| Beneficio | Impacto |
|-----------|---------|
| Trazabilidad completa almacén-entrega | Reduce reclamaciones 30-50% |
| Inventario en tiempo real | Elimina roturas de stock |
| Picking optimizado | Reduce tiempo preparación 20-40% |
| Control de ubicaciones | Reduce búsqueda de mercancía |
| Integración TMS-SGA nativa | Flujo sin fisuras |
| Devoluciones automatizadas | Cierre de ciclo rápido |
| Diferenciación comercial | Más completo que TMS-only |

---

## Integración TMS ↔ SGA

Este es el **valor diferencial** — la integración nativa entre transporte y almacén.

| Trigger | TMS (existente) | SGA (nuevo) |
|---------|-----------------|-------------|
| Recepción completada | ShipmentEvent(IN_HUB) | ReceivingOrder completada |
| Ruta planificada | Route(PLANNED) + RouteStops | PickingOrder generada |
| Carga completada | Route(ACTIVE), ShipmentEvent(OUT_FOR_DELIVERY) | StockMovement(OUTBOUND) |
| Excepción en ruta | ShipmentEvent(EXCEPTION) | ReturnOrder creada |
| Devolución procesada | ShipmentEvent(RETURNED) | StockMovement(RETURN) |
| Import CSV | CsvImportRun, Shipment | ReceivingOrder esperada |

### Flujo Completo Integrado

```
CSV Import → Shipment(CREATED)
  → ReceivingOrder(EXPECTED) → Recepción → Inventory + ShipmentEvent(IN_HUB)
    → Route(PLANNED) → PickingOrder → Picking → Packing
      → Expedición → StockMovement(OUTBOUND) + ShipmentEvent(OUT_FOR_DELIVERY)
        → Route(ACTIVE) → GPS tracking → Mercure SSE
          → Entrega → Pod → ShipmentEvent(DELIVERED)
          → Excepción → ReturnOrder → Inspección → Stock/Descarte
```

---

## Soluciones Open-Source de Referencia

### PHP/Symfony
- **Sylius** — E-commerce con gestión de inventario (buen modelo de stock)
- **Akeneo PIM** — Catálogo de productos (referencia para Product)

### Otros (referencia de modelo de datos)
- **Odoo Inventory** (Python) — SGA completo open-source, excelente referencia
- **OpenBoxes** (Java) — WMS especializado en supply chain
- **Inventree** (Python/Django) — Gestión de inventario con ubicaciones

### Referencia en España
- **Mecalux Easy WMS** — Muy extendido, propietario
- **Generix WMS** — Común en e-commerce español
- **SAP EWM** — Grandes operadores

### Funcionalidades que más valoran en España
1. Trazabilidad de lote (obligatoria en alimentación/farmacia)
2. Gestión de devoluciones (tasa ~10-15% en e-commerce)
3. Picking por olas (para picos: Black Friday, rebajas)
4. Integración con transportistas (SEUR, MRW, Correos Express, GLS)
5. Albarán electrónico (requisito legal)
6. Multi-idioma
7. Informes para la AEAT (valoración de inventario para contabilidad)

---

## Plan de Implementación Incremental

### Fase 0: Registro Simple — 3 semanas
- `WarehouseEntry` (entrada/salida)
- API de registro por escaneo
- Integración con `ShipmentEvent`
- **Criterio:** Operador registra entradas/salidas, cliente ve evento en timeline

### Fase 1: Almacén con Ubicaciones — 6 semanas (acumulado ~9 semanas)
- `Warehouse`, `WarehouseZone`, `StorageLocation`
- `Product` básica (SKU, nombre, EAN)
- `InventoryItem` (stock por ubicación)
- `StockMovement` (trazabilidad)
- **Criterio:** Operador configura almacén y sabe dónde está cada referencia

### Fase 2: Recepción y Expedición — 6 semanas (acumulado ~15 semanas)
- `ReceivingOrder` + `ReceivingOrderLine`
- Recepción contra albarán con discrepancias
- Expedición vinculada a Routes con verificación
- Escaneo de códigos de barras
- **Criterio:** Recepción/expedición se verifican contra lo esperado

### Fase 3: Picking y Packing — 6 semanas (acumulado ~21 semanas)
- `PickingOrder` + `PickingOrderLine` + `PackingRecord`
- Generación automática de picking desde Routes
- Pantalla móvil de picking con secuencia optimizada
- **Criterio:** Pedido se prepara con flujo picking/packing sin pasos manuales

### Fase 4: Devoluciones e Inventario Cíclico — 4 semanas (acumulado ~25 semanas)
- `ReturnOrder`, `CycleCount` + `CycleCountLine`
- Flujo de devolución completo
- Inventario cíclico por zonas
- **Criterio:** Devoluciones se procesan automáticamente, inventario verificado periódicamente

### Fase 5: Optimización Avanzada — 6+ semanas
- Picking por olas
- Multi-almacén con transferencias
- Reservas de stock automáticas
- Mapa visual 2D del almacén
- App móvil PWA
- Informes avanzados

---

## Riesgos

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| Scope creep | Alto | Fases estrictas con entregables |
| Complejidad inventario tiempo real | Alto | Empezar sin reservas ni multi-almacén |
| Adopción por operarios | Alto | UX móvil simple, formación, piloto |
| Performance muchos movimientos | Medio | Índices DB, cache Redis |
| Integración hardware | Medio | Empezar con lectores USB |
| Competencia con SGA existentes | Alto | Diferenciarse por integración nativa con TMS |
| Tiempo de desarrollo vs expectativas | Alto | MVP (Fase 0) primero |

---

## Recomendación Final

> **Implementar Fase 0 en 3 semanas, ponerlo en producción con cliente piloto, y usar su feedback para decidir el ritmo de avance.**

Justificación:
1. El TMS ya funciona — el valor inmediato es conectar almacén con transporte
2. Fase 0 se integra naturalmente con `ShipmentEvent(IN_HUB)` y `CustomerLocation`
3. El SGA completo es un producto en sí mismo — validar antes de construir
4. Cada fase entrega valor independiente
5. La arquitectura (PublicIdTrait, CustomerScoped, eventos) permite crecer sin romper
