# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-02-17

### Fixed

**Laravel — CI test runner**
- `iso27001-laravel/phpunit.xml` renamed to `phpunit.xml.dist` — `php artisan test` (PHPUnit 11) resolves `phpunit.xml.dist` before `phpunit.xml`; without it CI was crashing with "Could not read XML" because Composer's Laravel skeleton was dropping an empty scaffold file under that name during `composer install`

**Symfony — CI `composer install` crash**
- Root `.gitignore`: changed `.env` → `/.env` — the broad pattern was preventing `iso27001-symfony/.env` from being tracked; the file is Symfony's committed non-secret defaults (analogous to `.env.example`) and must be present for `bootEnv()` to succeed
- `.github/workflows/ci.yml`: added `APP_ENV: test` to the `composer install` step in the `symfony-tests` job — the `post-install-cmd` hook runs `cache:clear` via `symfony-cmd`, which boots the Symfony kernel; without `APP_ENV` set it defaulted to `dev`, tried to load `.env` + `.env.dev.local`, and crashed with `PathException` before the file was available

**Secret scan (gitleaks)**
- `iso27001-symfony/.env.dev`: replaced high-entropy hex `APP_SECRET=8d84ba2b7a4853da852c3a771b6ea0f1` with low-entropy placeholder `dev-only-change-me-in-env-local` — gitleaks `generic-api-key` rule triggers on entropy; the value was a committed dev placeholder with no production use

### Changed

**README**
- Added badges section: CI status, ISO 27001, language/framework versions (Python, FastAPI, PHP, Symfony, Laravel, TypeScript, NestJS, Java, Spring Boot, Go, Elixir), infrastructure (Terraform, AWS, Docker, PostgreSQL, Redis, Prometheus)
- Added Documentation table linking CHANGELOG, CHECKLIST, infra README, and all five ADRs
- Added CHANGELOG link in CI Status section

### Added

**Cloud Infrastructure layer — AWS + Terraform**
- `infra/terraform/modules/vpc/` — VPC (`10.0.0.0/16`), 2 public + 2 private subnets across 2 AZs, IGW, NAT gateway, route tables, 4 security groups (ALB, ECS, RDS, Redis) (A.9)
- `infra/terraform/modules/rds/` — RDS PostgreSQL 16, `storage_encrypted = true`, `backup_retention_period = 7`, `deletion_protection = true`, master password auto-generated and stored in Secrets Manager (A.10, A.17)
- `infra/terraform/modules/elasticache/` — ElastiCache Redis 7 replication group, `at_rest_encryption_enabled = true`, `transit_encryption_enabled = true`, auth token in Secrets Manager, `automatic_failover_enabled` (A.10, A.17)
- `infra/terraform/modules/secrets/` — one Secrets Manager secret per stack, `recovery_window_in_days = 7`, `secret_values` is `sensitive` (A.10)
- `infra/terraform/modules/ecr/` — ECR repository per stack, `image_tag_mutability = IMMUTABLE`, `scan_on_push = true`, lifecycle policy (keep last 10 tagged + expire untagged after 7 days) (A.14)
- `infra/terraform/modules/ecs-service/` — ECS Fargate service; IAM task execution role (least-privilege: own secret + ECR + CloudWatch only); IAM task role (no permissions by default); CloudWatch log group `retention_in_days = 365`; secrets injected via `secrets:` block (not `environment:`); ALB target group + host-based listener rule; in-container health check (A.9, A.10, A.12)
- `infra/terraform/environments/staging/` — root module wiring shared VPC + RDS + ElastiCache + ECS cluster + ALB and 7× `for_each` over secrets / ECR repos / ECS services for all stacks; `terraform.tfvars.example` documenting all required inputs
- `infra/.gitignore` — excludes `.tfstate`, `.tfvars`, `.terraform/`, `.tfplan`
- `infra/terraform/README.md` — module reference, port mapping table, ISO 27001 controls table, quick-start guide, ECR push commands
- `docs/adr/0005-cloud-infrastructure.md` — ADR documenting choice of AWS ECS Fargate + plain Terraform HCL over CDK, GCP Cloud Run, Kubernetes, and Pulumi

