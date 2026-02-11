# ISO 27001 Compliant REST API Reference Implementation

Three production-grade reference implementations of a REST API demonstrating
**ISO 27001** security controls with Domain-Driven Design, structured telemetry,
and defensive security patterns in **PHP (Symfony 7.2 / Laravel 12)** and
**Python (FastAPI 0.111+)**.

## Project Structure

```
iso27001-fastapi/    FastAPI (Python 3.11)
iso27001-symfony/    Symfony 7.2 (PHP 8.2)
iso27001-laravel/    Laravel 12 (PHP 8.2)
```

---

## What Is Implemented

### ISO 27001 Security Controls

| Control | Feature | FastAPI | Symfony | Laravel |
|---------|---------|:-------:|:-------:|:-------:|
| A.9 | JWT authentication (short-lived access + refresh tokens) | ✓ | ✓ | ✓ |
| A.9 | RBAC: `admin > manager > analyst > viewer` | ✓ | ✓ | ✓ |
| A.9 | Rate limiting — auth (10/min), write (30/min), global (100/min) | ✓ | ✓ | ✓ |
| A.9 | Brute-force protection on login endpoint | ✓ | ✓ | ✓ |
| A.10 | AES-256-GCM field-level encryption (PII at rest) | ✓ | ✓ | ✓ |
| A.10 | bcrypt / Argon2 password hashing | ✓ | ✓ | ✓ |
| A.10 | HSTS, X-Frame-Options, CSP, X-Content-Type-Options headers | ✓ | ✓ | ✓ |
| A.12 | Structured JSON logging — automatic sensitive-field redaction | ✓ | ✓ | ✓ |
| A.12 | Correlation ID (`X-Request-ID`) through logs, events, responses | ✓ | ✓ | ✓ |
| A.12 | Identical top-level log shape across all stacks (CloudWatch-queryable) | ✓ | ✓ | ✓ |
| A.12 | Immutable append-only `audit_logs` table | ✓ | ✓ | ✓ |
| A.12 | Event-driven audit trail (domain events → audit listeners) | ✓ | ✓ | ✓ |
| A.14 | Input validation (Pydantic v2 / Symfony Validator / FormRequest) | ✓ | ✓ | ✓ |
| A.14 | Static analysis — mypy strict (Python), PHPStan level 8 (PHP) | ✓ | ✓ | ✓ |
| A.14 | No stack traces or internals exposed to clients | ✓ | ✓ | ✓ |
| A.17 | Health checks — liveness, readiness, detailed (admin) | ✓ | ✓ | ✓ |
| A.17 | Error Budget Tracker (99.9% SLA, 5xx budget deduction) | ✓ | ✓ | ✓ |
| A.17 | Quality Score Calculator (risk-weighted: security 40%, integrity 20%, …) | ✓ | ✓ | ✓ |

### Observability

| Feature | FastAPI | Symfony | Laravel |
|---------|:-------:|:-------:|:-------:|
| Prometheus metrics (`/metrics`) | ✓ | — | — |
| Request count + latency histograms | ✓ | ✓ | ✓ |
| AWS CloudWatch custom metrics emitter | ✓ | ✓ | ✓ |
| AWS X-Ray trace header propagation | ✓ | — | — |
| Normalised top-level log shape (all stacks) | ✓ | ✓ | ✓ |

### Architecture

| Feature | FastAPI | Symfony | Laravel |
|---------|:-------:|:-------:|:-------:|
| Domain-Driven Design (4-layer) | ✓ | ✓ | ✓ |
| Repository pattern + interface segregation | ✓ | ✓ | ✓ |
| Domain events dispatched from service layer | ✓ | ✓ | ✓ |
| SoftDeletes (audit trail preservation) | ✓ | ✓ | ✓ |
| UUID primary keys | ✓ | ✓ | ✓ |
| Docker + docker-compose | ✓ | ✓ | ✓ |
| CI (GitHub Actions) | ✓ | ✓ | ✓ |

---

## What Is Not Yet Implemented

Architecture-ready (patterns exist, wiring points are marked) but not
production-complete:

| Feature | Notes |
|---------|-------|
| **Prometheus metrics — Symfony / Laravel** | FastAPI exposes `/metrics`. PHP stacks have `MetricsCollector` stub but no exporter endpoint. |
| **X-Ray SDK integration — Symfony / Laravel** | FastAPI reads and propagates the header. Full `aws-xray-sdk` segment tracing not wired in PHP. |
| **CloudWatch metrics — requires live credentials** | All three `CloudWatchEmitter` classes are no-ops without `boto3` / `aws/aws-sdk-php` installed and AWS credentials present. |
| **Error Budget / Quality Score — in-process only (PHP)** | PHP-FPM workers are isolated processes. The `ErrorBudgetTracker` counters reset per request. Back with Redis or APCu for cross-process accuracy. |
| **P95/P99 per-endpoint latency alerts** | Prometheus histogram is recorded; no SLO alert rule or dashboard is defined. |
| **4xx vs 5xx error rate separation** | Error budget counts only 5xx. Separate rules for 4xx anomalies are not defined. |
| **External dependency health (circuit breakers)** | Readiness check does not include downstream API state. |
| **Fraud / anomaly detection** | Auth failures and rate-limit hits are counted; no rule engine is wired. |
| **Business-level telemetry** | Infrastructure metrics only. No domain KPIs (conversion, revenue) are emitted. |

---

## Getting Started

A `Makefile` unifies commands for all three stacks.

### Prerequisites

- Docker & Docker Compose
- PHP 8.2+ & Composer
- Python 3.11+ & pip

### Commands

```bash
make up                 # Start all Docker containers
make down               # Stop all containers
make logs               # View structured JSON logs

make setup-python       # pip install -e .[dev]  (includes mypy + pytest)
make test-python        # pytest

make setup-php          # composer install + generate JWT RSA keys
make migration-php      # Doctrine migrations
make test-php           # phpunit

make setup-laravel      # composer install + artisan key:generate
make migration-laravel  # Eloquent migrations
make test-laravel       # php artisan test

make db-reset           # Drop + recreate + migrate all databases

make check-security     # composer audit (both PHP) + pip-audit (Python)
make check-static       # PHPStan level 8 (both PHP) + mypy strict (Python)
```

---

## Log Shape (all stacks)

Every log line from every stack produces the same top-level JSON structure,
enabling a single CloudWatch Logs Insights query across all services:

```json
{
  "timestamp":   "2025-02-10T14:30:00.123Z",
  "level":       "INFO",
  "message":     "request.completed",
  "service":     "iso27001-api",
  "environment": "production",
  "request_id":  "550e8400-e29b-41d4-a716-446655440000",
  "context": {
    "method": "POST",
    "path": "/api/v1/users",
    "status_code": 201,
    "duration_ms": 45.2
  }
}
```

```
# CloudWatch Logs Insights — works identically for all three log groups
fields timestamp, level, message, service, request_id
| filter level = "ERROR"
| sort timestamp desc
| limit 50
```

---

## ISO 27001 Annex A Reference

| Annex | Title | Controls demonstrated |
|-------|-------|-----------------------|
| A.9 | Access Control | JWT, RBAC, rate limiting, brute-force protection |
| A.10 | Cryptography | AES-256-GCM field encryption, bcrypt/Argon2 password hashing, transport security headers |
| A.12 | Operations Security | Structured logging, audit trail, correlation IDs, log normalisation |
| A.14 | Secure Development | Input validation, static analysis, secure error handling |
| A.17 | Business Continuity | Health checks, error budget, rate limiting, availability SLA |
