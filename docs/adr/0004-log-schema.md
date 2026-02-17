# ADR 0004 — Structured Log Schema

**Status:** Accepted
**Date:** 2025-01-01
**ISO 27001 Controls:** A.12 (Operations security — logging and monitoring), A.16 (Information security incident management)

---

## Context

ISO 27001 A.12.4 requires that event logs capture user activities, exceptions, faults, and
information security events in a format that supports audit and incident investigation. A.16
requires that incidents can be reconstructed from log evidence.

With seven different technology stacks (Python, PHP×2, Node.js, Java, Go, Elixir), each has its
own default log format. Without a shared schema, aggregating logs in a SIEM (e.g., CloudWatch
Logs Insights, Elasticsearch) requires per-stack parsing rules and makes cross-stack correlation
harder during incidents.

---

## Decision

All seven stacks emit **JSON-structured logs** with the following canonical fields:

```json
{
  "timestamp":   "2025-01-15T10:23:45.123Z",  // ISO 8601 UTC — always present
  "level":       "info",                        // debug | info | warn | error
  "service":     "iso27001-fastapi",            // stack identifier — always present
  "env":         "production",                  // APP_ENV value — always present
  "method":      "POST",                        // HTTP method — on request events
  "path":        "/api/v1/auth/login",          // URL path, no query string — on request events
  "status":      200,                           // HTTP status code — on response events
  "duration_ms": 47,                            // response time in ms — on response events
  "trace_id":    "Root=1-abc;Parent=def",       // X-Ray trace ID when propagated — optional
  "user_id":     "usr_xxxxxxxx",               // authenticated user UUID — when available
  "message":     "request completed"            // human-readable description — always present
}
```

**Security-sensitive fields MUST NOT be logged:**

| Field | Reason |
|---|---|
| `password` | Credential exposure |
| `access_token`, `refresh_token` | Token theft via logs |
| Full `email` | PII — log `email_hash` (SHA-256) instead |
| Request body for `POST /auth/*` | Contains credentials |
| `Authorization` header value | Token exposure |

**Implementation per stack:**

| Stack | Logger | Config |
|---|---|---|
| FastAPI | `structlog` + `pydantic` | `app/core/middleware/telemetry.py` |
| Symfony | `monolog` with JSON formatter | `config/packages/monolog.yaml` |
| Laravel | `monolog` with JSON formatter | `config/logging.php` |
| NestJS | `pino` + `pino-http` | `src/core/middleware/telemetry.middleware.ts` |
| Spring Boot | `logback` with `logstash-logback-encoder` | `src/main/resources/logback-spring.xml` |
| Go/Gin | `log/slog` with JSON handler | `internal/core/middleware/telemetry.go` |
| Elixir/Phoenix | `Logger` with JSON formatter via `Jason` | `lib/iso27001_phoenix_web/endpoint.ex` + `config/config.exs` |

**Log destinations:**

- Local: `stdout` (JSON) — consumed by Docker log driver.
- Production: CloudWatch Logs via the CloudWatch emitter in `src/infrastructure/telemetry/`.
  The emitter is optional (dynamic SDK require); when the AWS SDK is absent, logs flow to
  stdout only.

**Log levels by event type:**

| Event | Level |
|---|---|
| Incoming request completed (2xx/3xx) | `info` |
| Client error (4xx) | `warn` |
| Server error (5xx) | `error` |
| Security event (auth failure, lockout, rate-limit hit) | `warn` |
| Startup / shutdown | `info` |
| Redis fallback activated | `warn` |
| Unhandled exception | `error` |

---

## Alternatives Considered

| Option | Reason rejected |
|---|---|
| Plain-text logs | Not machine-parseable; cannot be queried in CloudWatch Logs Insights |
| OpenTelemetry structured logs | Correct long-term direction but adds significant setup complexity for a blueprint |
| ECS (Elastic Common Schema) | Well-defined but vendor-aligned; the custom schema above covers all required A.12/A.16 fields with less overhead |
| Different schema per stack | Prevents cross-stack correlation; fails A.12.4 audit requirements — especially problematic across seven stacks |

---

## Consequences

- All telemetry middleware must be reviewed whenever a new field is added to the schema to
  ensure no PII or credentials leak.
- Log retention policy (minimum 1 year per ISO 27001 A.12.4) is the operator's responsibility
  and is out of scope for the application layer, but the CloudWatch log group configuration
  in each stack's infrastructure module should set `retention_in_days: 365` by default.
- The `trace_id` field enables end-to-end request tracing when AWS X-Ray is active, satisfying
  A.16 incident reconstruction requirements without a dedicated distributed tracing backend.
