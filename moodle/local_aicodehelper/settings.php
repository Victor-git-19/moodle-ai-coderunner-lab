<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicodehelper', get_string('pluginname', 'local_aicodehelper'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_aicodehelper/endpoint',
        get_string('endpoint', 'local_aicodehelper'),
        get_string('endpoint_desc', 'local_aicodehelper'),
        'http://ai-service:8000/api/v1/analyze',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configtext(
        'local_aicodehelper/timeout',
        get_string('timeout', 'local_aicodehelper'),
        get_string('timeout_desc', 'local_aicodehelper'),
        60,
        PARAM_INT
    ));
}

