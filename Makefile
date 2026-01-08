.PHONY: build up down test shell clean ecs ecs-fix phpstan

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

test: up
	docker compose exec php bash run-tests.sh

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
