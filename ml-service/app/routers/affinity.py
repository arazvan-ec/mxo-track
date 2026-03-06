"""Driver-zone affinity router.

POST /predict/driver-zone-affinity — compute affinity scores from delivery history.
"""

from __future__ import annotations

from typing import Optional

from fastapi import APIRouter
from pydantic import BaseModel, Field
from sqlalchemy import text

from app.db import get_engine
from app.models.driver_affinity import AffinityInput, AffinityScore, compute_affinity

router = APIRouter(tags=["affinity"])


# ---------------------------------------------------------------------------
# Request / Response schemas
# ---------------------------------------------------------------------------


class AffinityRequest(BaseModel):
    """Request body for driver-zone affinity prediction."""

    driver_ids: Optional[list[int]] = Field(
        default=None,
        description="Filter to specific driver IDs. If null, query all drivers with delivery history.",
    )
    zone_ids: Optional[list[int]] = Field(
        default=None,
        description="Filter to specific zone IDs. If null, query all zones.",
    )


class AffinityItem(BaseModel):
    """A single driver-zone affinity result."""

    driver_id: int
    zone_id: int
    zone_name: str
    score: float = Field(ge=0.0, le=1.0)
    deliveries: int


class AffinityResponse(BaseModel):
    """Response for driver-zone affinity prediction."""

    affinities: list[AffinityItem]


# ---------------------------------------------------------------------------
# SQL query to aggregate delivery stats per (driver, zone)
# ---------------------------------------------------------------------------

_AFFINITY_SQL = text("""
    SELECT
        r.driver_id,
        rs.delivery_zone_id AS zone_id,
        COALESCE(dz.name, 'Zone ' || rs.delivery_zone_id) AS zone_name,
        COUNT(*)::int AS delivery_count,
        COUNT(*) FILTER (WHERE rs.status = 'EXCEPTION')::int AS exception_count,
        COALESCE(
            AVG(
                EXTRACT(EPOCH FROM (rs.delivered_at - r.start_at))
            ) FILTER (WHERE rs.delivered_at IS NOT NULL AND r.start_at IS NOT NULL),
            0
        ) AS avg_service_time_seconds
    FROM route_stop rs
    JOIN route_plan r ON rs.route_id = r.id
    LEFT JOIN delivery_zone dz ON dz.id = rs.delivery_zone_id
    WHERE r.driver_id IS NOT NULL
      AND rs.delivery_zone_id IS NOT NULL
      AND rs.is_origin = false
      {driver_filter}
      {zone_filter}
    GROUP BY r.driver_id, rs.delivery_zone_id, dz.name
    ORDER BY r.driver_id, delivery_count DESC
""")


def _build_query(driver_ids: list[int] | None, zone_ids: list[int] | None) -> tuple[str, dict]:
    """Build the SQL query with optional filters.

    Returns:
        Tuple of (query_string, bind_params).
    """
    driver_filter = ""
    zone_filter = ""
    params: dict = {}

    if driver_ids:
        driver_filter = "AND r.driver_id = ANY(:driver_ids)"
        params["driver_ids"] = driver_ids

    if zone_ids:
        zone_filter = "AND rs.delivery_zone_id = ANY(:zone_ids)"
        params["zone_ids"] = zone_ids

    query_str = _AFFINITY_SQL.text.format(
        driver_filter=driver_filter,
        zone_filter=zone_filter,
    )
    return query_str, params


# ---------------------------------------------------------------------------
# Endpoint
# ---------------------------------------------------------------------------


@router.post("/predict/driver-zone-affinity", response_model=AffinityResponse)
async def predict_driver_zone_affinity(request: AffinityRequest) -> AffinityResponse:
    """Compute driver-zone affinity scores from delivery history.

    Uses aggregated stats (delivery count, exception rate, avg service time)
    to produce a weighted affinity score per (driver, zone) pair.
    """
    query_str, params = _build_query(request.driver_ids, request.zone_ids)

    engine = get_engine()

    inputs: list[AffinityInput] = []
    with engine.connect() as conn:
        rows = conn.execute(text(query_str), params)
        for row in rows:
            inputs.append(
                AffinityInput(
                    driver_id=row.driver_id,
                    zone_id=row.zone_id,
                    zone_name=row.zone_name,
                    delivery_count=row.delivery_count,
                    exception_count=row.exception_count,
                    avg_service_time_seconds=float(row.avg_service_time_seconds),
                )
            )

    scores: list[AffinityScore] = compute_affinity(inputs)

    return AffinityResponse(
        affinities=[
            AffinityItem(
                driver_id=s.driver_id,
                zone_id=s.zone_id,
                zone_name=s.zone_name,
                score=s.score,
                deliveries=s.deliveries,
            )
            for s in scores
        ]
    )
