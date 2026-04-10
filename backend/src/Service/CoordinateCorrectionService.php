<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DriverFeedbackRepository;

final readonly class CoordinateCorrectionService
{
    private const int MIN_FEEDBACKS = 3;
    private const float MAX_DEVIATION_METERS = 50.0;
    private const float EARTH_RADIUS_METERS = 6_371_000.0;

    public function __construct(
        private DriverFeedbackRepository $feedbackRepository,
    ) {
    }

    /**
     * Returns corrected [lat, lng] if ≥3 driver feedbacks for the address
     * agree within 50m. Returns null otherwise.
     *
     * @return array{0: float, 1: float}|null
     */
    public function getCorrectedCoordinates(string $address): ?array
    {
        $feedbacks = $this->feedbackRepository->findByAddress($address);

        // Filter to feedbacks with non-null corrected coordinates
        $withCoords = [];
        foreach ($feedbacks as $feedback) {
            if ($feedback->getCorrectedLat() !== null && $feedback->getCorrectedLng() !== null) {
                $withCoords[] = $feedback;
            }
        }

        if (\count($withCoords) < self::MIN_FEEDBACKS) {
            return null;
        }

        // Calculate average
        $sumLat = 0.0;
        $sumLng = 0.0;
        foreach ($withCoords as $feedback) {
            $sumLat += $feedback->getCorrectedLat();
            $sumLng += $feedback->getCorrectedLng();
        }
        $count = \count($withCoords);
        $avgLat = $sumLat / $count;
        $avgLng = $sumLng / $count;

        // Check that every point is within MAX_DEVIATION_METERS of the average
        foreach ($withCoords as $feedback) {
            $distance = self::haversineMeters(
                $avgLat,
                $avgLng,
                $feedback->getCorrectedLat(),
                $feedback->getCorrectedLng(),
            );

            if ($distance > self::MAX_DEVIATION_METERS) {
                return null;
            }
        }

        return [$avgLat, $avgLng];
    }

    /**
     * Haversine formula: distance in meters between two (lat, lng) points.
     */
    private static function haversineMeters(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLatRad = deg2rad($lat2 - $lat1);
        $dLngRad = deg2rad($lng2 - $lng1);

        $a = sin($dLatRad / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($dLngRad / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(sqrt($a));
    }
}
