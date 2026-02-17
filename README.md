# ISO 27001 Compliant REST API Reference Implementation

Seven reference implementations of a REST API demonstrating
**ISO 27001** security controls with Domain-Driven Design, structured telemetry,
and defensive security patterns across **PHP (Symfony 7.3 / Laravel 12)**,
**Python (FastAPI 0.111+)**, **Node.js (NestJS 11 / TypeScript)**,
**Java (Spring Boot 3.4 / Java 21)**, **Go (Gin)**, and **Elixir (Phoenix / OTP)**.

## Project Structure

```
iso27001-fastapi/    FastAPI (Python 3.11)
iso27001-symfony/    Symfony 7.3 (PHP 8.2)
iso27001-laravel/    Laravel 12 (PHP 8.2)
iso27001-nestjs/     NestJS 11 (Node.js / TypeScript)
iso27001-springboot/ Spring Boot 3.4 (Java 21)
iso27001-gin/        Go 1.22 / Gin
iso27001-phoenix/    Elixir 1.16 / Phoenix (OTP 26)
rules/               Cross-project ISO 27001 rule registry + validator
.pre-commit-config.yaml   Root-level pre-commit hooks (secret scan, lint, format)
```

---

## CI Status — All Tests Passing

All seven projects pass their full CI pipeline:

| Project | Tests | Static Analysis | Layer Boundaries | Secret Scan |
|---------|:-----:|:---------------:|:----------------:|:-----------:|
| **FastAPI** | 46 passed | mypy strict ✓ | import-linter (3/3 contracts) ✓ | gitleaks ✓ |
| **Symfony** | All passed | PHPStan level 8 ✓ | deptrac ✓ | gitleaks ✓ |
| **Laravel** | All passed | PHPStan level 8 ✓ | deptrac ✓ | gitleaks ✓ |
| **NestJS** | 35 passed | TypeScript strict ✓ | check-layers (3/3 contracts) ✓ | gitleaks ✓ |
| **Spring Boot** | 21 passed | checkstyle ✓ | ArchUnit (5/5 rules) ✓ | gitleaks ✓ |
| **Go/Gin** | 25 passed | go vet ✓ | go build ✓ | gitleaks ✓ |
| **Elixir/Phoenix** | 20 passed | mix compile --warnings-as-errors ✓ | mix compile ✓ | gitleaks ✓ |

**FastAPI test breakdown (46 tests):**

| Suite | Tests | Coverage |
|-------|------:|---------|
| `tests/integration/test_health.py` | 5 | Health endpoints (liveness, readiness, detailed) |
| `tests/unit/test_events.py` | 9 | Domain event bus — subscribe, publish, multi-listener |
| `tests/unit/test_rate_limiter.py` | 14 | Redis-backed rate limiter + in-process fallback |
| `tests/unit/test_error_budget.py` | 6 | SLA tracker — 5xx budget, 4xx separation, exhaustion flag |
| `tests/unit/test_quality_score.py` | 6 | Risk-weighted composite score, production gate |
| `tests/unit/test_security.py` | 6 | JWT creation/decode/pair, bcrypt hash + verify |

**NestJS test breakdown (35 tests):**

| Suite | Tests | Coverage |
|-------|------:|---------|
| `tests/unit/security.spec.ts` | 6 | JWT creation/decode/pair, bcrypt hash + verify |
| `tests/unit/brute-force.spec.ts` | 6 | Lockout after 5 failures, isolation, Redis fallback |
| `tests/unit/error-budget.spec.ts` | 6 | SLA tracker — 5xx budget, 4xx separation, exhaustion flag |
| `tests/unit/quality-score.spec.ts` | 6 | Risk-weighted composite score, production gate |
| `tests/unit/events.spec.ts` | 5 | Domain event bus — subscribe, publish, multi-listener |
| `tests/unit/prometheus-metrics.spec.ts` | 6 | Prometheus counters, histograms, labels |

**Spring Boot test breakdown (21 tests):**

| Suite | Tests | Coverage |
|-------|------:|---------|
| `LayerArchitectureTest.java` | 5 | ArchUnit DDD layer boundaries |
| `UserServiceTest.java` | 6 | bcrypt hashing, domain events, duplicate email guard |
| `ErrorBudgetTrackerTest.java` | 5 | SLA tracker — 5xx budget, 4xx separation, reset |
| `QualityScoreCalculatorTest.java` | 5 | Risk-weighted composite score, production gate |

