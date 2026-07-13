<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

final class payload_builder_test extends \basic_testcase {
    public function test_hidden_test_data_is_never_in_payload(): void {
        $visible = (object) [
            'iscorrect' => true,
            'display' => 'SHOW',
            'hiderestiffail' => false,
            'testcode' => 'print(square(2))',
            'stdin' => '2',
            'expected' => '4',
            'got' => '4',
            'extra' => 'visible extra must still be excluded',
        ];
        $hidden = (object) [
            'iscorrect' => false,
            'display' => 'HIDE_IF_FAIL',
            'hiderestiffail' => false,
            'testcode' => 'SECRET_TEST_CODE',
            'stdin' => 'SECRET_INPUT',
            'expected' => 'SECRET_EXPECTED',
            'got' => 'SECRET_ACTUAL',
            'extra' => 'SECRET_TEMPLATE_DATA',
        ];

        $payload = payload_builder::sanitize_test_results(
            [$visible, $hidden],
            [['Test', 'testcode'], ['Input', 'stdin'], ['Expected', 'expected'], ['Got', 'got']]
        );
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertFalse($payload[0]['hidden']);
        $this->assertSame('2', $payload[0]['visible_output']['stdin']);
        $this->assertTrue($payload[1]['hidden']);
        $this->assertEquals((object) [], $payload[1]['visible_output']);
        $this->assertSame('wrong answer', $payload[1]['error_type']);
        $this->assertStringNotContainsString('SECRET_', $json);
        $this->assertStringNotContainsString('extra', $json);
    }

    public function test_hide_rest_after_failed_test_is_respected(): void {
        $first = (object) [
            'iscorrect' => false, 'display' => 'SHOW', 'hiderestiffail' => true,
            'stdin' => 'public', 'expected' => 'public', 'got' => 'wrong',
        ];
        $second = (object) [
            'iscorrect' => false, 'display' => 'SHOW', 'hiderestiffail' => false,
            'stdin' => 'SECRET_AFTER_FAILURE', 'expected' => 'SECRET', 'got' => 'SECRET',
        ];
        $payload = payload_builder::sanitize_test_results(
            [$first, $second], [['Input', 'stdin'], ['Expected', 'expected'], ['Got', 'got']]
        );

        $this->assertFalse($payload[0]['hidden']);
        $this->assertTrue($payload[1]['hidden']);
        $this->assertStringNotContainsString('SECRET', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_passed_conditionally_hidden_test_stays_hidden(): void {
        $hidden = (object) [
            'iscorrect' => true, 'display' => 'HIDE_IF_FAIL', 'hiderestiffail' => false,
            'stdin' => 'SECRET_PASS_INPUT', 'expected' => 'SECRET_PASS_EXPECTED', 'got' => 'SECRET_PASS_ACTUAL',
        ];
        $payload = payload_builder::sanitize_test_results(
            [$hidden], [['Input', 'stdin'], ['Expected', 'expected'], ['Got', 'got']]
        );

        $this->assertTrue($payload[0]['passed']);
        $this->assertTrue($payload[0]['hidden']);
        $this->assertStringNotContainsString('SECRET_PASS', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_timeout_and_runtime_errors_are_classified(): void {
        $timeout = (object) [
            'iscorrect' => false, 'display' => 'SHOW', 'hiderestiffail' => false,
            'got' => '***Time limit exceeded***',
        ];
        $runtime = (object) [
            'iscorrect' => false, 'display' => 'SHOW', 'hiderestiffail' => false,
            'got' => 'Traceback: ZeroDivisionError',
        ];
        $payload = payload_builder::sanitize_test_results([$timeout, $runtime], [['Got', 'got']]);
        $this->assertSame('timeout', $payload[0]['error_type']);
        $this->assertSame('runtime error', $payload[1]['error_type']);
    }
}
