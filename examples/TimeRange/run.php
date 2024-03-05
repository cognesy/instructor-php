<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class TimeRange {
    /** The start time in hours. */
    public int $startTime;
    /** The end time in hours. */
    public int $endTime;
}

class UserDetail
{
    public int $name;
    /** Time range during which the user is working. */
    public TimeRange $workTime;
    /** Time range reserved for leisure activities. */
    public TimeRange $leisureTime;
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => "Yesterday Jason worked from 9 for 5 hours. Later I watched 2 hour movie which I finished at 19."]],
    responseModel: UserDetail::class,
    maxRetries: 2
);

assert($user->name === "Jason");
assert($user->workTime->startTime === 9);
assert($user->workTime->endTime === 14);
assert($user->leisureTime->startTime === 17);
assert($user->leisureTime->endTime === 19);
dump($user);
