# Diseño de ServiceType: Enum+Flags vs Todo-como-Enum

> Fecha: 2026-02-27
> Contexto: El cliente necesita entender la diferencia para elegir

---

## El Problema

Tienes servicios como "Entrega urgente de paquete frágil con contra reembolso". ¿Cómo lo modelas?

---

## Opción A: Todo como Enum (un tipo por cada combinación)

```php
enum ServiceType: string
{
    case DELIVERY = 'DELIVERY';
    case DELIVERY_SAMEDAY = 'DELIVERY_SAMEDAY';
    case DELIVERY_SAMEDAY_FRAGILE = 'DELIVERY_SAMEDAY_FRAGILE';
    case DELIVERY_SAMEDAY_COD = 'DELIVERY_SAMEDAY_COD';
    case DELIVERY_SAMEDAY_FRAGILE_COD = 'DELIVERY_SAMEDAY_FRAGILE_COD';
    case DELIVERY_NEXTDAY_AM = 'DELIVERY_NEXTDAY_AM';
    case DELIVERY_NEXTDAY_AM_FRAGILE = 'DELIVERY_NEXTDAY_AM_FRAGILE';
    case DELIVERY_NEXTDAY_AM_COD = 'DELIVERY_NEXTDAY_AM_COD';
    case DELIVERY_NEXTDAY_AM_FRAGILE_COD = 'DELIVERY_NEXTDAY_AM_FRAGILE_COD';
    case PICKUP = 'PICKUP';
    case PICKUP_SAMEDAY = 'PICKUP_SAMEDAY';
    case RETURN = 'RETURN';
    case RETURN_COD = 'RETURN_COD';
    // ... y sigue creciendo
}
```

### Ejemplo real con datos

| Tipo base | x Urgencia (5) | x Handling (4) | x COD (2) | x Ubicación (3) | = Combinaciones |
|-----------|:-:|:-:|:-:|:-:|:-:|
| DELIVERY | 5 | 4 | 2 | 3 | 120 |
| PICKUP | 5 | 4 | 2 | 3 | 120 |
| RETURN | 5 | 4 | 2 | 3 | 120 |
| DELIVERY_AND_PICKUP | 5 | 4 | 2 | 3 | 120 |
| EXCHANGE | 5 | 4 | 2 | 3 | 120 |
| **Total** | | | | | **600 enums** |

### Ventajas
- Simple de entender: un valor = un servicio exacto
- Fácil de buscar en DB: `WHERE service_type = 'DELIVERY_SAMEDAY_COD'`

### Desventajas
- **Explosión combinatoria**: 600+ valores posibles
- **Cada nuevo atributo multiplica**: si añades "temperatura controlada", se duplican todos
- **Queries complejas**: "dame todos los COD" → `WHERE service_type LIKE '%COD%'` (horrible)
- **Código rígido**: cada nuevo servicio requiere nuevo enum + migración DB
- **CSV import**: el cliente tiene que saber el código exacto: `DELIVERY_SAMEDAY_FRAGILE_COD`

---

## Opción B: Enum Pequeño + Flags (composable)

```php
// Lo que HACE el conductor (5 valores, estable)
enum ServiceType: string
{
    case DELIVERY = 'DELIVERY';
    case PICKUP = 'PICKUP';
    case RETURN = 'RETURN';
    case DELIVERY_AND_PICKUP = 'DELIVERY_AND_PICKUP';
    case EXCHANGE = 'EXCHANGE';
}
```

Y luego campos separados en la entidad Shipment:

```php
// CUÁNDO (urgencia)
#[ORM\Column(length: 20, enumType: PriorityLevel::class)]
private PriorityLevel $priority = PriorityLevel::STANDARD;

// Contra reembolso (si != null, es COD)
#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
private ?string $cashOnDeliveryAmount = null;

// DÓNDE se entrega
#[ORM\Column(length: 20, enumType: DeliveryLocation::class)]
private DeliveryLocation $deliveryLocation = DeliveryLocation::DOOR;

// Flags de manipulación (múltiples simultáneos)
#[ORM\Column(type: 'json')]
private array $handlingFlags = [];

// ¿Requiere firma?
#[ORM\Column]
private bool $signatureRequired = false;

// ¿Requiere verificación de identidad?
#[ORM\Column]
private bool $idVerificationRequired = false;
```

### Enums auxiliares

```php
enum PriorityLevel: string
{
    case STANDARD = 'STANDARD';       // 24-48h
    case SAME_DAY = 'SAME_DAY';       // Hoy
    case NEXT_DAY = 'NEXT_DAY';       // Mañana
    case NEXT_DAY_AM = 'NEXT_DAY_AM'; // Mañana antes 12:00
    case SCHEDULED = 'SCHEDULED';     // Fecha/hora elegida
}

enum DeliveryLocation: string
{
    case DOOR = 'DOOR';               // Puerta del destinatario
    case PICKUP_POINT = 'PICKUP_POINT'; // Punto de recogida/tienda
    case LOCKER = 'LOCKER';           // Taquilla automática
    case DOCK = 'DOCK';               // Muelle de carga (B2B)
}

enum HandlingFlag: string
{
    case FRAGILE = 'FRAGILE';
    case TEMPERATURE_CONTROLLED = 'TEMPERATURE_CONTROLLED';
    case HAZMAT = 'HAZMAT';
    case DO_NOT_STACK = 'DO_NOT_STACK';
    case WHITE_GLOVE = 'WHITE_GLOVE';
}
```

