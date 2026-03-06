"""FastAPI ML sidecar service for mxo-track logistics predictions."""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.routers import service_time

app = FastAPI(
    title="mxo-track ML Service",
    description="Machine learning sidecar for delivery service time prediction and route optimization.",
    version="0.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(service_time.router)


@app.get("/health")
def health() -> dict[str, str]:
    """Health check endpoint."""
    return {"status": "ok", "service": "ml-sidecar"}
