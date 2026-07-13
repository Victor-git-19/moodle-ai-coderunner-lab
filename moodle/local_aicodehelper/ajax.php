<?php
// This file is part of Moodle - http://moodle.org/

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

header('Content-Type: application/json; charset=utf-8');

try {
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
    if ($cached) {
        $analysis = json_decode($cached->responsejson, true, 30, JSON_THROW_ON_ERROR);
        echo json_encode([
            'success' => true,
            'cached' => true,
            'analysis' => $analysis,
            'html' => \local_aicodehelper\output_renderer::render($analysis),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    $maximum = max(1, (int) (get_config('local_aicodehelper', 'maxanalyses') ?: 3));
    $used = $DB->count_records('local_aicodehelper_analysis', [
        'userid' => $USER->id,
        'attemptid' => $attemptid,
        'slot' => $slot,
    ]);
    if ($used >= $maximum) {
        throw new moodle_exception('limitreached', 'local_aicodehelper', '', $maximum);
    }

    $payload = \local_aicodehelper\payload_builder::from_attempt($attempt, $slot);
    $analysis = \local_aicodehelper\service_client::analyze($payload);
    $record = (object) ($conditions + [
        'responsejson' => json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'timecreated' => time(),
    ]);
    try {
        $DB->insert_record('local_aicodehelper_analysis', $record);
    } catch (dml_write_exception $exception) {
        // A parallel request may have inserted the same step. Return that cached result.
        $cached = $DB->get_record('local_aicodehelper_analysis', $conditions, '*', MUST_EXIST);
        $analysis = json_decode($cached->responsejson, true, 30, JSON_THROW_ON_ERROR);
    }

    echo json_encode([
        'success' => true,
        'cached' => false,
        'analysis' => $analysis,
        'html' => \local_aicodehelper\output_renderer::render($analysis),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
