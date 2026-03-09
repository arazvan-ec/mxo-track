"""Demand forecast router — train and predict daily delivery demand."""

from __future__ import annotations

import logging

import pandas as pd
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy import text

from app.db import get_engine
from app.models.demand_forecast import DemandForecastModel

logger = logging.getLogger(__name__)
router = APIRouter()

# Singleton model instance
_model = DemandForecastModel()


# ---------------------------------------------------------------------------
# Pydantic schemas
# ---------------------------------------------------------------------------

class TrainResponse(BaseModel):
    status: str
    rows_used: int
    date_range_start: str
    date_range_end: str
    mae: float | None = None
    rmse: float | None = None


class PredictRequest(BaseModel):
    days: int = Field(default=7, ge=1, le=90, description="Days ahead to forecast")


class PredictionItem(BaseModel):
    date: str
    predicted: float
    lower: float
    upper: float


class PredictResponse(BaseModel):
    predictions: list[PredictionItem]


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post("/train/demand-forecast", response_model=TrainResponse)
async def train_demand_forecast() -> TrainResponse:
    """Train Prophet model on historical daily delivery counts.

    Queries route_stop table for delivered stops grouped by date.
    """
    engine = get_engine()

    query = text("""
        SELECT
            DATE(delivered_at) AS ds,
            COUNT(*)::int AS y
        FROM route_stop
        WHERE status = 'DELIVERED'
          AND delivered_at IS NOT NULL
        GROUP BY DATE(delivered_at)
        ORDER BY ds
    """)

    try:
        with engine.connect() as conn:
            result = conn.execute(query)
            rows = result.fetchall()
    except Exception as exc:
        logger.exception("Failed to query delivery data")
        raise HTTPException(status_code=500, detail=f"Database query failed: {exc}") from exc

    if len(rows) < 2:
        raise HTTPException(
            status_code=422,
            detail=f"Insufficient data for training: {len(rows)} days found, need at least 2",
        )

    df = pd.DataFrame(rows, columns=["ds", "y"])

    try:
        metrics = _model.train(df)
        _model.save()
    except Exception as exc:
        logger.exception("Model training failed")
        raise HTTPException(status_code=500, detail=f"Training failed: {exc}") from exc

    return TrainResponse(
        status="trained",
        rows_used=metrics.rows_used,
        date_range_start=metrics.date_range_start,
        date_range_end=metrics.date_range_end,
        mae=metrics.mae,
        rmse=metrics.rmse,
    )


@router.post("/predict/demand-forecast", response_model=PredictResponse)
async def predict_demand_forecast(request: PredictRequest) -> PredictResponse:
    """Predict future daily delivery demand.

    If no trained model exists, attempts to load from disk.
    Returns empty predictions if no model is available.
    """
    if not _model.is_trained:
        _model.load()

    if not _model.is_trained:
        return PredictResponse(predictions=[])

    try:
        predictions = _model.predict(days=request.days)
    except Exception as exc:
        logger.exception("Prediction failed")
        raise HTTPException(status_code=500, detail=f"Prediction failed: {exc}") from exc

    return PredictResponse(
        predictions=[
            PredictionItem(
                date=p.date,
                predicted=p.predicted,
                lower=p.lower,
                upper=p.upper,
            )
            for p in predictions
        ]
    )
