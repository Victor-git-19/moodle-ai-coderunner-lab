#!/usr/bin/env bash
set -euo pipefail

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

curl -fsSL --max-time 15 "${MOODLE_URL%/}/login/index.php" >/dev/null
echo "Moodle page: OK"

jobe_response="$(docker compose exec -T moodle curl -fsS \
    -H 'Content-Type: application/json' \
    --data-binary '{"run_spec":{"language_id":"python3","sourcefilename":"main.py","sourcecode":"print(\"Hello from Jobe\")\n"}}' \
    http://jobe/jobe/index.php/restapi/runs/)"

printf '%s' "$jobe_response" | docker compose exec -T ai-service python -c '
import json, sys
result = json.load(sys.stdin)
assert result.get("outcome") in (0, 15), result
assert result.get("stdout") == "Hello from Jobe\n", result
' >/dev/null
echo "Jobe Python execution: OK"

docker compose exec -T moodle curl -fsS http://ai-service:8000/health >/dev/null
echo "AI endpoint: OK"

ai_response="$(docker compose exec -T moodle curl -fsS \
    -H 'Content-Type: application/json' \
    --data-binary '{"language":"python","task":"Вывести квадрат числа","code":"n = int(input())\nprint(n * n)"}' \
    http://ai-service:8000/api/v1/analyze)"

printf '%s' "$ai_response" | docker compose exec -T ai-service python -c '
import json, sys
result = json.load(sys.stdin)
assert isinstance(result.get("summary"), str) and result["summary"], result
assert isinstance(result.get("issues"), list), result
assert "fallback_used" in result, result
' >/dev/null
echo "AI code analysis: OK"

docker compose exec -T moodle test -f /var/www/html/public/question/type/coderunner/version.php
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php \
    --component=qtype_coderunner --name=jobe_host | grep -qx 'jobe'
echo "CodeRunner plugin: OK"

docker compose exec -T moodle test -f /var/www/html/public/local/aicodehelper/version.php
docker compose exec -T moodle php -r '
define("CLI_SCRIPT", true);
require "/var/www/html/config.php";
exit($DB->record_exists("config_plugins", ["plugin" => "local_aicodehelper"]) ? 0 : 1);
'
echo "local_aicodehelper plugin: OK"