---

## What Is Implemented

### ISO 27001 Security Controls

| Control | Feature | FastAPI | Symfony | Laravel | NestJS | Spring Boot | Go/Gin | Phoenix |
|---------|---------|:-------:|:-------:|:-------:|:------:|:-----------:|:------:|:-------:|
| A.9 | JWT authentication — short-lived access token (30 min) + refresh token (7 days) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.9 | `POST /auth/refresh` — token rotation (old token revoked on issue) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.9 | RBAC: `admin > manager > analyst > viewer` enforced on every route | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.9 | Rate limiting — auth (10/min), write (30/min), global (100/min) per IP | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.9 | Brute-force lockout — 5 failures → 15 min lock (Redis-backed with fallback) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.10 | AES-256-GCM field-level encryption (PII at rest) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.10 | bcrypt / Argon2 password hashing | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.10 | HSTS, X-Frame-Options, CSP, X-Content-Type-Options headers | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.12 | Structured JSON logging — automatic sensitive-field redaction | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.12 | Correlation ID (`X-Request-ID`) through logs, events, responses | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.12 | Identical top-level log shape across all stacks (CloudWatch-queryable) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.12 | Immutable append-only `audit_logs` table | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.12 | Event-driven audit trail (domain events → audit listeners) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.14 | Input validation (Pydantic v2 / Symfony Validator / FormRequest / class-validator / Jakarta / binding / Ecto) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.14 | Static analysis — mypy strict · PHPStan 8 · tsc strict · checkstyle · go vet · mix compile | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.14 | No stack traces or internals exposed to clients | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.17 | Health checks — liveness, readiness, detailed (admin) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.17 | Error Budget Tracker — wired to every response; Redis-backed, in-process fallback | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| A.17 | Quality Score Calculator (risk-weighted: security 40%, integrity 20%, …) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Auth Endpoints

| Method | Path | Auth required | Notes |
|--------|------|:---:|-------|
| `POST` | `/api/v1/auth/login` | No | Returns access token + refresh token. Rate-limited (10/min). Brute-force locked after 5 failures. |
| `POST` | `/api/v1/auth/refresh` | Yes (valid token) | Issues new token, revokes old one immediately (token rotation). |
| `POST` | `/api/v1/auth/logout` | Yes | Revokes current token. |

### Observability

