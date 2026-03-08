<?php

declare(strict_types=1);

namespace App\Prediction;

interface AnomalyDetectorInterface
{
    /**
     * Detect anomalies for a single entity based on provided metrics.
     *
     * @param string $entityType Type of entity (e.g. 'vehicle', 'route', 'driver')
     * @param string $entityId Public ID of the entity
     * @param array<string, mixed> $metrics Key-value metrics to analyze
     */
    public function detect(string $entityType, string $entityId, array $metrics): ?AnomalyResult;

    /**
     * Detect anomalies for multiple entities in a single batch call.
     *
     * @param list<array{entityType: string, entityId: string, metrics: array<string, mixed>}> $items
     * @return list<AnomalyResult>
     */
    public function detectBatch(array $items): array;
}
