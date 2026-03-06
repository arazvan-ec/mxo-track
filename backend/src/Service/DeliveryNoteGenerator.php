<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generates delivery notes (albaranes) for routes and individual shipments.
 */
final class DeliveryNoteGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Generates delivery note data for a complete route.
     *
     * @return array{
     *     noteNumber: string,
     *     date: string,
     *     route: array{name: string, publicId: string},
     *     driver: array{name: string|null, email: string}|null,
     *     vehicle: array{name: string}|null,
     *     customer: array{name: string, address: string|null, phone: string|null}|null,
     *     origin: array{name: string, address: string}|null,
     *     stops: list<array{
     *         sequence: int,
     *         address: string,
     *         recipientName: string|null,
     *         shipmentReference: string|null,
     *         serviceType: string|null,
     *         parcels: list<array{sequence: string, weightKg: float, volumeM3: float, ean: string|null, description: string|null}>,
     *         totalWeightKg: float|null,
     *         totalVolumeM3: float|null,
     *         totalParcels: int,
     *     }>,
     *     totals: array{weightKg: float, volumeM3: float, parcels: int, stops: int},
     * }
     */
    public function generateForRoute(Route $route): array
    {
        $stops = $this->getStopsWithShipments($route);

        $driver = $route->getDriver();
        $vehicle = $route->getVehicle();
        $customer = $route->getCustomer();
        $origin = $route->getOriginLocation();

        $totalWeight = 0.0;
        $totalVolume = 0.0;
        $totalParcels = 0;
        $stopData = [];

        foreach ($stops as $stop) {
            $shipment = $stop->getShipment();
            $parcelsData = [];

            if ($shipment !== null) {
                foreach ($shipment->getParcels() as $parcel) {
                    $parcelsData[] = [
                        'sequence' => $parcel->getLabel(),
                        'weightKg' => $parcel->getWeightKg(),
                        'volumeM3' => $parcel->getVolumeM3(),
                        'ean' => $parcel->getEan(),
                        'description' => $parcel->getDescription(),
                    ];
                }

                $weight = $shipment->getTotalWeightKg() ?? 0.0;
                $volume = $shipment->getTotalVolumeM3() ?? 0.0;
                $parcels = $shipment->getTotalParcels();
            } else {
                $weight = 0.0;
                $volume = 0.0;
                $parcels = 0;
            }

            $totalWeight += $weight;
            $totalVolume += $volume;
            $totalParcels += $parcels;

            $stopData[] = [
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'recipientName' => $stop->getRecipientName(),
                'shipmentReference' => $shipment?->getReference(),
                'serviceType' => $shipment?->getServiceType()->label(),
                'parcels' => $parcelsData,
                'totalWeightKg' => $shipment?->getTotalWeightKg(),
                'totalVolumeM3' => $shipment?->getTotalVolumeM3(),
                'totalParcels' => $parcels,
            ];
        }

        return [
            'noteNumber' => $this->generateNoteNumber($route),
            'date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'route' => [
                'name' => $route->getName(),
                'publicId' => $route->getPublicIdString(),
            ],
            'driver' => $driver !== null ? [
                'name' => $driver->getName(),
                'email' => $driver->getEmail(),
            ] : null,
            'vehicle' => $vehicle !== null ? [
                'name' => $vehicle->getName(),
            ] : null,
            'customer' => $customer !== null ? [
                'name' => $customer->getName(),
                'address' => $customer->getAddress(),
                'phone' => $customer->getContactPhone(),
            ] : null,
            'origin' => $origin !== null ? [
                'name' => $origin->getName(),
                'address' => $origin->getAddress(),
            ] : null,
            'stops' => $stopData,
            'totals' => [
                'weightKg' => $totalWeight,
                'volumeM3' => $totalVolume,
                'parcels' => $totalParcels,
                'stops' => \count($stopData),
            ],
        ];
    }

    /**
     * Generates delivery note data for a single shipment.
     *
     * @return array{
     *     noteNumber: string,
     *     date: string,
     *     shipmentReference: string,
     *     serviceType: string,
     *     recipientName: string|null,
     *     address: string|null,
     *     phone: string|null,
     *     parcels: list<array{sequence: string, weightKg: float, volumeM3: float, ean: string|null, description: string|null}>,
     *     totals: array{weightKg: float|null, volumeM3: float|null, parcels: int},
     * }
     */
    public function generateForShipment(Shipment $shipment): array
    {
        $parcelsData = [];
        foreach ($shipment->getParcels() as $parcel) {
            $parcelsData[] = [
                'sequence' => $parcel->getLabel(),
                'weightKg' => $parcel->getWeightKg(),
                'volumeM3' => $parcel->getVolumeM3(),
                'ean' => $parcel->getEan(),
                'description' => $parcel->getDescription(),
            ];
        }

        return [
            'noteNumber' => sprintf('ALB-%s-%s', strtoupper(substr($shipment->getPublicIdString(), -8)), date('ymd')),
            'date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'shipmentReference' => $shipment->getReference(),
            'serviceType' => $shipment->getServiceType()->label(),
            'recipientName' => $shipment->getRecipientName(),
            'address' => $shipment->getAddress(),
            'phone' => $shipment->getRecipientPhone(),
            'parcels' => $parcelsData,
            'totals' => [
                'weightKg' => $shipment->getTotalWeightKg(),
                'volumeM3' => $shipment->getTotalVolumeM3(),
                'parcels' => $shipment->getTotalParcels(),
            ],
        ];
    }

    private function generateNoteNumber(Route $route): string
    {
        return sprintf('ALB-%s-%s', strtoupper(substr($route->getPublicIdString(), -8)), date('ymd'));
    }

    /** @return list<RouteStop> */
    private function getStopsWithShipments(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->leftJoin('s.shipment', 'sh')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = false')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
