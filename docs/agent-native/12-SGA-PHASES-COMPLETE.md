# SGA: Plan Completo de Todas las Fases

> Fecha: 2026-02-27
> Cada fase es independiente y entrega valor por sí sola

---

## Vista General

```
Fase 0: Registro de Entrada/Salida
    ↓
Fase 1: Almacén con Ubicaciones
    ↓
Fase 2: Recepción y Expedición
    ↓
Fase 3: Picking y Packing
    ↓
Fase 4: Devoluciones e Inventario Cíclico
    ↓
Fase 5: Optimización Avanzada (multi-almacén, olas, PWA)
```

---

## FASE 0: Registro de Entrada/Salida

### Objetivo
Saber qué paquetes entraron y salieron del almacén, cuándo y quién lo registró.

### Lo que consigue el usuario
- Operario escanea código de barras al recibir paquete → queda registrado
- Operario escanea al cargar en furgoneta → queda registrado
- El cliente B2B ve en el timeline: "En almacén desde 08:30"
- El operador ve: cuántos paquetes hay ahora mismo en el almacén

### Entidades nuevas

**`WarehouseMovement`**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | ID público |
| shipment | FK → Shipment | Envío asociado |
| location | FK → CustomerLocation | Ubicación/almacén |
| type | Enum: INBOUND, OUTBOUND | Entrada o salida |
| operator | FK → User | Quién lo registró |
| scannedBarcode | string, nullable | Código escaneado |
| notes | text, nullable | Observaciones |
| createdAt | DateTimeImmutable | Cuándo |

### Pantallas
- **Admin:** Lista de movimientos del día, filtrar por almacén/fecha/tipo
- **API:** Endpoint para app de escaneo: `POST /api/warehouse/scan`

### Integración con TMS
- Al registrar INBOUND → crea ShipmentEvent(IN_HUB) automáticamente
- Al registrar OUTBOUND → vincula con Route si existe

### Tareas

| # | Tarea | Detalle |
|---|-------|---------|
| 0.1 | Crear enum `WarehouseMovementType` | INBOUND, OUTBOUND |
| 0.2 | Crear entidad `WarehouseMovement` | Campos arriba descritos |
| 0.3 | Crear migración Doctrine | ALTER TABLE / CREATE TABLE |
| 0.4 | Crear `WarehouseMovementService` | Registrar movimiento + crear ShipmentEvent |
| 0.5 | Crear endpoint API para escaneo | POST /api/warehouse/scan |
| 0.6 | Crear vista admin de movimientos | Lista con filtros |
| 0.7 | Tests unitarios | Service + API |
| 0.8 | Fixtures con datos de ejemplo | 20-30 movimientos |

---

## FASE 1: Almacén con Ubicaciones

### Objetivo
Modelar la estructura física del almacén: zonas, pasillos, estanterías, niveles. Saber DÓNDE está cada cosa.

### Lo que consigue el usuario
- Configurar almacén: "Zona A tiene 5 pasillos, cada pasillo 10 estanterías, cada estantería 4 niveles"
- Buscar: "¿Dónde está el paquete PED-0045?" → "Pasillo A, Estantería 3, Nivel 2"
- Dashboard: mapa visual del almacén con ocupación por zona
- Alertas: "Zona de expedición tiene 45 paquetes esperando carga"

### Entidades nuevas

**`Warehouse`** — El almacén físico
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| customer | FK → Customer | Cliente dueño (multi-tenant) |
| name | string | "Almacén Madrid Sur" |
| code | string | "MAD-01" |
| address | string | Dirección |
| latitude / longitude | float | Coordenadas |
| isActive | bool | |

**`WarehouseZone`** — Zona dentro del almacén
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| warehouse | FK → Warehouse | |
| name | string | "Zona Recepción", "Pasillo A" |
| code | string | "REC", "ALM-A" |
| zoneType | Enum | RECEIVING, STORAGE, PICKING, PACKING, SHIPPING, RETURNS |

