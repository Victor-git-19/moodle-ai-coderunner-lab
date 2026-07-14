<?php
// This file is part of Moodle - http://moodle.org/

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

header('Content-Type: application/json; charset=utf-8');

/**
 * Отправить браузеру готовый безопасный HTML и завершить запрос.
 *
 * @param array $analysis Структурированный ответ AI service.
 * @param bool $cached Был ли ответ взят из кэша.
 */
function local_aicodehelper_send_analysis(array $analysis, bool $cached): void {
    echo json_encode([
        'success' => true,
        'cached' => $cached,
        'html' => \local_aicodehelper\output_renderer::render($analysis),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

try {
    // Все проверки доступа выполняются до чтения попытки и обращения к AI service.
    require_sesskey();
    $attemptid = required_param('attemptid', PARAM_INT);
    $slot = required_param('slot', PARAM_INT);
    $attempt = \mod_quiz\quiz_attempt::create($attemptid);
    require_login($attempt->get_course(), false, $attempt->get_cm());
    require_capability('local/aicodehelper:analyzeattempt', $attempt->get_context());

    if (!$attempt->is_own_attempt() && !$attempt->is_review_allowed()) {
        throw new moodle_exception('nopermission', 'local_aicodehelper');
    }
    if (!(bool) get_config('local_aicodehelper', 'integrationenabled')) {
        throw new moodle_exception('integrationdisabled', 'local_aicodehelper');
    }

    $qa = $attempt->get_question_attempt($slot);
    $stepid = (int) $qa->get_last_step()->get_id();
    if (!$stepid) {
        throw new moodle_exception('notgraded', 'local_aicodehelper');
    }

    $conditions = [
        'userid' => $USER->id,
        'attemptid' => $attemptid,
        'slot' => $slot,
        'stepid' => $stepid,
    ];
    $cached = $DB->get_record('local_aicodehelper_analysis', $conditions);
    $cachedfallback = null;
    if ($cached) {
        $analysis = json_decode($cached->responsejson, true, 30, JSON_THROW_ON_ERROR);
        if (empty($analysis['fallback_used'])) {
            local_aicodehelper_send_analysis($analysis, true);
        }
        // Временный сбой Ollama не должен навсегда закреплять fallback за этой попыткой.
        $cachedfallback = $cached;
    }

    $maximum = max(1, (int) (get_config('local_aicodehelper', 'maxanalyses') ?: 3));
    $used = $DB->count_records('local_aicodehelper_analysis', [
        'userid' => $USER->id,
        'attemptid' => $attemptid,
        'slot' => $slot,
    ]);
    if ($cachedfallback) {
        $used--;
    }
    if ($used >= $maximum) {
        throw new moodle_exception('limitreached', 'local_aicodehelper', '', $maximum);
    }

    $payload = \local_aicodehelper\payload_builder::from_attempt($attempt, $slot);
    $analysis = \local_aicodehelper\service_client::analyze($payload);
    if (!empty($analysis['fallback_used'])) {
        local_aicodehelper_send_analysis($analysis, false);
    }

    $iscached = false;
    if ($cachedfallback) {
        $cachedfallback->responsejson = json_encode(
            $analysis,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $cachedfallback->timecreated = time();
        $DB->update_record('local_aicodehelper_analysis', $cachedfallback);
    } else {
        $record = (object) ($conditions + [
            'responsejson' => json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'timecreated' => time(),
        ]);
        try {
            $DB->insert_record('local_aicodehelper_analysis', $record);
        } catch (dml_write_exception) {
            // Параллельный запрос мог первым записать тот же шаг. Возвращаем его результат.
            $cached = $DB->get_record('local_aicodehelper_analysis', $conditions, '*', MUST_EXIST);
            $analysis = json_decode($cached->responsejson, true, 30, JSON_THROW_ON_ERROR);
            $iscached = true;
        }
    }

    local_aicodehelper_send_analysis($analysis, $iscached);
} catch (Throwable $exception) {
    http_response_code($exception instanceof invalid_parameter_exception ? 400 : 403);
    debugging($exception->getMessage(), DEBUG_DEVELOPER);
    echo json_encode([
        'success' => false,
        'error' => get_string_manager()->string_exists($exception->errorcode ?? '', 'local_aicodehelper')
            ? get_string($exception->errorcode, 'local_aicodehelper', $exception->a ?? null)
            : get_string('requesterror', 'local_aicodehelper'),
    ], JSON_UNESCAPED_UNICODE);
}
