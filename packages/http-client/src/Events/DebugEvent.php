<?php

namespace Cognesy\Http\Events;

use Psr\Log\LogLevel;

class DebugEvent extends \Cognesy\Events\Event
{
    public $logLevel = LogLevel::DEBUG;
}