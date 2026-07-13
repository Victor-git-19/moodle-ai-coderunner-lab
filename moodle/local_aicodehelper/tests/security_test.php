<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

final class security_test extends \advanced_testcase {
    public function test_user_without_capability_cannot_analyze(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_module::instance($quiz->cmid);

        $this->assertFalse(has_capability('local/aicodehelper:analyzeattempt', $context));
    }

    public function test_invalid_sesskey_is_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(confirm_sesskey('definitely-wrong'));
        $this->assertTrue(confirm_sesskey(sesskey()));
    }

    public function test_same_step_has_unique_cached_response(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        \question_engine::save_questions_usage_by_activity($quba);
        $attemptid = $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'attempt' => 1,
            'uniqueid' => $quba->get_id(),
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'inprogress',
            'timestart' => time(),
            'timefinish' => 0,
            'timemodified' => time(),
            'timemodifiedoffline' => 0,
            'timecheckstate' => null,
            'sumgrades' => null,
        ]);
        $record = (object) [
            'userid' => $user->id, 'attemptid' => $attemptid, 'slot' => 1, 'stepid' => 10,
            'responsejson' => '{}', 'timecreated' => time(),
        ];
        $DB->insert_record('local_aicodehelper_analysis', $record);
        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_aicodehelper_analysis', $record);
    }
}
