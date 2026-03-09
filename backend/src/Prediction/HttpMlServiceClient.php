<?php

declare(strict_types=1);

namespace App\Prediction;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpMlServiceClient implements EtaPredictorInterface, DemandForecasterInterface, AnomalyDetectorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
    ) {
    }

    public function predictEta(string $routeStopId, array $currentPosition, array $trafficData = []): EtaPrediction
    {
        $data = $this->postJson('/api/eta/predict', [
            'route_stop_id' => $routeStopId,
            'current_position' => $currentPosition,
            'traffic_data' => $trafficData,
        ]);

        return new EtaPrediction(
            routeStopId: (string) ($data['route_stop_id'] ?? $routeStopId),
            estimatedArrival: new \DateTimeImmutable((string) ($data['estimated_arrival'] ?? 'now')),
            confidenceScore: (float) ($data['confidence_score'] ?? 0.0),
            modelVersion: (string) ($data['model_version'] ?? 'unknown'),
        );
    }

    /** @return list<EtaPrediction> */
    public function predictBatchEta(array $stops): array
    {
        $data = $this->postJson('/api/eta/predict-batch', [
            'stops' => $stops,
        ]);

        $predictions = $data['predictions'] ?? [];

        return array_map(
            static fn(array $p) => new EtaPrediction(
                routeStopId: (string) ($p['route_stop_id'] ?? ''),
                estimatedArrival: new \DateTimeImmutable((string) ($p['estimated_arrival'] ?? 'now')),
                confidenceScore: (float) ($p['confidence_score'] ?? 0.0),
                modelVersion: (string) ($p['model_version'] ?? 'unknown'),
            ),
            $predictions,
        );
    }

    /** @return list<DemandForecast> */
    public function forecast(string $zoneId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $data = $this->postJson('/api/demand/forecast', [
            'zone_id' => $zoneId,
            'from' => $from->format(\DATE_ATOM),
            'to' => $to->format(\DATE_ATOM),
        ]);

        return $this->mapForecasts($data['forecasts'] ?? []);
    }

    /** @return list<DemandForecast> */
    public function forecastNext(string $zoneId, int $days): array
    {
        $data = $this->postJson('/api/demand/forecast-next', [
            'zone_id' => $zoneId,
            'days' => $days,
        ]);

        return $this->mapForecasts($data['forecasts'] ?? []);
    }

    public function detect(string $entityType, string $entityId, array $metrics): ?AnomalyResult
    {
        $data = $this->postJson('/api/anomaly/detect', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metrics' => $metrics,
        ]);

        if (($data['detected'] ?? false) === false) {
            return null;
        }

        return new AnomalyResult(
            entityType: (string) ($data['entity_type'] ?? $entityType),
            entityId: (string) ($data['entity_id'] ?? $entityId),
            anomalyType: (string) ($data['anomaly_type'] ?? 'unknown'),
            severity: (float) ($data['severity'] ?? 0.0),
            description: (string) ($data['description'] ?? ''),
            detectedAt: new \DateTimeImmutable((string) ($data['detected_at'] ?? 'now')),
        );
    }

    /** @return list<AnomalyResult> */
    public function detectBatch(array $items): array
    {
        $data = $this->postJson('/api/anomaly/detect-batch', [
            'items' => $items,
        ]);

        $anomalies = $data['anomalies'] ?? [];

        return array_map(
            static fn(array $a) => new AnomalyResult(
                entityType: (string) ($a['entity_type'] ?? ''),
                entityId: (string) ($a['entity_id'] ?? ''),
                anomalyType: (string) ($a['anomaly_type'] ?? 'unknown'),
                severity: (float) ($a['severity'] ?? 0.0),
                description: (string) ($a['description'] ?? ''),
                detectedAt: new \DateTimeImmutable((string) ($a['detected_at'] ?? 'now')),
            ),
            $anomalies,
        );
    }

    /**
     * @param list<array<string, mixed>> $forecasts
     * @return list<DemandForecast>
     */
    private function mapForecasts(array $forecasts): array
    {
        return array_map(
            static fn(array $f) => new DemandForecast(
                zoneId: (string) ($f['zone_id'] ?? ''),
                date: new \DateTimeImmutable((string) ($f['date'] ?? 'now')),
                expectedShipments: (int) ($f['expected_shipments'] ?? 0),
                confidenceInterval: [
                    'low' => (int) ($f['confidence_interval']['low'] ?? 0),
                    'high' => (int) ($f['confidence_interval']['high'] ?? 0),
                ],
            ),
            $forecasts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . $path, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->warning('ML service returned error status.', [
                    'path' => $path,
                    'status' => $statusCode,
                ]);

                return [];
            }

            $data = $response->toArray(false);

            return \is_array($data) ? $data : [];
        } catch (TransportExceptionInterface|\JsonException $e) {
            $this->logger->error('ML service request failed.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
