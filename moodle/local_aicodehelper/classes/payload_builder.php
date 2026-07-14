<?php
// This file is part of Moodle - http://moodle.org/

namespace local_aicodehelper;

defined('MOODLE_INTERNAL') || die();

/** Собирает из попытки CodeRunner только разрешённые студенту данные. */
final class payload_builder {
    private const MAX_FIELD_LENGTH = 10000;

    /**
     * Построить запрос к AI service по текущему шагу попытки.
     *
     * @param \mod_quiz\quiz_attempt $attempt Попытка теста Moodle.
     * @param int $slot Номер вопроса внутри попытки.
     * @return array Безопасный payload для AI service.
     */
    public static function from_attempt(\mod_quiz\quiz_attempt $attempt, int $slot): array {
        $qa = $attempt->get_question_attempt($slot);
        $question = $qa->get_question();
        if (!($question instanceof \qtype_coderunner_question)) {
            throw new \moodle_exception('notcoderunner', 'local_aicodehelper');
        }

        $serialized = $qa->get_last_qt_var('_testoutcome', '');
        $outcome = $serialized ? @unserialize($serialized) : null;
        if (!($outcome instanceof \qtype_coderunner_testing_outcome)) {
            throw new \moodle_exception('notgraded', 'local_aicodehelper');
        }

        $tests = self::sanitize_test_results($outcome->testresults ?? [], $question->result_columns());
        $passed = count(array_filter($tests, static fn(array $test): bool => $test['passed']));
        $failed = count($tests) - $passed;
        $messages = self::collect_messages($outcome, $tests);
        $fraction = $qa->get_fraction();

        return [
            'language' => self::language($qa, $question),
            'task' => self::plain_text($question->format_questiontext($qa)),
            'question_name' => self::plain_text($question->name),
            'code' => (string) $qa->get_last_qt_var('answer', ''),
            'grade' => $fraction === null ? null : round($fraction * $qa->get_max_mark(), 7),
            'max_grade' => (float) $qa->get_max_mark(),
            'attempt_number' => (int) $attempt->get_attempt_number(),
            'status' => self::status($outcome, $failed, $messages),
            'passed_tests' => $passed,
            'failed_tests' => $failed,
            'test_results' => $tests,
            'compiler_message' => $messages['compiler_message'],
            'runtime_error' => $messages['runtime_error'],
            'stdout' => $messages['stdout'],
            'stderr' => $messages['stderr'],
            'timeout' => $messages['timeout'],
            'memory_limit' => $messages['memory_limit'],
            'response_mode' => get_config('local_aicodehelper', 'responsemode') ?: 'teacher',
            'allow_full_solution' => (bool) get_config('local_aicodehelper', 'allowfullsolution'),
        ];
    }

    /**
     * Удалить из результатов закрытые входы, ответы и test code.
     *
     * Публичный метод отдельно тестируется, потому что это главная граница безопасности плагина.
     *
     * @param array $testresults Сырые результаты CodeRunner.
     * @param array $resultcolumns Колонки, показанные студенту настройками вопроса.
     * @return array Результаты, которые можно передать AI service.
     */
    public static function sanitize_test_results(array $testresults, array $resultcolumns): array {
        // Сначала определяем, какие колонки CodeRunner разрешил видеть студенту.
        $allowedfields = [];
        foreach ($resultcolumns as $column) {
            if (!is_array($column)) {
                $column = (array) $column;
            }
            for ($index = 1; $index < count($column); $index++) {
                $field = (string) $column[$index];
                if ($field === '' || str_contains($field, '%') || str_contains($field, '(')) {
                    continue;
                }
                if (in_array($field, ['testcode', 'stdin', 'expected', 'got'], true)) {
                    $allowedfields[$field] = true;
                }
            }
        }

        $safe = [];
        $hidingrest = false;
        foreach (array_values($testresults) as $index => $result) {
            $passed = !empty($result->iscorrect);
            $visible = !$hidingrest && self::is_visible($result);
            $error = self::error_type((string) ($result->got ?? ''), $passed);
            $item = [
                'number' => $index + 1,
                'passed' => $passed,
                'hidden' => !$visible,
                'error_type' => $error,
                'message' => $visible ? self::visible_message($result, $error) : self::hidden_message($passed, $error),
                'visible_output' => [],
            ];

            if ($visible) {
                foreach (array_keys($allowedfields) as $field) {
                    if (isset($result->$field)) {
                        $item['visible_output'][$field] = self::plain_text((string) $result->$field);
                    }
                }
            }
            if (!$item['visible_output']) {
                // По контракту API пустой visible_output должен быть объектом {}, а не списком [].
                $item['visible_output'] = new \stdClass();
            }
            $safe[] = $item;
            if (!empty($result->hiderestiffail) && !$passed) {
                $hidingrest = true;
            }
        }
        return $safe;
    }

