.PHONY: up down hooks

up:
	docker compose up -d

down:
	docker compose down

hooks:
	git config core.hooksPath .githooks

.PHONY: backup restore

backup:
	docker compose exec -u www-data app php artisan app:backup

restore:
	@test -n "$(FOLDER)" || { echo "Usage: make restore FOLDER=<folder>"; exit 1; }
	docker compose stop app
	docker compose run --rm --no-deps --entrypoint "" -u www-data app php artisan app:restore "$(FOLDER)" || { docker compose start app; exit 1; }
	docker compose start app
