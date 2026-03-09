<?php

declare(strict_types=1);

namespace App\Prediction;

interface DemandForecasterInterface
{
    /**
     * Forecast shipment demand for a zone over a date range.
     *
     * @param string $zoneId Identifier of the geographic zone
     * @param \DateTimeImmutable $from Start of the forecast range
     * @param \DateTimeImmutable $to End of the forecast range
     * @return list<DemandForecast>
     */
    public function forecast(string $zoneId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * Forecast shipment demand for the next N days starting from today.
     *
     * @param string $zoneId Identifier of the geographic zone
     * @param int $days Number of days to forecast
     * @return list<DemandForecast>
     */
    public function forecastNext(string $zoneId, int $days): array;
}