**`StorageLocation`** — Posición concreta
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| zone | FK → WarehouseZone | |
| code | string | "A-01-03-02" (pasillo-estantería-nivel-posición) |
| aisle | string | "A" |
| rack | int | 1 |
| level | int | 3 |
| position | int | 2 |
| locationType | Enum | PALLET, SHELF, FLOOR, COLD, HAZMAT |
| maxWeightKg | decimal | Peso máximo |
| maxVolumeM3 | decimal | Volumen máximo |
| isOccupied | bool | |

### Actualizar `WarehouseMovement`
```diff
+ storageLocation FK → StorageLocation (nullable)
```

### Pantallas
- **Config almacén:** Crear/editar zonas y ubicaciones
- **Mapa visual:** Grid 2D del almacén con colores por ocupación
- **Búsqueda:** "¿Dónde está X?" con resultado de ubicación

### Tareas

| # | Tarea |
|---|-------|
| 1.1 | Crear enums: `ZoneType`, `LocationType` |
| 1.2 | Crear entidad `Warehouse` |
| 1.3 | Crear entidad `WarehouseZone` |
| 1.4 | Crear entidad `StorageLocation` |
| 1.5 | Crear migraciones |
| 1.6 | Crear `WarehouseConfigService` (CRUD de estructura) |
| 1.7 | Actualizar `WarehouseMovement` con ubicación |
| 1.8 | Crear API: configurar almacén |
| 1.9 | Crear vistas admin: config almacén |
| 1.10 | Crear vista: mapa visual 2D de ocupación |
| 1.11 | Crear búsqueda por paquete → ubicación |
| 1.12 | Tests y fixtures |

---

## FASE 2: Recepción y Expedición

### Objetivo
Proceso formalizado de recibir mercancía (verificar contra albarán del proveedor) y expedir (verificar que todo se carga correctamente).

### Lo que consigue el usuario

**Recepción:**
- Llega un camión con 50 paquetes del cliente Raúl
- Operario crea "Orden de Recepción" con lo esperado (del CSV o del envío)
- Escanea cada bulto → sistema compara con lo esperado
- Si falta 1 → "Discrepancia: 49/50 recibidos, falta PED-0023"
- Si hay daño → marcar bulto como dañado con foto
- Al completar → todos los paquetes pasan a estado IN_HUB

**Expedición:**
- Ruta R-045 planificada con 23 paradas
- Sistema genera "Orden de Expedición": lista de paquetes a cargar
- Operario escanea cada paquete al cargarlo en la furgoneta
- Si escanea paquete que no va en esa ruta → alerta
- Si falta paquete → "Quedan 3 paquetes sin cargar"
- Al completar → ShipmentEvent(OUT_FOR_DELIVERY) para todos

### Entidades nuevas

**`ReceivingOrder`** — Orden de recepción
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| warehouse | FK → Warehouse | |
| customer | FK → Customer | |
| reference | string | "REC-2026-0001" |
| expectedDate | date | Fecha esperada |
| status | Enum | EXPECTED, IN_PROGRESS, COMPLETED, COMPLETED_WITH_DISCREPANCIES |
| completedAt | DateTimeImmutable, nullable | |
| completedBy | FK → User, nullable | |

**`ReceivingOrderLine`** — Línea de recepción
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| receivingOrder | FK → ReceivingOrder | |
| shipment | FK → Shipment | |
| expectedQuantity | int | Bultos esperados |
| receivedQuantity | int | Bultos recibidos |
| damagedQuantity | int | Dañados |
| assignedLocation | FK → StorageLocation, nullable | Ubicación asignada |

**`ShippingOrder`** — Orden de expedición
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| warehouse | FK → Warehouse | |
| route | FK → Route | |
| status | Enum | PENDING, PICKING, LOADING, COMPLETED |
| completedAt | DateTimeImmutable, nullable | |

