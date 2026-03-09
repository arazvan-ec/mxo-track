"""Driver-zone affinity scoring model.

Simple weighted scoring approach for MVP — no ML model needed.
Computes affinity based on delivery count, exception rate, and average service time.
"""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class AffinityInput:
    """Raw aggregated stats for a (driver, zone) pair."""

    driver_id: int
    zone_id: int
    zone_name: str
    delivery_count: int
    exception_count: int
    avg_service_time_seconds: float


@dataclass(frozen=True)
class AffinityScore:
    """Computed affinity score for a (driver, zone) pair."""

    driver_id: int
    zone_id: int
    zone_name: str
    score: float
    deliveries: int


# Weights for the affinity formula
_W_DELIVERIES = 0.50
_W_EXCEPTION_RATE = 0.30
_W_SERVICE_TIME = 0.20

# Normalization caps
_MAX_DELIVERIES = 200  # deliveries at or above this get score 1.0
_MAX_SERVICE_TIME_SECONDS = 600.0  # 10 min — above this gets score 0.0


def compute_affinity(inputs: list[AffinityInput]) -> list[AffinityScore]:
    """Compute weighted affinity scores for a list of (driver, zone) pairs.

    Score formula (0..1):
        score = W_del * norm_deliveries
              + W_exc * (1 - exception_rate)
              + W_svc * (1 - norm_service_time)

    Higher score = better affinity (more deliveries, fewer exceptions, faster service).
    """
    results: list[AffinityScore] = []

    for inp in inputs:
        if inp.delivery_count == 0:
            results.append(
                AffinityScore(
                    driver_id=inp.driver_id,
                    zone_id=inp.zone_id,
                    zone_name=inp.zone_name,
                    score=0.0,
                    deliveries=0,
                )
            )
            continue

        # Normalize delivery count (0..1)
        norm_deliveries = min(inp.delivery_count / _MAX_DELIVERIES, 1.0)

        # Exception rate (0..1), lower is better
        exception_rate = inp.exception_count / inp.delivery_count if inp.delivery_count > 0 else 0.0
        exception_rate = min(exception_rate, 1.0)

        # Normalize service time (0..1), lower is better
        norm_svc = min(inp.avg_service_time_seconds / _MAX_SERVICE_TIME_SECONDS, 1.0)

        score = (
            _W_DELIVERIES * norm_deliveries
            + _W_EXCEPTION_RATE * (1.0 - exception_rate)
            + _W_SERVICE_TIME * (1.0 - norm_svc)
        )

        # Clamp to [0, 1]
        score = max(0.0, min(1.0, score))

        results.append(
            AffinityScore(
                driver_id=inp.driver_id,
                zone_id=inp.zone_id,
                zone_name=inp.zone_name,
                score=round(score, 4),
                deliveries=inp.delivery_count,
            )
        )

    return results
