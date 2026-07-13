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

invalid_sesskey_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 \
    --data 'attemptid=1&slot=1&sesskey=invalid' \
    "${MOODLE_URL%/}/local/aicodehelper/ajax.php")"
if [[ "$invalid_sesskey_status" != "403" ]]; then
    echo "Invalid sesskey was not rejected" >&2
    exit 1
fi
echo "AI AJAX sesskey protection: OK"

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

safe_payload="$(docker compose exec -T moodle php <<'PHP'
<?php
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
$visible = (object) [
    'iscorrect' => true, 'display' => 'SHOW', 'hiderestiffail' => false,
    'stdin' => '2', 'expected' => '4', 'got' => '4', 'testcode' => 'print(square(2))',
];
$hidden = (object) [
    'iscorrect' => false, 'display' => 'HIDE_IF_FAIL', 'hiderestiffail' => false,
    'stdin' => 'SECRET_HIDDEN_INPUT', 'expected' => 'SECRET_HIDDEN_EXPECTED',
    'got' => 'SECRET_HIDDEN_ACTUAL', 'testcode' => 'SECRET_HIDDEN_TEST_CODE',
    'extra' => 'SECRET_TEMPLATE_DATA',
];
$hiddenpassed = (object) [
    'iscorrect' => true, 'display' => 'HIDE_IF_FAIL', 'hiderestiffail' => false,
    'stdin' => 'SECRET_PASSED_INPUT', 'expected' => 'SECRET_PASSED_EXPECTED',
    'got' => 'SECRET_PASSED_ACTUAL', 'testcode' => 'SECRET_PASSED_TEST_CODE',
];
$tests = local_aicodehelper\payload_builder::sanitize_test_results(
    [$visible, $hidden, $hiddenpassed],
    [['Test', 'testcode'], ['Input', 'stdin'], ['Expected', 'expected'], ['Got', 'got']]
);
echo json_encode([
    'language' => 'python',
    'task' => 'Вывести квадрат числа',
    'question_name' => 'Квадрат числа',
    'code' => "n = int(input())\nprint(n * n)",
    'grade' => 0,
    'max_grade' => 1,
    'attempt_number' => 1,
    'status' => 'gradedwrong',
    'passed_tests' => 2,
    'failed_tests' => 1,
    'test_results' => $tests,
    'response_mode' => 'teacher',
    'allow_full_solution' => false,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
PHP
)"

if grep -q 'SECRET_' <<<"$safe_payload"; then
    echo "Hidden test data leaked into AI payload" >&2
    exit 1
fi
echo "Safe CodeRunner payload: OK"

ai_response="$(printf '%s' "$safe_payload" | docker compose exec -T moodle curl -fsS \
    -H 'Content-Type: application/json' \
    --data-binary @- \
    http://ai-service:8000/api/v1/analyze)"

printf '%s' "$ai_response" | docker compose exec -T ai-service python -c '
import json, sys
result = json.load(sys.stdin)
assert isinstance(result.get("verdict"), str) and result["verdict"], result
assert isinstance(result.get("issues"), list), result
assert isinstance(result.get("strengths"), list), result
assert isinstance(result.get("failed_test_analysis"), list), result
assert isinstance(result.get("edge_cases"), list), result
assert isinstance(result.get("complexity"), dict), result
assert isinstance(result.get("hardcode_warnings"), list), result
assert isinstance(result.get("next_step"), str) and result["next_step"], result
assert "fallback_used" in result, result
' >/dev/null
echo "Structured teacher analysis: OK"

docker compose exec -T -e PYTHONPATH=/app ai-service pytest -q \
    tests/test_api.py::test_successful_attempt_uses_coderunner_result >/dev/null
echo "AI fallback: OK"

docker compose exec -T moodle test -f /var/www/html/public/question/type/coderunner/version.php
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php \
    --component=qtype_coderunner --name=jobe_host | grep -qx 'jobe'
echo "CodeRunner plugin: OK"

docker compose exec -T moodle php -r '
define("CLI_SCRIPT", true);
require "/var/www/html/config.php";
require_once $CFG->dirroot . "/question/type/coderunner/classes/sandbox.php";
$sandbox = qtype_coderunner_sandbox::get_instance("jobesandbox");
$result = $sandbox->execute(
    "print(\"Hello from CodeRunner\")\n",
    "python3",
    "",
    null,
    null,
    false
);
exit($result->error === 0 && $result->output === "Hello from CodeRunner\n" ? 0 : 1);
'
echo "CodeRunner to Jobe execution: OK"

docker compose exec -T moodle test -f /var/www/html/public/local/aicodehelper/version.php
docker compose exec -T moodle php -r '
define("CLI_SCRIPT", true);
require "/var/www/html/config.php";
exit($DB->record_exists("config_plugins", ["plugin" => "local_aicodehelper"]) ? 0 : 1);
'
echo "local_aicodehelper plugin: OK"
