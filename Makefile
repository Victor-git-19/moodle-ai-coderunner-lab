.PHONY: setup up down restart logs check smoke test reset

setup:
	./scripts/create-env.sh

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f --tail=200

check:
	./scripts/check.sh

smoke:
	./scripts/smoke-test.sh

test:
	docker compose run --rm --no-deps -e PYTHONPATH=/app ai-service pytest -q
	docker compose exec -T moodle php /var/www/html/public/local/aicodehelper/tests/cli_test.php

reset:
	@if [ "$(CONFIRM)" != "yes" ]; then \
		echo "Data was not deleted. Run: make reset CONFIRM=yes"; \
		exit 1; \
	fi
	docker compose down -v
