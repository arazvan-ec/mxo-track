# Plan: Tiempo de Servicio Variable por Envio

## Objetivo

Actualmente, cuando se integre VROOM para la optimizacion de rutas, todas las paradas de entrega usaran un tiempo de servicio fijo de 5 minutos (300 segundos). Sin embargo, distintos tipos de entrega requieren tiempos diferentes:

| Tipo de entrega | Tiempo estimado |
|-----------------|-----------------|
| Drop-off sin contacto | 2 min (120s) |
| Entrega estandar | 5 min (300s) |
| Entrega con firma/POD | 8 min (480s) |
| Articulos voluminosos | 15 min (900s) |

Este plan hace que el tiempo de servicio sea configurable por cada `Shipment`, permitiendo que VROOM calcule rutas mas realistas en funcion del tipo de entrega.

## Estado Actual

- **`VroomRequestMapper`** (planificado en `backend/src/Service/VroomRequestMapper.php`): No existe todavia. Segun la arquitectura definida en CLAUDE.md, este servicio convertira entidades del dominio (Vehicle, Shipment) a formato VROOM. Definira una constante `SERVICE_TIME_SECONDS = 300` que se aplicara a todos los jobs de VROOM de forma uniforme.
- **`RouteOptimizationService`** (`backend/src/Service/RouteOptimizationService.php`): Servicio existente que optimiza el orden de paradas usando un heuristico nearest-neighbor con distancias Haversine. No maneja tiempos de servicio.
- **`Shipment`** (`backend/src/Entity/Shipment.php`): Entidad existente con propiedades de direccion, coordenadas, destinatario y tracking token. No tiene ninguna propiedad relacionada con tiempo de servicio.
- **No existe `RouteOptimizationApiController`**. La gestion de rutas se hace via `RouteAdminController` (`backend/src/Controller/Admin/RouteAdminController.php`).

## Cambios Propuestos

### 1. Entity: Shipment

Agregar propiedad `serviceTimeSeconds` a `App\Entity\Shipment`:

- Tipo: `int`, nullable, default `null` (null = usar el valor por defecto del sistema, 300s)
- Columna Doctrine: `service_time_seconds` (integer, nullable)
- Getter y setter

```php
#[ORM\Column(type: 'integer', nullable: true)]
private ?int $serviceTimeSeconds = null;

public function getServiceTimeSeconds(): ?int
{
    return $this->serviceTimeSeconds;
}

public function setServiceTimeSeconds(?int $serviceTimeSeconds): void
{
    $this->serviceTimeSeconds = $serviceTimeSeconds;
}
```

### 2. Migration

Nueva migracion de Doctrine para agregar la columna:

```sql
ALTER TABLE shipment ADD service_time_seconds INT DEFAULT NULL;
```

Generar con:
```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate -n
```

### 3. VroomRequestMapper

Cuando se implemente `VroomRequestMapper`, aplicar la siguiente logica:

- Renombrar la constante de `SERVICE_TIME_SECONDS` a `DEFAULT_SERVICE_TIME_SECONDS`
- Al construir cada job de VROOM, usar el tiempo de servicio del shipment si esta definido, o el default si es null:

```php
final class VroomRequestMapper
{
    private const DEFAULT_SERVICE_TIME_SECONDS = 300;

    private function buildJob(Shipment $shipment, int $jobId): array
    {
        $serviceTime = $shipment->getServiceTimeSeconds()
            ?? self::DEFAULT_SERVICE_TIME_SECONDS;

        return [
            'id' => $jobId,
            'location' => [$shipment->getLongitude(), $shipment->getLatitude()],
            'service' => $serviceTime,
            'delivery' => [$shipment->getWeightGrams(), $shipment->getVolumeCm3(), 1],
            // ...
        ];
    }
}
```

### 4. DTO / API

Agregar `service_time_seconds` al DTO de creacion/actualizacion de shipments:

- Campo: `serviceTimeSeconds` (int, opcional)
- Validacion:
  - `Range(min: 60, max: 1800)` - entre 1 y 30 minutos
  - `Positive` si se proporciona
- En `fromArray()`: mapear desde `service_time_seconds`

```php
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Range(min: 60, max: 1800, notInRangeMessage: 'Service time must be between 60 and 1800 seconds (1-30 minutes)')]
public readonly ?int $serviceTimeSeconds;
```

Tambien agregar al CSV importer (`ShipmentCsvImporter`) como columna opcional.

### 5. UI (Admin)

Agregar campo de tiempo de servicio al formulario de shipment en el panel admin:

- Tipo: `ChoiceType` (dropdown) con presets predefinidos
- Opciones:
  - `120` - "2 min - Drop-off sin contacto"
  - `300` - "5 min - Entrega estandar" (seleccionado por defecto)
  - `480` - "8 min - Entrega con firma/POD"
  - `900` - "15 min - Articulos voluminosos"
- Permitir tambien un campo de texto libre para valores personalizados (con validacion 60-1800)
- Placeholder: "Por defecto (5 min)"

## Modelo de Datos

Mapeo Doctrine completo para la nueva propiedad:

```php
#[ORM\Entity(repositoryClass: \App\Repository\ShipmentRepository::class)]
class Shipment implements CustomerScopedEntityInterface, SoftDeletableInterface
{
    // ... propiedades existentes ...

    /**
     * Tiempo de servicio en segundos para esta entrega.
     * null = usar el valor por defecto del sistema (300s / 5 min).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $serviceTimeSeconds = null;

    public function getServiceTimeSeconds(): ?int
    {
        return $this->serviceTimeSeconds;
    }

    public function setServiceTimeSeconds(?int $serviceTimeSeconds): void
    {
        $this->serviceTimeSeconds = $serviceTimeSeconds;
    }
}
```

## Verificacion

1. **Crear shipment con serviceTimeSeconds personalizado**: Crear un shipment con `serviceTimeSeconds = 480` y verificar que se persiste correctamente en la base de datos.
2. **Construir ruta con VROOM**: Verificar que el job de VROOM correspondiente usa `"service": 480` en lugar de 300.
3. **Shipment con serviceTimeSeconds = null**: Construir ruta y verificar que VROOM recibe el default de 300 segundos.
4. **Compatibilidad retroactiva**: Verificar que shipments existentes (que tendran `service_time_seconds = NULL`) siguen funcionando correctamente con el valor por defecto.
5. **Validacion de rango**: Intentar crear shipment con `serviceTimeSeconds = 30` (menor que 60) y verificar que falla la validacion.
6. **Importacion CSV**: Verificar que la columna `service_time_seconds` es opcional en el CSV y se importa correctamente cuando esta presente.

## Dependencias

- Este plan se puede implementar de forma independiente (pasos 1, 2, 4, 5).
- El paso 3 (VroomRequestMapper) depende de que se implemente la integracion con VROOM (ver `RouteBuilder`, `VroomApiClient` en la arquitectura planificada).
- Se recomienda implementar los pasos 1 y 2 primero para que la columna este disponible cuando se construya el mapper.
