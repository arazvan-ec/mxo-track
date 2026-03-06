"""Zone clustering model using K-means for delivery zone detection."""

from __future__ import annotations

import logging
from dataclasses import dataclass

import numpy as np
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

logger = logging.getLogger(__name__)

# Earth radius in km for Haversine-based radius calculation
EARTH_RADIUS_KM = 6371.0


@dataclass
class DeliveryZone:
    id: int
    center_lat: float
    center_lng: float
    radius_km: float
    delivery_count: int
    suggested_name: str


class ZoneClustering:
    """K-means clustering for delivery zone identification."""

    def cluster(
        self,
        coordinates: np.ndarray,
        n_clusters: int | None = None,
    ) -> list[DeliveryZone]:
        """Cluster delivery coordinates into zones.

        Args:
            coordinates: Array of shape (n, 2) with [latitude, longitude] rows.
            n_clusters: Number of clusters. If None, auto-detect using elbow/silhouette.

        Returns:
            List of detected delivery zones.
        """
        if len(coordinates) < 2:
            raise ValueError("Need at least 2 coordinate points for clustering")

        if n_clusters is None:
            n_clusters = self._detect_optimal_k(coordinates)

        # Clamp n_clusters to valid range
        n_clusters = max(2, min(n_clusters, len(coordinates)))

        # Scale coordinates for better clustering (lat/lng have different scales)
        scaler = StandardScaler()
        coords_scaled = scaler.fit_transform(coordinates)

        kmeans = KMeans(
            n_clusters=n_clusters,
            n_init=10,
            max_iter=300,
            random_state=42,
        )
        labels = kmeans.fit_predict(coords_scaled)

        # Compute zone properties from original (unscaled) coordinates
        zones: list[DeliveryZone] = []
        for cluster_id in range(n_clusters):
            mask = labels == cluster_id
            cluster_coords = coordinates[mask]

            if len(cluster_coords) == 0:
                continue

            center_lat = float(cluster_coords[:, 0].mean())
            center_lng = float(cluster_coords[:, 1].mean())
            radius_km = self._compute_radius_km(cluster_coords, center_lat, center_lng)

            zones.append(
                DeliveryZone(
                    id=cluster_id,
                    center_lat=round(center_lat, 6),
                    center_lng=round(center_lng, 6),
                    radius_km=round(radius_km, 2),
                    delivery_count=int(mask.sum()),
                    suggested_name=f"Zona {cluster_id + 1}",
                )
            )

        # Sort by delivery count descending
        zones.sort(key=lambda z: z.delivery_count, reverse=True)

        return zones

    def _detect_optimal_k(
        self,
        coordinates: np.ndarray,
        max_k: int = 10,
    ) -> int:
        """Auto-detect optimal number of clusters using silhouette score.

        Args:
            coordinates: Array of shape (n, 2).
            max_k: Maximum number of clusters to test.

        Returns:
            Optimal number of clusters.
        """
        n_samples = len(coordinates)
        max_k = min(max_k, n_samples - 1)

        if max_k < 2:
            return 2

        scaler = StandardScaler()
        coords_scaled = scaler.fit_transform(coordinates)

        best_k = 2
        best_score = -1.0

        for k in range(2, max_k + 1):
            kmeans = KMeans(n_clusters=k, n_init=10, max_iter=300, random_state=42)
            labels = kmeans.fit_predict(coords_scaled)

            # Silhouette score needs at least 2 distinct labels
            n_unique = len(set(labels))
            if n_unique < 2:
                continue

            score = silhouette_score(coords_scaled, labels)
            if score > best_score:
                best_score = score
                best_k = k

        logger.info("Optimal k detected: %d (silhouette=%.3f)", best_k, best_score)
        return best_k

    @staticmethod
    def _compute_radius_km(
        coords: np.ndarray,
        center_lat: float,
        center_lng: float,
    ) -> float:
        """Compute max distance from center to any point in the cluster (Haversine)."""
        if len(coords) <= 1:
            return 0.5  # default minimum radius

        lat1 = np.radians(center_lat)
        lng1 = np.radians(center_lng)
        lats = np.radians(coords[:, 0])
        lngs = np.radians(coords[:, 1])

        dlat = lats - lat1
        dlng = lngs - lng1

        a = np.sin(dlat / 2) ** 2 + np.cos(lat1) * np.cos(lats) * np.sin(dlng / 2) ** 2
        c = 2 * np.arctan2(np.sqrt(a), np.sqrt(1 - a))
        distances = EARTH_RADIUS_KM * c

        max_dist = float(distances.max())
        return max(0.5, max_dist)  # minimum 0.5km radius
