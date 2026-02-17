.PHONY: help up down logs \
        setup-php setup-python setup-laravel setup-nestjs setup-springboot setup-gin setup-phoenix \
        test-php test-python test-laravel test-nestjs test-springboot test-gin test-phoenix \
        migration-php migration-laravel migration-nestjs migration-phoenix \
        db-reset check-security check-static check-layers check-rules check-openapi \
        infra-fmt infra-validate infra-plan clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ── Docker ──────────────────────────────────────────────────────────────────

up: ## Start Docker containers (all stacks)
	cd iso27001-fastapi && docker-compose up -d
	cd iso27001-symfony && docker-compose up -d
	cd iso27001-laravel && docker-compose up -d
	cd iso27001-nestjs && docker-compose up -d
	cd iso27001-springboot && docker-compose up -d
	cd iso27001-gin && docker-compose up -d
	cd iso27001-phoenix && docker-compose up -d

down: ## Stop all Docker containers
	cd iso27001-fastapi && docker-compose down
	cd iso27001-symfony && docker-compose down
	cd iso27001-laravel && docker-compose down
	cd iso27001-nestjs && docker-compose down
	cd iso27001-springboot && docker-compose down
	cd iso27001-gin && docker-compose down
	cd iso27001-phoenix && docker-compose down

logs: ## View Docker logs (all stacks)
	docker-compose -f iso27001-fastapi/docker-compose.yml logs -f &
	docker-compose -f iso27001-symfony/docker-compose.yml logs -f &
	docker-compose -f iso27001-laravel/docker-compose.yml logs -f &
	docker-compose -f iso27001-nestjs/docker-compose.yml logs -f &
	docker-compose -f iso27001-springboot/docker-compose.yml logs -f &
	docker-compose -f iso27001-gin/docker-compose.yml logs -f &
	docker-compose -f iso27001-phoenix/docker-compose.yml logs -f

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

setup-springboot: ## Install Spring Boot Maven dependencies
	cd iso27001-springboot && mvn dependency:go-offline -q

setup-gin: ## Download Go/Gin module dependencies and generate go.sum
	cd iso27001-gin && go mod tidy

setup-phoenix: ## Install Elixir/Phoenix dependencies and create database
	cd iso27001-phoenix && mix setup

# ── Tests ─────────────────────────────────────────────────────────────────────

test-php: ## Run Symfony (PHP) tests
	cd iso27001-symfony && php bin/phpunit

test-laravel: ## Run Laravel (PHP) tests
	cd iso27001-laravel && php artisan test

test-python: ## Run FastAPI (Python) tests
	cd iso27001-fastapi && pytest

test-nestjs: ## Run NestJS (Node.js) tests
	cd iso27001-nestjs && npm test

test-springboot: ## Run Spring Boot (Java) tests (includes ArchUnit layer check)
	cd iso27001-springboot && mvn test -B

test-gin: ## Run Go/Gin unit tests
	cd iso27001-gin && go test ./tests/... -v -race

test-phoenix: ## Run Elixir/Phoenix tests
	cd iso27001-phoenix && mix test test/unit

# ── Migrations ───────────────────────────────────────────────────────────────

migration-php: ## Run Symfony Doctrine migrations
	cd iso27001-symfony && php bin/console doctrine:migrations:migrate --no-interaction

migration-laravel: ## Run Laravel Eloquent migrations
	cd iso27001-laravel && php artisan migrate --no-interaction

migration-nestjs: ## Run NestJS TypeORM migrations (synchronize)
	cd iso27001-nestjs && npm run migration:run

migration-phoenix: ## Run Elixir/Phoenix Ecto migrations
	cd iso27001-phoenix && mix ecto.migrate

# ── Database reset ───────────────────────────────────────────────────────────

db-reset: ## Reset all databases (Drop & Create & Migrate)
	cd iso27001-symfony && php bin/console doctrine:database:drop --force --if-exists
	cd iso27001-symfony && php bin/console doctrine:database:create
	make migration-php
	cd iso27001-laravel && php artisan migrate:fresh

