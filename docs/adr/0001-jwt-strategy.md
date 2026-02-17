# ADR 0001 — JWT Authentication Strategy

**Status:** Accepted
**Date:** 2025-01-01
**ISO 27001 Controls:** A.9 (Access control), A.10 (Cryptography)

---

## Context

The seven blueprint implementations (FastAPI, Symfony, Laravel, NestJS, Spring Boot, Go/Gin,
Elixir/Phoenix) all require stateless authentication that works across horizontal replicas
without shared session state. We need a strategy that satisfies ISO 27001 A.9 (access control)
and A.10 (cryptographic key management) while remaining consistent across all seven stacks.

---

## Decision

We use **short-lived access tokens + long-lived refresh tokens** with the following contract:

| Property | Access token | Refresh token |
|---|---|---|
| Algorithm | RS256 (PHP stacks) / HS256 (Python/Node/Go/Elixir) | Same algorithm as access token |
| Lifetime | 30 minutes | 7 days |
| Payload | `sub` (user UUID), `role`, `jti`, `exp`, `iat` | `sub`, `jti`, `exp` |
| Storage guidance | Memory only (not localStorage) | HttpOnly cookie or secure storage |
| Rotation | Issued as a pair on login and refresh | Rotated on every refresh |

**Key-management rules (A.10):**

- PHP stacks use RSA-2048 key pairs generated at deploy time; the private key is never committed
  to the repository (enforced by `.gitignore` and `gitleaks` pre-commit hook).
- Python, Node, Go, and Elixir stacks use a symmetric `JWT_SECRET` / `GUARDIAN_SECRET` injected
  via environment variable; minimum entropy requirement is 256 bits (32 random bytes).
- Go uses `golang-jwt/v5` with HS256; Elixir uses Guardian with HS256 and a unique `jti` claim
  on every token.
- Key rotation is achieved by redeploying with a new secret; all existing sessions invalidate
  naturally at token expiry.

**Brute-force protection (A.9):**

- Login endpoints enforce a sliding-window rate limit: max 5 attempts per account per 15 minutes.
- After 5 consecutive failures the account is locked for 15 minutes (Redis-backed;
  in-process map as fallback when Redis is unavailable).
- Lockout status is communicated with HTTP 429 and `code: ACCOUNT_LOCKED` in the response body.

---

## Alternatives Considered

| Option | Reason rejected |
|---|---|
| Opaque tokens (session cookies) | Requires shared session store; breaks horizontal scaling |
| OAuth 2.0 authorization server | Adds external dependency not appropriate for a self-contained blueprint |
| Single long-lived JWT | Cannot be revoked before expiry; fails A.9 minimum necessary access |
| Paseto (Platform-Agnostic Security Tokens) | Limited library support across all seven stacks simultaneously |

---

## Consequences

- Every stack must expose `POST /auth/login` and `POST /auth/refresh` with the same request/
  response shape (enforced by `openapi.yaml` + Spectral).
- Logout is client-side (token discard) due to stateless design; short access-token lifetime
  bounds the window of exposure for a stolen token.
- Rotating refresh tokens reduces the blast radius of refresh token theft.
