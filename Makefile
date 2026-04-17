include .env

DC = docker compose
PHP = $(DC) exec -u www-data php
DB = $(DC) exec db
CI = composer install --prefer-dist --no-progress --no-interaction
CIP = composer install --no-progress --no-interaction --no-dev --optimize-autoloader --prefer-dist --classmap-authoritative

up:
	@$(DC) up -d --build --remove-orphans
	@$(PHP) $(CI)
	@$(PHP) php artisan key:generate
	@$(PHP) php artisan migrate --force
	@$(PHP) php artisan storage:link --force
	@$(PHP) php artisan optimize:clear
	@$(PHP) php artisan optimize

ps:
	@$(DC) ps

logs:
	@$(DC) logs -f

down:
	@$(DC) down --rmi=local --remove-orphans

bash:
	@$(PHP) bash

psql:
	@$(DB) psql -U $(DB_USERNAME) -d $(DB_DATABASE)

ci:
	@$(PHP) $(CI)

cip:
	@$(PHP) $(CIP)

composer: ## example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(PHP) composer $(c)

dump:
	@cat ./../$(COMPOSE_PROJECT_NAME).sql | $(DC) exec -T db psql -U $(COMPOSE_PROJECT_NAME) -d $(COMPOSE_PROJECT_NAME)

pull:
	@$(DC) pull $(IMAGE)
	@docker volume rm -f backend
	@$(DC) up --force-recreate --build -d --remove-orphans nginx php reverb
	@$(PHP) $(CIP)
	@$(PHP) php artisan migrate --force
	@$(PHP) php artisan storage:link --if-not-exists
	@$(PHP) php artisan optimize:clear
	@$(PHP) php artisan optimize
	@docker image prune -f

test:
	@$(DB) psql -U $(DB_USERNAME) -d postgres -c "CREATE DATABASE widget_test" >/dev/null 2>&1 || true
	@$(PHP) php artisan test
