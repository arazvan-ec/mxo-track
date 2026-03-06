<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerLocation;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Enum\VehicleSkill;

/**
 * Converts domain entities to VROOM API request format.
 *
 * VROOM uses:
 * - [longitude, latitude] coordinate order
 * - Integer capacities (we use grams, cm³, parcels)
 * - Time in seconds
 */
final class VroomRequestMapper
{
    /** Default service time in seconds when a shipment does not specify one. */
    public const DEFAULT_SERVICE_TIME_SECONDS = 300; // 5 minutes per delivery stop

    /**
     * @param list<Vehicle> $vehicles
     * @return array{vroomVehicles: list<array>, vehicleMap: array<int, Vehicle>}
     */
    public function mapVehicles(array $vehicles, ?CustomerLocation $origin, ?int $maxTasks = null): array
    {
        $vroomVehicles = [];
        $vehicleMap = [];

        foreach ($vehicles as $index => $vehicle) {
            $vroomVehicle = [
                'id' => $index,
                'capacity' => [
                    $this->kgToGrams($vehicle->getMaxWeightKg()),
                    $this->m3ToCm3($vehicle->getMaxVolumeM3()),
                    $vehicle->getMaxParcels() ?? 9999,
                ],
            ];

            if ($maxTasks !== null) {
                $vroomVehicle['max_tasks'] = $maxTasks;
            }

            if ($origin !== null && $origin->getLatitude() !== null && $origin->getLongitude() !== null) {
                $coords = [$origin->getLongitude(), $origin->getLatitude()];
                $vroomVehicle['start'] = $coords;
                $vroomVehicle['end'] = $coords; // return to origin
            }

            $vehicleSkills = array_map(fn (VehicleSkill $s) => $s->value, $vehicle->getSkills());
            if (!empty($vehicleSkills)) {
                $vroomVehicle['skills'] = $vehicleSkills;
            }

            $vroomVehicles[] = $vroomVehicle;
            $vehicleMap[$index] = $vehicle;
        }

        return [
            'vroomVehicles' => $vroomVehicles,
            'vehicleMap' => $vehicleMap,
        ];
    }

    /**
     * @param list<Shipment> $shipments
     * @return array{vroomJobs: list<array>, shipmentMap: array<int, Shipment>}
     */
    public function mapJobs(array $shipments): array
    {
        $vroomJobs = [];
        $shipmentMap = [];

        foreach ($shipments as $index => $shipment) {
            if ($shipment->getLatitude() === null || $shipment->getLongitude() === null) {
                continue; // Skip shipments without coordinates
            }

            $job = [
                'id' => $index,
                'location' => [$shipment->getLongitude(), $shipment->getLatitude()],
                'service' => $shipment->getServiceTimeSeconds() ?? self::DEFAULT_SERVICE_TIME_SECONDS,
                'amount' => [
                    $this->kgToGrams($shipment->getTotalWeightKg()),
                    $this->m3ToCm3($shipment->getTotalVolumeM3()),
                    $shipment->getTotalParcels(),
                ],
                'priority' => $shipment->getPriority()->toVroomPriority(),
            ];

            // Add time windows if the shipment has delivery preferences
            $windowStart = $shipment->getPreferredWindowStart();
            $windowEnd = $shipment->getPreferredWindowEnd();

            if ($windowStart !== null && $windowEnd !== null) {
                $job['time_windows'] = [[
                    $this->timeToSeconds($windowStart),
                    $this->timeToSeconds($windowEnd),
                ]];
            }

            $requiredSkills = array_map(fn (VehicleSkill $s) => $s->value, $shipment->getRequiredSkills());
            if (!empty($requiredSkills)) {
                $job['skills'] = $requiredSkills;
            }

            $vroomJobs[] = $job;
            $shipmentMap[$index] = $shipment;
        }

        return [
            'vroomJobs' => $vroomJobs,
            'shipmentMap' => $shipmentMap,
        ];
    }

    private function kgToGrams(?float $kg): int
    {
        return $kg !== null ? (int) round($kg * 1000) : 999999;
    }

    private function m3ToCm3(?float $m3): int
    {
        return $m3 !== null ? (int) round($m3 * 1_000_000) : 999999;
    }

    private function timeToSeconds(\DateTimeImmutable $time): int
    {
        return (int) $time->format('H') * 3600
            + (int) $time->format('i') * 60
            + (int) $time->format('s');
    }
}
