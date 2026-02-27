# Investigación: Estados de Envíos, Bultos e Incidencias

> Fecha: 2026-02-27
> Contexto: Respuesta a Q2 — Investigación de estados de SEUR, MRW, GLS, DHL, UPS, Correos Express, Amazon, AfterShip

---

## Resumen Ejecutivo

- **Estado actual del codebase:** 7 ShipmentEventType, 4 RouteStopStatus, 5 ExceptionCode
- **Recomendación:** Añadir campo `status` explícito a Shipment + ampliar enums
- **Parcel independiente:** NO para MVP — usar `parcel_count` en Shipment. Parcel entity cuando sea necesario
- **Máquina de estados completa** con transiciones válidas documentadas

---

## Estado Actual de mxo-track

### ShipmentEventType (7 valores)
```
CREATED, PICKED_UP, IN_HUB, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, EXCEPTION
```

### RouteStopStatus (4 valores)
```
PENDING, DELIVERED, EXCEPTION, SKIPPED
```

### ExceptionCode (5 valores)
```
ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER
```

**Nota:** No existe campo `status` en Shipment — se deriva del último ShipmentEvent.

---

## Lo que Usan los Operadores Españoles

### SEUR
- Notificado → En almacén → En tránsito → En gestión → **En reparto** → Entregado
- Especiales: Entrega parcial, Aviso ausente, Domicilio desconocido, En demora
- Disponible para recoger en tienda, Paquete entregado no recibido

### MRW
- Pendiente recoger → Recogido → En tránsito → Pendiente recibir destino → **En reparto** → Entregado
- Especiales: Retenido aduana, Devuelto, Datos incorrectos, Incidencia cobro

### GLS Spain
- Etiqueta creada → Recogido → En tránsito → **En reparto** → Entregado
- Especiales: Ausente, Recanalizada (wrong hub), Depositado en Parcel Shop

### Correos Express
- En preparación → **En reparto** → Entregado
- Especiales: Incidencia, Entrega fallida, Estacionado (5 días esperando datos)

### DHL (API unificada, solo 5 estados)
- Pre-transit → Transit → Delivered → Failure → Unknown

### UPS
- Manifest → Pickup → In Transit → Exception → Delivery info

### AfterShip (estándar normalizado de 1,100+ carriers)
9 estados principales:
```
Pending → InfoReceived → InTransit → OutForDelivery → Delivered
                                       → AttemptFail → AvailableForPickup
                                                     → Exception → Expired
```

---

## Estados de SHIPMENT Recomendados

### MVP (8 estados)

| Estado | Código | Español | Descripción |
|--------|--------|---------|-------------|
| Registrado | `REGISTERED` | Registrado | Confirmado en el sistema, pendiente de recogida |
| Recogido | `PICKED_UP` | Recogido | Recogido del remitente/almacén |
| En Tránsito | `IN_TRANSIT` | En tránsito | Moviéndose entre instalaciones |
| En Reparto | `OUT_FOR_DELIVERY` | En reparto | En vehículo de entrega |
| Entregado | `DELIVERED` | Entregado | Entregado con éxito |
| Intento Fallido | `ATTEMPT_FAILED` | Intento fallido | Intento de entrega sin éxito |
| Devuelto | `RETURNED` | Devuelto | Devuelto al remitente |
| Cancelado | `CANCELLED` | Cancelado | Cancelado antes de entrega |

### Fase 2 (6 estados adicionales)

| Estado | Código | Español |
|--------|--------|---------|
| Borrador | `DRAFT` | Borrador — creado pero no confirmado |
| En Almacén | `IN_HUB` | En almacén — en centro de distribución |
| Entrega Parcial | `PARTIALLY_DELIVERED` | Entrega parcial — algunos bultos entregados |
| Disponible Recogida | `AVAILABLE_FOR_PICKUP` | En punto de recogida |
| En Devolución | `RETURNING` | En proceso de devolución |
| Extraviado | `LOST` | No se puede localizar |

---

## Máquina de Estados del Shipment

```
                    +---> CANCELLED
                    |
REGISTERED -----> PICKED_UP -----> IN_TRANSIT -----> OUT_FOR_DELIVERY
                    |                   |                    |
                    |                   |                    +---> DELIVERED ✓
                    |                   |                    |
                    |                   |                    +---> ATTEMPT_FAILED
                    |                   |                    |         |
                    |                   |                    |         +---> OUT_FOR_DELIVERY (retry)
                    |                   |                    |         |
                    |                   |                    |         +---> AVAILABLE_FOR_PICKUP
                    |                   |                    |         |
                    |                   |                    |         +---> RETURNING
                    |                   |                    |
                    |                   |                    +---> PARTIALLY_DELIVERED
                    |                   |                              |
                    |                   |                              +---> DELIVERED ✓
                    |                   |
                    |                   +---> IN_HUB -----> IN_TRANSIT (loop)
                    |
                    +---> RETURNING -----> RETURNED ✓
```

