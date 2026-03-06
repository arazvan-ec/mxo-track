<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Forecasts daily delivery demand using the ML sidecar (Prophet).
 *
 * Falls back to empty predictions if the sidecar is unavailable.
 */
final class DemandForecastService
{
    public function __construct(
        private readonly MlApiClient $mlApiClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Trigger model training on the ML sidecar.
     *
     * @return array{status: string, rows_used: int, date_range_start: string, date_range_end: string, mae: float|null, rmse: float|null}|null
     */
    public function train(): ?array
    {
        $result = $this->mlApiClient->train('train/demand-forecast');

        if ($result === null) {
            $this->logger->warning('Demand forecast training failed — ML sidecar unavailable');
        }

        return $result;
    }

    /**
     * Forecast daily demand for the next N days.
     *
     * Tries the ML sidecar first; falls back to empty predictions if unavailable.
     *
     * @return list<array{date: string, predicted: float, lower: float, upper: float}>
     */
    public function forecast(int $days = 7): array
    {
        $result = $this->mlApiClient->predict('predict/demand-forecast', [
            'days' => $days,
        ]);

        if ($result !== null && isset($result['predictions']) && \is_array($result['predictions'])) {
            /** @var list<array{date: string, predicted: float, lower: float, upper: float}> */
            return $result['predictions'];
        }

        $this->logger->info('ML sidecar unavailable for demand forecast, returning empty predictions');

        return [];
    }
}
