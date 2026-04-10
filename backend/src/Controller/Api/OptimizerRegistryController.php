<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Provider\ProviderFactoryRegistry;
use App\Provider\ServiceType;
use App\Repository\RoutePerformanceMetricRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
final class OptimizerRegistryController
{
    public function __construct(
        private readonly ProviderFactoryRegistry $registry,
        private readonly RoutePerformanceMetricRepository $metricsRepo,
    ) {}

    #[Route('/api/admin/route-planner/optimizers', name: 'api_admin_route_planner_optimizers', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $allProviders = $this->registry->getAvailableProviders();
        $optimizerNames = $allProviders[ServiceType::RouteOptimizer->value] ?? [];

        $since = new \DateTimeImmutable('-90 days');
        $metricRows = $this->metricsRepo->getMetricsByOptimizer($since);

        // Index metrics by optimizer name for fast lookup
        $metricsByName = [];
        foreach ($metricRows as $row) {
            $metricsByName[$row['optimizer_used']] = [
                'avg_distance_km' => $row['avg_distance_km'],
                'avg_duration_min' => $row['avg_duration_min'],
                'route_count' => $row['route_count'],
                'avg_success_rate' => $row['avg_success_rate'],
            ];
        }

        $result = [];
        foreach ($optimizerNames as $name) {
            $result[] = [
                'name' => $name,
                'stats' => $metricsByName[$name] ?? null,
            ];
        }

        return new JsonResponse($result);
    }
}
