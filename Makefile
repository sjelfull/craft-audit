.PHONY: build up down test test-postgres test-all shell clean ecs ecs-fix phpstan

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

test: up
	docker compose exec php bash run-tests.sh

test-postgres: up
	docker compose exec -e CRAFT_DB_DRIVER=pgsql -e CRAFT_DB_SERVER=postgres -e CRAFT_DB_PORT=5432 php bash run-tests.sh

test-all: test test-postgres

ecs: up
	docker compose exec php composer check-cs

ecs-fix: up
	docker compose exec php composer fix-cs

phpstan: up
	docker compose exec php composer phpstan

shell: up
	docker compose exec php bash

clean:
	docker compose down -v
	rm -rf vendor composer.lock config/app.php config/general.php config/project storage
