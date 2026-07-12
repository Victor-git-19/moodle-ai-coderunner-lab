<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname'] = 'AI code helper';
$string['pageheading'] = 'Code analysis';
$string['intro'] = 'Paste educational code. The service performs static analysis and asks the local model for a short explanation. Code is not executed here.';
$string['language'] = 'Language';
$string['language_python'] = 'Python';
$string['language_javascript'] = 'JavaScript';
$string['language_java'] = 'Java';
$string['task'] = 'Task';
$string['code'] = 'Code';
$string['analyze'] = 'Analyze';
$string['result'] = 'Analysis result';
$string['issues'] = 'Issues';
$string['suggestions'] = 'Suggestions';
$string['complexity'] = 'Complexity';
$string['none'] = 'None';
$string['unknown'] = 'Unknown';
$string['emptycode'] = 'Enter code to analyze.';
$string['serviceerror'] = 'The AI service is temporarily unavailable. Try again later.';
$string['invalidresponse'] = 'The AI service returned an invalid response.';
$string['fallback'] = 'Ollama was unavailable, so this result contains static analysis only.';
$string['endpoint'] = 'AI service endpoint';
$string['endpoint_desc'] = 'Server-side URL of the analyze endpoint. The browser never connects to this address.';
$string['timeout'] = 'Request timeout';
$string['timeout_desc'] = 'Maximum time to wait for the AI service, in seconds.';
