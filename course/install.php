#!/usr/bin/env php
<?php
// Installs the demo Python course through Moodle APIs. Safe to run repeatedly.

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

/** Escape a value for Moodle XML. */
function xml_value(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Preserve code and test whitespace exactly as the official CodeRunner export does. */
function xml_cdata(string $value): string {
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
}

/** Build the HTML shown in a CodeRunner question. */
function build_question_html(array $task): string {
    $html = '<p>' . s($task['description']) . '</p>';
    $html .= '<p><strong>Входные данные:</strong> ' . s($task['input']) . '</p>';
    $html .= '<p><strong>Выходные данные:</strong> ' . s($task['output']) . '</p>';
    $html .= '<p><em>Программа должна читать стандартный ввод и печатать только ответ.</em></p>';
    return $html;
}

/** Convert all course tasks to Moodle XML understood by CodeRunner. */
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
            $xml .= "    <resultcolumns></resultcolumns>\n";
            $xml .= "    <template></template>\n";
            $xml .= "    <iscombinatortemplate>0</iscombinatortemplate>\n";
            $xml .= '    <answer>' . xml_cdata($task['answer']) . "</answer>\n";
            $xml .= "    <validateonsave>1</validateonsave>\n";
            $xml .= "    <testsplitterre></testsplitterre>\n";
            $xml .= "    <language></language><acelang></acelang><sandbox></sandbox><grader></grader>\n";
            $xml .= "    <cputimelimitsecs>2</cputimelimitsecs><memlimitmb>128</memlimitmb>\n";
            $xml .= "    <sandboxparams></sandboxparams><templateparams></templateparams>\n";
            $xml .= "    <uiplugin>none</uiplugin><uiparameters></uiparameters>\n";
            $xml .= "    <testcases>\n";

            foreach ($task['tests'] as $test) {
                $useasexample = $test['visible'] ? '1' : '0';
                $display = $test['visible'] ? 'SHOW' : 'HIDE';
                $xml .= "      <testcase testtype=\"0\" useasexample=\"{$useasexample}\" " .
                    "hiderestiffail=\"0\" mark=\"1.0000000\">\n";
                $xml .= "        <testcode><text></text></testcode>\n";
                $xml .= '        <stdin><text>' . xml_cdata($test['stdin']) . "</text></stdin>\n";
                $xml .= '        <expected><text>' . xml_cdata($test['expected']) . "</text></expected>\n";
                $xml .= "        <extra><text></text></extra>\n";
                $xml .= '        <display><text>' . $display . "</text></display>\n";
                $xml .= "      </testcase>\n";
            }

            $xml .= "    </testcases>\n";
            $xml .= "  </question>\n";
        }
    }

    return $xml . "</quiz>\n";
}

/** Add a page resource and return its course-module data. */
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

/** Add the hidden question bank used by the five quizzes. */
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

/** Add a quiz with safe review settings for programming practice. */
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

/** Import the generated XML into a module-level question category. */
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

/** Return the current question IDs by their unique course names. */
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

$content = require __DIR__ . '/content.php';
$existing = $DB->get_record('course', ['shortname' => $content['shortname']]);
if ($existing) {
    echo "Python course already exists (course id {$existing->id}).\n";
    exit(0);
}

$categoryid = (int) (getenv('PYTHON_COURSE_CATEGORY_ID') ?: 1);
if (!$DB->record_exists('course_categories', ['id' => $categoryid])) {
    fwrite(STDERR, "Moodle course category {$categoryid} does not exist.\n");
    exit(1);
}

\core\session\manager::set_user(get_admin());
$transaction = $DB->start_delegated_transaction();

try {
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
    add_course_page($course, 0, $content['intro']);

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
        add_course_page($course, $sectionnumber, $section['theory']);
    }

    $qbankmodule = add_question_bank($course);
    $qbankcontext = context_module::instance($qbankmodule->coursemodule);
    $questioncategory = question_get_default_category($qbankcontext->id, true);
    import_questions($course, $questioncategory, $qbankcontext, build_question_xml($content['sections']));
    $questionids = get_course_question_ids((int) $questioncategory->id);

    foreach ($content['sections'] as $index => $section) {
        $quizmodule = add_course_quiz($course, $index + 1, $section['quiz']);
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
    echo "Python course created: {$course->fullname} (course id {$course->id}).\n";
} catch (Throwable $error) {
    $transaction->rollback($error);
}
