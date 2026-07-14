<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

// Штатный hook Moodle позволяет добавить кнопку без изменения CodeRunner.
$callbacks = [
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_aicodehelper\hook_callbacks::class . '::before_footer_html_generation',
        'priority' => 0,
    ],
];