### Tabla de Transiciones Válidas

| Desde | Permitido a |
|-------|------------|
| `REGISTERED` | `PICKED_UP`, `CANCELLED` |
| `PICKED_UP` | `IN_HUB`, `IN_TRANSIT`, `CANCELLED` |
| `IN_HUB` | `IN_TRANSIT`, `RETURNING`, `CANCELLED` |
| `IN_TRANSIT` | `IN_HUB`, `OUT_FOR_DELIVERY`, `RETURNING` |
| `OUT_FOR_DELIVERY` | `DELIVERED`, `PARTIALLY_DELIVERED`, `ATTEMPT_FAILED` |
| `ATTEMPT_FAILED` | `OUT_FOR_DELIVERY`, `AVAILABLE_FOR_PICKUP`, `RETURNING` |
| `PARTIALLY_DELIVERED` | `OUT_FOR_DELIVERY`, `DELIVERED`, `RETURNING` |
| `AVAILABLE_FOR_PICKUP` | `DELIVERED`, `RETURNING` |
| `RETURNING` | `RETURNED` |
| `DELIVERED` | _(terminal)_ |
| `RETURNED` | _(terminal)_ |
| `CANCELLED` | _(terminal)_ |
| `LOST` | _(terminal, admin puede setear desde cualquier estado)_ |

---

## Tipos de EVENTO Recomendados (audit trail)

Los eventos son registros inmutables de lo que ocurrió. NO son el estado actual — son el historial.

### MVP

| Evento | Código | Descripción |
|--------|--------|-------------|
| Creado | `CREATED` | Envío registrado |
| Recogido | `PICKED_UP` | Recogido del remitente |
| En Tránsito | `IN_TRANSIT` | En tránsito |
| En Reparto | `OUT_FOR_DELIVERY` | En vehículo de entrega |
| Entregado | `DELIVERED` | Entregado con éxito |
| Intento Entrega | `DELIVERY_ATTEMPTED` | Intento fallido con código de razón |
| Excepción | `EXCEPTION` | Problema grave/operacional |
| Devuelto | `RETURNED` | Devuelto al remitente |
| Cancelado | `CANCELLED` | Cancelado |

### Fase 2

| Evento | Código | Descripción |
|--------|--------|-------------|
| Manifestado | `MANIFESTED` | Etiqueta generada, carrier notificado |
| Llegada Hub | `ARRIVED_HUB` | Llegó a centro de distribución |
| Salida Hub | `DEPARTED_HUB` | Salió de centro |
| Cargado Vehículo | `LOADED_VEHICLE` | Cargado en vehículo |
| Entrega Parcial | `PARTIALLY_DELIVERED` | Algunos bultos entregados |
| Disponible Recogida | `AVAILABLE_FOR_PICKUP` | En punto de recogida |
| Inicio Devolución | `RETURN_INITIATED` | Proceso de devolución iniciado |
| POD Capturado | `POD_CAPTURED` | Prueba de entrega registrada |
| Reprogramado | `RESCHEDULED` | Nueva fecha/hora |
| Dirección Corregida | `ADDRESS_CORRECTED` | Dirección actualizada |
| Redirigido | `REDIRECTED` | Redirigido a nueva dirección/punto |
| Reasignado | `REASSIGNED` | Movido a otra ruta/conductor |
| Override Admin | `STATUS_OVERRIDE` | Admin cambió estado manualmente |

---

## Códigos de INCIDENCIA (Exception Codes)

### MVP (8 códigos)

| Código | Español | Descripción | Categoría |
|--------|---------|-------------|-----------|
| `ABSENT` | Ausente | Destinatario no presente | Destinatario |
| `REFUSED` | Rehusado | Destinatario rechaza entrega | Destinatario |
| `WRONG_ADDRESS` | Dirección incorrecta | Dirección no existe o errónea | Dirección |
| `INCOMPLETE_ADDRESS` | Dirección incompleta | Falta piso, portal, etc. | Dirección |
| `ACCESS_RESTRICTED` | Acceso restringido | Comunidad cerrada, portero, etc. | Dirección |
| `DAMAGED` | Dañado | Paquete visiblemente dañado | Paquete |
| `NO_TIME` | Sin tiempo | Conductor sin tiempo (ruta larga) | Operacional |
| `OTHER` | Otro | Requiere notas libres | General |