| Feature | FastAPI | Symfony | Laravel | NestJS | Spring Boot | Go/Gin | Phoenix |
|---------|:-------:|:-------:|:-------:|:------:|:-----------:|:------:|:-------:|
| Prometheus metrics (`/metrics`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Request count + latency histograms | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| AWS CloudWatch custom metrics emitter | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| AWS X-Ray trace header propagation | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Normalised top-level log shape (all stacks) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Architecture

| Feature | FastAPI | Symfony | Laravel | NestJS | Spring Boot | Go/Gin | Phoenix |
|---------|:-------:|:-------:|:-------:|:------:|:-----------:|:------:|:-------:|
| Domain-Driven Design (4-layer) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Repository pattern + interface segregation | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Domain events dispatched from service layer | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| SoftDeletes (audit trail preservation) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| UUID primary keys | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Docker + docker-compose | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| CI (GitHub Actions) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| DDD layer boundary enforcement (deptrac / import-linter / check-layers / ArchUnit / go build / mix compile) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Cross-project ISO 27001 rule registry | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## Cloud Infrastructure (AWS + Terraform)

Full reference IaC for deploying all seven stacks to AWS ECS Fargate.
See [`infra/terraform/README.md`](infra/terraform/README.md) for full documentation.

```
infra/terraform/
├── modules/
│   ├── vpc/          Shared VPC, subnets, security groups
│   ├── rds/          RDS PostgreSQL 16 — encrypted, automated backups
│   ├── elasticache/  ElastiCache Redis 7 — in-transit + at-rest encryption
│   ├── secrets/      Secrets Manager — one secret JSON blob per stack
│   ├── ecr/          Container registry — immutable tags, scan on push
│   └── ecs-service/  Fargate service + IAM + CloudWatch logs + ALB rule
└── environments/
    └── staging/      Root module: one ECS service × 7 stacks
```

| Control | Infrastructure resource | File |
|---------|------------------------|------|
| A.9 | ECS task execution IAM role (least-privilege) | `modules/ecs-service/main.tf` |
| A.9 | VPC security groups (ALB→ECS, ECS→RDS, ECS→Redis) | `modules/vpc/main.tf` |
| A.10 | RDS `storage_encrypted = true`; password in Secrets Manager | `modules/rds/main.tf` |
| A.10 | ElastiCache at-rest + in-transit encryption; auth token in Secrets Manager | `modules/elasticache/main.tf` |
| A.10 | ECS `secrets:` block — no plaintext in task definition JSON | `modules/ecs-service/main.tf` |
| A.12 | CloudWatch log groups, `retention_in_days = 365` | `modules/ecs-service/main.tf` |
| A.14 | ECR `scan_on_push = true`, `image_tag_mutability = IMMUTABLE` | `modules/ecr/main.tf` |
| A.17 | RDS `backup_retention_period = 7`, `deletion_protection = true` | `modules/rds/main.tf` |
| A.17 | ElastiCache `automatic_failover_enabled` | `modules/elasticache/main.tf` |

Makefile shortcuts: `make infra-fmt` · `make infra-validate` · `make infra-plan`

---

## Optional Infrastructure & Known Limits

All optional integrations follow an "if available, use it" pattern — every stack
runs fully without them and silently activates each capability when the
corresponding package or service is present.

| Feature | Notes |
|---------|-------|
| **Prometheus metrics — PHP** | `/metrics` endpoint wired on all stacks. PHP stacks return live metrics when `promphp/prometheus_client_php` is installed (`composer require promphp/prometheus_client_php`); returns an informative stub otherwise. |
| **X-Ray SDK segment tracing — PHP** | `X-Amzn-Trace-Id` header is extracted and propagated on all stacks. Full `aws-xray-sdk-php` segment tracing hooks are present; activate by installing the SDK. |
| **CloudWatch metrics — requires live credentials** | `CloudWatchEmitter.emitRequest()` is called on every response in all seven stacks (FastAPI: `CorrelationIdMiddleware`; Symfony: `TelemetrySubscriber`; Laravel: `TelemetryMiddleware`; NestJS: `TelemetryMiddleware`; Spring Boot: `TelemetryFilter`; Go/Gin: `TelemetryMiddleware`; Phoenix: `MetricsController`). The emitter is a no-op without `boto3` / `aws/aws-sdk-php` / `@aws-sdk/client-cloudwatch` / `software.amazon.awssdk:cloudwatch` installed and AWS credentials present. |
| **Error Budget — cross-process accuracy** | `ErrorBudgetTracker.record()` is called on every response in all seven stacks. PHP/Java/Go auto-detect Redis and use atomic increments; Elixir uses an Agent with ETS fallback. Snapshot includes a `backend` field (`redis` or `in-process`). |
| **P95/P99 per-endpoint latency alerts** | Prometheus histogram is recorded; no SLO alert rule or dashboard is defined. |
| **4xx vs 5xx error rate separation** | Error budget counts only 5xx. Separate rules for 4xx anomalies are not defined. |
| **External dependency health (circuit breakers)** | Readiness check does not include downstream API state. |
| **Fraud / anomaly detection** | Auth failures and rate-limit hits are counted; no rule engine is wired. |
| **Business-level telemetry** | Infrastructure metrics only. No domain KPIs (conversion, revenue) are emitted. |

---

## Getting Started

A `Makefile` unifies commands for all seven stacks.

### Prerequisites

- Docker & Docker Compose
- PHP 8.2+ & Composer
- Python 3.11+ & pip
- Node.js 20+ & npm
- Java 21+ & Maven 3.9+
- Go 1.22+
- Elixir 1.16+ & OTP 26+

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
make test-nestjs        # jest

make setup-springboot   # mvn dependency:resolve
make test-springboot    # mvn verify

make setup-gin          # go mod tidy
make test-gin           # go test ./tests/... -v -race

make setup-phoenix      # mix setup (deps + db create + migrate)
make migration-phoenix  # mix ecto.migrate
make test-phoenix       # mix test test/unit

make db-reset           # Drop + recreate + migrate all databases

make check-security     # composer audit + pip-audit + npm audit + govulncheck + mix deps.audit
make check-static       # PHPStan 8 + mypy strict + tsc strict + checkstyle + go vet + mix compile
make check-layers       # deptrac (PHP) + import-linter (Python) + check-layers (NestJS) + ArchUnit (Java) + go build + mix compile
make check-rules        # Verify every ISO 27001 rule maps to an existing file
make check-openapi      # Spectral lint all 7 OpenAPI specs
```

---

## Architecture Enforcement

Rules and boundaries are enforced by tooling, not by review. Five mechanisms
keep all seven codebases aligned without sharing code.

### ISO 27001 Rule Registry (`rules/iso27001-rules.yaml`)

Machine-readable map of every security control to its implementation file in
each project. 16 rules covering A.9 through A.17, verified across all seven stacks.
CI runs `check_rules.py` on every push — if a file is renamed or deleted without
updating the registry, the build fails.

```bash
make check-rules   # exits 1 and lists any missing files
```

### DDD Layer Boundaries

Prevents architectural drift: Domain must never import Infrastructure, etc.

| Tool | Projects | Config |
|------|----------|--------|
| [deptrac](https://github.com/deptrac/deptrac) | Symfony, Laravel | `deptrac.yaml` per project |
| [import-linter](https://import-linter.readthedocs.io/) | FastAPI | `iso27001-fastapi/.importlinter` |
| [check-layers.ts](iso27001-nestjs/check-layers.ts) | NestJS | custom TS script (3 contracts) |
| [ArchUnit](https://www.archunit.org/) | Spring Boot | `LayerArchitectureTest.java` (5 rules) |
| `go build ./...` | Go/Gin | package import graph enforced by compiler |
| `mix compile` | Elixir/Phoenix | OTP application boundary enforced by compiler |

```bash
make check-layers  # deptrac (PHP) + lint-imports (Python) + check-layers (NestJS) + ArchUnit (Java) + go build (Go) + mix compile (Elixir)
```

Layer rules (identical intent across all seven stacks):

```
Api / Http  →  Core / Application  →  Domain      (top-down only)
Infrastructure  →  Domain                           (implements contracts)
Domain  ←  Infrastructure                           (FORBIDDEN)
```

### Pre-commit Hooks (`.pre-commit-config.yaml`)

Root-level hooks covering all seven projects on every commit:

| Hook | Covers | Purpose |
|------|--------|---------|
| gitleaks | all | A.10: secret scanning |
| ruff + ruff-format | FastAPI | Python linting + formatting |
| pint | Laravel | PHP formatting |
| phpstan | Laravel, Symfony | Static analysis gate |
| tsc --noEmit | NestJS | TypeScript strict type check |
| check-layers | NestJS | DDD layer boundary check |
| go-vet | Go/Gin | Static analysis + go.sum bootstrap |
| mix-compile | Elixir/Phoenix | Warnings-as-errors compile check |
| spectral | all OpenAPI specs | Auth, rate-limit, error-shape rules |
| detect-private-key | all | A.10: blocks key material |

```bash
pip install pre-commit && pre-commit install   # one-time setup
```

---

## Log Shape (all stacks)

Every log line from every stack produces the same top-level JSON structure,
enabling a single CloudWatch Logs Insights query across all seven services:

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
# CloudWatch Logs Insights — works identically for all seven log groups
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
| A.14 | Secure Development | Input validation (Pydantic v2 / Symfony Validator / FormRequest / class-validator / Jakarta Bean Validation / Go binding tags / Ecto changesets), mypy strict + PHPStan level 8 + tsc strict + checkstyle + go vet + mix compile --warnings-as-errors in CI, no stack traces or internals exposed to clients |
| A.17 | Business Continuity | Liveness / readiness / detailed health checks, error budget tracker (99.9% SLA, 5xx budget deduction, wired to every response), quality score calculator (security 40% · integrity 20% · reliability 15% · auditability 15% · performance 5% · 5% reserved), tiered rate limiting |

---

## Architecture Decision Records

Key design decisions are documented in [`docs/adr/`](docs/adr/):

| ADR | Title | Controls |
|-----|-------|----------|
| [0001](docs/adr/0001-jwt-strategy.md) | JWT Authentication Strategy | A.9, A.10 |
| [0002](docs/adr/0002-ddd-layering.md) | DDD Layered Architecture | A.14 |
| [0003](docs/adr/0003-redis-fallback-pattern.md) | Redis Fallback Pattern | A.12, A.17 |
| [0004](docs/adr/0004-log-schema.md) | Structured Log Schema | A.12, A.16 |
| [0005](docs/adr/0005-cloud-infrastructure.md) | Cloud Infrastructure (AWS + Terraform) | A.9, A.10, A.12, A.14, A.17 |

Each ADR documents: the context, the decision made, alternatives considered, and consequences — covering all seven stacks.
