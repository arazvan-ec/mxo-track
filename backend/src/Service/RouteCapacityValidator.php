<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;

final class RouteCapacityValidator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Validates that all shipments in the route fit within the vehicle's capacity.
     * Must be called BEFORE starting the route.
     *
     * @return array{
     *     valid: bool,
     *     errors: list<string>,
     *     totalWeightKg: float,
     *     totalVolumeM3: float,
     *     totalParcels: int,
     *     weightUtilization: float|null,
     *     volumeUtilization: float|null,
     *     parcelUtilization: float|null,
     * }
     */
    public function validate(Route $route): array
    {
        $vehicle = $route->getVehicle();
        $stops = $this->getDeliveryStops($route);

        $totalWeight = 0.0;
        $totalVolume = 0.0;
        $totalParcels = 0;
        $errors = [];

        foreach ($stops as $stop) {
            $shipment = $stop->getShipment();
            if ($shipment === null) {
                continue;
            }

            $weight = $shipment->getTotalWeightKg();
            $volume = $shipment->getTotalVolumeM3();
            $parcels = $shipment->getTotalParcels();

            if ($weight === null && $volume === null) {
                $errors[] = sprintf(
                    'Envío %s no tiene peso ni volumen configurado.',
                    $shipment->getReference(),
                );
            }

            $totalWeight += $weight ?? 0.0;
            $totalVolume += $volume ?? 0.0;
            $totalParcels += $parcels;
        }

        $weightUtil = null;
        $volumeUtil = null;
        $parcelUtil = null;

        if ($vehicle !== null) {
            $maxWeight = $vehicle->getMaxWeightKg();
            $maxVolume = $vehicle->getMaxVolumeM3();
            $maxParcels = $vehicle->getMaxParcels();

            if ($maxWeight !== null && $maxWeight > 0) {
                $weightUtil = ($totalWeight / $maxWeight) * 100;
                if ($totalWeight > $maxWeight) {
                    $errors[] = sprintf(
                        'Peso total (%.2f kg) excede la capacidad del vehículo (%.2f kg).',
                        $totalWeight,
                        $maxWeight,
                    );
                }
            }

            if ($maxVolume !== null && $maxVolume > 0) {
                $volumeUtil = ($totalVolume / $maxVolume) * 100;
                if ($totalVolume > $maxVolume) {
                    $errors[] = sprintf(
                        'Volumen total (%.4f m³) excede la capacidad del vehículo (%.4f m³).',
                        $totalVolume,
                        $maxVolume,
                    );
                }
            }

            if ($maxParcels !== null && $maxParcels > 0) {
                $parcelUtil = ($totalParcels / $maxParcels) * 100;
                if ($totalParcels > $maxParcels) {
                    $errors[] = sprintf(
                        'Número de bultos (%d) excede la capacidad del vehículo (%d).',
                        $totalParcels,
                        $maxParcels,
                    );
                }
            }
        } else {
            $errors[] = 'La ruta no tiene vehículo asignado.';
        }

        // Update route totals
        $route->setTotalWeightKg($totalWeight);
        $route->setTotalVolumeM3($totalVolume);
        $route->setTotalParcels($totalParcels);

        return [
            'valid' => \count($errors) === 0,
            'errors' => $errors,
            'totalWeightKg' => $totalWeight,
            'totalVolumeM3' => $totalVolume,
            'totalParcels' => $totalParcels,
            'weightUtilization' => $weightUtil,
            'volumeUtilization' => $volumeUtil,
            'parcelUtilization' => $parcelUtil,
        ];
    }

    /**
     * Quick check: can this shipment fit into the given vehicle
     * considering already-assigned shipments?
     */
    public function canFitShipment(Vehicle $vehicle, Shipment $shipment, float $currentWeightKg = 0.0, float $currentVolumeM3 = 0.0, int $currentParcels = 0): bool
    {
        $maxWeight = $vehicle->getMaxWeightKg();
        $maxVolume = $vehicle->getMaxVolumeM3();
        $maxParcels = $vehicle->getMaxParcels();

        $shipmentWeight = $shipment->getTotalWeightKg() ?? 0.0;
        $shipmentVolume = $shipment->getTotalVolumeM3() ?? 0.0;
        $shipmentParcels = $shipment->getTotalParcels();

        if ($maxWeight !== null && ($currentWeightKg + $shipmentWeight) > $maxWeight) {
            return false;
        }

        if ($maxVolume !== null && ($currentVolumeM3 + $shipmentVolume) > $maxVolume) {
            return false;
        }

        if ($maxParcels !== null && ($currentParcels + $shipmentParcels) > $maxParcels) {
            return false;
        }

        return true;
    }

    /** @return list<RouteStop> */
    private function getDeliveryStops(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = false')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