**`ShippingOrderLine`** — Línea de expedición
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| shippingOrder | FK → ShippingOrder | |
| shipment | FK → Shipment | |
| sourceLocation | FK → StorageLocation | De dónde se coge |
| scanned | bool | ¿Ya escaneado/cargado? |
| scannedAt | DateTimeImmutable, nullable | |

### Integración con TMS
- Crear ruta → genera ShippingOrder automáticamente
- Completar recepción → ShipmentEvent(IN_HUB) + mover a ubicación
- Completar expedición → ShipmentEvent(OUT_FOR_DELIVERY) + WarehouseMovement(OUTBOUND)

### Tareas

| # | Tarea |
|---|-------|
| 2.1 | Crear enums: `ReceivingStatus`, `ShippingStatus` |
| 2.2 | Crear entidades `ReceivingOrder` + `ReceivingOrderLine` |
| 2.3 | Crear entidades `ShippingOrder` + `ShippingOrderLine` |
| 2.4 | Crear migraciones |
| 2.5 | Crear `ReceivingService` (crear orden, registrar líneas, completar) |
| 2.6 | Crear `ShippingService` (generar desde ruta, escanear, completar) |
| 2.7 | Auto-generar ShippingOrder al planificar ruta |
| 2.8 | API para escaneo en recepción |
| 2.9 | API para escaneo en expedición |
| 2.10 | Vistas admin: órdenes de recepción |
| 2.11 | Vistas admin: órdenes de expedición |
| 2.12 | Alertas de discrepancias |
| 2.13 | Tests y fixtures |

---

## FASE 3: Picking y Packing

### Objetivo
Preparación de pedidos optimizada: desde que se planifica la ruta hasta que los paquetes están embalados y listos para cargar.

### Lo que consigue el usuario

**Picking:**
- Se planifica Ruta R-050 con 30 paradas
- Sistema genera automáticamente la "Orden de Picking": lista de paquetes a recoger, ordenada por ubicación en el almacén (minimiza recorrido del operario)
- Operario con PDA/móvil: "Ir a A-03-02-01, coger PED-0156 (5.2kg)"
- Escanea → confirma → siguiente ubicación
- Si no encuentra → marca "no encontrado" → alerta

**Packing:**
- Operario recibe paquetes del picking
- Verifica contenido vs pedido
- Pesa (verificar que peso real ≈ peso declarado)
- Embala según tipo (frágil → burbuja, normal → cartón)
- Genera etiqueta con código de barras
- Registro de packing: peso final, tipo embalaje, quién lo hizo

### Entidades nuevas

**`PickingOrder`** — Orden de picking
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| warehouse | FK → Warehouse | |
| route | FK → Route, nullable | Ruta asociada |
| status | Enum | PENDING, IN_PROGRESS, COMPLETED, PARTIALLY_COMPLETED |
| assignedTo | FK → User, nullable | Operario asignado |
| startedAt, completedAt | DateTimeImmutable | |

**`PickingOrderLine`** — Línea de picking
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| pickingOrder | FK → PickingOrder | |
| shipment | FK → Shipment | |
| sourceLocation | FK → StorageLocation | Dónde ir a buscar |
| sequence | int | Orden optimizado de recorrido |
| quantityRequested | int | Cuántos coger |
| quantityPicked | int | Cuántos cogidos realmente |
| status | Enum | PENDING, PICKED, NOT_FOUND, SKIPPED |
| pickedAt | DateTimeImmutable, nullable | |

**`PackingRecord`** — Registro de embalaje
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| shipment | FK → Shipment | |
| packingType | Enum | STANDARD, FRAGILE, COLD, CUSTOM |
| verifiedWeightKg | decimal | Peso verificado |
| verifiedVolumeM3 | decimal, nullable | Volumen verificado |
| barcode | string | Código de barras generado |
| packedBy | FK → User | |
| packedAt | DateTimeImmutable | |

### Optimización de recorrido de picking
El sistema ordena las líneas de picking por ubicación física:
```
Zona A, Pasillo 1, Estantería 1 → Estantería 5 (ida)
Zona A, Pasillo 2, Estantería 5 → Estantería 1 (vuelta)
... patrón serpentina
```

