# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-02-12

### Added

**Cross-project architecture enforcement**
- `rules/iso27001-rules.yaml` — machine-readable rule registry: 16 rules covering A.9/A.10/A.12/A.14/A.17, each mapping a policy to the exact implementation file in all three projects; acts as the cross-project compliance contract
- `rules/check_rules.py` — validates that every file listed in the registry exists on disk; exits 1 with a diff of missing files; run via `make check-rules`
- `iso27001-symfony/deptrac.yaml` — DDD layer boundary enforcement for Symfony: Domain → no outward deps, Infrastructure → Domain only, Api → all layers
- `iso27001-laravel/deptrac.yaml` — identical DDD boundary rules for Laravel (Http / Application / Infrastructure / Domain)
- `iso27001-fastapi/.importlinter` — same boundary contract for Python: `app.domain` forbidden from importing `app.infrastructure` or `app.api`; strict top-down layer ordering enforced
- `.pre-commit-config.yaml` — root-level pre-commit hooks: gitleaks (A.10 secret scanning), ruff + ruff-format (FastAPI), pint + phpstan (Laravel/Symfony)
- `qossmic/deptrac ^2.0` added to `require-dev` in both `iso27001-symfony/composer.json` and `iso27001-laravel/composer.json`
- `import-linter ^2.1` added to `[project.optional-dependencies] dev` in `iso27001-fastapi/pyproject.toml`
- Root `.gitignore` created covering IDE files, OS artifacts, and key material (A.10)
- `iso27001-symfony/.gitignore` expanded: added `.phpunit.cache`, `.idea/`, `.vscode/`, `.fleet/`, `composer.lock`, `*.log`, `coverage/`, `.phpstan.cache`

**CI — `.github/workflows/ci.yml`**
- New `secret-scan` job: runs `gitleaks/gitleaks-action@v2` on full git history (A.10)
- New `rule-registry` job: runs `rules/check_rules.py` to verify compliance file map is not stale
- `Layer boundaries (A.14 — deptrac)` step added to both PHP jobs (Symfony + Laravel)
- `Layer boundaries (A.14 — import-linter)` step added to the FastAPI job

**Makefile**
- `make check-layers` — runs deptrac (both PHP projects) + import-linter (Python) in sequence
- `make check-rules` — runs `rules/check_rules.py`

## [1.2.0] - 2026-02-12

### Added

