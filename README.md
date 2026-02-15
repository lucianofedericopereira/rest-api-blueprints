# ISO 27001 Compliant REST API Reference Implementation

Four reference implementations of a REST API demonstrating
**ISO 27001** security controls with Domain-Driven Design, structured telemetry,
and defensive security patterns in **PHP (Symfony 7.3 / Laravel 12)**,
**Python (FastAPI 0.111+)**, and **Node.js (NestJS 10 / TypeScript)**.

> **NestJS note:** The `iso27001-nestjs/` implementation is a *Node.js safety-net
> blueprint* — it demonstrates the same ISO 27001 controls and DDD patterns in
> idiomatic NestJS, without the exhaustive CI pipeline (no deptrac, no
> mypy/PHPStan-equivalent gate) of the other three stacks.

## Project Structure

```
iso27001-fastapi/    FastAPI (Python 3.11)
iso27001-symfony/    Symfony 7.3 (PHP 8.2)
iso27001-laravel/    Laravel 12 (PHP 8.2)
iso27001-nestjs/     NestJS 10 (Node.js / TypeScript) — Node safety-net blueprint
rules/               Cross-project ISO 27001 rule registry + validator
.pre-commit-config.yaml   Root-level pre-commit hooks (secret scan, lint, format)
```

---

## CI Status — All Tests Passing

All three projects pass their full CI pipeline as of **2026-02-13 (v1.4.5)**:

| Project | Tests | Static Analysis | Layer Boundaries | Secret Scan |
|---------|:-----:|:---------------:|:----------------:|:-----------:|
| **FastAPI** | 46 passed | mypy strict ✓ | import-linter (3/3 contracts) ✓ | gitleaks ✓ |
| **Symfony** | All passed | PHPStan level 8 ✓ | deptrac ✓ | gitleaks ✓ |
| **Laravel** | All passed | PHPStan level 8 ✓ | deptrac ✓ | gitleaks ✓ |
| **NestJS** | — (no CI gate) | TypeScript strict | manual / convention | — |

**FastAPI test breakdown (46 tests):**

| Suite | Tests | Coverage |
|-------|------:|---------|
| `tests/integration/test_health.py` | 5 | Health endpoints (liveness, readiness, detailed) |
| `tests/unit/test_events.py` | 9 | Domain event bus — subscribe, publish, multi-listener |
| `tests/unit/test_rate_limiter.py` | 14 | Redis-backed rate limiter + in-process fallback |
| `tests/unit/test_error_budget.py` | 6 | SLA tracker — 5xx budget, 4xx separation, exhaustion flag |
| `tests/unit/test_quality_score.py` | 6 | Risk-weighted composite score, production gate |
| `tests/unit/test_security.py` | 6 | JWT creation/decode/pair, bcrypt hash + verify |

---

## What Is Implemented

### ISO 27001 Security Controls

| Control | Feature | FastAPI | Symfony | Laravel | NestJS |
|---------|---------|:-------:|:-------:|:-------:|:------:|
| A.9 | JWT authentication — short-lived access token (30 min) + refresh token (7 days) | ✓ | ✓ | ✓ | ✓ |
| A.9 | `POST /auth/refresh` — token rotation (old token revoked on issue) | ✓ | ✓ | ✓ | ✓ |
| A.9 | RBAC: `admin > manager > analyst > viewer` enforced on every route | ✓ | ✓ | ✓ | ✓ |
| A.9 | Rate limiting — auth (10/min), write (30/min), global (100/min) per IP | ✓ | ✓ | ✓ | ✓ |
| A.9 | Brute-force lockout — 5 failures → 15 min lock (Redis-backed with fallback) | ✓ | ✓ | ✓ | ✓ |
| A.10 | AES-256-GCM field-level encryption (PII at rest) | ✓ | ✓ | ✓ | ✓ |
| A.10 | bcrypt / Argon2 password hashing | ✓ | ✓ | ✓ | ✓ |
| A.10 | HSTS, X-Frame-Options, CSP, X-Content-Type-Options headers | ✓ | ✓ | ✓ | ✓ |
| A.12 | Structured JSON logging — automatic sensitive-field redaction | ✓ | ✓ | ✓ | ✓ |
| A.12 | Correlation ID (`X-Request-ID`) through logs, events, responses | ✓ | ✓ | ✓ | ✓ |
| A.12 | Identical top-level log shape across all stacks (CloudWatch-queryable) | ✓ | ✓ | ✓ | ✓ |
| A.12 | Immutable append-only `audit_logs` table | ✓ | ✓ | ✓ | ✓ |
| A.12 | Event-driven audit trail (domain events → audit listeners) | ✓ | ✓ | ✓ | ✓ |
| A.14 | Input validation (Pydantic v2 / Symfony Validator / FormRequest / class-validator) | ✓ | ✓ | ✓ | ✓ |
| A.14 | Static analysis — mypy strict (Python), PHPStan level 8 (PHP), tsc strict (TS) | ✓ | ✓ | ✓ | ✓ |
| A.14 | No stack traces or internals exposed to clients | ✓ | ✓ | ✓ | ✓ |
| A.17 | Health checks — liveness, readiness, detailed (admin) | ✓ | ✓ | ✓ | ✓ |
| A.17 | Error Budget Tracker — wired to every response; Redis-backed, in-process fallback | ✓ | ✓ | ✓ | ✓ |
| A.17 | Quality Score Calculator (risk-weighted: security 40%, integrity 20%, …) | ✓ | ✓ | ✓ | ✓ |

