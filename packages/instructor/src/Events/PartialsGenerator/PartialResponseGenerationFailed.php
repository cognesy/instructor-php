<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\StructuredOutputEvent;
use Psr\Log\LogLevel;

final class PartialResponseGenerationFailed extends StructuredOutputEvent
{
    public string $logLevel = LogLevel::WARNING;
}