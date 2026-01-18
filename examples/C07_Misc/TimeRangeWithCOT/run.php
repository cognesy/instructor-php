<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class TimeRange
{
    /** Step by step reasoning to get the correct time range */
    public string $chainOfThought;
    /** The start time in hours (0-23 format) */
    public int $startTime;
    /** The end time in hours (0-23 format) */
    public int $endTime;
}

$timeRange = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => "Workshop with Apex Industries started 9 and it took us 6 hours to complete."]],
    responseModel: TimeRange::class,
    maxRetries: 2
)->get();

dump($timeRange);

assert($timeRange->startTime === 9);
assert($timeRange->endTime === 15);
?>
