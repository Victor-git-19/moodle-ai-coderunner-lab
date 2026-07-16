#!/usr/bin/env php
<?php
// Создаёт или дополняет учебный курс через API Moodle.

define('CLI_SCRIPT', true);

$configpath = getenv('MOODLE_CONFIG') ?: '/var/www/html/config.php';
if (!is_readable($configpath)) {
    fwrite(STDERR, "Moodle config.php is not readable: {$configpath}\n");
    exit(1);
}

require $configpath;
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->dirroot . '/lib/questionlib.php';
require_once $CFG->dirroot . '/lib/resourcelib.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';
require_once $CFG->dirroot . '/question/format/xml/format.php';

use core_question\local\bank\question_bank_helper;
use mod_quiz\quiz_settings;

/** Экранировать обычное значение для Moodle XML. */
function xml_value(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Сохранить пробелы в коде и тестах с помощью XML CDATA. */
function xml_cdata(string $value): string {
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
}

/** Собрать понятное условие вопроса CodeRunner. */
function build_question_html(array $task): string {
    $html = '<p>' . s($task['description']) . '</p>';
    $html .= '<p><strong>Входные данные:</strong> ' . s($task['input']) . '</p>';
    $html .= '<p><strong>Выходные данные:</strong> ' . s($task['output']) . '</p>';
    $mode = $task['mode'] ?? 'program';
    if ($mode === 'class') {
        $html .= '<p><em>Обязательно объявите класс <code>' . s($task['required_class']) .
            '</code>. Не читайте input() и не печатайте демонстрационный результат: CodeRunner создаст объект сам.</em></p>';
    } else if (isset($task['required_async_function'])) {
        $html .= '<p><em>Обязательно объявите корутину с помощью <code>async def ' .
            s($task['required_async_function']) .
            '(...)</code>. Не читайте input() и не запускайте корутину самостоятельно.</em></p>';
    } else if ($mode === 'function') {
        $html .= '<p><em>Обязательно объявите функцию с помощью <code>def ' .
            s($task['required_function']) .
            '(...)</code>. Не читайте input() и не печатайте результат: CodeRunner вызовет функцию сам.</em></p>';
    } else {
        $html .= '<p><em>Программа должна читать стандартный ввод и печатать только ответ.</em></p>';
    }
    if (!empty($task['required_symbols'])) {
        $html .= '<p><strong>Обязательный API:</strong> <code>' .
            implode('</code>, <code>', array_map('s', $task['required_symbols'])) . '</code>.</p>';
    }
    return $html;
}

/** Добавить к тесту простую проверку требуемой функции, корутины или класса. */
function build_test_code(array $task, array $test): string {
    $checks = ['import inspect'];
    if (isset($task['required_class'])) {
        $name = $task['required_class'];
        $checks[] = "assert inspect.isclass({$name}), 'Объявите класс {$name}'";
    }
    if (isset($task['required_function'])) {
        $name = $task['required_function'];
        $checks[] = "assert inspect.isfunction({$name}) and {$name}.__name__ == '{$name}', " .
            "'Объявите функцию {$name} через def'";
    }
    if (isset($task['required_async_function'])) {
        $name = $task['required_async_function'];
        $checks[] = "assert inspect.iscoroutinefunction({$name}), " .
            "'Объявите корутину {$name} через async def'";
    }
    $callable = $task['required_function'] ?? $task['required_async_function'] ?? null;
    foreach ($task['required_symbols'] ?? [] as $symbol) {
        $checks[] = "assert '{$symbol}' in {$callable}.__code__.co_names, 'Используйте {$symbol}'";
    }
    if (isset($task['max_workers'])) {
        $workers = (int) $task['max_workers'];
        $checks[] = "assert {$workers} in {$callable}.__code__.co_consts, " .
            "'Укажите max_workers={$workers}'";
    }
    $checks[] = $test['testcode'] ?? '';
    return implode("\n", array_filter($checks, static fn(string $line): bool => $line !== ''));
}

/** Вернуть только понятный студенту вызов из последней строки теста. */
function visible_test_code(array $test): string {
    $lines = preg_split('/\R/', trim($test['testcode'] ?? '')) ?: [];
    return $lines ? (string) end($lines) : '';
}

/** Колонки результата для функций и классов без служебной проверки inspect. */
function callable_result_columns(): string {
    return json_encode([
        ['Вызов', 'extra'],
        ['Ожидаемый', 'expected'],
        ['Получено', 'got'],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/** Преобразовать задания курса в стандартный Moodle XML для CodeRunner. */
function build_question_xml(array $sections): string {
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n";

    foreach ($sections as $section) {
        foreach ($section['tasks'] as $task) {
            $xml .= "  <question type=\"coderunner\">\n";
            $xml .= '    <name><text>' . xml_value($task['name']) . "</text></name>\n";
            $xml .= '    <questiontext format="html"><text>' .
                xml_cdata(build_question_html($task)) . "</text></questiontext>\n";
            $xml .= "    <generalfeedback format=\"html\"><text></text></generalfeedback>\n";
            $xml .= "    <defaultgrade>1.0000000</defaultgrade>\n";
            $xml .= "    <penalty>0.0000000</penalty>\n";
            $xml .= "    <hidden>0</hidden>\n";
            $xml .= "    <coderunnertype>python3</coderunnertype>\n";
            $xml .= "    <prototypetype>0</prototypetype>\n";
            $xml .= "    <allornothing>1</allornothing>\n";
            $xml .= "    <penaltyregime>10, 20, ...</penaltyregime>\n";
            $xml .= "    <precheck>0</precheck>\n";
            $xml .= "    <showsource>0</showsource>\n";
            $xml .= "    <answerboxlines>14</answerboxlines>\n";
            $xml .= "    <answerboxcolumns>100</answerboxcolumns>\n";
            $xml .= "    <answerpreload></answerpreload>\n";
            $xml .= "    <useace>0</useace>\n";
            $resultcolumns = ($task['mode'] ?? 'program') === 'program'
                ? ''
                : callable_result_columns();
            $xml .= '    <resultcolumns>' . xml_cdata($resultcolumns) . "</resultcolumns>\n";
            $xml .= "    <template></template>\n";
            $iscombinator = ($task['mode'] ?? 'program') === 'program' ? 0 : 1;
            $xml .= "    <iscombinatortemplate>{$iscombinator}</iscombinatortemplate>\n";
            $xml .= '    <answer>' . xml_cdata($task['answer']) . "</answer>\n";
            $xml .= "    <validateonsave>1</validateonsave>\n";
            $xml .= "    <testsplitterre></testsplitterre>\n";
            $xml .= "    <language></language><acelang></acelang><sandbox></sandbox><grader></grader>\n";
            $cputime = (int) ($task['cputime'] ?? 2);
            $memory = (int) ($task['memory'] ?? 128);
            $xml .= "    <cputimelimitsecs>{$cputime}</cputimelimitsecs>" .
                "<memlimitmb>{$memory}</memlimitmb>\n";
            $xml .= "    <sandboxparams></sandboxparams><templateparams></templateparams>\n";
            $xml .= "    <uiplugin>none</uiplugin><uiparameters></uiparameters>\n";
            $xml .= "    <testcases>\n";

            foreach ($task['tests'] as $test) {
                $useasexample = $test['visible'] ? '1' : '0';
                $display = $test['visible'] ? 'SHOW' : 'HIDE';
                $xml .= "      <testcase testtype=\"0\" useasexample=\"{$useasexample}\" " .
                    "hiderestiffail=\"0\" mark=\"1.0000000\">\n";
                $testcode = ($task['mode'] ?? 'program') === 'program'
                    ? ($test['testcode'] ?? '')
                    : build_test_code($task, $test);
                $xml .= '        <testcode><text>' . xml_cdata($testcode) . "</text></testcode>\n";
                $xml .= '        <stdin><text>' . xml_cdata($test['stdin']) . "</text></stdin>\n";
                $xml .= '        <expected><text>' . xml_cdata($test['expected']) . "</text></expected>\n";
                $extra = ($task['mode'] ?? 'program') === 'program' ? '' : visible_test_code($test);
                $xml .= '        <extra><text>' . xml_cdata($extra) . "</text></extra>\n";
                $xml .= '        <display><text>' . $display . "</text></display>\n";
                $xml .= "      </testcase>\n";
            }

            $xml .= "    </testcases>\n";
            $xml .= "  </question>\n";
        }
    }

    return $xml . "</quiz>\n";
}

/** Добавить в раздел страницу с теорией. */
function add_course_page(stdClass $course, int $sectionnumber, array $page): stdClass {
    [, , , , $data] = prepare_new_moduleinfo_data($course, 'page', $sectionnumber);
    $data->name = $page['name'];
    $data->introeditor['text'] = '';
    $data->introeditor['format'] = FORMAT_HTML;
    $data->content = $page['content'];
    $data->contentformat = FORMAT_HTML;
    $data->display = RESOURCELIB_DISPLAY_AUTO;
    $data->printintro = 0;
    $data->printlastmodified = 0;
    $data->showdescription = 0;
    return add_moduleinfo($data, $course);
}

/** Найти созданную курсом активность указанного типа в разделе. */
function find_section_activity(int $courseid, int $sectionnumber, string $modname): stdClass|false {
    global $DB;

    $sql = "SELECT cm.id AS coursemodule, cm.instance
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modname
              JOIN {course_sections} cs ON cs.id = cm.section
             WHERE cm.course = :courseid AND cs.section = :sectionnumber
          ORDER BY cm.id";
    $records = $DB->get_records_sql($sql, [
        'modname' => $modname,
        'courseid' => $courseid,
        'sectionnumber' => $sectionnumber,
    ], 0, 1);
    return $records ? reset($records) : false;
}

/** Создать страницу раздела или обновить её содержимое без смены ID. */
function sync_course_page(stdClass $course, int $sectionnumber, array $page): stdClass {
    global $DB;

    $module = find_section_activity((int) $course->id, $sectionnumber, 'page');
    if (!$module) {
        return add_course_page($course, $sectionnumber, $page);
    }
    $record = $DB->get_record('page', ['id' => $module->instance], '*', MUST_EXIST);
    $record->name = $page['name'];
    $record->content = $page['content'];
    $record->contentformat = FORMAT_HTML;
    $record->timemodified = time();
    $DB->update_record('page', $record);
    return (object) ['coursemodule' => $module->coursemodule, 'instance' => $module->instance];
}

/** Добавить скрытый банк вопросов курса. */
function add_question_bank(stdClass $course): stdClass {
    [, , , , $data] = prepare_new_moduleinfo_data($course, 'qbank', 0);
    $data->name = 'Банк задач курса Python';
    $data->type = question_bank_helper::TYPE_STANDARD;
    $data->visible = 0;
    $data->visibleoncoursepage = 0;
    $data->showdescription = 0;
    $data->introeditor['text'] = '';
    $data->introeditor['format'] = FORMAT_HTML;
    return add_moduleinfo($data, $course);
}

/** Получить существующий банк вопросов курса или создать его. */
function get_or_add_question_bank(stdClass $course): stdClass {
    $module = find_section_activity((int) $course->id, 0, 'qbank');
    return $module ?: add_question_bank($course);
}

/** Добавить тест с настройками просмотра, которые не раскрывают правильный ответ. */
function add_course_quiz(stdClass $course, int $sectionnumber, array $quiz): stdClass {
    [, , , , $data] = prepare_new_moduleinfo_data($course, 'quiz', $sectionnumber);
    $data->name = $quiz['name'];
    $data->introeditor['text'] = $quiz['intro'];
    $data->introeditor['format'] = FORMAT_HTML;
    $data->showdescription = 1;
    $data->timeopen = 0;
    $data->timeclose = 0;
    $data->timelimit = 0;
    $data->preferredbehaviour = 'deferredfeedback';
    $data->attempts = 0;
    $data->attemptonlast = 0;
    $data->grademethod = QUIZ_GRADEHIGHEST;
    $data->decimalpoints = 2;
    $data->questiondecimalpoints = -1;
    $data->questionsperpage = 1;
    $data->shuffleanswers = 0;
    $data->sumgrades = 0;
    $data->grade = 3;
    $data->overduehandling = 'autosubmit';
    $data->graceperiod = 86400;
    $data->quizpassword = '';
    $data->subnet = '';
    $data->browsersecurity = '';
    $data->delay1 = 0;
    $data->delay2 = 0;
    $data->showuserpicture = 0;
    $data->showblocks = 0;
    $data->navmethod = QUIZ_NAVMETHOD_FREE;

    foreach (['during', 'immediately', 'open', 'closed'] as $phase) {
        $data->{'attempt' . $phase} = 1;
        $data->{'correctness' . $phase} = 1;
        $data->{'maxmarks' . $phase} = 1;
        $data->{'marks' . $phase} = 1;
        $data->{'specificfeedback' . $phase} = 1;
        $data->{'generalfeedback' . $phase} = 1;
        $data->{'rightanswer' . $phase} = 0;
        $data->{'overallfeedback' . $phase} = 0;
    }

    return add_moduleinfo($data, $course);
}

/** Создать тест раздела или обновить только его название и описание. */
function sync_course_quiz(stdClass $course, int $sectionnumber, array $quiz): stdClass {
    global $DB;

    $module = find_section_activity((int) $course->id, $sectionnumber, 'quiz');
    if (!$module) {
        return add_course_quiz($course, $sectionnumber, $quiz);
    }
    $record = $DB->get_record('quiz', ['id' => $module->instance], '*', MUST_EXIST);
    $record->name = $quiz['name'];
    $record->intro = $quiz['intro'];
    $record->introformat = FORMAT_HTML;
    $record->timemodified = time();
    $DB->update_record('quiz', $record);
    return (object) ['coursemodule' => $module->coursemodule, 'instance' => $module->instance];
}

/** Импортировать Moodle XML в категорию банка вопросов курса. */
function import_questions(stdClass $course, stdClass $category, context_module $context, string $xml): void {
    $filename = make_request_directory() . '/python-course.xml';
    if (file_put_contents($filename, $xml) === false) {
        throw new RuntimeException('Cannot create the temporary Moodle XML file.');
    }

    $format = new qformat_xml();
    $format->setCategory($category);
    $format->setContexts([$context]);
    $format->setCourse($course);
    $format->setFilename($filename);
    $format->setRealfilename('python-course.xml');
    $format->setMatchgrades('error');
    $format->setCatfromfile(false);
    $format->setContextfromfile(false);
    $format->setStoponerror(true);

    ob_start();
    try {
        $success = $format->importpreprocess() && $format->importprocess() && $format->importpostprocess();
    } finally {
        $importoutput = ob_get_clean();
    }
    if (!$success) {
        throw new RuntimeException('Moodle could not import the CodeRunner questions. ' . trim($importoutput));
    }
}

/** Получить ID импортированных вопросов по их уникальным названиям. */
function get_course_question_ids(int $categoryid): array {
    global $DB;

    $sql = "SELECT q.id, q.name, qv.version
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             WHERE qbe.questioncategoryid = :categoryid
               AND q.qtype = :qtype
          ORDER BY qv.version DESC";
    $records = $DB->get_records_sql($sql, ['categoryid' => $categoryid, 'qtype' => 'coderunner']);

    $ids = [];
    foreach ($records as $record) {
        if (!isset($ids[$record->name])) {
            $ids[$record->name] = (int) $record->id;
        }
    }
    return $ids;
}

/** Оставить для импорта только вопросы, которых ещё нет в банке. */
function missing_question_sections(array $sections, array $questionids): array {
    $result = [];
    foreach ($sections as $section) {
        $missing = array_values(array_filter(
            $section['tasks'],
            static fn(array $task): bool => !isset($questionids[$task['name']]),
        ));
        if ($missing) {
            $section['tasks'] = $missing;
            $result[] = $section;
        }
    }
    return $result;
}

/** Синхронизировать только технический режим созданных курсом функций и классов. */
function sync_question_runtime_settings(array $sections, array $questionids): void {
    global $DB;

    foreach ($sections as $section) {
        foreach ($section['tasks'] as $task) {
            if (($task['mode'] ?? 'program') === 'program' || !isset($questionids[$task['name']])) {
                continue;
            }
            $options = $DB->get_record(
                'question_coderunner_options',
                ['questionid' => $questionids[$task['name']]],
                '*',
                MUST_EXIST,
            );
            $options->iscombinatortemplate = 1;
            $options->cputimelimitsecs = (int) ($task['cputime'] ?? 2);
            $options->memlimitmb = (int) ($task['memory'] ?? 128);
            $options->resultcolumns = callable_result_columns();
            $DB->update_record('question_coderunner_options', $options);

            // Поле extra отвечает только за короткий пример в таблице результата.
            $tests = array_values($DB->get_records(
                'question_coderunner_tests',
                ['questionid' => $questionids[$task['name']]],
                'id ASC',
            ));
            if (count($tests) !== count($task['tests'])) {
                throw new RuntimeException('Unexpected test count: ' . $task['name']);
            }
            foreach ($tests as $index => $testrecord) {
                $testrecord->extra = visible_test_code($task['tests'][$index]);
                $DB->update_record('question_coderunner_tests', $testrecord);
            }
        }
    }
}

$content = require __DIR__ . '/content.php';
$categoryid = (int) (getenv('PYTHON_COURSE_CATEGORY_ID') ?: 1);
if (!$DB->record_exists('course_categories', ['id' => $categoryid])) {
    fwrite(STDERR, "Moodle course category {$categoryid} does not exist.\n");
    exit(1);
}

\core\session\manager::set_user(get_admin());
// При любой ошибке транзакция отменяет неполное обновление курса.
$transaction = $DB->start_delegated_transaction();

try {
    // Shortname остаётся устойчивым идентификатором курса при обновлении.
    $course = $DB->get_record('course', ['shortname' => $content['shortname']]);
    $created = !$course;
    if ($created) {
        $course = create_course((object) [
            'category' => $categoryid,
            'fullname' => $content['fullname'],
            'shortname' => $content['shortname'],
            'summary' => $content['summary'],
            'summaryformat' => FORMAT_HTML,
            'format' => 'topics',
            'numsections' => count($content['sections']),
            'visible' => 1,
            'showgrades' => 1,
            'newsitems' => 0,
        ]);
    } else {
        update_course((object) [
            'id' => $course->id,
            'fullname' => $content['fullname'],
            'summary' => $content['summary'],
            'summaryformat' => FORMAT_HTML,
            'format' => 'topics',
            'numsections' => count($content['sections']),
        ]);
        $course = get_course($course->id);
    }

    course_create_sections_if_missing($course, range(0, count($content['sections'])));
    $zerosection = $DB->get_record(
        'course_sections',
        ['course' => $course->id, 'section' => 0],
        '*',
        MUST_EXIST,
    );
    course_update_section($course, $zerosection, [
        'name' => 'Начало работы',
        'summary' => '<p>Прочитайте правила курса, затем переходите к темам по порядку.</p>',
        'summaryformat' => FORMAT_HTML,
    ]);
    sync_course_page($course, 0, $content['intro']);

    foreach ($content['sections'] as $index => $section) {
        $sectionnumber = $index + 1;
        $sectionrecord = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => $sectionnumber],
            '*',
            MUST_EXIST,
        );
        course_update_section($course, $sectionrecord, [
            'name' => $section['name'],
            'summary' => '<p>' . s($section['summary']) . '</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        sync_course_page($course, $sectionnumber, $section['theory']);
    }

    $qbankmodule = get_or_add_question_bank($course);
    $qbankcontext = context_module::instance($qbankmodule->coursemodule);
    $questioncategory = question_get_default_category($qbankcontext->id, true);
    $questionids = get_course_question_ids((int) $questioncategory->id);
    $missingsections = missing_question_sections($content['sections'], $questionids);
    if ($missingsections) {
        import_questions($course, $questioncategory, $qbankcontext, build_question_xml($missingsections));
        $questionids = get_course_question_ids((int) $questioncategory->id);
    }
    sync_question_runtime_settings($content['sections'], $questionids);

    foreach ($content['sections'] as $index => $section) {
        $quizmodule = sync_course_quiz($course, $index + 1, $section['quiz']);
        $quiz = $DB->get_record('quiz', ['id' => $quizmodule->instance], '*', MUST_EXIST);
        $quiz->cmid = $quizmodule->coursemodule;

        foreach ($section['tasks'] as $task) {
            if (!isset($questionids[$task['name']])) {
                throw new RuntimeException('Imported question not found: ' . $task['name']);
            }
            quiz_add_quiz_question($questionids[$task['name']], $quiz, 0, 1);
        }
        quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    }

    rebuild_course_cache($course->id, true);
    $transaction->allow_commit();
    $action = $created ? 'created' : 'updated';
    echo "Python course {$action}: {$course->fullname} (course id {$course->id}).\n";
} catch (Throwable $error) {
    $transaction->rollback($error);
}
