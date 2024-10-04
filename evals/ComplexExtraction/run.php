<?php

use Cognesy\Evals\ComplexExtraction\ExtractProjectEvents;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');


$report = file_get_contents(__DIR__ . '/report.txt');
$examples = require 'examples.php';
$connection = 'openai';
$withStreaming = false;

$action = new ExtractProjectEvents($report, $examples, $connection, $withStreaming);
$events = $action();
dump($events->all());