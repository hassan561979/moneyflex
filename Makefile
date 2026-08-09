.DEFAULT_GOAL := help
COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T app

# Build the image with the host user's ids so bind-mounted files stay writable.
export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: help
help: ## Show the available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

.PHONY: init
init: ## First-time setup: .env, build, start, migrate, seed
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) up -d
	@$(MAKE) --no-print-directory wait
	$(EXEC) php artisan key:generate --force
	@$(MAKE) --no-print-directory migrate
	@$(MAKE) --no-print-directory seed
	@echo "\nAPI ready on http://localhost:$${APP_HOST_PORT:-8080}/api/v1/health"

.PHONY: up
up: ## Start the stack
	@test -f .env || cp .env.example .env
	$(COMPOSE) up -d --build
	@$(MAKE) --no-print-directory wait

.PHONY: down
down: ## Stop the stack (volumes are kept)
	$(COMPOSE) down

.PHONY: destroy
destroy: ## Stop the stack and delete all volumes
	$(COMPOSE) down -v

.PHONY: wait
wait: ## Block until the API answers its health check
	@printf "waiting for the stack"
	@for i in $$(seq 1 60); do \
		if curl -fsS "http://localhost:$${APP_HOST_PORT:-8080}/api/v1/health" >/dev/null 2>&1; then \
			echo " ok"; exit 0; \
		fi; \
		printf "."; sleep 2; \
	done; \
	echo " timed out"; $(COMPOSE) logs --tail=40 app nginx; exit 1

.PHONY: logs
logs: ## Tail the application logs
	$(COMPOSE) logs -f app nginx

.PHONY: shell
shell: ## Open a shell inside the app container
	$(COMPOSE) exec app sh

.PHONY: migrate
migrate: ## Run database migrations
	$(EXEC) php artisan migrate --force

.PHONY: fresh
fresh: ## Drop everything and re-migrate with seed data
	$(EXEC) php artisan migrate:fresh --seed --force

.PHONY: seed
seed: ## Seed the database
	$(EXEC) php artisan db:seed --force

.PHONY: test
test: ## Run the test suite
	$(EXEC) php artisan test

.PHONY: lint
lint: ## Check code style
	$(EXEC) ./vendor/bin/pint --test

.PHONY: fix
fix: ## Apply code style fixes
	$(EXEC) ./vendor/bin/pint

.PHONY: swagger
swagger: ## Regenerate the OpenAPI documentation
	$(EXEC) php artisan l5-swagger:generate