### Auth Endpoints

| Method | Path | Auth required | Notes |
|--------|------|:---:|-------|
| `POST` | `/api/v1/auth/login` | No | Returns access token + refresh token. Rate-limited (10/min). Brute-force locked after 5 failures. |
| `POST` | `/api/v1/auth/refresh` | Yes (valid token) | Issues new token, revokes old one immediately (token rotation). |
| `POST` | `/api/v1/auth/logout` | Yes | Revokes current token. |

### Observability

| Feature | FastAPI | Symfony | Laravel | NestJS |
|---------|:-------:|:-------:|:-------:|:------:|
| Prometheus metrics (`/metrics`) | ✓ | ✓ | ✓ | stub |
| Request count + latency histograms | ✓ | ✓ | ✓ | — |
| AWS CloudWatch custom metrics emitter | ✓ | ✓ | ✓ | stub |
| AWS X-Ray trace header propagation | ✓ | ✓ | ✓ | — |
| Normalised top-level log shape (all stacks) | ✓ | ✓ | ✓ | ✓ |

### Architecture

| Feature | FastAPI | Symfony | Laravel | NestJS |
|---------|:-------:|:-------:|:-------:|:------:|
| Domain-Driven Design (4-layer) | ✓ | ✓ | ✓ | ✓ |
| Repository pattern + interface segregation | ✓ | ✓ | ✓ | ✓ |
| Domain events dispatched from service layer | ✓ | ✓ | ✓ | ✓ |
| SoftDeletes (audit trail preservation) | ✓ | ✓ | ✓ | ✓ |
| UUID primary keys | ✓ | ✓ | ✓ | ✓ |
| Docker + docker-compose | ✓ | ✓ | ✓ | ✓ |
| CI (GitHub Actions) | ✓ | ✓ | ✓ | — |
| DDD layer boundary enforcement (deptrac / import-linter) | ✓ | ✓ | ✓ | — |
| Cross-project ISO 27001 rule registry | ✓ | ✓ | ✓ | ✓ |

---

## Optional Infrastructure & Known Limits

All optional integrations follow an "if available, use it" pattern — the API
runs fully without them and silently activates each capability when the
corresponding package or service is present.

