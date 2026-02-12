# TODO

## Done ✓

- [x] Fix all CI failures — Symfony composer allow-plugins, Laravel PHPStan, Python mypy 73 errors
- [x] Fix outdated tests — Symfony `RateLimitSubscriberTest` (missing 3rd constructor arg), FastAPI `test_rate_limiter.py` (rewritten to match actual API)
- [x] Add Larastan to Laravel — `larastan/larastan ^3.0`, `phpstan/phpstan ^2.1`, updated `phpstan.neon`
- [x] Fix intelephense P1009 — added `barryvdh/laravel-ide-helper ^3.1`; `php artisan ide-helper:generate` wired into `make setup-laravel`
- [x] Add `rules/iso27001-rules.yaml` — 16-rule compliance contract mapping every ISO 27001 control to its implementation file in all three projects; paths verified against filesystem
- [x] Add `rules/check_rules.py` — validates registry against filesystem; wired into CI and `make check-rules`
- [x] Add `deptrac.yaml` to Symfony and Laravel — DDD layer boundary enforcement
- [x] Add `.importlinter` to FastAPI — same DDD boundary contract in Python
- [x] Add `.pre-commit-config.yaml` — gitleaks, ruff, pint, phpstan across all three projects
- [x] Expand `.gitignore` files — root, Symfony, Laravel all updated
- [x] CI hardened — gitleaks secret-scan job, rule-registry job, deptrac + import-linter steps per project
- [x] Makefile — `check-layers`, `check-rules` targets; `ide-helper:generate` in `setup-laravel`

---

## Roadmap

### High priority

- [ ] **Validate deptrac configs** — run `vendor/bin/deptrac analyse` in both PHP projects after `composer install` and fix any collector pattern mismatches
- [ ] **Symfony PHPStan framework extension** — add `phpstan/phpstan-symfony ^1.0` to `require-dev` and `phpstan.neon`; gives Symfony-aware analysis on par with Larastan

### Medium priority

- [ ] **OpenAPI specs + Spectral** — add `openapi.yaml` per project and a root `.spectral.yaml` ruleset enforcing auth-required, rate-limit headers, and consistent error shape
- [ ] **ADR directory** — `docs/adr/` with decisions for JWT strategy, DDD layering, Redis fallback pattern, log schema

### Low priority

- [ ] Move roadmap to issue tracker and delete this file
