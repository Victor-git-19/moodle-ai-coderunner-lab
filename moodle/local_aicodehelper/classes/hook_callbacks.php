<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

final class hook_callbacks {
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook,
    ): void {
        global $PAGE;

        if (!isloggedin() || isguestuser() || !get_config('local_aicodehelper', 'integrationenabled')
                || !has_capability('local/aicodehelper:analyzeattempt', $PAGE->context)) {
            return;
        }

        $path = $PAGE->url->get_path();
        if (!in_array($path, ['/mod/quiz/attempt.php', '/mod/quiz/review.php'], true)) {
            return;
        }

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return;
        }

        $config = [
            'attemptId' => $attemptid,
            'endpoint' => (new \moodle_url('/local/aicodehelper/ajax.php'))->out(false),
            'sesskey' => sesskey(),
            'onlyFailed' => (bool) get_config('local_aicodehelper', 'onlyfailed'),
            'showAfterGrading' => (bool) get_config('local_aicodehelper', 'showaftergrading'),
            'strings' => [
                'button' => get_string('analyzesolution', 'local_aicodehelper'),
                'loading' => get_string('loading', 'local_aicodehelper'),
                'showAgain' => get_string('showanalysisagain', 'local_aicodehelper'),
                'error' => get_string('requesterror', 'local_aicodehelper'),
            ],
        ];

        $PAGE->requires->js(new \moodle_url('/local/aicodehelper/integration.js'));
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $hook->add_html(\html_writer::tag('script', 'window.localAiCodeHelper = ' . $json . ';'));
    }
}
