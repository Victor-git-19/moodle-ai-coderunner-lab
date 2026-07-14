<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

/** Проверяет экранирование недоверенного текста модели. */
final class output_renderer_test extends \basic_testcase {
    public function test_model_html_is_escaped(): void {
        $html = output_renderer::render([
            'verdict' => '<script>alert(1)</script>',
            'strengths' => ['<img src=x onerror=alert(1)>'],
            'issues' => [[
                'severity' => 'error',
                'title' => '<b>Title</b>',
                'explanation' => '<svg onload=alert(1)>',
                'hint' => '<a href=javascript:alert(1)>click</a>',
                'line' => 4,
            ]],
            'failed_test_analysis' => [],
            'edge_cases' => [],
            'complexity' => ['time' => 'O(1)', 'memory' => 'O(1)', 'comment' => '<i>x</i>'],
            'style' => [],
            'hardcode_warnings' => [],
            'next_step' => '<iframe src=x>',
            'fallback_used' => false,
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;iframe', $html);
    }
}
