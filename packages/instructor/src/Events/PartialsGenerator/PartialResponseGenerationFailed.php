<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Events\Event;
use Psr\Log\LogLevel;

final class PartialResponseGenerationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;
}