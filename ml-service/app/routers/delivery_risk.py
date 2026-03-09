"""FastAPI router for delivery risk prediction endpoints."""

from __future__ import annotations

import logging
from typing import Literal

import numpy as np
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from app.db import get_db_connection
from app.models.delivery_risk import FEATURE_NAMES, DeliveryRiskModel

logger = logging.getLogger(__name__)

router = APIRouter(tags=["delivery-risk"])

# Singleton model instance
_model = DeliveryRiskModel()
_model.load()  # Try loading on startup


# ── Pydantic schemas ──────────────────────────────────────────────────────────


class TrainResponse(BaseModel):
    """Response from the training endpoint."""

    auc_roc: float
    accuracy: float
    precision: float
    recall: float
    samples_total: int
    samples_positive: int
    model_version: str


class PredictRequest(BaseModel):
    """Input features for a single delivery risk prediction."""

    hour_of_day: int = Field(ge=0, le=23, description="Hour of day (0-23)")
    day_of_week: int = Field(ge=0, le=6, description="Day of week (0=Monday, 6=Sunday)")
    has_phone: bool = Field(description="Whether recipient has a phone number")
    parcel_count: int = Field(ge=1, description="Number of parcels in this stop")
    weight_kg: float = Field(ge=0, description="Weight in kilograms")
    stop_sequence: int = Field(ge=0, description="Stop order in the route")


class PredictResponse(BaseModel):
    """Delivery risk prediction result."""

    risk_score: float = Field(ge=0.0, le=1.0)
    risk_level: Literal["LOW", "MEDIUM", "HIGH"]
    model_version: str


# ── Helpers ───────────────────────────────────────────────────────────────────


def _risk_level(score: float) -> Literal["LOW", "MEDIUM", "HIGH"]:
    """Classify risk score into a level."""
    if score < 0.2:
        return "LOW"
    if score <= 0.5:
        return "MEDIUM"
    return "HIGH"


_TRAINING_QUERY = """
    SELECT
        EXTRACT(HOUR FROM r.start_at)::int       AS hour_of_day,
        EXTRACT(DOW FROM r.start_at)::int         AS day_of_week,
        (rs.recipient_phone IS NOT NULL)           AS has_phone,
        1                                          AS parcel_count,
        0.0                                        AS weight_kg,
        rs.sequence                                AS stop_sequence,
        rs.status                                  AS status
    FROM route_stop rs
    JOIN route_plan r ON rs.route_id = r.id
    WHERE rs.status IN ('DELIVERED', 'EXCEPTION')
      AND rs.is_origin = false
      AND r.start_at IS NOT NULL
"""


# ── Endpoints ─────────────────────────────────────────────────────────────────


@router.post("/train/delivery-risk", response_model=TrainResponse)
async def train_delivery_risk() -> TrainResponse:
    """Train the delivery risk model on historical route stop data."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(_TRAINING_QUERY)
            rows = cur.fetchall()
    finally:
        conn.close()

    if len(rows) < 10:
        raise HTTPException(
            status_code=400,
            detail=f"Not enough training data: {len(rows)} rows (need >= 10)",
        )

    features_list: list[list[float]] = []
    labels_list: list[int] = []

    for row in rows:
        hour, dow, has_phone, parcels, weight, seq, status = row
        features_list.append([
            float(hour or 0),
            float(dow or 0),
            float(bool(has_phone)),
            float(parcels),
            float(weight),
            float(seq),
        ])
        labels_list.append(1 if status == "EXCEPTION" else 0)

    features = np.array(features_list)
    labels = np.array(labels_list)

    # Need both classes present
    if len(set(labels_list)) < 2:
        raise HTTPException(
            status_code=400,
            detail="Training data must contain both DELIVERED and EXCEPTION samples",
        )

    metrics = _model.train(features, labels)

    return TrainResponse(
        auc_roc=metrics.auc_roc,
        accuracy=metrics.accuracy,
        precision=metrics.precision,
        recall=metrics.recall,
        samples_total=metrics.samples_total,
        samples_positive=metrics.samples_positive,
        model_version=metrics.model_version,
    )


@router.post("/predict/delivery-risk", response_model=PredictResponse)
async def predict_delivery_risk(request: PredictRequest) -> PredictResponse:
    """Predict delivery failure risk for a single stop."""
    if _model.model is None:
        # Fallback: no model available
        return PredictResponse(
            risk_score=0.0,
            risk_level="LOW",
            model_version="fallback",
        )

    features = {
        "hour_of_day": request.hour_of_day,
        "day_of_week": request.day_of_week,
        "has_phone": int(request.has_phone),
        "parcel_count": request.parcel_count,
        "weight_kg": request.weight_kg,
        "stop_sequence": request.stop_sequence,
    }

    try:
        score = _model.predict(features)
    except Exception as exc:
        logger.exception("Prediction failed")
        raise HTTPException(status_code=500, detail=str(exc)) from exc

    return PredictResponse(
        risk_score=round(score, 4),
        risk_level=_risk_level(score),
        model_version=_model.model_version,
    )
