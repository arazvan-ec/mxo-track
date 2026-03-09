"""Delivery risk prediction model using LightGBM."""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import joblib
import lightgbm as lgb
import numpy as np
from sklearn.metrics import accuracy_score, precision_score, recall_score, roc_auc_score
from sklearn.model_selection import train_test_split

logger = logging.getLogger(__name__)

MODEL_DIR = Path(__file__).resolve().parent.parent.parent / "models"
MODEL_PATH = MODEL_DIR / "delivery_risk.joblib"
META_PATH = MODEL_DIR / "delivery_risk_meta.json"

FEATURE_NAMES = [
    "hour_of_day",
    "day_of_week",
    "has_phone",
    "parcel_count",
    "weight_kg",
    "stop_sequence",
]


@dataclass
class TrainMetrics:
    """Metrics returned after training."""

    auc_roc: float
    accuracy: float
    precision: float
    recall: float
    samples_total: int
    samples_positive: int
    model_version: str


class DeliveryRiskModel:
    """LightGBM binary classifier for delivery failure prediction."""

    def __init__(self) -> None:
        self.model: lgb.LGBMClassifier | None = None
        self.model_version: str = "fallback"

    def train(self, features: np.ndarray, labels: np.ndarray) -> TrainMetrics:
        """Train the model on historical route stop data.

        Args:
            features: 2D array with columns matching FEATURE_NAMES.
            labels: 1D binary array (1 = EXCEPTION, 0 = DELIVERED).

        Returns:
            TrainMetrics with evaluation results.
        """
        if len(features) < 10:
            raise ValueError(f"Need at least 10 samples to train, got {len(features)}")

        X_train, X_test, y_train, y_test = train_test_split(
            features, labels, test_size=0.2, random_state=42, stratify=labels
        )

        self.model = lgb.LGBMClassifier(
            n_estimators=100,
            max_depth=5,
            learning_rate=0.1,
            num_leaves=31,
            min_child_samples=5,
            random_state=42,
            verbose=-1,
        )
        self.model.fit(X_train, y_train)

        y_pred = self.model.predict(X_test)
        y_prob = self.model.predict_proba(X_test)[:, 1]

        auc = float(roc_auc_score(y_test, y_prob))
        acc = float(accuracy_score(y_test, y_pred))
        prec = float(precision_score(y_test, y_pred, zero_division=0))
        rec = float(recall_score(y_test, y_pred, zero_division=0))

        import datetime

        self.model_version = datetime.datetime.now(datetime.timezone.utc).strftime(
            "%Y%m%d_%H%M%S"
        )

        self.save()

        metrics = TrainMetrics(
            auc_roc=round(auc, 4),
            accuracy=round(acc, 4),
            precision=round(prec, 4),
            recall=round(rec, 4),
            samples_total=len(features),
            samples_positive=int(labels.sum()),
            model_version=self.model_version,
        )
        logger.info("Model trained: %s", metrics)
        return metrics

    def predict(self, features: dict[str, Any]) -> float:
        """Predict delivery failure probability for a single stop.

        Args:
            features: Dict with keys matching FEATURE_NAMES.

        Returns:
            Probability of failure (0.0 to 1.0).
        """
        if self.model is None:
            raise RuntimeError("Model not loaded")

        X = np.array([[features[f] for f in FEATURE_NAMES]])
        proba = self.model.predict_proba(X)[:, 1]
        return float(proba[0])

    def save(self) -> None:
        """Persist model and metadata to disk."""
        MODEL_DIR.mkdir(parents=True, exist_ok=True)
        joblib.dump(self.model, MODEL_PATH)
        META_PATH.write_text(json.dumps({"model_version": self.model_version}))
        logger.info("Model saved to %s (version: %s)", MODEL_PATH, self.model_version)

    def load(self) -> bool:
        """Load model from disk. Returns True if successful."""
        if MODEL_PATH.exists() and META_PATH.exists():
            self.model = joblib.load(MODEL_PATH)
            meta = json.loads(META_PATH.read_text())
            self.model_version = meta.get("model_version", "unknown")
            logger.info("Model loaded (version: %s)", self.model_version)
            return True
        logger.warning("No saved model found at %s", MODEL_PATH)
        return False
