<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\OptimizationStrategyComparison;
use App\Provider\ProviderFactoryRegistry;
use Doctrine\ORM\EntityManagerInterface;

final readonly class StrategyComparisonService
{
    public function __construct(
        private ProviderFactoryRegistry $registry,
        private RouteBuilder $routeBuilder,
        private EntityManagerInterface $em,
        private OptimizationLogger $logger,
    ) {
    }

    /**
     * Compare all available route optimizers on the same shipment/vehicle set.
     *
     * @param list<\App\Domain\Shipment\Model\Shipment> $shipments
     * @param list<\App\Entity\Vehicle>                  $vehicles
     *
     * @return list<array{optimizer_name: string, distance_km: float, duration_min: int, route_count: int, unassigned_count: int}>
     */
    public function compare(
        array $shipments,
        array $vehicles,
        Customer $customer,
        ?CustomerLocation $origin = null,
        int $maxStopsPerRoute = 30,
    ): array {
        $available = $this->registry->getAvailableProviders();
        $optimizerNames = $available['route_optimizer'] ?? [];

        if ($optimizerNames === []) {
            return [];
        }

        $results = [];

        foreach ($optimizerNames as $name) {
            $optimizer = $this->registry->createByName($name);

            $routes = $this->routeBuilder->buildRoutes(
                $shipments,
                $vehicles,
                $customer,
                $origin,
                $maxStopsPerRoute,
                $optimizer,
            );

            $distanceKm = 0.0;
            $durationMin = 0;
            $unassignedCount = 0;

            foreach ($routes as $entry) {
                $route = $entry['route'];
                $distanceKm += $route->getTotalDistanceKm() ?? 0.0;
                $durationMin += $route->getEstimatedDurationMinutes() ?? 0;
            }

            $results[] = [
                'optimizer_name' => $name,
                'distance_km' => $distanceKm,
                'duration_min' => $durationMin,
                'route_count' => \count($routes),
                'unassigned_count' => $unassignedCount,
            ];
        }

        if (\count($results) >= 2) {
            $a = $results[0];
            $b = $results[1];

            $chosen = $a['distance_km'] <= $b['distance_km'] ? 'a' : 'b';

            $comparison = new OptimizationStrategyComparison(
                strategyA: [
                    'strategy' => $a['optimizer_name'],
                    'params' => [],
                    'result' => [
                        'distance_km' => $a['distance_km'],
                        'duration_min' => $a['duration_min'],
                        'stops' => $a['route_count'],
                        'unassigned' => $a['unassigned_count'],
                    ],
                ],
                strategyB: [
                    'strategy' => $b['optimizer_name'],
                    'params' => [],
                    'result' => [
                        'distance_km' => $b['distance_km'],
                        'duration_min' => $b['duration_min'],
                        'stops' => $b['route_count'],
                        'unassigned' => $b['unassigned_count'],
                    ],
                ],
                chosen: $chosen,
                shipmentCount: \count($shipments),
                customer: $customer,
                chosenReason: sprintf(
                    '%s had shorter distance (%.1f km vs %.1f km)',
                    $chosen === 'a' ? $a['optimizer_name'] : $b['optimizer_name'],
                    $chosen === 'a' ? $a['distance_km'] : $b['distance_km'],
                    $chosen === 'a' ? $b['distance_km'] : $a['distance_km'],
                ),
            );

            $this->em->persist($comparison);
            $this->em->flush();
        }

        return $results;
    }
}
