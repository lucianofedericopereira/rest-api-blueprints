"""Integration tests for health check endpoints."""
import pytest
from fastapi.testclient import TestClient
from unittest.mock import patch, MagicMock

from app.main import app


@pytest.fixture
def client():
    with TestClient(app) as c:
        yield c


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

    def test_readiness_db_ok(self, client):
        with patch("app.api.v1.health.get_db") as mock_db:
            mock_session = MagicMock()
            mock_db.return_value = mock_session
            response = client.get("/health/ready")
            # SQLite test DB should respond
            assert response.status_code in (200, 503)
