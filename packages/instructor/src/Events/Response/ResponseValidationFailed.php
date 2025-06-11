<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Events\Event;
use Psr\Log\LogLevel;

final class ResponseValidationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;
}