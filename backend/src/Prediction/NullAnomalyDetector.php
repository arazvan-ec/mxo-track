<?php

declare(strict_types=1);

namespace App\Prediction;

use Psr\Log\LoggerInterface;

final readonly class NullAnomalyDetector implements AnomalyDetectorInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function detect(string $entityType, string $entityId, array $metrics): ?AnomalyResult
    {
        $this->logger->debug('NullAnomalyDetector::detect called.', [
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);

        return null;
    }

    /** @return list<AnomalyResult> */
    public function detectBatch(array $items): array
    {
        $this->logger->debug('NullAnomalyDetector::detectBatch called.', ['count' => \count($items)]);

        return [];
    }
}
