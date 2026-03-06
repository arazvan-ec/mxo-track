"""Service time prediction: training and inference endpoints."""

from typing import Any

import numpy as np
from fastapi import APIRouter, Depends
from pydantic import BaseModel, Field
from sqlalchemy import text
from sqlalchemy.orm import Session

from app.db import get_db
from app.models.service_time import ServiceTimeModel

router = APIRouter()

# Singleton model instance
_model = ServiceTimeModel()


class PredictRequest(BaseModel):
    """Input features for service time prediction."""

    hour_of_day: int = Field(ge=0, le=23, description="Hour of delivery (0-23)")
    day_of_week: int = Field(ge=0, le=6, description="Day of week (0=Monday, 6=Sunday)")
    stop_sequence: int = Field(ge=1, description="Stop sequence number within the route")
    parcel_count: int = Field(ge=1, default=1, description="Number of parcels in the shipment")
    weight_kg: float = Field(ge=0.0, default=1.0, description="Total weight in kilograms")


class PredictResponse(BaseModel):
    """Prediction result with model version."""

    predicted_seconds: int
    model_version: str


class TrainResponse(BaseModel):
    """Training result with metrics."""

    status: str
    samples: int
    metrics: dict[str, float]
    model_version: str


TRAINING_QUERY = text("""
    SELECT
        EXTRACT(HOUR FROM rs.delivered_at) AS hour_of_day,
        EXTRACT(DOW FROM rs.delivered_at)  AS day_of_week,
        rs.sequence                         AS stop_sequence,
        1                                   AS parcel_count,
        1.0                                 AS weight_kg,
        rs.delivered_at                     AS delivered_at,
        rs.route_id                         AS route_id
    FROM route_stop rs
    WHERE rs.delivered_at IS NOT NULL
      AND rs.is_origin = false
    ORDER BY rs.route_id, rs.sequence
""")


def _build_training_data(db: Session) -> tuple[np.ndarray, np.ndarray]:
    """Query the database and compute service time labels.

    Service time for a stop is approximated as the difference between
    consecutive delivered_at timestamps within the same route. The first
    delivered stop in a route is excluded (no prior reference).

    Returns:
        Tuple of (features, labels) numpy arrays.
    """
    rows = db.execute(TRAINING_QUERY).fetchall()

    features: list[list[float]] = []
    labels: list[float] = []

    prev_delivered_at = None
    prev_route_id = None

    for row in rows:
        hour_of_day = float(row.hour_of_day)
        day_of_week = float(row.day_of_week)
        stop_sequence = float(row.stop_sequence)
        parcel_count = float(row.parcel_count)
        weight_kg = float(row.weight_kg)
        delivered_at = row.delivered_at
        route_id = row.route_id

        if prev_route_id == route_id and prev_delivered_at is not None:
            diff_seconds = (delivered_at - prev_delivered_at).total_seconds()
            # Only use reasonable service times (30s to 30min)
            if 30 <= diff_seconds <= 1800:
                features.append([
                    hour_of_day,
                    day_of_week,
                    stop_sequence,
                    parcel_count,
                    weight_kg,
                ])
                labels.append(diff_seconds)

        prev_delivered_at = delivered_at
        prev_route_id = route_id

    return np.array(features, dtype=np.float64), np.array(labels, dtype=np.float64)


@router.post("/train/service-time", response_model=TrainResponse)
def train_service_time(db: Session = Depends(get_db)) -> dict[str, Any]:
    """Train the service time prediction model from historical delivery data.

    Reads completed route stops from the database, computes service time
    labels from consecutive delivered_at timestamps, and trains a LightGBM
    regressor.
    """
    global _model

    X, y = _build_training_data(db)

    if len(X) < 10:
        return {
            "status": "insufficient_data",
            "samples": len(X),
            "metrics": {"mae": 0.0, "r2": 0.0},
            "model_version": "none",
        }

    _model = ServiceTimeModel()
    metrics = _model.train(X, y)
    _model.save()

    return {
        "status": "trained",
        "samples": len(X),
        "metrics": metrics,
        "model_version": _model.version,
    }


@router.post("/predict/service-time", response_model=PredictResponse)
def predict_service_time(request: PredictRequest) -> dict[str, Any]:
    """Predict service time in seconds for a single delivery stop.

    If no trained model is available, returns a fallback estimate of 300 seconds.
    """
    global _model

    # Try loading model from disk if not in memory
    if _model.model is None:
        _model.load()

    # Fallback when no model is available
    if _model.model is None:
        return {
            "predicted_seconds": 300,
            "model_version": "fallback",
        }

    features = np.array([[
        request.hour_of_day,
        request.day_of_week,
        request.stop_sequence,
        request.parcel_count,
        request.weight_kg,
    ]], dtype=np.float64)

    prediction = _model.predict(features)
    predicted_seconds = int(round(prediction[0]))

    return {
        "predicted_seconds": predicted_seconds,
        "model_version": _model.version,
    }
