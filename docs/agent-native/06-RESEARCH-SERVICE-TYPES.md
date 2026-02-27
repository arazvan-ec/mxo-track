# Investigación: Tipos de Servicio para Logística Last-Mile

> Fecha: 2026-02-27
> Contexto: Respuesta a Q1 — Investigación de tipos de servicio de operadores españoles/europeos

---

## Resumen Ejecutivo

Investigados SEUR, MRW, GLS, Correos Express, DHL y UPS. Los tipos de servicio se organizan en 3 tiers según urgencia para MVP. **Recomendación clave:** separar "qué hace el conductor" (ServiceType) de "cómo/cuándo" (flags y campos).

---

## Investigación por Operador

### SEUR (DPDgroup)
- Standard (SEUR 24), time-definite (SEUR 8:30, 10, 13:30)
- Same-day (Sameday, Now)
- Sábados, frío (SEUR Frío), vinos/botellas
- Predict Cross-Border (entrega flexible con control del destinatario)

### MRW
- Same-day (Inmediato, Urgente Hoy)
- Next-day time-definite (8:30, 10, 12, 14, 19)
- Economy (2 días), marítimo (islas)
- Internacional, equipaje, valija

### GLS Spain
- BusinessParcel (B2B), Express (next-day)
- FlexDeliveryService (control del destinatario)
- ShopDeliveryService (punto de recogida)
- CashService (contra reembolso)
- ShopReturnService, **ExchangeService** (entrega + recogida simultánea)
- Firma de documentos, valija, farmacéutico

### Correos Express
- Paq 24 (standard), ePaq 24 (B2C ecommerce)
- Paq Punto (punto de recogida)
- Paq 14 (antes 14:00), Paq 10 (antes 10:00)
- Islas, internacional, equipaje

### DHL
- Express Domestic (antes 9:00, 12:00)
- Same Day, Same Day palet
- Parcel (entrega tarde hasta 21:00)
- eCommerce Connect, reverse logistics
- GoGreen Plus, ServicePoints/lockers

### UPS
- Standard (3-6 días), Express Saver (1-3 días)
- Express (next-day 10:30/12:00), Express Plus (8:30/9:00)
- Access Point, COD, Returns (RS1, RS3, ERL)

---

## Tipos de Servicio Recomendados

### Tier 1 — MVP (90%+ de operaciones)

| Enum | Nombre ES | Descripción |
|------|-----------|-------------|
| `DELIVERY` | Entrega | Entrega estándar de bultos a dirección. B2C/B2B |
| `PICKUP` | Recogida | Recogida de bultos del remitente. Primera pata de un nuevo envío |
| `RETURN` | Devolución | Logística inversa: recogida del cliente final para devolver al almacén |
| `DELIVERY_AND_PICKUP` | Entrega y Recogida | Entrega + recogida en la misma parada. Una visita, dos acciones |
| `EXCHANGE` | Cambio/Intercambio | Entregar reemplazo y recoger original. Ambas acciones obligatorias y vinculadas |

### Tier 2 — Post-MVP (alto valor)

| Enum | Nombre ES | Descripción |
|------|-----------|-------------|
| `CASH_ON_DELIVERY` | Contra Reembolso | Cobrar antes de entregar. Relevante en España (sur, rural) |
| `DOCUMENT_DELIVERY` | Entrega Documentos | Documentos con firma y posible retorno de copias firmadas |
| `PICKUP_POINT_DELIVERY` | Punto de Recogida | Entrega en tienda, locker, punto de conveniencia |
| `SCHEDULED_DELIVERY` | Entrega Programada | Ventana horaria elegida por el destinatario |
| `RELAY` | Relevo/Trasbordo | Transferencia entre vehículos en punto intermedio |

### Tier 3 — Especializado (por vertical)

| Enum | Nombre ES | Descripción |
|------|-----------|-------------|
| `WHITE_GLOVE` | Guante Blanco | Entrega premium: colocación, desembalaje, instalación |
| `TEMPERATURE_CONTROLLED` | Temperatura Controlada | Cadena de frío: refrigerado, congelado. Pharma, alimentación |
| `HAZMAT` | Mercancías Peligrosas | Clasificación ADR. Conductor certificado, vehículo marcado |
| `FRAGILE` | Frágil | Manipulación especial. Sin apilar |
| `MILK_RUN` | Ruta Recogida Múltiple | Ruta circular recogiendo de múltiples proveedores |
| `VALIJA` | Valija Interna | Valija entre oficinas de la misma empresa. Recurrente |
| `INSTALLATION` | Instalación | Entrega + instalación/puesta en marcha profesional |

