#!/usr/bin/env php
<?php
// Проверяет структуру курса и при необходимости запускает эталонные ответы через Jobe.

define('CLI_SCRIPT', true);

$configpath = getenv('MOODLE_CONFIG') ?: '/var/www/html/config.php';
if (!is_readable($configpath)) {
    fwrite(STDERR, "Moodle config.php is not readable: {$configpath}\n");
    exit(1);
}

require $configpath;
require_once $CFG->libdir . '/questionlib.php';

$content = require __DIR__ . '/content.php';
$course = $DB->get_record('course', ['shortname' => $content['shortname']], '*', MUST_EXIST);
// Словарь по названию позволяет сравнить файл курса с вопросами в Moodle.
$expectedtasks = [];
foreach ($content['sections'] as $section) {
    foreach ($section['tasks'] as $task) {
        $expectedtasks[$task['name']] = $task;
    }
}

// Берём только последнюю версию каждого вопроса из скрытого банка курса.
$sql = "SELECT q.id, q.name
          FROM {question} q
          JOIN {question_versions} qv ON qv.questionid = q.id
          JOIN (
                SELECT questionbankentryid, MAX(version) AS version
                  FROM {question_versions}
              GROUP BY questionbankentryid
          ) latest ON latest.questionbankentryid = qv.questionbankentryid
                  AND latest.version = qv.version
          JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
          JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
          JOIN {context} ctx ON ctx.id = qc.contextid AND ctx.contextlevel = :contextlevel
          JOIN {course_modules} cm ON cm.id = ctx.instanceid AND cm.course = :courseid
          JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
         WHERE q.qtype = :qtype";
$questions = $DB->get_records_sql($sql, [
    'contextlevel' => CONTEXT_MODULE,
    'courseid' => $course->id,
    'modulename' => 'qbank',
    'qtype' => 'coderunner',
]);

if (count($questions) !== count($expectedtasks)) {
    throw new RuntimeException(
        'Expected ' . count($expectedtasks) . ' CodeRunner questions, found ' . count($questions) . '.',
    );
}

$runreference = in_array('--run-reference', $argv, true);
foreach ($questions as $record) {
    if (!isset($expectedtasks[$record->name])) {
        throw new RuntimeException('Unexpected CodeRunner question: ' . $record->name);
    }

    $task = $expectedtasks[$record->name];
    $question = question_bank::load_question($record->id);
    if (
        $question->coderunnertype !== 'python3' ||
        (int) $question->useace !== 0 ||
        $question->uiplugin !== 'none'
    ) {
        throw new RuntimeException('Incorrect CodeRunner settings: ' . $record->name);
    }
    $expectedcombinator = ($task['mode'] ?? 'program') === 'program' ? 0 : 1;
    if ((int) $question->iscombinatortemplate !== $expectedcombinator) {
        throw new RuntimeException('Incorrect CodeRunner test mode: ' . $record->name);
    }
    if (count($question->testcases) !== 3) {
        throw new RuntimeException('Each task must have exactly three tests: ' . $record->name);
    }

    $visible = 0;
    $hidden = 0;
    foreach ($question->testcases as $testcase) {
        if (($task['mode'] ?? 'program') !== 'program' && trim((string) $testcase->testcode) === '') {
            throw new RuntimeException('Function or class test has no test code: ' . $record->name);
        }
        if ($testcase->display === 'SHOW' && (int) $testcase->useasexample === 1) {
            $visible++;
        } else if ($testcase->display === 'HIDE' && (int) $testcase->useasexample === 0) {
            $hidden++;
        }
    }
    if ($visible !== 2 || $hidden !== 1) {
        throw new RuntimeException('Expected two examples and one hidden test: ' . $record->name);
    }

    if ($runreference) {
        // grade_response использует тот же Jobe, что и обычная попытка студента.
        $question->start_attempt(null);
        [$fraction] = $question->grade_response(['answer' => $question->answer]);
        if ((float) $fraction !== 1.0) {
            throw new RuntimeException('Reference answer failed Jobe tests: ' . $record->name);
        }
        echo "Reference answer: OK — {$record->name}\n";

        if (isset($task['invalid_answer'])) {
            // Эта проверка воспроизводит замечание преподавателя: вывод без def не должен пройти.
            $invalidquestion = question_bank::load_question($record->id);
            $invalidquestion->start_attempt(null);
            [$invalidfraction] = $invalidquestion->grade_response(['answer' => $task['invalid_answer']]);
            if ((float) $invalidfraction === 1.0) {
                throw new RuntimeException('Answer without required def was accepted: ' . $record->name);
            }
            echo "Required definition: OK — {$record->name}\n";
        }
    }
}

$quizcount = $DB->count_records('quiz', ['course' => $course->id]);
$pagecount = $DB->count_records('page', ['course' => $course->id]);
$slotcount = $DB->count_records_sql(
    'SELECT COUNT(1) FROM {quiz_slots} qs JOIN {quiz} q ON q.id = qs.quizid WHERE q.course = :courseid',
    ['courseid' => $course->id],
);

if ($quizcount !== count($content['sections']) || $pagecount !== count($content['sections']) + 1) {
    throw new RuntimeException("Incorrect course structure: {$pagecount} pages, {$quizcount} quizzes.");
}
if ($slotcount !== count($expectedtasks)) {
    throw new RuntimeException("Expected " . count($expectedtasks) . " quiz slots, found {$slotcount}.");
}

echo 'Python course: OK (' . count($expectedtasks) . " CodeRunner tasks)\n";
