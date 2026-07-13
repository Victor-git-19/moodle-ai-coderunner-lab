.PHONY: help setup build up down restart logs ps test check smoke reset

help:
	@echo "setup    создать .env"
	@echo "build    собрать образы"
	@echo "up       запустить проект"
	@echo "down     остановить проект"
	@echo "restart  перезапустить и дождаться сервисов"
	@echo "logs     показать логи"
	@echo "ps       показать состояние сервисов"
	@echo "test     запустить тесты"
	@echo "check    проверить сервисы"
	@echo "smoke    проверить реальные связи"
	@echo "reset    удалить данные только с CONFIRM=yes"

setup:
	./scripts/create-env.sh

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart db jobe ollama ai-service moodle
	docker compose up -d --wait --wait-timeout 180 db jobe ollama ai-service moodle

logs:
	docker compose logs -f --tail=200

ps:
	docker compose ps

check:
	./scripts/check.sh

smoke:
	./scripts/smoke-test.sh

test:
	docker build --target test -t moodle-ai-coderunner-lab-ai-test -f ai-service/Dockerfile .
	docker run --rm moodle-ai-coderunner-lab-ai-test
	docker compose exec -T moodle php /var/www/html/public/local/aicodehelper/tests/cli_test.php

reset:
	@if [ "$(CONFIRM)" != "yes" ]; then \
		echo "Data was not deleted. Run: make reset CONFIRM=yes"; \
		exit 1; \
	fi
	docker compose down -v
