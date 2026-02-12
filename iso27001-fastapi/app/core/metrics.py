from prometheus_client import Counter, Histogram, generate_latest, CONTENT_TYPE_LATEST
from fastapi import Response

REQUEST_COUNT = Counter(
    "http_requests_total",
    "Total HTTP requests",
    ["method", "endpoint", "status_code"]
)

REQUEST_LATENCY = Histogram(
    "http_request_duration_seconds",
    "HTTP request latency",
    ["method", "endpoint"],
    buckets=(0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5),
)

# A.17: Separate 4xx (client errors) from 5xx (server errors) for accurate alerting.
# Alert on sustained 5xx rate; monitor 4xx for abuse/anomaly patterns without
# conflating them with availability budget consumption.
ERROR_CLASS_COUNT = Counter(
    "http_errors_total",
    "HTTP error responses split by error class (4xx vs 5xx)",
    ["error_class"],  # "4xx" or "5xx"
)

# A.17: SLO alert thresholds — defined once, referenced everywhere.
SLO_P95_LATENCY_MS: float = 200.0   # alert if P95 exceeds this
SLO_P99_LATENCY_MS: float = 500.0   # alert if P99 exceeds this
SLO_ERROR_RATE_PCT: float = 0.1     # alert if 5xx error rate (%) exceeds this (= 1 - 99.9% SLA)


def record_error_class(status_code: int) -> None:
    """Increment the appropriate error-class counter for 4xx and 5xx responses."""
    if 400 <= status_code < 500:
        ERROR_CLASS_COUNT.labels(error_class="4xx").inc()
    elif status_code >= 500:
        ERROR_CLASS_COUNT.labels(error_class="5xx").inc()


def get_metrics() -> Response:
    return Response(generate_latest(), media_type=CONTENT_TYPE_LATEST)