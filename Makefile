.PHONY: build up down test shell clean

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

test: up
	docker compose exec php bash run-tests.sh

shell: up
	docker compose exec php bash

clean:
	docker compose down -v
	rm -rf vendor composer.lock config/app.php config/general.php config/project storage