| Feature | Notes |
|---------|-------|
| **Prometheus metrics — PHP** | `/metrics` endpoint wired on all stacks. PHP stacks return live metrics when `promphp/prometheus_client_php` is installed (`composer require promphp/prometheus_client_php`); returns an informative stub otherwise. |
| **X-Ray SDK segment tracing — PHP** | `X-Amzn-Trace-Id` header is extracted and propagated on all stacks. Full `aws-xray-sdk-php` segment tracing hooks are present; activate by installing the SDK. |
| **CloudWatch metrics — requires live credentials** | `CloudWatchEmitter.emitRequest()` is called on every response in all three stacks (FastAPI: `CorrelationIdMiddleware`; Symfony: `TelemetrySubscriber`; Laravel: `TelemetryMiddleware`). The emitter is a no-op without `boto3` / `aws/aws-sdk-php` installed and AWS credentials present. Install: `pip install -e .[aws]` (Python) or `composer require aws/aws-sdk-php` (PHP). |
| **Error Budget — cross-process accuracy (PHP)** | `ErrorBudgetTracker.record()` is called on every response in all three stacks (FastAPI: `CorrelationIdMiddleware`; Symfony: `TelemetrySubscriber`; Laravel: `TelemetryMiddleware`). PHP auto-detects Redis (`REDIS_URL` / `REDIS_HOST`) and uses atomic `INCR`; falls back to in-process counters. Snapshot includes a `backend` field (`redis` or `in-process`). |
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
- Node.js 20+ & npm

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

make setup-nestjs       # npm install
make migration-nestjs   # TypeORM synchronize

make db-reset           # Drop + recreate + migrate all databases

make check-security     # composer audit (both PHP) + pip-audit (Python)
make check-static       # PHPStan level 8 (both PHP) + mypy strict (Python)
make check-layers       # DDD layer boundaries: deptrac (PHP) + import-linter (Python)
make check-rules        # Verify every ISO 27001 rule maps to an existing file
```

---

## Architecture Enforcement

Rules and boundaries are enforced by tooling, not by review. Three mechanisms
keep all three codebases aligned without sharing code.

### ISO 27001 Rule Registry (`rules/iso27001-rules.yaml`)

Machine-readable map of every security control to its implementation file in
each project. 16 rules covering A.9 through A.17. CI runs `check_rules.py` on
every push — if a file is renamed or deleted without updating the registry, the
build fails.

```bash
make check-rules   # exits 1 and lists any missing files
```

### DDD Layer Boundaries

Prevents architectural drift: Domain must never import Infrastructure, etc.

| Tool | Projects | Config |
|------|----------|--------|
| [deptrac](https://github.com/deptrac/deptrac) | Symfony, Laravel | `deptrac.yaml` per project |
| [import-linter](https://import-linter.readthedocs.io/) | FastAPI | `iso27001-fastapi/.importlinter` |

```bash
make check-layers  # deptrac (both PHP) + lint-imports (Python)
```

Layer rules (identical intent across all three stacks):

```
Api / Http  →  Application  →  Domain      (top-down only)
Infrastructure  →  Domain                   (implements contracts)
Domain  ←  Infrastructure                   (FORBIDDEN)
```

### Pre-commit Hooks (`.pre-commit-config.yaml`)

Root-level hooks covering all three projects on every commit:

| Hook | Covers | Purpose |
|------|--------|---------|
| gitleaks | all | A.10: secret scanning |
| ruff + ruff-format | FastAPI | Python linting + formatting |
| pint | Laravel | PHP formatting |
| phpstan | Laravel, Symfony | Static analysis gate |
| detect-private-key | all | A.10: blocks key material |

```bash
pip install pre-commit && pre-commit install   # one-time setup
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
| A.9 | Access Control | JWT access + refresh tokens, RBAC (admin > manager > analyst > viewer), tiered rate limiting (auth 10/min · write 30/min · global 100/min), brute-force account lockout (5 failures → 15 min lock, Redis-backed) |
| A.10 | Cryptography | AES-256-GCM field encryption (32-byte key, 12-byte IV, 16-byte auth tag), bcrypt/Argon2 password hashing, HSTS + X-Frame-Options + CSP + X-Content-Type-Options on every response |
| A.12 | Operations Security | Structured JSON logging with sensitive-field redaction, immutable audit_logs table, event-driven audit trail, correlation IDs (X-Request-ID) in logs and responses, identical log shape across all stacks |
| A.14 | Secure Development | Input validation (Pydantic v2 / Symfony Validator / FormRequest), mypy strict + PHPStan level 8 in CI, no stack traces or internals exposed to clients |
| A.17 | Business Continuity | Liveness / readiness / detailed health checks, error budget tracker (99.9% SLA, 5xx budget deduction, wired to every response), quality score calculator (security 40% · integrity 20% · reliability 15% · auditability 15% · performance 5% · 5% reserved), tiered rate limiting |
