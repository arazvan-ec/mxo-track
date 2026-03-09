<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Groups shipments by geographic zone using k-means clustering.
 *
 * Pure PHP implementation — no external dependencies.
 */
final class ShipmentClusteringService
{
    private const int MAX_ITERATIONS = 50;

    /** @var list<string> Predefined cluster colours (hex) */
    private const array CLUSTER_COLORS = [
        '#E53E3E', // red
        '#3182CE', // blue
        '#38A169', // green
        '#D69E2E', // yellow
        '#805AD5', // purple
        '#DD6B20', // orange
        '#319795', // teal
        '#D53F8C', // pink
        '#2B6CB0', // dark blue
        '#276749', // dark green
    ];

    /**
     * Run k-means clustering on shipment coordinates.
     *
     * @param list<array{id: string|int, lat: float, lng: float}> $shipments
     * @param int $numClusters Number of clusters (k)
     * @return list<array{centroid: array{lat: float, lng: float}, shipmentIds: list<string|int>, color: string}>
     */
    public function cluster(array $shipments, int $numClusters): array
    {
        if ($shipments === [] || $numClusters < 1) {
            return [];
        }

        // Clamp k to the number of shipments
        $numClusters = min($numClusters, \count($shipments));

        // Initialise centroids via k-means++ seeding
        $centroids = $this->initCentroids($shipments, $numClusters);

        $assignments = [];

        for ($iter = 0; $iter < self::MAX_ITERATIONS; $iter++) {
            // Assign each shipment to the nearest centroid
            $newAssignments = $this->assignToClusters($shipments, $centroids);

            // Check convergence
            if ($newAssignments === $assignments) {
                break;
            }

            $assignments = $newAssignments;

            // Recompute centroids
            $centroids = $this->recomputeCentroids($shipments, $assignments, $numClusters);
        }

        // Build result
        return $this->buildResult($shipments, $assignments, $centroids, $numClusters);
    }

    /**
     * K-means++ initialisation: pick first centroid at random, then each subsequent
     * centroid with probability proportional to squared distance from nearest existing centroid.
     *
     * @param list<array{id: string|int, lat: float, lng: float}> $shipments
     * @return list<array{lat: float, lng: float}>
     */
    private function initCentroids(array $shipments, int $k): array
    {
        $centroids = [];

        // Pick first centroid randomly
        $firstIndex = random_int(0, \count($shipments) - 1);
        $centroids[] = ['lat' => $shipments[$firstIndex]['lat'], 'lng' => $shipments[$firstIndex]['lng']];

        for ($c = 1; $c < $k; $c++) {
            $distances = [];
            $totalDist = 0.0;

            foreach ($shipments as $s) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($centroids as $centroid) {
                    $d = $this->squaredDistance($s['lat'], $s['lng'], $centroid['lat'], $centroid['lng']);
                    if ($d < $minDist) {
                        $minDist = $d;
                    }
                }
                $distances[] = $minDist;
                $totalDist += $minDist;
            }

            // Weighted random selection
            if ($totalDist <= 0.0) {
                // All points coincide — just pick next distinct index
                $centroids[] = ['lat' => $shipments[$c % \count($shipments)]['lat'], 'lng' => $shipments[$c % \count($shipments)]['lng']];

                continue;
            }

            $threshold = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) * $totalDist;
            $cumulative = 0.0;
            $chosen = \count($shipments) - 1;

            foreach ($distances as $i => $d) {
                $cumulative += $d;
                if ($cumulative >= $threshold) {
                    $chosen = $i;

                    break;
                }
            }

            $centroids[] = ['lat' => $shipments[$chosen]['lat'], 'lng' => $shipments[$chosen]['lng']];
        }

        return $centroids;
    }

    /**
     * Assign each shipment to its nearest centroid.
     *
     * @param list<array{id: string|int, lat: float, lng: float}> $shipments
     * @param list<array{lat: float, lng: float}> $centroids
     * @return list<int> Cluster index per shipment
     */
    private function assignToClusters(array $shipments, array $centroids): array
    {
        $assignments = [];

        foreach ($shipments as $s) {
            $bestCluster = 0;
            $bestDist = PHP_FLOAT_MAX;

            foreach ($centroids as $ci => $centroid) {
                $d = $this->squaredDistance($s['lat'], $s['lng'], $centroid['lat'], $centroid['lng']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestCluster = $ci;
                }
            }

            $assignments[] = $bestCluster;
        }

        return $assignments;
    }

    /**
     * Recompute centroids as the mean of assigned points.
     *
     * @param list<array{id: string|int, lat: float, lng: float}> $shipments
     * @param list<int> $assignments
     * @return list<array{lat: float, lng: float}>
     */
    private function recomputeCentroids(array $shipments, array $assignments, int $k): array
    {
        $sums = array_fill(0, $k, ['lat' => 0.0, 'lng' => 0.0, 'count' => 0]);

        foreach ($assignments as $i => $cluster) {
            $sums[$cluster]['lat'] += $shipments[$i]['lat'];
            $sums[$cluster]['lng'] += $shipments[$i]['lng'];
            $sums[$cluster]['count']++;
        }

        $centroids = [];
        foreach ($sums as $s) {
            if ($s['count'] > 0) {
                $centroids[] = [
                    'lat' => $s['lat'] / $s['count'],
                    'lng' => $s['lng'] / $s['count'],
                ];
            } else {
                // Empty cluster — keep a zero centroid (will be reassigned next iteration)
                $centroids[] = ['lat' => 0.0, 'lng' => 0.0];
            }
        }

        return $centroids;
    }

    /**
     * Build the final result array.
     *
     * @param list<array{id: string|int, lat: float, lng: float}> $shipments
     * @param list<int> $assignments
     * @param list<array{lat: float, lng: float}> $centroids
     * @return list<array{centroid: array{lat: float, lng: float}, shipmentIds: list<string|int>, color: string}>
     */
    private function buildResult(array $shipments, array $assignments, array $centroids, int $k): array
    {
        $clusters = [];

        for ($c = 0; $c < $k; $c++) {
            $ids = [];
            foreach ($assignments as $i => $cluster) {
                if ($cluster === $c) {
                    $ids[] = $shipments[$i]['id'];
                }
            }

            // Skip empty clusters
            if ($ids === []) {
                continue;
            }

            $clusters[] = [
                'centroid' => $centroids[$c],
                'shipmentIds' => $ids,
                'color' => self::CLUSTER_COLORS[$c % \count(self::CLUSTER_COLORS)],
            ];
        }

        return $clusters;
    }

    private function squaredDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        return $dLat * $dLat + $dLng * $dLng;
    }
}