---

## Comparación Directa: El Mismo Servicio

**Servicio:** "Entrega urgente same-day de paquete frágil con contra reembolso de 150€ en punto de recogida"

### Opción A (Todo-Enum)
```php
$shipment->setServiceType(ServiceType::DELIVERY_SAMEDAY_FRAGILE_COD_PICKUP_POINT);
// Si este enum no existe, hay que crear migración DB
```

### Opción B (Enum+Flags)
```php
$shipment->setServiceType(ServiceType::DELIVERY);
$shipment->setPriority(PriorityLevel::SAME_DAY);
$shipment->setHandlingFlags([HandlingFlag::FRAGILE->value]);
$shipment->setCashOnDeliveryAmount('150.00');
$shipment->setDeliveryLocation(DeliveryLocation::PICKUP_POINT);
// Funciona sin cambios en DB
```

---

## Queries: ¿Cómo busco?

### "Dame todos los envíos contra reembolso de hoy"

**Opción A:**
```sql
WHERE service_type IN (
    'DELIVERY_COD', 'DELIVERY_SAMEDAY_COD',
    'DELIVERY_NEXTDAY_AM_COD', 'DELIVERY_SAMEDAY_FRAGILE_COD',
    'DELIVERY_NEXTDAY_AM_FRAGILE_COD', ...
)
-- Hay que listar TODOS los enums que contienen COD
-- Si añades un nuevo enum COD y olvidas actualizar esta query, pierdes datos
```

**Opción B:**
```sql
WHERE cash_on_delivery_amount IS NOT NULL
-- Una condición. Siempre funciona. Aunque añadas nuevos tipos.
```

### "Dame todos los envíos frágiles que son same-day"

**Opción A:**
```sql
WHERE service_type IN ('DELIVERY_SAMEDAY_FRAGILE', 'DELIVERY_SAMEDAY_FRAGILE_COD', ...)
```

**Opción B:**
```sql
WHERE priority = 'SAME_DAY'
  AND handling_flags @> '["FRAGILE"]'  -- PostgreSQL JSON contains
```

---

## CSV Import: ¿Cómo lo ve el cliente?

### Opción A: El cliente tiene que saber el código exacto
```csv
reference,service_type,recipient,...
PED-001,DELIVERY_SAMEDAY_FRAGILE_COD,Juan García,...
PED-002,DELIVERY_NEXTDAY_AM,María López,...
```
Problema: ¿Qué pasa si escribe `DELIVERY_SAME_DAY` en vez de `DELIVERY_SAMEDAY`? Error.

### Opción B: El cliente rellena columnas intuitivas
```csv
reference,service_type,priority,fragile,cod_amount,recipient,...
PED-001,DELIVERY,SAME_DAY,true,150.00,Juan García,...
PED-002,DELIVERY,NEXT_DAY_AM,false,,María López,...
```
Más intuitivo, cada columna es independiente, se puede omitir.

---

## ¿Y para los Agentes (LLM)?

### Opción A
El LLM tiene que conocer 600 enums y elegir el correcto. Si elige uno que no existe → error.

### Opción B
El LLM compone:
```json
{
  "service_type": "DELIVERY",
  "priority": "SAME_DAY",
  "handling_flags": ["FRAGILE"],
  "cash_on_delivery_amount": 150.00
}
```
Más natural, menos errores, más composable — **alineado con principios agent-native**.

---

## Cuándo Añades un Nuevo Atributo

**Ejemplo:** El cliente pide "entrega con instalación"

### Opción A
1. Crear nuevos enums: `DELIVERY_INSTALLATION`, `DELIVERY_SAMEDAY_INSTALLATION`, `DELIVERY_SAMEDAY_FRAGILE_INSTALLATION`, etc.
2. Migración DB para añadir valores al enum
3. Actualizar TODAS las queries que filtran por tipo
4. Actualizar CSV importer
5. **N enums nuevos = N x tipos existentes**

### Opción B
1. Añadir un campo: `$requiresInstallation = false`
2. Una migración: `ALTER TABLE shipment ADD requires_installation BOOLEAN DEFAULT FALSE`
3. Listo. Las queries existentes siguen funcionando.
4. **1 campo nuevo, 0 cambios en código existente**

---

## Tabla Resumen

| Criterio | A (Todo-Enum) | B (Enum+Flags) |
|----------|:---:|:---:|
| Simplicidad inicial | 8/10 | 6/10 |
| Escalabilidad | 2/10 | 9/10 |
| Queries por atributo | 2/10 | 9/10 |
| Añadir nuevo atributo | 2/10 | 9/10 |
| CSV import amigable | 4/10 | 8/10 |
| Compatibilidad LLM/agent | 3/10 | 9/10 |
| Código mantenible | 3/10 | 8/10 |
| Claridad para el equipo | 7/10 | 7/10 |
| **TOTAL** | **31/80** | **65/80** |

---

## Mi Recomendación

**Opción B (Enum+Flags).** Razones:

1. **Es agent-native por naturaleza** — composable, granular
2. **Escala sin dolor** — añadir atributo = añadir campo, no multiplicar enums
3. **Queries limpias** — filtrar por cualquier dimensión independientemente
4. **CSV amigable** — columnas separadas, cada una opcional
5. **Menos errores** — imposible crear combinaciones inválidas con enum+flags

El ServiceType enum se mantiene pequeño (5 valores) y estable. Todo lo demás son campos que se combinan libremente.
