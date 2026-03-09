<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Provides driver-zone affinity scores from the ML sidecar.
 *
 * Affinities are used to assign VROOM "virtual skills" so that drivers are
 * preferentially routed to zones where they have high delivery performance.
 */
final class DriverAffinityService
{
    /** Minimum affinity score to qualify as a "skilled" zone for a driver. */
    private const float HIGH_AFFINITY_THRESHOLD = 0.6;

    public function __construct(
        private readonly MlApiClient $mlClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetch all driver-zone affinity scores from the ML sidecar.
     *
     * @param list<int>|null $driverIds Filter to specific drivers (null = all)
     * @param list<int>|null $zoneIds   Filter to specific zones (null = all)
     *
     * @return list<array{driver_id: int, zone_id: int, zone_name: string, score: float, deliveries: int}>
     */
    public function getAffinityScores(?array $driverIds = null, ?array $zoneIds = null): array
    {
        $payload = [];
        if ($driverIds !== null) {
            $payload['driver_ids'] = $driverIds;
        }
        if ($zoneIds !== null) {
            $payload['zone_ids'] = $zoneIds;
        }

        $response = $this->mlClient->post('/predict/driver-zone-affinity', $payload);

        if ($response === null || !isset($response['affinities'])) {
            $this->logger->warning('Failed to fetch affinity scores from ML sidecar');

            return [];
        }

        /** @var list<array{driver_id: int, zone_id: int, zone_name: string, score: float, deliveries: int}> */
        return $response['affinities'];
    }

    /**
     * Get VROOM virtual skill strings for a driver based on zone affinities.
     *
     * Returns skills like "ZONE_CENTRO", "ZONE_NORTE" for zones where the
     * driver has high affinity (score >= threshold).
     *
     * @return list<string>
     */
    public function getVroomSkillsForDriver(int $driverId): array
    {
        $scores = $this->getAffinityScores([$driverId]);

        $skills = [];
        foreach ($scores as $entry) {
            if ($entry['score'] >= self::HIGH_AFFINITY_THRESHOLD) {
                $skills[] = $this->zoneNameToSkill($entry['zone_name']);
            }
        }

        return $skills;
    }

    /**
     * Determine which zone a shipment falls into and return the required zone skill.
     *
     * For MVP, this uses a simple grid-based zone assignment. In production,
     * zone boundaries would come from a GIS layer or the delivery_zone table.
     *
     * @return list<string> Required VROOM skills (zone skill strings)
     */
    public function getVroomSkillsForShipment(float $lat, float $lng): array
    {
        // MVP: derive zone from coordinates using a simple name convention.
        // In production this would query delivery_zone polygons.
        $zoneName = $this->resolveZoneName($lat, $lng);
        if ($zoneName === null) {
            return [];
        }

        return [$this->zoneNameToSkill($zoneName)];
    }

    /**
     * Convert a zone name to a VROOM skill string.
     */
    private function zoneNameToSkill(string $zoneName): string
    {
        // Normalize: uppercase, replace spaces/special chars with underscore
        $normalized = preg_replace('/[^A-Z0-9]/', '_', mb_strtoupper($zoneName)) ?? $zoneName;
        $normalized = trim(preg_replace('/_+/', '_', $normalized) ?? $normalized, '_');

        return 'ZONE_' . $normalized;
    }

    /**
     * Resolve a zone name from coordinates (MVP heuristic for Madrid).
     *
     * Uses cardinal direction from city center as a simple zone classifier.
     */
    private function resolveZoneName(float $lat, float $lng): ?string
    {
        // Madrid city center reference point (Puerta del Sol)
        $centerLat = 40.4168;
        $centerLng = -3.7038;

        $dLat = $lat - $centerLat;
        $dLng = $lng - $centerLng;

        // If very close to center
        if (abs($dLat) < 0.01 && abs($dLng) < 0.01) {
            return 'CENTRO';
        }

        if ($dLat > 0 && abs($dLat) > abs($dLng)) {
            return 'NORTE';
        }
        if ($dLat < 0 && abs($dLat) > abs($dLng)) {
            return 'SUR';
        }
        if ($dLng > 0) {
            return 'ESTE';
        }

        return 'OESTE';
    }
}
