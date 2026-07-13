<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

final class service_client {
    public static function analyze(array $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $endpoint = get_config('local_aicodehelper', 'endpoint') ?: 'http://ai-service:8000/api/v1/analyze';
        $timeout = max(1, min(300, (int) (get_config('local_aicodehelper', 'timeout') ?: 60)));
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response = $curl->post($endpoint, $body, ['CURLOPT_TIMEOUT' => $timeout]);
        $status = (int) ($curl->info['http_code'] ?? 0);
        if ($curl->get_errno() !== 0 || $status !== 200 || $response === false) {
            throw new \moodle_exception('serviceerror', 'local_aicodehelper');
        }
        $decoded = json_decode($response, true, 30, JSON_THROW_ON_ERROR);
        self::validate($decoded);
        return $decoded;
    }

    private static function validate(mixed $result): void {
        $fields = [
            'verdict', 'strengths', 'issues', 'failed_test_analysis', 'edge_cases',
            'complexity', 'style', 'hardcode_warnings', 'next_step', 'fallback_used',
        ];
        if (!is_array($result) || array_diff($fields, array_keys($result))) {
            throw new \moodle_exception('invalidresponse', 'local_aicodehelper');
        }
    }
}
