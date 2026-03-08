<?php

declare(strict_types=1);

namespace App\Prediction;

use Psr\Log\LoggerInterface;

final readonly class NullDemandForecaster implements DemandForecasterInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /** @return list<DemandForecast> */
    public function forecast(string $zoneId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $this->logger->debug('NullDemandForecaster::forecast called.', ['zoneId' => $zoneId]);

        return [];
    }

    /** @return list<DemandForecast> */
    public function forecastNext(string $zoneId, int $days): array
    {
        $this->logger->debug('NullDemandForecaster::forecastNext called.', ['zoneId' => $zoneId, 'days' => $days]);

        return [];
    }
}
