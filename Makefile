.PHONY: help build up start stop logs install shell console migrate migrations-diff test phpunit sync-token

help:
	@grep -Ehs '^[a-zA-Z_-]+:.*?## .*$$' Makefile Makefile.local | \
	awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build and start the local server stack
	docker compose up -d --build

up: ## Start the local server stack
	docker compose up -d

start: up ## Alias for up

stop: ## Stop the local server stack
	docker compose stop

logs: ## Follow backend and nginx logs
	docker compose logs -f backend nginx

install: ## Install backend Composer dependencies
	docker compose exec backend composer install

shell: ## Open a shell in the backend container
	docker compose exec backend bash

console: ## Run a Symfony console command with `make console command="debug:router"`
	docker compose exec backend php bin/console $(command)

command-in-backend: ## Run a command in the backend container with `make command-in-backend command="composer install"`
	docker compose exec backend $(command)

migrations-diff: ## Generate a Doctrine migration diff
	docker compose exec backend php bin/console doctrine:migrations:diff

migrate: ## Run Doctrine migrations
	docker compose exec backend php bin/console doctrine:migrations:migrate

migrations-migrate: migrate ## Alias for migrate

test: ## Run PHPUnit tests
	docker compose exec backend php bin/phpunit --testdox

phpunit: test ## Alias for test

sync-token: ## Create a sync token with `make sync-token command="you@example.com iPhone"`
	docker compose exec backend php bin/console app:sync-token:create $(command)
