<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\StructuredOutputEvent;
use Psr\Log\LogLevel;

final class ResponseValidationFailed extends StructuredOutputEvent
{
    public string $logLevel = LogLevel::WARNING;
}