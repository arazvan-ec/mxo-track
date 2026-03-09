"""Fleet anomaly detection model.

Rule-based + z-score detection for MVP. Detects:
- Excessive speed (> 120 km/h)
- Unplanned stops (stopped > 45 min outside planned stop locations)
- Route deviation (> 2 km from planned route)
"""

from __future__ import annotations

import math
from dataclasses import dataclass
from enum import Enum


class AnomalyType(str, Enum):
    EXCESSIVE_SPEED = "EXCESSIVE_SPEED"
    UNPLANNED_STOP = "UNPLANNED_STOP"
    ROUTE_DEVIATION = "ROUTE_DEVIATION"


class Severity(str, Enum):
    LOW = "LOW"
    MEDIUM = "MEDIUM"
    HIGH = "HIGH"


@dataclass(frozen=True)
class Position:
    """A single GPS position."""

    lat: float
    lng: float
    speed: float  # km/h
    timestamp: str  # ISO 8601


@dataclass(frozen=True)
class PlannedStop:
    """A planned stop on the route."""

    lat: float
    lng: float
    sequence: int


@dataclass(frozen=True)
class Anomaly:
    """A detected anomaly."""

    type: AnomalyType
    severity: Severity
    lat: float
    lng: float
    timestamp: str
    details: str


# Thresholds
_SPEED_HIGH = 120.0  # km/h
_SPEED_VERY_HIGH = 150.0  # km/h
_STOPPED_SPEED_THRESHOLD = 2.0  # km/h — considered "stopped"
_UNPLANNED_STOP_MINUTES = 45
_PLANNED_STOP_RADIUS_KM = 0.3  # 300m — within this = at a planned stop
_ROUTE_DEVIATION_KM = 2.0
_ROUTE_DEVIATION_SEVERE_KM = 5.0


def haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    """Calculate the Haversine distance between two points in kilometers."""
    R = 6371.0
    dlat = math.radians(lat2 - lat1)
    dlng = math.radians(lng2 - lng1)
    a = (
        math.sin(dlat / 2) ** 2
        + math.cos(math.radians(lat1)) * math.cos(math.radians(lat2)) * math.sin(dlng / 2) ** 2
    )
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return R * c


def _min_distance_to_planned_stops(lat: float, lng: float, stops: list[PlannedStop]) -> float:
    """Return minimum distance (km) from a point to any planned stop."""
    if not stops:
        return float("inf")
    return min(haversine_km(lat, lng, s.lat, s.lng) for s in stops)


def _is_near_planned_stop(lat: float, lng: float, stops: list[PlannedStop]) -> bool:
    """Check if position is within radius of any planned stop."""
    return _min_distance_to_planned_stops(lat, lng, stops) <= _PLANNED_STOP_RADIUS_KM


def detect_anomalies(
    positions: list[Position],
    planned_stops: list[PlannedStop],
) -> list[Anomaly]:
    """Detect anomalies in a sequence of vehicle positions.

    Args:
        positions: Chronologically ordered GPS positions.
        planned_stops: The planned route stops for distance comparison.

    Returns:
        List of detected anomalies.
    """
    anomalies: list[Anomaly] = []

    if not positions:
        return anomalies

    # 1. Excessive speed detection
    for pos in positions:
        if pos.speed >= _SPEED_VERY_HIGH:
            anomalies.append(
                Anomaly(
                    type=AnomalyType.EXCESSIVE_SPEED,
                    severity=Severity.HIGH,
                    lat=pos.lat,
                    lng=pos.lng,
                    timestamp=pos.timestamp,
                    details=f"Speed {pos.speed:.1f} km/h exceeds {_SPEED_VERY_HIGH} km/h",
                )
            )
        elif pos.speed >= _SPEED_HIGH:
            anomalies.append(
                Anomaly(
                    type=AnomalyType.EXCESSIVE_SPEED,
                    severity=Severity.MEDIUM,
                    lat=pos.lat,
                    lng=pos.lng,
                    timestamp=pos.timestamp,
                    details=f"Speed {pos.speed:.1f} km/h exceeds {_SPEED_HIGH} km/h",
                )
            )

    # 2. Unplanned stop detection
    # Group consecutive "stopped" positions and check duration
    stop_start_idx: int | None = None
    for i, pos in enumerate(positions):
        is_stopped = pos.speed < _STOPPED_SPEED_THRESHOLD

        if is_stopped and stop_start_idx is None:
            stop_start_idx = i
        elif not is_stopped and stop_start_idx is not None:
            _check_unplanned_stop(positions, stop_start_idx, i - 1, planned_stops, anomalies)
            stop_start_idx = None

    # Check if still stopped at end of data
    if stop_start_idx is not None:
        _check_unplanned_stop(positions, stop_start_idx, len(positions) - 1, planned_stops, anomalies)

    # 3. Route deviation detection
    if planned_stops:
        for pos in positions:
            min_dist = _min_distance_to_planned_stops(pos.lat, pos.lng, planned_stops)
            if min_dist >= _ROUTE_DEVIATION_SEVERE_KM:
                anomalies.append(
                    Anomaly(
                        type=AnomalyType.ROUTE_DEVIATION,
                        severity=Severity.HIGH,
                        lat=pos.lat,
                        lng=pos.lng,
                        timestamp=pos.timestamp,
                        details=f"Vehicle is {min_dist:.1f} km from nearest planned stop",
                    )
                )
            elif min_dist >= _ROUTE_DEVIATION_KM:
                anomalies.append(
                    Anomaly(
                        type=AnomalyType.ROUTE_DEVIATION,
                        severity=Severity.MEDIUM,
                        lat=pos.lat,
                        lng=pos.lng,
                        timestamp=pos.timestamp,
                        details=f"Vehicle is {min_dist:.1f} km from nearest planned stop",
                    )
                )

    return anomalies


def _check_unplanned_stop(
    positions: list[Position],
    start_idx: int,
    end_idx: int,
    planned_stops: list[PlannedStop],
    anomalies: list[Anomaly],
) -> None:
    """Check if a period of being stopped qualifies as an unplanned stop."""
    start_pos = positions[start_idx]
    end_pos = positions[end_idx]

    # Parse ISO timestamps for duration estimate
    # Simple heuristic: count positions * assumed interval
    num_positions = end_idx - start_idx + 1
    if num_positions < 2:
        return

    # Estimate duration from timestamps
    try:
        from datetime import datetime

        t_start = datetime.fromisoformat(start_pos.timestamp.replace("Z", "+00:00"))
        t_end = datetime.fromisoformat(end_pos.timestamp.replace("Z", "+00:00"))
        duration_minutes = (t_end - t_start).total_seconds() / 60.0
    except (ValueError, TypeError):
        # Fallback: assume 1 minute per position
        duration_minutes = float(num_positions)

    if duration_minutes < _UNPLANNED_STOP_MINUTES:
        return

    # Check if near a planned stop
    if _is_near_planned_stop(start_pos.lat, start_pos.lng, planned_stops):
        return

    severity = Severity.HIGH if duration_minutes > 90 else Severity.MEDIUM

    anomalies.append(
        Anomaly(
            type=AnomalyType.UNPLANNED_STOP,
            severity=severity,
            lat=start_pos.lat,
            lng=start_pos.lng,
            timestamp=start_pos.timestamp,
            details=f"Vehicle stopped for ~{duration_minutes:.0f} min outside planned stops",
        )
    )
