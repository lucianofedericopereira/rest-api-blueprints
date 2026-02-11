"""Integration tests for health check endpoints."""
import pytest
from fastapi.testclient import TestClient
from unittest.mock import MagicMock

from app.main import app
from app.core.database import get_db


def _mock_db_ok():
    """Dependency override: DB responds to SELECT 1."""
    session = MagicMock()
    session.execute.return_value = None
    yield session


def _mock_db_error():
    """Dependency override: DB raises an exception."""
    session = MagicMock()
    session.execute.side_effect = Exception("connection refused")
    yield session


@pytest.fixture
def client():
    with TestClient(app) as c:
        yield c


@pytest.fixture
def client_db_ok():
    app.dependency_overrides[get_db] = _mock_db_ok
    with TestClient(app) as c:
        yield c
    app.dependency_overrides.clear()


@pytest.fixture
def client_db_error():
    app.dependency_overrides[get_db] = _mock_db_error
    with TestClient(app) as c:
        yield c
    app.dependency_overrides.clear()


class TestHealthEndpoints:
    def test_liveness(self, client):
        response = client.get("/health")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "ok"
        assert "timestamp" in data

    def test_liveness_sets_correlation_id(self, client):
        response = client.get("/health")
        assert "x-request-id" in response.headers

    def test_liveness_accepts_client_correlation_id(self, client):
        response = client.get("/health", headers={"X-Request-ID": "my-trace-123"})
        assert response.headers["x-request-id"] == "my-trace-123"

    def test_readiness_db_ok(self, client_db_ok):
        response = client_db_ok.get("/health/ready")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "ok"
        assert data["checks"]["database"]["status"] == "ok"

    def test_readiness_db_error_returns_503(self, client_db_error):
        response = client_db_error.get("/health/ready")
        assert response.status_code == 503
        data = response.json()
        assert data["status"] == "degraded"
        assert data["checks"]["database"]["status"] == "error"
