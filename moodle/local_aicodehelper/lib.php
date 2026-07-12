<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add the helper page to the main navigation for signed-in users.
 *
 * @param global_navigation $navigation
 */
function local_aicodehelper_extend_navigation(global_navigation $navigation): void {
    if (isloggedin() && !isguestuser()) {
        $navigation->add(
            get_string('pluginname', 'local_aicodehelper'),
            new moodle_url('/local/aicodehelper/index.php'),
            navigation_node::TYPE_CUSTOM
        );
    }
}

