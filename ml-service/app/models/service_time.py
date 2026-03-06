"""LightGBM-based service time prediction model."""

import os
import pickle
from datetime import datetime
from pathlib import Path

import lightgbm as lgb
import numpy as np
from numpy.typing import NDArray


MODEL_DIR = Path(os.environ.get("MODEL_DIR", "/app/models"))
MODEL_PATH = MODEL_DIR / "service_time_lgbm.pkl"
META_PATH = MODEL_DIR / "service_time_meta.pkl"


class ServiceTimeModel:
    """LightGBM regressor for predicting delivery service time in seconds."""

    FEATURE_NAMES: list[str] = [
        "hour_of_day",
        "day_of_week",
        "stop_sequence",
        "parcel_count",
        "weight_kg",
    ]

    def __init__(self) -> None:
        self.model: lgb.LGBMRegressor | None = None
        self.version: str = "untrained"

    def train(self, X: NDArray[np.float64], y: NDArray[np.float64]) -> dict[str, float]:
        """Train the LightGBM regressor and return metrics.

        Args:
            X: Feature matrix with columns matching FEATURE_NAMES.
            y: Target array of service times in seconds.

        Returns:
            Dictionary with MAE and R2 metrics.
        """
        self.model = lgb.LGBMRegressor(
            n_estimators=100,
            max_depth=6,
            learning_rate=0.1,
            num_leaves=31,
            min_child_samples=5,
            verbose=-1,
        )
        self.model.fit(X, y)
        self.version = datetime.utcnow().strftime("%Y%m%d_%H%M%S")

        predictions = self.model.predict(X)
        mae = float(np.mean(np.abs(predictions - y)))
        ss_res = float(np.sum((y - predictions) ** 2))
        ss_tot = float(np.sum((y - np.mean(y)) ** 2))
        r2 = 1.0 - (ss_res / ss_tot) if ss_tot > 0 else 0.0

        return {"mae": round(mae, 2), "r2": round(r2, 4)}

    def predict(self, features: NDArray[np.float64]) -> NDArray[np.float64]:
        """Predict service time in seconds for the given features.

        Args:
            features: 2D array with columns matching FEATURE_NAMES.

        Returns:
            Array of predicted service times in seconds.

        Raises:
            RuntimeError: If model has not been trained or loaded.
        """
        if self.model is None:
            raise RuntimeError("Model not loaded. Train or load a model first.")
        predictions: NDArray[np.float64] = self.model.predict(features)
        return np.maximum(predictions, 60.0)  # minimum 60 seconds

    def save(self, path: Path | None = None) -> None:
        """Persist model and metadata to disk.

        Args:
            path: Directory to save model files. Defaults to MODEL_DIR.
        """
        save_dir = path or MODEL_DIR
        save_dir.mkdir(parents=True, exist_ok=True)

        with open(save_dir / "service_time_lgbm.pkl", "wb") as f:
            pickle.dump(self.model, f)

        with open(save_dir / "service_time_meta.pkl", "wb") as f:
            pickle.dump({"version": self.version, "feature_names": self.FEATURE_NAMES}, f)

    def load(self, path: Path | None = None) -> bool:
        """Load model and metadata from disk.

        Args:
            path: Directory to load model files from. Defaults to MODEL_DIR.

        Returns:
            True if model loaded successfully, False otherwise.
        """
        load_dir = path or MODEL_DIR
        model_file = load_dir / "service_time_lgbm.pkl"
        meta_file = load_dir / "service_time_meta.pkl"

        if not model_file.exists():
            return False

        with open(model_file, "rb") as f:
            self.model = pickle.load(f)  # noqa: S301

        if meta_file.exists():
            with open(meta_file, "rb") as f:
                meta = pickle.load(f)  # noqa: S301
                self.version = meta.get("version", "unknown")

        return True
