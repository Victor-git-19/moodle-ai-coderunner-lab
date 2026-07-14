<?php
// Короткие проверки против уже установленного учебного стенда.

define('CLI_SCRIPT', true);
require __DIR__ . '/../../../../config.php';

/** Завершить проверку исключением, если условие не выполнено. */
function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$visible = (object) [
    'iscorrect' => true, 'display' => 'SHOW', 'hiderestiffail' => false,
    'stdin' => '2', 'expected' => '4', 'got' => '4', 'testcode' => 'public test',
];
$hidden = (object) [
    'iscorrect' => false, 'display' => 'HIDE_IF_FAIL', 'hiderestiffail' => false,
    'stdin' => 'SECRET_INPUT', 'expected' => 'SECRET_EXPECTED',
    'got' => 'SECRET_OUTPUT', 'testcode' => 'SECRET_TEST_CODE', 'extra' => 'SECRET_EXTRA',
];
$hiddenpassed = (object) [
    'iscorrect' => true, 'display' => 'HIDE_IF_FAIL', 'hiderestiffail' => false,
    'stdin' => 'SECRET_PASS_INPUT', 'expected' => 'SECRET_PASS_EXPECTED', 'got' => 'SECRET_PASS_OUTPUT',
];
$tests = local_aicodehelper\payload_builder::sanitize_test_results(
    [$visible, $hidden, $hiddenpassed],
    [['Test', 'testcode'], ['Input', 'stdin'], ['Expected', 'expected'], ['Got', 'got']]
);
$json = json_encode($tests, JSON_THROW_ON_ERROR);
check($tests[1]['hidden'] === true, 'Hidden test was marked visible');
check($tests[1]['visible_output'] == (object) [], 'Hidden output map is not empty');
check($tests[2]['hidden'] === true, 'Passed conditionally hidden test was marked visible');
check(!str_contains($json, 'SECRET_'), 'Hidden test data leaked into payload');

$analysis = [
    'verdict' => '<script>alert(1)</script>',
    'strengths' => ['<img src=x onerror=alert(1)>'],
    'issues' => [], 'failed_test_analysis' => [], 'edge_cases' => [],
    'complexity' => ['time' => 'O(1)', 'memory' => 'O(1)', 'comment' => '<b>x</b>'],
    'style' => [], 'hardcode_warnings' => [], 'next_step' => '<iframe src=x>',
    'fallback_used' => false,
];
$html = local_aicodehelper\output_renderer::render($analysis);
check(!str_contains($html, '<script>'), 'Model script was not escaped');
check(!str_contains($html, '<img'), 'Model image was not escaped');
check(!str_contains($html, '<iframe'), 'Model iframe was not escaped');

check(!confirm_sesskey('definitely-wrong'), 'Invalid sesskey was accepted');
check(get_capability_info('local/aicodehelper:analyzeattempt') !== null, 'Capability is not installed');
check($DB->get_manager()->table_exists('local_aicodehelper_analysis'), 'Analysis cache table is missing');

echo "local_aicodehelper CLI tests: OK\n";
