# ADR 0003 — Redis Fallback Pattern

**Status:** Accepted
**Date:** 2026-02-17
**ISO 27001 Controls:** A.12 (Operations security), A.17 (Information security aspects of business continuity)

---

## Context

Several security controls depend on a shared, low-latency data store:

- **Rate limiting** — sliding-window counters shared across replicas (A.12).
- **Brute-force protection** — per-account failure counters (A.9).
- **Distributed locks** — preventing concurrent token refresh races (A.10).

Redis is the natural choice, but making it a hard dependency means a Redis outage takes down
the entire API. ISO 27001 A.17 (business continuity) requires the service to degrade gracefully
rather than fail completely.

---

## Decision

We use a **Redis-primary, in-process-map fallback** pattern across all seven stacks:

1. At startup, attempt to connect to Redis using the `REDIS_URL` environment variable.
2. If the connection succeeds, all counters and locks use Redis.
3. If the connection fails (or `REDIS_URL` is absent), fall back to an in-process
   `Map<string, {count, expiry}>` that is local to the running instance.
4. All callers interact through an identical interface regardless of which backend is active.
5. A structured log warning is emitted when the fallback is active so that operations teams
   are alerted.

**Security implications of fallback mode:**

- Rate limit counters are per-instance in fallback mode, so the effective limit per user is
  `configured_limit × replica_count`. This is a known degradation accepted for availability.
- Brute-force counters are also per-instance in fallback, which means a distributed brute-force
  attack distributing requests across replicas may exceed the threshold before lockout.
- Both risks are mitigated by keeping the fallback window short (auto-reconnect every 30 seconds)
  and by logging all fallback-mode decisions for post-incident review.

**Implementation per stack:**

| Stack | Rate limiter | Brute-force guard |
|---|---|---|
| FastAPI | `app/infrastructure/rate_limiter.py` | `app/infrastructure/brute_force.py` |
| Symfony | `src/Infrastructure/RateLimiter/` | `src/Infrastructure/Security/` |
| Laravel | `app/Infrastructure/RateLimiter/` | `app/Infrastructure/Security/` |
| NestJS | `src/infrastructure/rate-limiter/` | `src/infrastructure/security/brute-force.guard.ts` |
| Spring Boot | `src/main/java/.../infrastructure/security/` | `src/main/java/.../infrastructure/security/` |
| Go/Gin | `internal/infrastructure/ratelimiter/` (sync/atomic + Redis Lua) | `internal/infrastructure/security/brute_force.go` |
| Elixir/Phoenix | `lib/iso27001_phoenix/core/middleware/rate_limiter.ex` (GenServer + ETS) | `lib/iso27001_phoenix/core/middleware/brute_force.ex` (GenServer + ETS) |

---

## Alternatives Considered

| Option | Reason rejected |
|---|---|
| Hard Redis dependency (fail if unavailable) | Violates A.17 — Redis becomes a single point of failure |
| Memcached instead of Redis | Redis adds Lua scripting for atomic counter operations; Memcached lacks pub/sub for lock notifications |
| Database-backed counters | Adds write load to primary DB; latency too high for per-request rate checks |
| External rate-limit proxy (e.g. Kong) | Outside scope of application-layer blueprint; adds infrastructure coupling |

---

## Consequences

- Every stack ships with two test modes: one with a Redis mock, one with `null` passed to the
  constructor (triggers in-process fallback). Both must pass CI.
- The in-process fallback is runtime-appropriate per stack: Node.js/Python use a single-threaded
  event loop; PHP forks per request; Go uses `sync/atomic` and mutex-protected maps for
  goroutine safety; Elixir uses ETS (Erlang Term Storage) tables which are process-safe and
  lock-free for concurrent reads.
- Operators deploying to production must monitor Redis availability; the fallback is a safety
  net, not a primary mode of operation.

---

← [ADR 0002 — DDD Layered Architecture](0002-ddd-layering.md) · [ADR index](../../README.md#architecture-decision-records) · Next: [ADR 0004 — Structured Log Schema](0004-log-schema.md) →
