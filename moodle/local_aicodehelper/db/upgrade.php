<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_local_aicodehelper_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026071301) {
        $table = new xmldb_table('local_aicodehelper_analysis');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('slot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('stepid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('responsejson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('attemptid_fk', XMLDB_KEY_FOREIGN, ['attemptid'], 'quiz_attempts', ['id']);
        $table->add_index('request_unique', XMLDB_INDEX_UNIQUE, ['userid', 'attemptid', 'slot', 'stepid']);
        $table->add_index('attempt_lookup', XMLDB_INDEX_NOTUNIQUE, ['userid', 'attemptid', 'slot']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $defaults = [
            'integrationenabled' => 1,
            'showaftergrading' => 1,
            'onlyfailed' => 0,
            'responsemode' => 'teacher',
            'allowfullsolution' => 0,
            'maxanalyses' => 3,
        ];
        foreach ($defaults as $name => $value) {
            if (get_config('local_aicodehelper', $name) === false) {
                set_config($name, $value, 'local_aicodehelper');
            }
        }
        upgrade_plugin_savepoint(true, 2026071301, 'local', 'aicodehelper');
    }
    if ($oldversion < 2026071302) {
        upgrade_plugin_savepoint(true, 2026071302, 'local', 'aicodehelper');
    }
    return true;
}
