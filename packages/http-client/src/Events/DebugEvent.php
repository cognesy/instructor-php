<?php

namespace Cognesy\Http\Events;

use Cognesy\Utils\Events\Event;
use Psr\Log\LogLevel;

class DebugEvent extends Event
{
    public $logLevel = LogLevel::DEBUG;
}