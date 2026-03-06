<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\FleetAnomalyCheckMessage;
use App\Service\FleetAnomalyService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles FleetAnomalyCheckMessage by running anomaly detection via the ML sidecar.
 */
#[AsMessageHandler]
final class FleetAnomalyCheckHandler
{
    public function __construct(
        private readonly FleetAnomalyService $anomalyService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(FleetAnomalyCheckMessage $message): void
    {
        $vehicleId = $message->getVehicleId();
        $routeId = $message->getRouteId();

        $this->logger->info('Running fleet anomaly check', [
            'vehicleId' => $vehicleId,
            'routeId' => $routeId,
        ]);

        $anomalies = $this->anomalyService->checkAnomaly($vehicleId, $routeId);

        if (\count($anomalies) > 0) {
            $this->logger->warning('Fleet anomalies detected', [
                'vehicleId' => $vehicleId,
                'routeId' => $routeId,
                'count' => \count($anomalies),
                'anomalies' => $anomalies,
            ]);
        } else {
            $this->logger->info('No anomalies detected', [
                'vehicleId' => $vehicleId,
                'routeId' => $routeId,
            ]);
        }
    }
}
