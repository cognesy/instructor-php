<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\StructuredOutput;

use Cognesy\Instructor\Events\StructuredOutputEvent;
use Psr\Log\LogLevel;

final class StructuredOutputRequestReceived extends StructuredOutputEvent
{
    public string $logLevel = LogLevel::INFO;
}
