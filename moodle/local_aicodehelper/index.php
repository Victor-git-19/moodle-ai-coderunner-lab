<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$context = context_system::instance();
$pageurl = new moodle_url('/local/aicodehelper/index.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_aicodehelper'));
$PAGE->set_heading(get_string('pluginname', 'local_aicodehelper'));

$language = optional_param('language', 'python', PARAM_ALPHANUMEXT);
$task = optional_param('task', '', PARAM_RAW);
$code = optional_param('code', '', PARAM_RAW);
$result = null;
$error = null;
$allowedlanguages = ['python', 'javascript', 'java'];

if (!in_array($language, $allowedlanguages, true)) {
    $language = 'python';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if (trim($code) === '') {
        $error = get_string('emptycode', 'local_aicodehelper');
    } else {
        $endpoint = getenv('AI_SERVICE_URL') ?: 'http://ai-service:8000/api/v1/analyze';
        $timeout = max(1, (int) (getenv('AI_TIMEOUT') ?: 60));
        $payload = json_encode([
            'language' => $language,
            'task' => $task,
            'code' => $code,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        try {
            // The endpoint is fixed by the server environment, not supplied by the browser.
            $curl = new curl(['ignoresecurity' => true]);
            $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
            $response = $curl->post($endpoint, $payload, ['CURLOPT_TIMEOUT' => $timeout]);
            $status = (int) ($curl->info['http_code'] ?? 0);

            if ($curl->get_errno() !== 0 || $status !== 200 || $response === false) {
                throw new moodle_exception('serviceerror', 'local_aicodehelper');
            }

            $decoded = json_decode($response, true, 20, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !isset($decoded['summary'], $decoded['issues'], $decoded['suggestions'])) {
                throw new moodle_exception('invalidresponse', 'local_aicodehelper');
            }
            $result = $decoded;
        } catch (Throwable $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            $error = get_string('serviceerror', 'local_aicodehelper');
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pageheading', 'local_aicodehelper'));
echo html_writer::tag('p', s(get_string('intro', 'local_aicodehelper')));

if ($error !== null) {
    echo $OUTPUT->notification(s($error), 'error');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::tag('label', s(get_string('language', 'local_aicodehelper')), ['for' => 'id_language']);
$options = [];
foreach ($allowedlanguages as $option) {
    $options[$option] = get_string('language_' . $option, 'local_aicodehelper');
}
echo html_writer::select($options, 'language', $language, false, ['id' => 'id_language', 'class' => 'form-select mb-3']);

echo html_writer::tag('label', s(get_string('task', 'local_aicodehelper')), ['for' => 'id_task']);
echo html_writer::tag('textarea', s($task), [
    'id' => 'id_task',
    'name' => 'task',
    'rows' => 4,
    'class' => 'form-control mb-3',
]);

echo html_writer::tag('label', s(get_string('code', 'local_aicodehelper')), ['for' => 'id_code']);
echo html_writer::tag('textarea', s($code), [
    'id' => 'id_code',
    'name' => 'code',
    'rows' => 14,
    'class' => 'form-control font-monospace mb-3',
    'required' => 'required',
]);

echo html_writer::tag('button', s(get_string('analyze', 'local_aicodehelper')), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

if ($result !== null) {
    echo $OUTPUT->heading(get_string('result', 'local_aicodehelper'), 3);
    echo html_writer::tag('p', s((string) $result['summary']));

    foreach (['issues', 'suggestions'] as $section) {
        echo html_writer::tag('h4', s(get_string($section, 'local_aicodehelper')));
        $items = is_array($result[$section]) ? $result[$section] : [];
        if ($items) {
            echo html_writer::alist(array_map(static fn($item) => s((string) $item), $items));
        } else {
            echo html_writer::tag('p', s(get_string('none', 'local_aicodehelper')));
        }
    }

    echo html_writer::tag('p', s(get_string('complexity', 'local_aicodehelper') . ': ' .
        (string) ($result['complexity'] ?? get_string('unknown', 'local_aicodehelper'))));
    if (!empty($result['fallback_used'])) {
        echo $OUTPUT->notification(s(get_string('fallback', 'local_aicodehelper')), 'warning');
    }
}

echo $OUTPUT->footer();