### Tareas

| # | Tarea |
|---|-------|
| 3.1 | Crear enums: `PickingStatus`, `PickingLineStatus`, `PackingType` |
| 3.2 | Crear entidades `PickingOrder` + `PickingOrderLine` |
| 3.3 | Crear entidad `PackingRecord` |
| 3.4 | Crear migraciones |
| 3.5 | Crear `PickingService` (generar orden, optimizar recorrido) |
| 3.6 | Crear `PackingService` (verificar, pesar, etiquetar) |
| 3.7 | Auto-generar PickingOrder al planificar ruta |
| 3.8 | Algoritmo de optimización de recorrido (serpentina) |
| 3.9 | API para PDA/móvil de picking |
| 3.10 | API para estación de packing |
| 3.11 | Generador de etiquetas (código de barras) |
| 3.12 | Vistas admin: órdenes de picking |
| 3.13 | Vistas admin: estación de packing |
| 3.14 | Tests y fixtures |

---

## FASE 4: Devoluciones e Inventario Cíclico

### Objetivo
Gestionar el flujo inverso (devoluciones) y verificar que el inventario real coincide con el del sistema.

### Lo que consigue el usuario

**Devoluciones:**
- Conductor marca entrega como "Rehusado" o "Dañado"
- Sistema crea automáticamente Orden de Devolución
- Paquete llega al almacén → operario recibe
- Inspección: ¿el producto está bien? ¿dañado? ¿faltan piezas?
- Decisión: reintegrar a stock / descartar / enviar a cliente para gestión
- Cliente B2B notificado de la devolución y su estado

**Inventario Cíclico:**
- Planificar: "Contar zona A esta semana, zona B la siguiente"
- Operario va a cada ubicación, escanea y cuenta
- Sistema compara: "Ubicación A-03-02: sistema dice 5, conteo dice 4 → discrepancia"
- Ajuste: supervisor aprueba corrección de stock
- Informe: "3 discrepancias en 200 ubicaciones (1.5% error)"

### Entidades nuevas

**`ReturnOrder`** — Orden de devolución
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| warehouse | FK → Warehouse | |
| originalShipment | FK → Shipment | Envío original |
| reason | Enum | REFUSED, DAMAGED, WRONG_ITEM, CUSTOMER_REQUEST, ABSENT_3X |
| status | Enum | PENDING, RECEIVED, INSPECTED, RESTOCKED, DISCARDED |
| inspectionNotes | text, nullable | |
| inspectedBy | FK → User, nullable | |
| resolution | Enum, nullable | RESTOCK, DISCARD, RETURN_TO_SENDER |

**`CycleCount`** — Inventario cíclico
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| warehouse | FK → Warehouse | |
| zone | FK → WarehouseZone, nullable | Zona a contar (null = todo) |
| status | Enum | PLANNED, IN_PROGRESS, COMPLETED |
| plannedDate | date | |
| assignedTo | FK → User | |
| completedAt | DateTimeImmutable, nullable | |

**`CycleCountLine`** — Línea de conteo
| Campo | Tipo | Descripción |
|-------|------|-------------|
| publicId | ULID | |
| cycleCount | FK → CycleCount | |
| location | FK → StorageLocation | |
| systemQuantity | int | Lo que dice el sistema |
| countedQuantity | int, nullable | Lo que contó el operario |
| hasDiscrepancy | bool | |
| adjustmentApproved | bool | |
| approvedBy | FK → User, nullable | |

### Tareas

