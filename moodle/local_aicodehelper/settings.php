<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicodehelper', get_string('pluginname', 'local_aicodehelper'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_aicodehelper/integrationenabled',
        get_string('integrationenabled', 'local_aicodehelper'),
        get_string('integrationenabled_desc', 'local_aicodehelper'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_aicodehelper/showaftergrading',
        get_string('showaftergrading', 'local_aicodehelper'),
        get_string('showaftergrading_desc', 'local_aicodehelper'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_aicodehelper/onlyfailed',
        get_string('onlyfailed', 'local_aicodehelper'),
        get_string('onlyfailed_desc', 'local_aicodehelper'),
        0
    ));
    $settings->add(new admin_setting_configselect(
        'local_aicodehelper/responsemode',
        get_string('responsemode', 'local_aicodehelper'),
        get_string('responsemode_desc', 'local_aicodehelper'),
        'teacher',
        [
            'hint' => get_string('mode_hint', 'local_aicodehelper'),
            'detailed' => get_string('mode_detailed', 'local_aicodehelper'),
            'teacher' => get_string('mode_teacher', 'local_aicodehelper'),
        ]
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_aicodehelper/allowfullsolution',
        get_string('allowfullsolution', 'local_aicodehelper'),
        get_string('allowfullsolution_desc', 'local_aicodehelper'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_aicodehelper/maxanalyses',
        get_string('maxanalyses', 'local_aicodehelper'),
        get_string('maxanalyses_desc', 'local_aicodehelper'),
        3,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_aicodehelper/timeout',
        get_string('timeout', 'local_aicodehelper'),
        get_string('timeout_desc', 'local_aicodehelper'),
        60,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_aicodehelper/endpoint',
        get_string('endpoint', 'local_aicodehelper'),
        get_string('endpoint_desc', 'local_aicodehelper'),
        'http://ai-service:8000/api/v1/analyze',
        PARAM_URL
    ));
}
