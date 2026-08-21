.PHONY: help up down restart logs ps build pull shell-php shell-db db-cli test composer-install tunnel-up tunnel-down tunnel-logs network-recover

help:
	@printf "Available targets:\n"
	@printf "  up               Start core stack (php, nginx, db, mailpit)\n"
	@printf "  down             Stop core stack\n"
	@printf "  restart          Restart core stack\n"
	@printf "  build            Build services\n"
	@printf "  pull             Pull upstream images\n"
	@printf "  logs             Tail all logs\n"
	@printf "  ps               Show service status\n"
	@printf "  shell-php        Open bash shell in php container\n"
	@printf "  shell-db         Open shell in db container\n"
	@printf "  db-cli           Open MariaDB client as root\n"
	@printf "  composer-install Install PHP deps (repo root)\n"
	@printf "  test             Run PHPUnit tests\n"
	@printf "  tunnel-up        Start cloudflared profile\n"
	@printf "  tunnel-down      Stop cloudflared profile\n"
	@printf "  tunnel-logs      Tail cloudflared logs\n"
	@printf "  network-recover  Recover stale network state\n"

up:
	docker compose up -d --build

down:
	docker compose down

restart: down up

build:
	docker compose build

pull:
	docker compose pull

logs:
	docker compose logs -f

ps:
	docker compose ps

shell-php:
	docker compose exec php bash

shell-db:
	docker compose exec db sh

db-cli:
	docker compose exec db mariadb -uroot -p$$DB_ROOT_PASSWORD

composer-install:
	docker compose exec php bash -lc "composer install"

test:
	docker compose exec php bash -lc "./vendor/bin/phpunit"

tunnel-up:
	docker compose --profile cloudflared up -d --build

tunnel-down:
	docker compose --profile cloudflared down

tunnel-logs:
	docker compose --profile cloudflared logs -f cloudflared

network-recover:
	docker compose --profile cloudflared down
	docker network prune -f
	docker compose --profile cloudflared up -d --build
