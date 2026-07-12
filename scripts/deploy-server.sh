#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

fail() {
    echo "Error: $*" >&2
    exit 1
}

env_value() {
    awk -F= -v key="$1" '$1 == key {sub(/^[^=]*=/, ""); print; exit}' .env
}

command -v docker >/dev/null 2>&1 || fail "Docker is not installed."
docker info >/dev/null 2>&1 || fail "Docker daemon is unavailable or the current user has no access."
docker compose version >/dev/null 2>&1 || fail "Docker Compose Plugin is not installed."
[[ -f .env ]] || fail "Missing .env. Run ./scripts/create-env.sh first."

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
(( memory_mb >= 4096 )) || fail "At least 4 GB RAM is required; 6 GB or more is recommended."
(( disk_mb >= 20480 )) || fail "At least 20 GB of free disk space is required."

echo "Building and starting services..."
docker compose up -d --build

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
