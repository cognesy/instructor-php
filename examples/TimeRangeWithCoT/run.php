<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class TimeRange
{
    /** Step by step reasoning to get the correct time range */
    public string $chainOfThought;
    /** The start time in hours (0-23 format) */
    public int $startTime;
    /** The end time in hours (0-23 format) */
    public int $endTime;
}

$timeRange = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => "Workshop with Apex Industries started 9 and it took us 6 hours to complete."]],
    responseModel: TimeRange::class,
    maxRetries: 2
);

assert($timeRange->startTime === 9);
assert($timeRange->endTime === 17);
dump($timeRange);
