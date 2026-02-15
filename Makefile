.PHONY: help up down logs \
        setup-php setup-python setup-laravel setup-nestjs \
        test-php test-python test-laravel \
        migration-php migration-laravel migration-nestjs \
        db-reset check-security check-static check-layers check-rules clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ── Docker ──────────────────────────────────────────────────────────────────

up: ## Start Docker containers (all stacks)
	cd iso27001-fastapi && docker-compose up -d
	cd iso27001-symfony && docker-compose up -d
	cd iso27001-laravel && docker-compose up -d
	cd iso27001-nestjs && docker-compose up -d

down: ## Stop all Docker containers
	cd iso27001-fastapi && docker-compose down
	cd iso27001-symfony && docker-compose down
	cd iso27001-laravel && docker-compose down
	cd iso27001-nestjs && docker-compose down

logs: ## View Docker logs (all stacks)
	docker-compose -f iso27001-fastapi/docker-compose.yml logs -f &
	docker-compose -f iso27001-symfony/docker-compose.yml logs -f &
	docker-compose -f iso27001-laravel/docker-compose.yml logs -f &
	docker-compose -f iso27001-nestjs/docker-compose.yml logs -f

# ── Setup ────────────────────────────────────────────────────────────────────

setup-php: ## Install Symfony PHP dependencies and generate JWT keys
	cd iso27001-symfony && composer install
	cd iso27001-symfony && chmod +x jwt_setup.sh && ./jwt_setup.sh

setup-laravel: ## Install Laravel PHP dependencies, generate app key, and generate IDE helper stubs
	cd iso27001-laravel && composer install
	cd iso27001-laravel && php artisan key:generate
	cd iso27001-laravel && php artisan ide-helper:generate

setup-python: ## Install FastAPI Python dependencies
	cd iso27001-fastapi && pip install -e .[dev]

setup-nestjs: ## Install NestJS Node.js dependencies
	cd iso27001-nestjs && npm install

# ── Tests ─────────────────────────────────────────────────────────────────────

test-php: ## Run Symfony (PHP) tests
	cd iso27001-symfony && php bin/phpunit

test-laravel: ## Run Laravel (PHP) tests
	cd iso27001-laravel && php artisan test

test-python: ## Run FastAPI (Python) tests
	cd iso27001-fastapi && pytest

# ── Migrations ───────────────────────────────────────────────────────────────

migration-php: ## Run Symfony Doctrine migrations
	cd iso27001-symfony && php bin/console doctrine:migrations:migrate --no-interaction

migration-laravel: ## Run Laravel Eloquent migrations
	cd iso27001-laravel && php artisan migrate --no-interaction

migration-nestjs: ## Run NestJS TypeORM migrations (synchronize)
	cd iso27001-nestjs && npm run migration:run

# ── Database reset ───────────────────────────────────────────────────────────

db-reset: ## Reset all databases (Drop & Create & Migrate)
	cd iso27001-symfony && php bin/console doctrine:database:drop --force --if-exists
	cd iso27001-symfony && php bin/console doctrine:database:create
	make migration-php
	cd iso27001-laravel && php artisan migrate:fresh

# ── Security audits (A.14) ───────────────────────────────────────────────────

check-security: ## Run security audits on all stacks (composer audit + pip-audit)
	cd iso27001-symfony && composer audit
	cd iso27001-laravel && composer audit
	cd iso27001-fastapi && pip-audit

check-static: ## Run static analysis on all stacks (PHPStan level 8 + mypy strict)
	cd iso27001-symfony && vendor/bin/phpstan analyse --no-progress
	cd iso27001-laravel && vendor/bin/phpstan analyse --no-progress
	cd iso27001-fastapi && mypy app

check-layers: ## Enforce DDD layer boundaries (deptrac PHP + import-linter Python)
	cd iso27001-symfony && vendor/bin/deptrac analyse
	cd iso27001-laravel && vendor/bin/deptrac analyse
	cd iso27001-fastapi && lint-imports

check-rules: ## Verify every rule in rules/iso27001-rules.yaml maps to an existing file
	@python3 rules/check_rules.py

# ── Cleanup ───────────────────────────────────────────────────────────────────

clean: ## Clean up build artifacts
	rm -rf iso27001-symfony/vendor iso27001-symfony/var/cache
	rm -rf iso27001-laravel/vendor iso27001-laravel/bootstrap/cache
	rm -rf iso27001-fastapi/*.egg-info
	rm -rf iso27001-nestjs/node_modules iso27001-nestjs/dist
