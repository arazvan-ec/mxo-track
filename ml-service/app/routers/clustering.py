"""Delivery zone clustering router — compute delivery zones via K-means."""

from __future__ import annotations

import logging

import numpy as np
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import text

from app.db import get_engine
from app.models.zone_clustering import ZoneClustering

logger = logging.getLogger(__name__)
router = APIRouter()


# ---------------------------------------------------------------------------
# Pydantic schemas
# ---------------------------------------------------------------------------

class ClusterRequest(BaseModel):
    n_clusters: int | None = Field(
        default=None,
        ge=2,
        le=50,
        description="Number of clusters. If null, auto-detect using silhouette method.",
    )


class ZoneItem(BaseModel):
    id: int
    center_lat: float
    center_lng: float
    radius_km: float
    delivery_count: int
    suggested_name: str


class ClusterResponse(BaseModel):
    zones: list[ZoneItem]
    total_deliveries: int
    n_clusters: int


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post("/cluster/delivery-zones", response_model=ClusterResponse)
async def cluster_delivery_zones(request: ClusterRequest) -> ClusterResponse:
    """Compute delivery zones from historical stop coordinates.

    Queries route_stop lat/lng for delivered stops and runs K-means clustering.
    No persistence — the PHP side stores the results.
    """
    engine = get_engine()

    query = text("""
        SELECT latitude, longitude
        FROM route_stop
        WHERE status = 'DELIVERED'
          AND latitude IS NOT NULL
          AND longitude IS NOT NULL
    """)

    try:
        with engine.connect() as conn:
            result = conn.execute(query)
            rows = result.fetchall()
    except Exception as exc:
        logger.exception("Failed to query delivery coordinates")
        raise HTTPException(status_code=500, detail=f"Database query failed: {exc}") from exc

    if len(rows) < 2:
        raise HTTPException(
            status_code=422,
            detail=f"Insufficient data for clustering: {len(rows)} points found, need at least 2",
        )

    coordinates = np.array([[float(row[0]), float(row[1])] for row in rows])

    try:
        clustering = ZoneClustering()
        zones = clustering.cluster(coordinates, n_clusters=request.n_clusters)
    except Exception as exc:
        logger.exception("Clustering failed")
        raise HTTPException(status_code=500, detail=f"Clustering failed: {exc}") from exc

    return ClusterResponse(
        zones=[
            ZoneItem(
                id=z.id,
                center_lat=z.center_lat,
                center_lng=z.center_lng,
                radius_km=z.radius_km,
                delivery_count=z.delivery_count,
                suggested_name=z.suggested_name,
            )
            for z in zones
        ],
        total_deliveries=len(coordinates),
        n_clusters=len(zones),
    )
