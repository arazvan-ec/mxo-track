"""Fleet anomaly detection router.

POST /predict/fleet-anomaly — detect anomalies in vehicle positions.
"""

from __future__ import annotations

from fastapi import APIRouter
from pydantic import BaseModel, Field

from app.models.anomaly_detector import (
    Anomaly,
    AnomalyType,
    PlannedStop,
    Position,
    Severity,
    detect_anomalies,
)

router = APIRouter(tags=["anomaly"])


# ---------------------------------------------------------------------------
# Request / Response schemas
# ---------------------------------------------------------------------------


class PositionInput(BaseModel):
    """A single GPS position in the request."""

    lat: float
    lng: float
    speed: float = Field(ge=0.0, description="Speed in km/h")
    timestamp: str = Field(description="ISO 8601 timestamp")


class PlannedStopInput(BaseModel):
    """A planned stop on the route."""

    lat: float
    lng: float
    sequence: int


class AnomalyRequest(BaseModel):
    """Request body for fleet anomaly detection."""

    vehicle_id: int
    positions: list[PositionInput] = Field(min_length=1)
    planned_stops: list[PlannedStopInput] = Field(default_factory=list)


class AnomalyItem(BaseModel):
    """A single detected anomaly."""

    type: str = Field(description="Anomaly type: EXCESSIVE_SPEED, UNPLANNED_STOP, ROUTE_DEVIATION")
    severity: str = Field(description="Severity: LOW, MEDIUM, HIGH")
    lat: float
    lng: float
    timestamp: str
    details: str


class AnomalyResponse(BaseModel):
    """Response for fleet anomaly detection."""

    vehicle_id: int
    anomalies: list[AnomalyItem]


# ---------------------------------------------------------------------------
# Endpoint
# ---------------------------------------------------------------------------


@router.post("/predict/fleet-anomaly", response_model=AnomalyResponse)
async def predict_fleet_anomaly(request: AnomalyRequest) -> AnomalyResponse:
    """Detect anomalies in a sequence of vehicle GPS positions.

    Checks for:
    - Excessive speed (> 120 km/h)
    - Unplanned stops (stopped > 45 min outside planned stop locations)
    - Route deviation (> 2 km from planned route)
    """
    positions = [
        Position(lat=p.lat, lng=p.lng, speed=p.speed, timestamp=p.timestamp)
        for p in request.positions
    ]

    planned_stops = [
        PlannedStop(lat=s.lat, lng=s.lng, sequence=s.sequence)
        for s in request.planned_stops
    ]

    anomalies: list[Anomaly] = detect_anomalies(positions, planned_stops)

    return AnomalyResponse(
        vehicle_id=request.vehicle_id,
        anomalies=[
            AnomalyItem(
                type=a.type.value,
                severity=a.severity.value,
                lat=a.lat,
                lng=a.lng,
                timestamp=a.timestamp,
                details=a.details,
            )
            for a in anomalies
        ],
    )