    /**
     * Проверить, что тест явно помечен CodeRunner как открытый.
     *
     * @param object $result Результат теста CodeRunner.
     * @return bool true только для полностью открытого теста.
     */
    private static function is_visible(object $result): bool {
        if (!isset($result->display)) {
            return true;
        }
        // Условные режимы показа всё равно считаются закрытыми. Полные данные уходят в AI
        // только тогда, когда преподаватель явно выбрал режим SHOW.
        return $result->display === 'SHOW';
    }

    /**
     * Выделить доступные сообщения компилятора и выполнения.
     *
     * @param object $outcome Общий результат CodeRunner.
     * @param array $tests Уже очищенные результаты тестов.
     * @return array Сообщения и признаки лимитов.
     */
    private static function collect_messages(object $outcome, array $tests): array {
        $error = self::plain_text((string) ($outcome->errormessage ?? ''));
        $alloutput = [];
        foreach ($tests as $test) {
            $visibleoutput = (array) $test['visible_output'];
            if (!$test['hidden'] && isset($visibleoutput['got'])) {
                $alloutput[] = $visibleoutput['got'];
            }
        }
        $joined = implode("\n", $alloutput);
        $haystack = mb_strtolower($error . "\n" . $joined);
        $syntax = (int) ($outcome->status ?? 0) === 2 || str_contains($haystack, 'syntax')
            || str_contains($haystack, 'compile');
        $runtime = str_contains($haystack, 'runtime error') || str_contains($haystack, 'traceback')
            || str_contains($haystack, 'exception');
        $timeout = str_contains($haystack, 'time limit') || str_contains($haystack, 'timeout')
            || str_contains($haystack, 'превышен лимит времени');
        $memory = str_contains($haystack, 'memory limit') || str_contains($haystack, 'out of memory')
            || str_contains($haystack, 'превышен лимит памяти');

        return [
            'compiler_message' => $syntax ? $error : '',
            'runtime_error' => $runtime ? self::limit($joined ?: $error) : '',
            'stdout' => (!$syntax && !$runtime) ? self::limit($joined) : '',
            'stderr' => ($syntax || $runtime) ? self::limit($error) : '',
            'timeout' => $timeout,
            'memory_limit' => $memory,
        ];
    }

    /** Определить язык ответа из шага или настроек вопроса. */
    private static function language(\question_attempt $qa, object $question): string {
        $language = (string) $qa->get_last_qt_var('language', '');
        if ($language === '') {
            $language = (string) ($question->language ?? $question->coderunnertype ?? 'python');
        }
        return self::limit($language, 30);
    }

    /** Свести результат CodeRunner к короткому общему статусу. */
    private static function status(object $outcome, int $failed, array $messages): string {
        if ((int) ($outcome->status ?? 0) === 2) {
            return 'syntax_error';
        }
        if ($messages['timeout']) {
            return 'timeout';
        }
        if ($messages['memory_limit']) {
            return 'memory_limit';
        }
        if ($messages['runtime_error'] !== '') {
            return 'runtime_error';
        }
        return $failed > 0 ? 'incorrect' : 'correct';
    }

    /** Определить безопасный тип ошибки без деталей закрытого теста. */
    private static function error_type(string $output, bool $passed): string {
        if ($passed) {
            return '';
        }
        $text = mb_strtolower($output);
        if (str_contains($text, 'time limit') || str_contains($text, 'timeout')) {
            return 'timeout';
        }
        if (str_contains($text, 'memory limit') || str_contains($text, 'out of memory')) {
            return 'memory limit';
        }
        if (str_contains($text, 'runtime error') || str_contains($text, 'traceback') || str_contains($text, 'exception')) {
            return 'runtime error';
        }
        return 'wrong answer';
    }

    /** Сформировать сообщение для открытого теста. */
    private static function visible_message(object $result, string $error): string {
        if (!empty($result->iscorrect)) {
            return 'Passed';
        }
        if ($error !== 'wrong answer') {
            return self::plain_text((string) ($result->got ?? $error));
        }
        return 'Failed';
    }

    /** Сформировать краткое сообщение для закрытого теста без его данных. */
    private static function hidden_message(bool $passed, string $error): string {
        if ($passed) {
            return 'Hidden test passed';
        }
        return 'Hidden test failed: ' . ($error ?: 'wrong answer');
    }

    /** Удалить HTML и ограничить длину текста. */
    private static function plain_text(string $value): string {
        return self::limit(trim(html_to_text($value, 0, false)));
    }

    /** Обрезать поле до безопасной длины. */
    private static function limit(string $value, int $length = self::MAX_FIELD_LENGTH): string {
        return mb_substr($value, 0, $length);
    }
}
