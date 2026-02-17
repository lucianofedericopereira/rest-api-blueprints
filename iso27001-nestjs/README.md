# ISO 27001 NestJS Reference Implementation

> **Node.js Safety-Net Blueprint**
>
> This is a lighter-weight implementation of the same ISO 27001 REST API
> that exists for FastAPI, Symfony, and Laravel. It demonstrates the same
> security controls and DDD patterns in an idiomatic NestJS style, without
> the exhaustive CI pipeline (no deptrac, no mypy/PHPStan equivalent gate)
> of the other three stacks.

## Stack

| Component | Choice |
|-----------|--------|
| Framework | NestJS 10 (TypeScript strict) |
| ORM | TypeORM 0.3 (PostgreSQL) |
| JWT | `@nestjs/jwt` + `passport-jwt` |
| Rate limiting | `@nestjs/throttler` |
| Logging | NestJS Logger (structured JSON shape matching all stacks) |
| Validation | `class-validator` + `class-transformer` |
| Health | `@nestjs/terminus`-compatible endpoints |
| Encryption | Node.js built-in `crypto` — AES-256-GCM |
| Password hashing | `bcrypt` (cost 12) |
| Brute-force guard | Redis-backed with in-process fallback |

## ISO 27001 Controls

| Control | Feature | Status |
|---------|---------|:------:|
| A.9 | JWT access token (30 min) + refresh token (7 days) | ✓ |
| A.9 | Token rotation on `/auth/refresh` | ✓ |
| A.9 | RBAC: `admin > manager > analyst > viewer` | ✓ |
| A.9 | Rate limiting: auth 10/min · write 30/min · global 100/min | ✓ |
| A.9 | Brute-force lockout: 5 failures → 15 min lock (Redis + fallback) | ✓ |
| A.10 | AES-256-GCM field-level encryption (PII at rest) | ✓ |
| A.10 | bcrypt password hashing (cost ≥ 12) | ✓ |
| A.10 | HSTS, X-Frame-Options, CSP, X-Content-Type-Options | ✓ |
| A.12 | Structured JSON logging (matching cross-stack log shape) | ✓ |
| A.12 | Correlation ID (`X-Request-ID`) in logs + responses | ✓ |
| A.12 | Immutable append-only `audit_logs` table | ✓ |
| A.12 | Event-driven audit trail (domain events → audit listener) | ✓ |
| A.14 | Input validation via `class-validator` | ✓ |
| A.14 | No stack traces or internal details exposed to clients | ✓ |
| A.17 | Liveness, readiness, and detailed health checks | ✓ |
| A.17 | Error Budget Tracker (99.9% SLA, 5xx deducts budget) | ✓ |
| A.17 | Quality Score Calculator (security 40% · integrity 20% · …) | ✓ |

## Project Layout (DDD 4-Layer)

```
src/
  api/v1/               ← HTTP controllers (auth, users, health)
  domain/users/         ← Entity, DTOs, events, repository interface, service
  infrastructure/       ← TypeORM repo, FieldEncryptor, BruteForceGuard,
                           AuditService, ErrorBudgetTracker, QualityScoreCalculator
  core/                 ← Middleware, guards, filters, auth strategy, config
  main.ts               ← Bootstrap
  app.module.ts         ← NestJS root module
```

Layer rule (same intent as deptrac in PHP stacks):
```
api  →  domain  ←  infrastructure
core →  domain
Domain must never import infrastructure or api.
```

## Getting Started

```bash
cp .env.example .env
# edit .env — set JWT_SECRET, ENCRYPTION_KEY, DATABASE_URL

npm install
npm run start:dev
```

### With Docker

```bash
docker-compose up -d
```

## API Endpoints

| Method | Path | Auth | Notes |
|--------|------|:----:|-------|
| `POST` | `/api/v1/auth/login` | No | Rate-limited (10/min). Brute-force protected. |
| `POST` | `/api/v1/auth/refresh` | Token | Issues new pair, rotates tokens. |
| `POST` | `/api/v1/auth/logout` | Token | Client-side (stateless JWT). |
| `POST` | `/api/v1/users` | No | Register (viewer role). |
| `GET`  | `/api/v1/users` | admin | Paginated list. |
| `GET`  | `/api/v1/users/me` | any | Current user profile. |
| `GET`  | `/api/v1/users/:id` | owner/admin | |
| `PATCH`| `/api/v1/users/:id` | owner/admin | |
| `DELETE`| `/api/v1/users/:id` | admin | Soft delete. |
| `GET`  | `/health/live` | No | Liveness. |
| `GET`  | `/health/ready` | No | Readiness (DB check). |
| `GET`  | `/health/detail` | admin | Error budget + quality score. |
| `GET`  | `/metrics` | No | Prometheus-compatible stub. |

## Log Shape

Every log line matches the cross-stack shape:

```json
{
  "timestamp":   "2025-02-10T14:30:00.123Z",
  "level":       "INFO",
  "message":     "request.completed",
  "service":     "iso27001-api",
  "environment": "production",
  "request_id":  "550e8400-e29b-41d4-a716-446655440000",
  "context": {
    "method":      "POST",
    "path":        "/api/v1/users",
    "status_code": 201,
    "duration_ms": 45.2
  }
}
```