# ── Security audits (A.14) ───────────────────────────────────────────────────

check-security: ## Run security audits on all stacks (composer audit + pip-audit + npm audit + mvn + govulncheck)
	cd iso27001-symfony && composer audit
	cd iso27001-laravel && composer audit
	cd iso27001-fastapi && pip-audit
	cd iso27001-nestjs && npm audit --audit-level=high
	cd iso27001-springboot && mvn dependency-check:check -q
	cd iso27001-gin && govulncheck ./...
	cd iso27001-phoenix && mix deps.audit

check-static: ## Run static analysis on all stacks (PHPStan + mypy + tsc + checkstyle + go vet + mix compile)
	cd iso27001-symfony && vendor/bin/phpstan analyse --no-progress
	cd iso27001-laravel && vendor/bin/phpstan analyse --no-progress
	cd iso27001-fastapi && mypy app
	cd iso27001-nestjs && npx tsc --noEmit
	cd iso27001-springboot && mvn checkstyle:check -q
	cd iso27001-gin && go vet ./...
	cd iso27001-phoenix && mix compile --warnings-as-errors

check-layers: ## Enforce DDD layer boundaries (deptrac + import-linter + check-layers + ArchUnit + go build + mix compile)
	cd iso27001-symfony && vendor/bin/deptrac analyse
	cd iso27001-laravel && vendor/bin/deptrac analyse
	cd iso27001-fastapi && lint-imports
	cd iso27001-nestjs && npm run check:layers
	cd iso27001-springboot && mvn test -Dtest=LayerArchitectureTest -B
	cd iso27001-gin && go build ./...
	cd iso27001-phoenix && mix compile

check-rules: ## Verify every rule in rules/iso27001-rules.yaml maps to an existing file
	@python3 rules/check_rules.py

check-openapi: ## Lint all OpenAPI specs against .spectral.yaml (auth, rate-limit, error-shape rules)
	npx @stoplight/spectral-cli lint \
	  iso27001-fastapi/openapi.yaml \
	  iso27001-symfony/openapi.yaml \
	  iso27001-laravel/openapi.yaml \
	  iso27001-nestjs/openapi.yaml \
	  iso27001-springboot/openapi.yaml \
	  iso27001-gin/openapi.yaml \
	  iso27001-phoenix/openapi.yaml \
	  --ruleset .spectral.yaml

# ── Infrastructure (Terraform) ───────────────────────────────────────────────

infra-fmt: ## Format all Terraform files
	terraform fmt -recursive infra/terraform/

infra-validate: ## Validate all Terraform modules and staging environment
	@echo "Validating modules..."
	@for dir in infra/terraform/modules/*/; do \
	  echo "  → $$dir"; \
	  terraform -chdir="$$dir" init -backend=false -input=false -no-color > /dev/null; \
	  terraform -chdir="$$dir" validate -no-color; \
	done
	@echo "Validating environments/staging..."
	@terraform -chdir=infra/terraform/environments/staging init -backend=false -input=false -no-color > /dev/null
	@terraform -chdir=infra/terraform/environments/staging validate -no-color

infra-plan: ## Run terraform plan for staging (requires AWS credentials)
	cd infra/terraform/environments/staging && terraform plan

# ── Cleanup ───────────────────────────────────────────────────────────────────

clean: ## Clean up build artifacts
	rm -rf iso27001-symfony/vendor iso27001-symfony/var/cache
	rm -rf iso27001-laravel/vendor iso27001-laravel/bootstrap/cache
	rm -rf iso27001-fastapi/*.egg-info
	rm -rf iso27001-nestjs/node_modules iso27001-nestjs/dist
	rm -rf iso27001-springboot/target
	rm -f iso27001-gin/iso27001-gin
	rm -rf iso27001-phoenix/_build iso27001-phoenix/deps
