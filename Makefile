.PHONY: up down

up:
	docker compose up -d

down:
	docker compose down

.PHONY: backup restore

backup:
	docker compose exec app php artisan app:backup

restore:
	@test -n "$(FOLDER)" || { echo "Usage: make restore FOLDER=<folder>"; exit 1; }
	docker compose stop app
	docker compose run --rm --no-deps --entrypoint "" app php artisan app:restore "$(FOLDER)"
	docker compose start app
