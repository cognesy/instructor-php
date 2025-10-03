<?php declare(strict_types=1);

namespace Cognesy\Http\Events;

use Psr\Log\LogLevel;

class DebugEvent extends \Cognesy\Events\Event
{
    public string $logLevel = LogLevel::DEBUG;
}