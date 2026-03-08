<?php

declare(strict_types=1);

namespace App\Prediction;

use Psr\Log\LoggerInterface;

final readonly class NullEtaPredictor implements EtaPredictorInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function predictEta(string $routeStopId, array $currentPosition, array $trafficData = []): EtaPrediction
    {
        $this->logger->debug('NullEtaPredictor::predictEta called.', ['routeStopId' => $routeStopId]);

        return new EtaPrediction(
            routeStopId: $routeStopId,
            estimatedArrival: new \DateTimeImmutable('+30 minutes'),
            confidenceScore: 0.0,
            modelVersion: 'null',
        );
    }

    /** @return list<EtaPrediction> */
    public function predictBatchEta(array $stops): array
    {
        $this->logger->debug('NullEtaPredictor::predictBatchEta called.', ['count' => \count($stops)]);

        return array_map(
            fn(array $stop) => new EtaPrediction(
                routeStopId: (string) ($stop['routeStopId'] ?? ''),
                estimatedArrival: new \DateTimeImmutable('+30 minutes'),
                confidenceScore: 0.0,
                modelVersion: 'null',
            ),
            $stops,
        );
    }
}
