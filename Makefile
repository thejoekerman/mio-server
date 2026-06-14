# Include the internal makefile targets
-include vendor/move-elevator/makefile-tools/Makefile.internal

.PHONY: build
## Build and start the local server stack
build:
	docker compose up -d --build

.PHONY: up
## Start the local server stack
up:
	docker compose up -d

.PHONY: start
## Alias for up
start: up

.PHONY: stop
## Stop the local server stack
stop:
	docker compose stop

.PHONY: logs
## Follow backend and nginx logs
logs:
	docker compose logs -f backend nginx

.PHONY: install
## Install backend Composer dependencies
install:
	docker compose exec backend composer install

.PHONY: shell
## Open a shell in the backend container
shell:
	docker compose exec backend bash

.PHONY: console
## Run a Symfony console command in container
## Usage: make console debug:router
console:
	docker compose exec backend php bin/console $(Arguments)

.PHONY: command-in-backend
## Run a command in the backend container
## Usage: make command-in-backend composer install
command-in-backend:
	docker compose exec backend $(Arguments)

.PHONY: migrations-diff
## Generate a Doctrine migration diff
migrations-diff:
	docker compose exec backend php bin/console doctrine:migrations:diff

.PHONY: migrate
## Run Doctrine migrations
migrate:
	docker compose exec backend php bin/console doctrine:migrations:migrate

.PHONY: migrations-migrate
## Alias for migrate
migrations-migrate: migrate

.PHONY: test
## Run all tests and checks
test:
	@printf "\n\033[1;35m=== Phpcs ===\033[0m\n"
	@docker compose exec backend composer cs

	@printf "\n\033[1;36m=== Phpstan ===\033[0m\n"
	@docker compose exec backend composer phpstan

	@printf "\n\033[1;33m=== Phpunit ===\033[0m\n"
	@docker compose exec backend php bin/phpunit --testdox

	@printf "\n\033[1;32m=== TODO Check ===\033[0m\n"
	$(MAKE) todo

.PHONY: phpunit
## Run PHPUnit tests
phpunit:
	docker compose exec backend php bin/phpunit --testdox

.PHONY: cs
## Check coding standard (PSR-12)
cs:
	docker compose exec backend composer cs

.PHONY: cs-fix
## Auto-fix coding standard violations
cs-fix:
	docker compose exec backend composer cs:fix

.PHONY: phpstan
## Run PHPStan static analysis
phpstan:
	docker compose exec backend composer phpstan

.PHONY: qa
## Run all quality gates (coding standard + static analysis)
qa:
	docker compose exec backend composer qa

.PHONY: sync-token
## Create a sync token
## Usage: make sync-token you@example.com iPhone
sync-token:
	docker compose exec backend php bin/console app:sync-token:create $(Arguments)

.PHONY: list-users

list-users: ## List users
	docker compose exec backend php bin/console app:user:list

.PHONY: ai-usage
## Set ai usage for a user
## Usage: make ai-usage 123 yes
ai-usage:
	docker compose exec backend php bin/console app:user:ai-usage $(Arguments)

%::
	@true
