"""Demand forecast model using Prophet for time series prediction."""

from __future__ import annotations

import logging
import pickle
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pandas as pd
from prophet import Prophet

logger = logging.getLogger(__name__)

MODEL_DIR = Path("/tmp/ml-models")
MODEL_PATH = MODEL_DIR / "demand_forecast.pkl"


@dataclass
class DemandPrediction:
    date: str
    predicted: float
    lower: float
    upper: float


@dataclass
class TrainingMetrics:
    rows_used: int
    date_range_start: str
    date_range_end: str
    mae: float | None = None
    rmse: float | None = None


class DemandForecastModel:
    """Prophet-based demand forecast for daily delivery counts."""

    def __init__(self) -> None:
        self._model: Prophet | None = None

    def train(self, daily_counts: pd.DataFrame) -> TrainingMetrics:
        """Train Prophet model on daily delivery counts.

        Args:
            daily_counts: DataFrame with columns 'ds' (date) and 'y' (count).

        Returns:
            Training metrics.
        """
        if daily_counts.empty or len(daily_counts) < 2:
            raise ValueError("Need at least 2 data points to train the model")

        df = daily_counts[["ds", "y"]].copy()
        df["ds"] = pd.to_datetime(df["ds"])
        df = df.sort_values("ds").reset_index(drop=True)

        model = Prophet(
            daily_seasonality=True,
            weekly_seasonality=True,
            yearly_seasonality=False,
            changepoint_prior_scale=0.05,
        )
        model.fit(df)
        self._model = model

        # Compute in-sample metrics
        forecast = model.predict(df[["ds"]])
        residuals = df["y"].values - forecast["yhat"].values
        mae = float(abs(residuals).mean())
        rmse = float((residuals**2).mean() ** 0.5)

        return TrainingMetrics(
            rows_used=len(df),
            date_range_start=str(df["ds"].min().date()),
            date_range_end=str(df["ds"].max().date()),
            mae=round(mae, 2),
            rmse=round(rmse, 2),
        )

    def predict(self, days: int = 7) -> list[DemandPrediction]:
        """Predict future daily demand.

        Args:
            days: Number of days ahead to forecast.

        Returns:
            List of predictions with confidence intervals.
        """
        if self._model is None:
            return []

        future = self._model.make_future_dataframe(periods=days)
        # Only keep the future dates (not historical)
        future = future.tail(days)
        forecast = self._model.predict(future)

        predictions: list[DemandPrediction] = []
        for _, row in forecast.iterrows():
            predictions.append(
                DemandPrediction(
                    date=str(row["ds"].date()),
                    predicted=round(max(0.0, float(row["yhat"])), 1),
                    lower=round(max(0.0, float(row["yhat_lower"])), 1),
                    upper=round(max(0.0, float(row["yhat_upper"])), 1),
                )
            )

        return predictions

    def save(self, path: Path | None = None) -> None:
        """Persist model to disk."""
        save_path = path or MODEL_PATH
        save_path.parent.mkdir(parents=True, exist_ok=True)
        with open(save_path, "wb") as f:
            pickle.dump(self._model, f)
        logger.info("Demand forecast model saved to %s", save_path)

    def load(self, path: Path | None = None) -> bool:
        """Load model from disk.

        Returns:
            True if model was loaded successfully, False otherwise.
        """
        load_path = path or MODEL_PATH
        if not load_path.exists():
            logger.warning("No saved model found at %s", load_path)
            return False

        try:
            with open(load_path, "rb") as f:
                self._model = pickle.load(f)
            logger.info("Demand forecast model loaded from %s", load_path)
            return True
        except Exception:
            logger.exception("Failed to load model from %s", load_path)
            return False

    @property
    def is_trained(self) -> bool:
        return self._model is not None
