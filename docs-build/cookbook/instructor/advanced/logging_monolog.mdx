---
title: 'Monolog Logging'
docname: 'logging_monolog'
path: ''
---

## Overview

Instructor allows to easily log events with Monolog library.

## Example

<?php
require 'examples/boot.php';

use Cognesy\Events\Event;
use Cognesy\Instructor\StructuredOutput;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$log = new Logger('instructor');
$log->pushHandler(new StreamHandler('php://stdout'));

class User
{
    public int $age;
    public string $name;
}

$user = (new StructuredOutput)
    ->using('openai')
    ->wiretap(fn(Event $e) => $log->log($e->logLevel, $e->name(), ['id' => $e->id, 'data' => $e->data]))
    ->withMessages("Jason is 25 years old and works as an engineer.")
    ->withResponseClass(User::class)
    ->get();

assert($user->name === 'Jason');
assert($user->age === 25);