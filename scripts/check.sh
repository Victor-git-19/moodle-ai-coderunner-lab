#!/usr/bin/env bash
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f .env ]]; then
    echo "Missing .env. Run ./scripts/create-env.sh first." >&2
    exit 1
fi

env_value() {
    awk -F= -v key="$1" '$1 == key {sub(/^[^=]*=/, ""); print; exit}' .env
}

MOODLE_URL="$(env_value MOODLE_URL)"
OLLAMA_MODEL="$(env_value OLLAMA_MODEL)"
failures=0

report() {
    local label="$1"
    shift
    if "$@" >/dev/null 2>&1; then
        echo "$label: OK"
    else
        echo "$label: FAIL"
        failures=$((failures + 1))
    fi
}

healthy_container() {
    local service="$1"
    local id status
    id="$(docker compose ps -q "$service")"
    [[ -n "$id" ]] || return 1
    status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$id")"
    [[ "$status" == "healthy" || "$status" == "running" ]]
}

check_moodle() {
    healthy_container moodle && curl -fsSL --max-time 15 "${MOODLE_URL%/}/login/index.php"
}

check_jobe() {
    healthy_container jobe && docker compose exec -T jobe python3 -c \
        "import urllib.request; urllib.request.urlopen('http://localhost/jobe/index.php/restapi/languages', timeout=5)"
}

check_ai() {
    healthy_container ai-service && docker compose exec -T ai-service python -c \
        "import urllib.request; urllib.request.urlopen('http://localhost:8000/health', timeout=5)"
}

check_ollama() {
    healthy_container ollama && docker compose exec -T ollama ollama show "$OLLAMA_MODEL"
}

report "Database" healthy_container db
report "Moodle" check_moodle
report "Jobe" check_jobe
report "AI service" check_ai
report "Ollama" check_ollama

exit "$failures"