**Laravel**
- Added `larastan/larastan ^3.0` and `phpstan/extension-installer ^1.3` to `require-dev` (^3.0 adds Laravel 12 support; requires `phpstan/phpstan ^2.1` and `laravel/framework ^12.4.1`)
- Bumped `phpstan/phpstan` constraint from `^1.10` to `^2.1` (required by Larastan 3.x)
- Tightened `laravel/framework` minimum from `^12.0` to `^12.4.1` (required by Larastan 3.x illuminate/* constraint)
- Added `phpstan/extension-installer` to `config.allow-plugins`
- Updated `phpstan.neon` to include `vendor/larastan/larastan/extension.neon` (replaces bare `bleedingEdge.neon`) — enables Laravel-aware PHPStan rules for Eloquent, facades, and helpers
- Updated `.gitignore`: added `.phpstan.cache`, `phpstan-baseline.neon`, `.phpunit.cache`, `/bootstrap/cache/*.php`, and IDE helper generated stubs (`_ide_helper.php`, `_ide_helper_models.php`, `.phpstorm.meta.php`)
- Added `barryvdh/laravel-ide-helper ^3.1` to `require-dev` — generates facade stubs that fix intelephense P1009 "Undefined type" errors for `Illuminate\Support\Facades\*` (Request, DB, Log, Redis, Cache, etc.); run `php artisan ide-helper:generate` after `composer install`

### Fixed

**Symfony — tests**
- `RateLimitSubscriberTest`: added missing `$writeApiLimiter` mock (third constructor argument) — test was failing to instantiate `RateLimitSubscriber`
- `RateLimitSubscriberTest`: added `testWriteEndpointUsesWriteLimiter` to cover the write-tier path; added `$writeApiLimiter->expects($this->never())` assertion to `testNonApiRequestIgnored`

**FastAPI — tests**
- `test_rate_limiter.py`: completely rewrote to match actual `RedisRateLimiter` API — previous version tested a non-existent `__init__(redis_url, limit, window)` / `is_allowed()` interface
  - `TestTierSelection`: unit-tests the `_tier()` helper for auth / write / global routing
  - `TestLocalFallback`: unit-tests `_local_check()` and the `_local_windows` dict directly
  - `TestRedisRateLimiterNoRedis`: integration-tests `check(request)` with Redis patched to `None` (in-process fallback path)
  - `TestRedisRateLimiterWithRedis`: integration-tests `check(request)` with a mocked Redis client returning Lua result `0` (allow) or `1` (block)

## [1.1.0] - 2026-02-12

### Fixed

**Symfony**
- Added `config.allow-plugins` block to `composer.json` allowing `symfony/flex` and `symfony/runtime` — resolves Composer plugin blocked error in CI

**Laravel (PHPStan)**
- `AuditEntry`: added `@param array<string, mixed>` to `$changes` constructor parameter
- `AuditService`: added `@param array<string, mixed>` annotation; fixed `Request::header()` returning `array|string|null` by using `?? 'system'` guard before passing to `AuditEntry`
- `UserRepositoryInterface`: typed `paginate()` as `LengthAwarePaginator<User>`; added `@param array<string, mixed>` to `create()` and `update()`
- `EloquentUserRepository`: same annotations as interface; fixed `update()` return from `User|null` to `User` via `fresh() ?? $user` fallback
- `StructuredLogProcessor`: migrated from Monolog v2 array API to Monolog v3 `ProcessorInterface` — now receives and returns `LogRecord` via `with()`
- `StructuredJsonFormatter`: added `@return array<string, mixed>|null` to `mergeContext()`
- `ErrorBudgetTracker`: replaced bare `Redis::incr/del/get/ping()` facade calls (undefined static methods) with `Redis::connection()->method()` which has correct stubs
- `RouteServiceProvider`: replaced `$request->user()?->id ?? $ip` (nullsafe on `??` left-hand side) with explicit `$user !== null ? $user->id : $request->ip()`

**Python (mypy — 73 errors across 18 files)**
- `quality_score.py`: typed `SloAlert.to_dict()` → `dict[str, bool]`; `QualityScore.to_dict()` → `dict[str, object]`
- `events.py`: typed `DomainEvent.to_log_context()` → `dict[str, str]`
- `settings.py`: added `-> Settings` return type to `get_settings()`
- `encryption.py`: narrowed `type: ignore` to `[import-not-found]`; split `decrypt` return to give mypy explicit `bytes` annotation
- `aws_telemetry.py`: typed `_cloudwatch_client() -> object`; narrowed `type: ignore[import]` to `[import-untyped]`; typed `_put_metric` `extra_dimensions` as `list[dict[str, str]]`; typed `extract_trace_id` headers as `dict[str, str]`; added explicit `str | None` annotation for `raw` variable
- `telemetry.py`: fully typed `StructuredLogger` — `_redact`, `_entry`, `info`, `warning`, `error`, `audit` all have complete parameter and return annotations
- `database.py`: added `from typing import Generator`; typed `get_db() -> Generator[Session, None, None]`
- `audit.py`: added `# type: ignore[misc]` to `AuditLog(Base)` subclass; typed `changes` as `dict[str, str] | None`
- `models.py`: added `# type: ignore[misc]` to `User(Base)` subclass
- `service.py`: cast `saved_user.email/id/role` to `str()` when constructing `UserCreated` event; added `# type: ignore[assignment]` on `Column[str]` attribute assignments
- `rate_limiter.py`: changed `_redis_client()` return type from `# type: ignore[return]` to `-> object:`
- `metrics.py`: added `-> Response` return type to `get_metrics()`
- `brute_force.py`: same `_redis_client() -> object:` fix as rate_limiter
- `middleware.py`: imported `RequestResponseEndpoint`, `ASGIApp` from starlette; typed all three `dispatch` methods with `call_next: RequestResponseEndpoint` and `-> Response`; typed `RateLimitMiddleware.__init__` with `app: ASGIApp`
- `users.py`: added explicit return type annotations to all 6 route handlers (`-> UserResponse`, `-> list[UserResponse]`, `-> None`) with `# type: ignore[return-value]` for ORM→Pydantic returns
- `health.py`: typed `liveness() -> dict[str, str]`; `readiness() -> JSONResponse`; `detailed() -> JSONResponse`; changed `checks: dict` to `checks: dict[str, object]`; changed `detailed` parameter from `dict` to `User` dependency
- `deps.py`: added `require_role(role: str) -> Callable[..., User]` dependency factory for role-based access control
- `main.py`: wrapped `get_metrics` in `async def metrics_endpoint` to satisfy Starlette's async route signature; added `-> JSONResponse` to `api_error_handler`; fixed `auth.py` `Column[str]` → `str` casts on `user.id`, `user.hashed_password`, `user.role`

## [1.0.0] - 2026-02-10

### Added
- Initial release of three ISO 27001-compliant REST API blueprints
- FastAPI (Python) implementation with full type annotations, JWT auth, RBAC, rate limiting, audit logging, field encryption, CloudWatch telemetry, error budget tracking, and quality score
- Laravel 12 (PHP) implementation with Eloquent ORM, Sanctum, structured logging, brute-force protection, and DDD architecture
- Symfony 7.2 (PHP) implementation with Doctrine ORM, Lexik JWT, structured logging, and full security event subscribers
- Shared ISO 27001 controls: A.9 (access control), A.10 (cryptography), A.12 (audit/logging), A.17 (availability/SLO)
- Docker Compose setups for all three frameworks
- PHPStan level 8 static analysis for PHP projects
- mypy strict mode for Python project
- CI/CD pipeline definitions
