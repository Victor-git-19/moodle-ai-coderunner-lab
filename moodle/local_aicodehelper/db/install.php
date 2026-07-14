<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/** Задать безопасные настройки при первой установке плагина. */
function xmldb_local_aicodehelper_install(): void {
    set_config('integrationenabled', 1, 'local_aicodehelper');
    set_config('showaftergrading', 1, 'local_aicodehelper');
    set_config('onlyfailed', 0, 'local_aicodehelper');
    set_config('responsemode', 'teacher', 'local_aicodehelper');
    set_config('allowfullsolution', 0, 'local_aicodehelper');
    set_config('maxanalyses', 3, 'local_aicodehelper');
}
