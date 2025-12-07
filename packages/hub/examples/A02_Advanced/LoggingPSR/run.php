---
title: 'PSR-3 Logging'
docname: 'logging_psr'
path: ''
---

## Overview

Instructor allows to easily log events with any PSR-3 compliant logging library.

## Example

<?php
require 'examples/boot.php';

use Cognesy\Events\Event;
use Cognesy\Instructor\StructuredOutput;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class StdoutLogger implements LoggerInterface
{
    use LoggerTrait;

    #[\Override]
    public function log($level, $message, array $context = []): void {
        echo sprintf(
            "[%s] %s%s\n",
            strtoupper((string) $level),
            (string) $message,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}

$logger = new StdoutLogger();

class User
{
    public int $age;
    public string $name;
}

$user = (new StructuredOutput)
    ->using('openai')
    ->wiretap(fn(Event $e) => $logger->log($e->logLevel, $e->name(), ['id' => $e->id, 'data' => $e->data]))
    ->withMessages("Jason is 25 years old and works as an engineer.")
    ->withResponseClass(User::class)->get();

assert($user->name === 'Jason');
assert($user->age === 25);

// TODO: Add "Sample Output" section showing actual log messages
// Example format:
// ### Sample Output
// ```
// [2025-12-07T01:18:13.475202+00:00] instructor.DEBUG: 🎯 [PSR-3] Starting extraction: User
// [2025-12-07T01:18:14.659417+00:00] instructor.DEBUG: ✅ [PSR-3] Completed extraction: User
// ```