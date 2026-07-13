<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

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
        try {
            $result = \local_aicodehelper\service_client::analyze([
                'language' => $language,
                'task' => $task,
                'code' => $code,
                'response_mode' => get_config('local_aicodehelper', 'responsemode') ?: 'teacher',
                'allow_full_solution' => (bool) get_config('local_aicodehelper', 'allowfullsolution'),
            ]);
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
echo html_writer::tag('textarea', s($task), ['id' => 'id_task', 'name' => 'task', 'rows' => 4, 'class' => 'form-control mb-3']);
echo html_writer::tag('label', s(get_string('code', 'local_aicodehelper')), ['for' => 'id_code']);
echo html_writer::tag('textarea', s($code), [
    'id' => 'id_code', 'name' => 'code', 'rows' => 14,
    'class' => 'form-control font-monospace mb-3', 'required' => 'required',
]);
echo html_writer::tag('button', s(get_string('analyze', 'local_aicodehelper')), [
    'type' => 'submit', 'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');
if ($result !== null) {
    echo \local_aicodehelper\output_renderer::render($result);
}
echo $OUTPUT->footer();
