<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\RouteStop;
use Psr\Log\LoggerInterface;

/**
 * Predicts delivery service time using the ML sidecar.
 *
 * Falls back to a fixed 300-second estimate when the ML service
 * is unavailable or returns an error.
 */
final class ServiceTimePredictionService
{
    private const int FALLBACK_SECONDS = 300;

    public function __construct(
        private readonly MlApiClient $mlApiClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Predict service time in seconds for the given delivery features.
     */
    public function predict(
        int $hourOfDay,
        int $dayOfWeek,
        int $stopSequence,
        int $parcelCount,
        float $weightKg,
    ): int {
        $response = $this->mlApiClient->predict('service-time', [
            'hour_of_day' => $hourOfDay,
            'day_of_week' => $dayOfWeek,
            'stop_sequence' => $stopSequence,
            'parcel_count' => $parcelCount,
            'weight_kg' => $weightKg,
        ]);

        if ($response === [] || !isset($response['predicted_seconds'])) {
            $this->logger->warning('ML service time prediction failed, using fallback of {seconds}s', [
                'seconds' => self::FALLBACK_SECONDS,
            ]);

            return self::FALLBACK_SECONDS;
        }

        return (int) $response['predicted_seconds'];
    }

    /**
     * Predict service time for a specific route stop by extracting features
     * from the stop and its associated shipment.
     */
    public function predictForStop(RouteStop $stop): int
    {
        $deliveredAt = $stop->getDeliveredAt();
        $now = new \DateTimeImmutable();

        // Use deliveredAt if available, otherwise use current time for feature extraction
        $referenceTime = $deliveredAt ?? $now;

        $hourOfDay = (int) $referenceTime->format('G');
        $dayOfWeek = (int) $referenceTime->format('N') - 1; // PHP N is 1-7, we want 0-6
        $stopSequence = $stop->getSequence();

        // Default values — the Shipment entity does not yet have parcel_count/weight_kg
        $parcelCount = 1;
        $weightKg = 1.0;

        return $this->predict($hourOfDay, $dayOfWeek, $stopSequence, $parcelCount, $weightKg);
    }
}
