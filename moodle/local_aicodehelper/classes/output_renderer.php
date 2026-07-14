<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

/** Превращает ответ AI service в HTML с обязательным экранированием текста. */
final class output_renderer {
    /**
     * Собрать все разделы анализа в одну карточку Moodle.
     *
     * @param array $result Проверенный ответ AI service.
     * @return string Безопасный HTML для страницы попытки.
     */
    public static function render(array $result): string {
        $html = \html_writer::start_div('local-aicodehelper-result card card-body mt-3');
        $html .= self::text_section('generalassessment', (string) ($result['verdict'] ?? ''));
        $html .= self::list_section('strengthssection', $result['strengths'] ?? []);
        $html .= self::issues($result['issues'] ?? []);
        $html .= self::list_section('failedtestssection', $result['failed_test_analysis'] ?? []);
        $html .= self::list_section('edgecasessection', $result['edge_cases'] ?? []);
        $complexity = $result['complexity'] ?? [];
        $complexitytext = get_string('timecomplexity', 'local_aicodehelper') . ': ' . ($complexity['time'] ?? '') . '; '
            . get_string('memorycomplexity', 'local_aicodehelper') . ': ' . ($complexity['memory'] ?? '') . '. '
            . ($complexity['comment'] ?? '');
        $html .= self::text_section('complexitysection', $complexitytext);
        $html .= self::list_section('stylesection', $result['style'] ?? []);
        $html .= self::list_section('hardcodesection', $result['hardcode_warnings'] ?? []);
        $html .= self::text_section('nextstepsection', (string) ($result['next_step'] ?? ''));
        if (!empty($result['fallback_used'])) {
            $html .= \html_writer::div(s(get_string('fallback', 'local_aicodehelper')), 'alert alert-warning mb-0');
        }
        return $html . \html_writer::end_div();
    }

    /**
     * Вывести раздел с одним абзацем.
     *
     * @param string $heading Имя языковой строки заголовка.
     * @param string $text Текст модели, который необходимо экранировать.
     * @return string HTML раздела.
     */
    private static function text_section(string $heading, string $text): string {
        return \html_writer::tag('h4', s(get_string($heading, 'local_aicodehelper')))
            . \html_writer::tag('p', s($text));
    }

    /**
     * Вывести раздел со списком или сообщением об отсутствии пунктов.
     *
     * @param string $heading Имя языковой строки заголовка.
     * @param array $items Пункты ответа модели.
     * @return string HTML раздела.
     */
    private static function list_section(string $heading, array $items): string {
        $html = \html_writer::tag('h4', s(get_string($heading, 'local_aicodehelper')));
        if (!$items) {
            return $html . \html_writer::tag('p', s(get_string('none', 'local_aicodehelper')));
        }
        return $html . \html_writer::alist(array_map(static fn($item): string => s((string) $item), $items));
    }

    /**
     * Вывести найденные проблемы и направляющие подсказки.
     *
     * @param array $issues Массив проблем из ответа AI service.
     * @return string HTML раздела.
     */
    private static function issues(array $issues): string {
        $html = \html_writer::tag('h4', s(get_string('issuessection', 'local_aicodehelper')));
        if (!$issues) {
            return $html . \html_writer::tag('p', s(get_string('none', 'local_aicodehelper')));
        }
        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $line = empty($issue['line']) ? '' : ' (' . get_string('line', 'local_aicodehelper') . ' ' . (int) $issue['line'] . ')';
            $html .= \html_writer::start_div('alert alert-light border');
            $html .= \html_writer::tag('strong', s((string) ($issue['title'] ?? '')) . s($line));
            $html .= \html_writer::tag('p', s((string) ($issue['explanation'] ?? '')));
            $html .= \html_writer::tag('p', s(get_string('hint', 'local_aicodehelper') . ': ' . ($issue['hint'] ?? '')));
            $html .= \html_writer::end_div();
        }
        return $html;
    }
}
