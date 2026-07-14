#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Завершить развёртывание с коротким понятным сообщением.
fail() {
    echo "Error: $*" >&2
    exit 1
}

# Читаем .env как данные, а не выполняем его через source.
env_value() {
    awk -F= -v key="$1" '$1 == key {sub(/^[^=]*=/, ""); print; exit}' .env
}

command -v docker >/dev/null 2>&1 || fail "Docker is not installed."
docker info >/dev/null 2>&1 || fail "Docker daemon is unavailable or the current user has no access."
docker compose version >/dev/null 2>&1 || fail "Docker Compose Plugin is not installed."
if [[ ! -f .env ]]; then
    echo "Creating .env and generated passwords..."
    ./scripts/create-env.sh
fi

# До запуска проверяем порт и минимальные ресурсы слабого учебного сервера.
MOODLE_PORT="$(env_value MOODLE_PORT)"
MOODLE_URL="$(env_value MOODLE_URL)"
[[ "$MOODLE_PORT" =~ ^[0-9]+$ ]] || fail "MOODLE_PORT must be a number."

if command -v ss >/dev/null 2>&1 && ss -ltnH "sport = :$MOODLE_PORT" | grep -q .; then
    if [[ -z "$(docker compose ps -q moodle 2>/dev/null)" ]]; then
        fail "Port $MOODLE_PORT is already in use."
    fi
fi

memory_mb="$(awk '/MemTotal/ {print int($2 / 1024)}' /proc/meminfo)"
disk_mb="$(df -Pm "$ROOT_DIR" | awk 'NR == 2 {print $4}')"
echo "Memory: ${memory_mb} MB"
echo "Free disk space: ${disk_mb} MB"
(( memory_mb >= 3500 )) || fail "At least 4 GB RAM is required."
if (( memory_mb < 4096 )); then
    echo "Warning: the server reports less than 4096 MB; the small Ollama model may respond slowly."
fi
(( disk_mb >= 20480 )) || fail "At least 20 GB of free disk space is required."

echo "Building and starting services..."
docker compose up -d --build

# Модель загружается только при первом запуске, поэтому ожидание может занять время.
ready=false
for attempt in {1..80}; do
    if ./scripts/check.sh; then
        ready=true
        break
    fi
    echo "Services or the Ollama model are not ready yet ($attempt/80)."
    sleep 15
done

[[ "$ready" == true ]] || fail "Services did not become ready. Run: docker compose logs"

./scripts/smoke-test.sh

echo
echo "Deployment completed: $MOODLE_URL"
echo "Moodle admin: $(env_value MOODLE_ADMIN_USER)"
echo "Moodle admin password: see MOODLE_ADMIN_PASSWORD in $ROOT_DIR/.env"