**CI**
- `terraform-validate` job: `terraform fmt -check -recursive`, per-module `init -backend=false` + `validate`, staging environment `validate`

**Makefile**
- `infra-fmt` — `terraform fmt -recursive infra/terraform/`
- `infra-validate` — validates all modules + staging environment without AWS credentials
- `infra-plan` — `terraform plan` for staging (requires AWS credentials)

**Rule registry**
- 9 new infra rules added to `rules/iso27001-rules.yaml`: `A9-IAM-LEAST-PRIVILEGE`, `A9-SECURITY-GROUPS`, `A10-RDS-ENCRYPTION`, `A10-ELASTICACHE-ENCRYPTION`, `A10-SECRETS-MANAGER`, `A12-CLOUDWATCH-RETENTION`, `A14-ECR-SCAN-ON-PUSH`, `A17-RDS-BACKUPS`, `A17-ELASTICACHE-FAILOVER`
- `rules/check_rules.py`: added `"infra": ROOT / "infra"` to `STACK_ROOTS`; `check-rules` now validates Terraform files (`25 rules verified`)

**README**
- Cloud Infrastructure section with module tree, controls table, and Makefile shortcuts
- ADR table updated to include ADR 0005

**Elixir/Phoenix stack** (completed from previous session)
- All 7 `operation-description` Spectral warnings resolved in `openapi.yaml`
- All 4 ADRs (0001–0004) updated to reference all 7 stacks
- README tables updated for Go/Gin and Elixir/Phoenix across all sections

---

## [1.4.5] - 2026-02-13

### Fixed

**FastAPI — unit test failures (4 tests)**
- `app/infrastructure/error_budget.py`: `ErrorBudgetSnapshot.budget_exhausted` was evaluated against the pre-`round()` value of `budget_consumed_pct`; floating-point division of `1/1000 ÷ (1−0.999)` yields a result slightly below `100.0`, causing `>= 100.0` to return `False` even though the rounded display value is `100.0`; fix: round first into a named variable, then compare (`budget_consumed_pct_rounded >= 100.0`)
- `app/infrastructure/quality_score.py`: `QualityScore.composite()` weights sum to `0.95` (5% reserved for a future pillar), so a perfect input returned `0.95` instead of `1.0`; fix: divide raw weighted sum by `_WEIGHT_SUM = 0.95` to normalize — perfect inputs now return exactly `1.0` and the zero-security gate test still holds (`0.55 / 0.95 ≈ 0.578 < 0.70`)
- `pyproject.toml`: added `bcrypt>=3.2,<4.0` — `passlib 1.7.4` probes `bcrypt.__about__.__version__` which was removed in `bcrypt 4.0`; pinning to the compatible range restores `hash_password` and `verify_password` in CI

**Secret scan (gitleaks)**
- SARIF report returned `"results": []` — zero findings; all 46 pytest tests pass

## [1.4.4] - 2026-02-13

### Fixed

**FastAPI — A.14 layer boundary (import-linter, 3 broken contracts)**
- `app/domain/events.py`: extracted `DomainEvent`, `EventBus`, and `event_bus` singleton into the domain layer — eliminates the `domain → core` dependency at the event bus
- `app/domain/exceptions.py`: extracted `DomainError` and `ConflictError` into the domain layer — eliminates the `domain → core` dependency on exceptions
- `app/domain/persistence.py`: extracted SQLAlchemy `Base = declarative_base()` into the domain layer — eliminates the `domain → core` dependency on the database module
- `app/domain/users/events.py`: imports `DomainEvent` base from `app.domain.events` (was `app.core.events`)
- `app/domain/users/models.py`: imports `Base` from `app.domain.persistence` (was `app.core.database`)
- `app/domain/users/service.py`: imports `ConflictError` from `app.domain.exceptions` and `EventBus`/`event_bus` from `app.domain.events` (both were `app.core.*`)
- `app/core/events.py`: converted to a re-export shim for backward-compatible infrastructure/application consumers
- `app/core/database.py`: re-exports `Base` from `app.domain.persistence` for infrastructure consumers
- `app/main.py`: added `DomainConflictError` exception handler mapping `app.domain.exceptions.ConflictError` → HTTP 409
- All 3 import-linter contracts now pass: "Domain must not import core/infrastructure/api", "Infrastructure must not import api", "Strict top-down layer ordering"