---

## Recomendación de Diseño: ServiceType vs Flags

**Patrón clave descubierto:** No todo debe ser un ServiceType. Hay que separar:

### ServiceType (QUÉ hace el conductor en la parada)
```
DELIVERY, PICKUP, RETURN, DELIVERY_AND_PICKUP, EXCHANGE
```
→ Enum pequeño y estable. 5 valores para MVP.

### Campos/Flags separados (CÓMO se hace)

| Campo | Tipo | Valores |
|-------|------|---------|
| `priorityLevel` | Enum | STANDARD, SAME_DAY, NEXT_DAY_AM, NEXT_DAY_1030, NEXT_DAY_1400, SCHEDULED |
| `cashOnDeliveryAmount` | decimal, nullable | Si != null → es contra reembolso |
| `deliveryLocationType` | Enum | DOOR, PICKUP_POINT, LOCKER, DOCK |
| `handlingFlags` | JSON/Array | FRAGILE, TEMP_CONTROLLED, HAZMAT, WHITE_GLOVE, SIGNATURE_REQUIRED, ID_VERIFICATION |
| `documentReturn` | bool | Si el conductor debe retornar documentos firmados |
| `deliveryWindowStart/End` | Time | Ya existe en RouteStop |

**Ventaja:** Evita explosión combinatoria de enums. Un `DELIVERY` puede ser `SAME_DAY` + `CASH_ON_DELIVERY` + `FRAGILE` sin necesitar un tipo especial para cada combinación.

---

## B2B vs B2C

| Aspecto | B2B | B2C |
|---------|-----|-----|
| Tipos comunes | DELIVERY, PICKUP, DELIVERY_AND_PICKUP, MILK_RUN, VALIJA | DELIVERY, RETURN, EXCHANGE |
| Ventanas | Horario comercial, cita en muelle | Tardes, fines de semana, flexible |
| POD | Sello, firma empresa, inspección | Foto, firma, PIN |
| COD | Raro (facturación) | Común en España |
| Devoluciones | Planificadas, bulk | Ad-hoc, individuales |
| Handling especial | Palet, carretilla, muelle | Puerta, habitación, locker |

---

## Enum Completo (referencia futura)

```php
enum ServiceType: string
{
    // Tier 1 - MVP
    case DELIVERY = 'DELIVERY';
    case PICKUP = 'PICKUP';
    case RETURN = 'RETURN';
    case DELIVERY_AND_PICKUP = 'DELIVERY_AND_PICKUP';
    case EXCHANGE = 'EXCHANGE';

    // Tier 2 - Post-MVP
    case CASH_ON_DELIVERY = 'CASH_ON_DELIVERY';
    case DOCUMENT_DELIVERY = 'DOCUMENT_DELIVERY';
    case PICKUP_POINT_DELIVERY = 'PICKUP_POINT_DELIVERY';
    case SCHEDULED_DELIVERY = 'SCHEDULED_DELIVERY';
    case RELAY = 'RELAY';

    // Tier 3 - Especializado
    case WHITE_GLOVE = 'WHITE_GLOVE';
    case TEMPERATURE_CONTROLLED = 'TEMPERATURE_CONTROLLED';
    case HAZMAT = 'HAZMAT';
    case FRAGILE = 'FRAGILE';
    case MILK_RUN = 'MILK_RUN';
    case VALIJA = 'VALIJA';
    case INSTALLATION = 'INSTALLATION';
}
```

---

## Fuentes

- [SEUR: Servicios disponibles](https://www.seur.com/es/empresas/servicios-disponibles/transporte-nacional/)
- [MRW: Servicios transporte urgente](https://mrw.es/servicios_transporte_urgente/)
- [GLS Spain: Servicios para empresas](https://gls-group.com/ES/es/enviar-paquetes/envios-para-empresas/servicios/)
- [GLS Spain: CashService (COD)](https://gls-group.com/ES/es/enviar-paquetes/envios-para-empresas/servicios/cash-service/)
- [Correos Express](https://www.correosexpress.es/)
- [DHL Express España](https://www.dhl.com/es-es/home/express.html)
- [UPS España: Servicios de envío](https://www.ups.com/es/es/support/shipping-support/shipping-services)
- [AfterShip Shipping API: Service Types](https://www.aftership.com/docs/shipping/enum/service-options-lists)