| # | Tarea |
|---|-------|
| 4.1 | Crear enums: `ReturnReason`, `ReturnStatus`, `ReturnResolution`, `CycleCountStatus` |
| 4.2 | Crear entidades `ReturnOrder` |
| 4.3 | Crear entidades `CycleCount` + `CycleCountLine` |
| 4.4 | Crear migraciones |
| 4.5 | Crear `ReturnService` (crear desde excepción, recibir, inspeccionar, resolver) |
| 4.6 | Crear `CycleCountService` (planificar, ejecutar, comparar, ajustar) |
| 4.7 | Auto-crear ReturnOrder desde ShipmentEvent(EXCEPTION) |
| 4.8 | API para inspección de devoluciones |
| 4.9 | API para conteo cíclico con PDA |
| 4.10 | Vistas admin: devoluciones |
| 4.11 | Vistas admin: inventario cíclico |
| 4.12 | Informe de discrepancias |
| 4.13 | Notificaciones al cliente B2B por devoluciones |
| 4.14 | Tests y fixtures |

---

## FASE 5: Optimización Avanzada

### Objetivo
Features avanzadas para operaciones a escala.

### Sub-módulos

**5A: Multi-almacén**
- Múltiples almacenes por cliente
- Transferencias entre almacenes (TransferOrder)
- Stock consolidado: ver inventario total de todos los almacenes
- Decidir desde qué almacén enviar (más cercano al destino)

**5B: Picking por olas (Wave Picking)**
- Agrupar múltiples rutas en una "ola" de picking
- Un operario recoge para 3 rutas a la vez
- Separar en estación de packing por ruta
- Útil para picos (Black Friday, rebajas)

**5C: Reservas de stock**
- Al crear un envío, reservar stock automáticamente
- Evitar que dos rutas pidan el mismo paquete
- Liberar reserva si se cancela el envío

**5D: App móvil PWA**
- Progressive Web App para operarios
- Funciona offline (sync cuando hay conexión)
- Escaneo con cámara del móvil
- Notificaciones push

**5E: Informes avanzados**
- Valoración de inventario (para contabilidad/AEAT)
- Rotación de stock (ABC analysis)
- Productividad por operario
- Tiempos de proceso por fase (recepción → expedición)
- Tasa de discrepancias por zona

### Tareas (resumen — se detallarían al llegar)

| # | Sub-módulo | Tareas estimadas |
|---|-----------|-----------------|
| 5A | Multi-almacén | TransferOrder entity, service, API, vistas |
| 5B | Wave picking | WavePickingOrder entity, agrupador, vistas |
| 5C | Reservas | StockReservation entity, auto-reserva, liberación |
| 5D | PWA | Service worker, offline sync, cámara scan |
| 5E | Informes | Report services, PDF export, dashboard widgets |

---

## Resumen de Entidades por Fase

| Fase | Entidades | Total acumulado |
|------|-----------|:-:|
| 0 | WarehouseMovement | 1 |
| 1 | Warehouse, WarehouseZone, StorageLocation | 4 |
| 2 | ReceivingOrder, ReceivingOrderLine, ShippingOrder, ShippingOrderLine | 8 |
| 3 | PickingOrder, PickingOrderLine, PackingRecord | 11 |
| 4 | ReturnOrder, CycleCount, CycleCountLine | 14 |
| 5 | TransferOrder, WavePickingOrder, StockReservation | 17 |

---

## Flujo Completo (todas las fases integradas)

```
1. CSV Import → Shipment(REGISTERED)
        ↓
2. ReceivingOrder(EXPECTED) → Operario recibe → Escanea → IN_HUB
        ↓
3. Put-away → Asignar StorageLocation → WarehouseMovement(INBOUND)
        ↓
4. Ruta planificada → PickingOrder generada (secuencia optimizada)
        ↓
5. Operario picking → Recorre almacén → Escanea cada paquete
        ↓
6. Packing → Verifica → Pesa → Embala → Etiqueta → PackingRecord
        ↓
7. ShippingOrder → Operario carga en furgoneta → Escanea
        ↓
8. WarehouseMovement(OUTBOUND) → ShipmentEvent(OUT_FOR_DELIVERY)
        ↓
9. Route(ACTIVE) → GPS tracking → Mercure SSE → Dashboard en vivo
        ↓
10. Entrega OK → Pod → ShipmentEvent(DELIVERED)
    Excepción → ReturnOrder → Inspección → Restock/Descarte
```