### Fase 2 (adicionales)

| Código | Español | Categoría |
|--------|---------|-----------|
| `UNKNOWN_RECIPIENT` | Destinatario desconocido | Destinatario |
| `CANNOT_PAY_COD` | No puede pagar reembolso | Destinatario |
| `ADDRESS_NOT_FOUND` | Dirección no encontrada | Dirección |
| `LOST` | Extraviado | Paquete |
| `LABEL_DAMAGED` | Etiqueta dañada | Paquete |
| `WRONG_PACKAGE` | Paquete equivocado | Paquete |
| `VEHICLE_ISSUE` | Problema vehículo | Operacional |
| `REROUTED` | Recanalizado | Operacional |
| `HELD_AT_HUB` | Retenido en almacén | Operacional |
| `WEATHER` | Meteorología adversa | Externo |
| `FORCE_MAJEURE` | Fuerza mayor | Externo |
| `CUSTOMS_HOLD` | Retenido aduana | Externo |
| `HOLIDAY` | Festivo | Externo |

---

## Estado de RouteStop — SIN CAMBIOS

```
PENDING → DELIVERED
   |
   +→ EXCEPTION
   |       |
   |       +→ PENDING (reintento en nueva ruta)
   |
   +→ SKIPPED
```

El RouteStopStatus actual es correcto para la perspectiva operativa del conductor.

---

## Bulto Independiente vs Compartido

### ¿Cada bulto necesita su propio estado?

**Para MVP: NO.** Razones:
- La mayoría de envíos last-mile B2C son single-parcel
- Multi-parcel que viajan juntos → comparten estado
- Solo SEUR muestra "entrega parcial" — es edge case
- Añadir entidad Parcel luego es cambio aditivo, no breaking

**Preparar para Parcel entity:**
- Añadir `parcel_count` (default 1) a Shipment
- Usar JSON payload de ShipmentEvent para detalles por bulto cuando sea necesario
- CSV import acepta columna de parcel_count

**Cuándo migrar a Parcel independiente:**
- Cuando un cliente requiera escaneo de código de barras por bulto
- Cuando entrega parcial sea >5% de entregas
- Cuando bultos del mismo envío viajen en vehículos diferentes

---

## Cambios Propuestos al Codebase Actual

### ShipmentEventType — Añadir 3 para MVP

```diff
  case CREATED = 'CREATED';
  case PICKED_UP = 'PICKED_UP';
  case IN_HUB = 'IN_HUB';
  case IN_TRANSIT = 'IN_TRANSIT';
  case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
  case DELIVERED = 'DELIVERED';
  case EXCEPTION = 'EXCEPTION';
+ case DELIVERY_ATTEMPTED = 'DELIVERY_ATTEMPTED';
+ case RETURNED = 'RETURNED';
+ case CANCELLED = 'CANCELLED';
```

### ExceptionCode — Añadir 3 para MVP

```diff
  case ABSENT = 'ABSENT';
  case WRONG_ADDRESS = 'WRONG_ADDRESS';
  case REFUSED = 'REFUSED';
  case DAMAGED = 'DAMAGED';
  case OTHER = 'OTHER';
+ case INCOMPLETE_ADDRESS = 'INCOMPLETE_ADDRESS';
+ case ACCESS_RESTRICTED = 'ACCESS_RESTRICTED';
+ case NO_TIME = 'NO_TIME';
```

### Shipment — Añadir campo status

```diff
+ #[ORM\Column(length: 30, enumType: ShipmentStatus::class)]
+ private ShipmentStatus $status = ShipmentStatus::REGISTERED;
```

### RouteStopStatus — Sin cambios

Ya correcto para la perspectiva del conductor.

---

## Insights Clave de la Investigación

1. **"En reparto" es EL estado crítico** — cuando la ansiedad del cliente es máxima y el tracking en vivo (Mercure + Traccar) más valioso
2. **El loop "intento fallido → reintento"** es el flujo más complejo operativamente. SEUR/MRW: 2 intentos antes de desviar a punto de recogida o devolver
3. **Los carriers españoles distinguen "en tránsito" (long-haul) de "en reparto" (last-mile)** — son fases operativas muy diferentes
4. **DHL usa solo 5 estados en su API** mientras muestra más granularidad al cliente — refuerza el patrón de estado público simple + trail de eventos detallado
5. **La "entrega parcial" de SEUR confirma que multi-parcel existe** en España pero es edge case