**FastAPI — missing dependency**
- `pyproject.toml`: changed `pydantic>=2.6.0` → `pydantic[email]>=2.6.0` — `EmailStr` used in `app/domain/users/schemas.py` requires the `email-validator` package bundled in the `pydantic[email]` extra; its absence caused `ModuleNotFoundError` during test collection

## [1.4.3] - 2026-02-13

### Fixed

**Symfony — tests (12 errors)**
- `.env.test`: added `LOCK_DSN=flock` — `symfony/lock` requires the env var at container boot; `flock` uses the filesystem so no Redis is needed in CI
- Introduced `App\Audit\AuditServiceInterface` — `AuditService` is declared `final readonly`, making it impossible for PHPUnit to create a mock proxy; the interface is now the type-hint in `UserService` and `UserRegistrationTest`
- Introduced `App\RateLimiter\RateLimiterFactoryInterface` and `App\RateLimiter\RateLimiterFactoryAdapter` — `Symfony\Component\RateLimiter\RateLimiterFactory` is `final`, so it cannot be doubled; the adapter wraps the concrete factory and is wired in `services.yaml` via three named service definitions (`AnonymousApiLimiterFactory`, `LoginIpLimiterFactory`, `WriteApiLimiterFactory`); `RateLimitSubscriber` and `RateLimitSubscriberTest` now depend on the interface

**Laravel — static analysis (deptrac A.14, 4 violations)**
- `AuditUserCreated`, `AuditUserUpdated`, `AuditUserDeleted`: replaced direct `App\Infrastructure\Audit\AuditService` import with `App\Domain\Shared\Contracts\AuditServiceInterface` — Application layer listeners must not depend on Infrastructure
- `TelemetryDomainEventListener`: replaced `App\Infrastructure\Telemetry\MetricsCollector` with `App\Domain\Shared\Contracts\MetricsCollectorInterface` for the same reason
- Created `app/Domain/Shared/Contracts/AuditServiceInterface.php` and `MetricsCollectorInterface.php` — shared domain contracts for cross-cutting concerns
- `AuditService` and `MetricsCollector` now implement their respective domain interfaces
- `AppServiceProvider`: bound both interfaces to their Infrastructure implementations via `$this->app->bind()`

**FastAPI — mypy strict**
- `aws_telemetry.py`: broadened `# type: ignore` on both `aws_xray_sdk` imports from `[import-not-found]` to `[import-untyped,import-not-found]` — mypy raises `import-untyped` when the package is installed without stubs and `import-not-found` when it is absent; covering both codes eliminates the "unused ignore" errors regardless of install state

## [1.4.0] - 2026-02-12

### Fixed

**Symfony — dependencies**
- Migrated `qossmic/deptrac` → `deptrac/deptrac ^2.0` (abandoned package replaced with official successor)
- Added `symfony/yaml 7.3.*`, `symfony/lock 7.3.*` as explicit `require` entries — were previously implicit transitive deps that disappeared after the deptrac swap
- Bumped all Symfony packages from `7.2.*` to `7.3.*` to resolve CVE-2025-64500 (`symfony/http-foundation` — incorrect PATH_INFO parsing leading to limited authorization bypass, severity: high)
- Added `App\Infrastructure\Telemetry\CloudWatchEmitter` explicit service binding in `config/services.yaml` — scalar constructor args `$serviceName` and `$environment` cannot be autowired; resolves `DefinitionErrorExceptionPass` DI compile error

**Symfony — static analysis**
- `CloudWatchEmitter::buildClient()`: replaced `\Aws\CloudWatch\CloudWatchClient::class` FQCN reference with string variable `$fqcn` — eliminates Intelephense P1009 "Undefined type" errors on lines 114 and 122 without changing runtime behaviour

**Laravel — dependencies**
- Added `deptrac/deptrac 2.0` to `require-dev` and updated `composer.lock` — package was declared in `composer.json` but missing from the lock file, causing `composer install` to exit with code 4

**FastAPI — mypy (19 errors across 5 files)**
- `aws_telemetry.py`: changed `_cloudwatch_client()` return type `object` → `Any`; narrowed both `import-untyped` ignores to `import-not-found` (for `boto3` and `aws_xray_sdk`); typed `self._cw: Any` so `.put_metric_data()` resolves
- `rate_limiter.py`: changed `_redis_client()` return type `object` → `Any`; narrowed `import-untyped` → `import-not-found` (for `redis`) so `.eval()` resolves
- `brute_force.py`: same `object` → `Any` and `import-untyped` → `import-not-found` fixes; resolves `.get/.incr/.expire/.set/.delete` attr errors
- `middleware.py`: replaced `response.headers.pop("server", None)` with `if "server" in ...: del ...` — `MutableHeaders` supports `del` but not `.pop()`; same for `x-powered-by`
- `users.py`: removed 4 unused `# type: ignore[return-value]` comments — mypy strict already verified those returns correctly

## [1.4.2] - 2026-02-12

### Fixed

**Laravel — static analysis (PHPStan level 8, 15 errors)**
- `UserRepositoryInterface`, `EloquentUserRepository`: tightened `LengthAwarePaginator<User>` → `LengthAwarePaginator<int, User>` (two type parameters required by Larastan 3.x generics)
- `UserService::listUsers()`: added `@return LengthAwarePaginator<int, User>` docblock to match updated interface
- `phpstan.neon`: added `identifier: missingType.generics` to `ignoreErrors` — suppresses `HasFactory` missing generic type (`TFactory`) since no `UserFactory` is defined
- `MetricsController`: replaced `\Prometheus\*` FQCN references with string-variable class names + `@phpstan-ignore-next-line` — same pattern as Symfony; eliminates "Instantiated class not found" errors for optional dependency
- `RoleMiddleware`, `RouteServiceProvider`: added `/** @var User|null $user */` cast after `$request->user()` — resolves `App\Models\User` unknown class errors (Larastan defaults to wrong namespace; explicit cast forces `App\Domain\User\Models\User`)
- `UserResource`: added `@mixin User` to class docblock — resolves 5 "Access to undefined property" errors (`id`, `role`, `deleted_at`, `created_at`, `updated_at`) from `JsonResource` magic property delegation
- `CloudWatchEmitter`: replaced `env()` calls with `config()` (`services.aws.cloudwatch_namespace`, `services.aws.region`) — resolves Larastan "env() called outside config" errors; replaced `\Aws\CloudWatch\CloudWatchClient::class` FQCN reference with string variable `$clientClass` to eliminate "unknown class" static analysis error
- Added `config/services.php` with `services.aws.region` and `services.aws.cloudwatch_namespace` keys backed by `AWS_DEFAULT_REGION` / `AWS_CLOUDWATCH_NAMESPACE` env vars

**FastAPI — mypy strict (5 errors)**
- `aws_telemetry.py`: reverted `boto3` and `aws_xray_sdk` ignores back to `import-not-found` — packages are optional (`[aws]` extras) and not installed in CI; `import-untyped` caused "unused ignore" errors when the modules are absent
- `rate_limiter.py`, `brute_force.py`: removed `# type: ignore[import-untyped]` from `redis` imports — `redis` is now a direct dependency with bundled type stubs, making the ignore comment unused

## [1.4.1] - 2026-02-12

### Added

**Symfony — static analysis**
- Added `phpstan/phpstan-symfony ^1.0` to `require-dev` — enables Symfony-aware PHPStan rules (container parameter types, service definitions, event subscribers, form types) on par with Larastan for Laravel
- Added `vendor/phpstan/phpstan-symfony/extension.neon` to `phpstan.neon` includes

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
